<?php
/* ============================================================
   seo.php — the daily and weekly site checks.

   Every check answers a question the owner would otherwise have to pay
   someone to answer, and every result is a fact about the site as it is
   right now — fetched over HTTP from the live domain, not inferred from
   what the build was supposed to produce.

   A check reports one of:
     ok    nothing to do
     warn  worth fixing, not urgent
     fail  actively costing you something
     info  a fact worth knowing, no judgement

   Nothing here scores the site out of 100. A single number would hide
   exactly the detail that makes a finding actionable.
   ============================================================ */

declare(strict_types=1);

final class Seo
{
    /** Crawlers whose access decides whether the site can appear in AI answers. */
    public const AI_AGENTS = [
        'GPTBot'            => 'ChatGPT training and browsing',
        'OAI-SearchBot'     => 'ChatGPT search results',
        'ChatGPT-User'      => 'ChatGPT when a user asks it to visit',
        'ClaudeBot'         => 'Claude',
        'PerplexityBot'     => 'Perplexity',
        'Google-Extended'   => 'Google AI Overviews and Gemini',
        'Applebot-Extended' => 'Apple Intelligence',
    ];

    public static function base(): string
    {
        return rtrim((string)cfg('site.url', 'https://wwwebtech.in'), '/');
    }

    /* ── Recording ─────────────────────────────────────────── */

    public static function record(string $name, string $status, string $detail = '', ?int $score = null): array
    {
        $row = ['check_name' => $name, 'status' => $status, 'detail' => $detail, 'score' => $score];

        /* One row per check per day. Without this, running the weekly job a
           second time in a day appended a second copy of every check it
           makes, so the panel listed each one twice and the totals counted
           them twice. Replace rather than append. */
        DB::run('DELETE FROM wwt_seo_checks WHERE d = UTC_DATE() AND check_name = ?', [cut($name, 60)]);
        DB::run('INSERT INTO wwt_seo_checks (d, check_name, status, detail, score)
                 VALUES (UTC_DATE(), ?, ?, ?, ?)',
                [cut($name, 60), $status, cut($detail, 4000), $score]);
        return $row;
    }

    /** Wipe today's results before a re-run so the panel never shows two of each. */
    public static function clearToday(): void
    {
        DB::run('DELETE FROM wwt_seo_checks WHERE d = UTC_DATE()');
    }

    /* ── The page inventory ────────────────────────────────── */

    /** Every URL in sitemap.xml. This is what "the site" means to a search engine. */
    public static function sitemapUrls(): array
    {
        $r = Http::get(self::base() . '/sitemap.xml');
        if ($r['status'] !== 200 || $r['body'] === '') return [];
        $prev = libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($r['body']);
        libxml_use_internal_errors($prev);
        if ($xml === false) return [];
        $urls = [];
        foreach ($xml->url as $u) {
            $loc = trim((string)$u->loc);
            if ($loc !== '') $urls[] = $loc;
        }
        return $urls;
    }

    /* ── Checks ────────────────────────────────────────────── */

    /** Is the site up, on https, and not redirecting in a loop? */
    public static function checkReachable(): array
    {
        $r = Http::get(self::base() . '/', ['follow' => true]);
        if ($r['status'] === 0) {
            return self::record('site reachable', 'fail', 'Could not connect: ' . ($r['error'] ?: 'no response'));
        }
        if ($r['status'] !== 200) {
            return self::record('site reachable', 'fail', 'The homepage returned HTTP ' . $r['status']);
        }
        if (!str_starts_with($r['url'], 'https://')) {
            return self::record('site reachable', 'fail', 'The homepage is not served over https.');
        }
        return self::record('site reachable', 'ok',
            sprintf('Homepage 200 over https in %dms.', $r['ms']), null);
    }

    /** Does sitemap.xml exist, parse, and list live pages? */
    public static function checkSitemap(): array
    {
        $r = Http::get(self::base() . '/sitemap.xml');
        if ($r['status'] !== 200) {
            return self::record('sitemap', 'fail', 'sitemap.xml returned HTTP ' . $r['status']);
        }
        $urls = self::sitemapUrls();
        if (!$urls) return self::record('sitemap', 'fail', 'sitemap.xml is present but lists no URLs, or is not valid XML.');

        $robots = Http::get(self::base() . '/robots.txt');
        $listed = $robots['status'] === 200 && stripos($robots['body'], 'sitemap:') !== false;

        return self::record('sitemap', $listed ? 'ok' : 'warn',
            count($urls) . ' URLs listed.' . ($listed ? ' Referenced from robots.txt.'
                : ' robots.txt does not point to it, so some crawlers will not find it.'),
            count($urls));
    }

    /** Every page in the sitemap must actually be there. */
    public static function checkPagesLive(array $urls): array
    {
        $bad = [];
        foreach ($urls as $u) {
            $r = Http::head($u);
            /* Some hosts answer HEAD oddly; confirm with a GET before
               accusing a page of being broken. */
            if ($r['status'] !== 200) $r = Http::get($u);
            if ($r['status'] !== 200) $bad[] = $u . ' → ' . ($r['status'] ?: 'no response');
        }
        if (!$bad) {
            return self::record('pages reachable', 'ok', count($urls) . ' pages in the sitemap all return 200.');
        }
        return self::record('pages reachable', 'fail',
            count($bad) . ' of ' . count($urls) . " listed pages do not load:\n" . implode("\n", array_slice($bad, 0, 20)));
    }

    /**
     * Titles, descriptions, headings, canonicals. The lint most agencies
     * charge for, run on every page, every day.
     */
    public static function checkMetadata(array $urls): array
    {
        $issues = [];
        $checked = 0;

        foreach ($urls as $u) {
            $r = Http::get($u);
            if ($r['status'] !== 200) continue;
            $checked++;
            $html = $r['body'];
            $path = (string)parse_url($u, PHP_URL_PATH);

            $title = self::tagText($html, 'title');
            $desc  = self::metaContent($html, 'description');
            $h1s   = preg_match_all('/<h1\b[^>]*>(.*?)<\/h1>/is', $html);
            $canon = self::linkHref($html, 'canonical');
            $og    = self::metaProperty($html, 'og:image');

            if ($title === '')                 $issues[] = "$path — no <title>";
            elseif (mb_strlen($title) > 60)    $issues[] = "$path — title is " . mb_strlen($title) . " characters (over 60, Google truncates)";
            elseif (mb_strlen($title) < 15)    $issues[] = "$path — title is only " . mb_strlen($title) . " characters";

            if ($desc === '')                  $issues[] = "$path — no meta description";
            elseif (mb_strlen($desc) > 160)    $issues[] = "$path — description is " . mb_strlen($desc) . " characters (over 160)";
            elseif (mb_strlen($desc) < 70)     $issues[] = "$path — description is only " . mb_strlen($desc) . " characters";

            if ($h1s === 0)                    $issues[] = "$path — no <h1>";
            elseif ($h1s > 1)                  $issues[] = "$path — $h1s <h1> headings (should be one)";

            if ($canon === '')                 $issues[] = "$path — no canonical link";
            if ($og === '')                    $issues[] = "$path — no og:image, so shared links have no preview";
        }

        if ($checked === 0) return self::record('page metadata', 'fail', 'No pages could be fetched to check.');
        if (!$issues) return self::record('page metadata', 'ok', "All $checked pages have a sensible title, description, single h1, canonical and share image.", $checked);

        return self::record('page metadata', count($issues) > 4 ? 'fail' : 'warn',
            count($issues) . " issues across $checked pages:\n" . implode("\n", array_slice($issues, 0, 40)),
            $checked);
    }

    /**
     * Crawl the site's own links and report the ones that do not resolve.
     * A broken internal link is the cheapest possible SEO defect to fix and
     * the easiest to never notice.
     */
    public static function checkLinks(array $urls, int $maxPages = 30): array
    {
        $base    = self::base();
        $host    = (string)parse_url($base, PHP_URL_HOST);
        $seen    = [];
        $broken  = [];
        $checked = 0;

        foreach (array_slice($urls, 0, $maxPages) as $u) {
            $r = Http::get($u);
            if ($r['status'] !== 200) continue;
            $from = (string)parse_url($u, PHP_URL_PATH);

            preg_match_all('/<a\b[^>]*href="([^"]+)"/i', $r['body'], $m);
            foreach (array_unique($m[1] ?? []) as $href) {
                if ($href === '' || str_starts_with($href, '#')) continue;
                if (preg_match('#^(mailto|tel|javascript):#i', $href)) continue;

                $target = str_starts_with($href, 'http') ? $href : $base . '/' . ltrim($href, '/');
                if ((string)parse_url($target, PHP_URL_HOST) !== $host) continue;   // internal only

                $target = strtok($target, '#');
                if (isset($seen[$target])) continue;
                $seen[$target] = true;
                $checked++;

                $h = Http::head($target);
                if ($h['status'] !== 200) $h = Http::get($target);
                if (!in_array($h['status'], [200, 301, 308], true)) {
                    $broken[] = $from . ' → ' . $href . ' (' . ($h['status'] ?: 'no response') . ')';
                }
            }
        }

        if ($checked === 0) return self::record('internal links', 'warn', 'No links could be checked.');
        if (!$broken) return self::record('internal links', 'ok', "$checked distinct internal links, all resolving.", $checked);
        return self::record('internal links', 'fail',
            count($broken) . " broken internal links:\n" . implode("\n", array_slice($broken, 0, 25)), $checked);
    }

    /**
     * Can AI answer engines read the site? This is the GEO half of the job,
     * and it is a robots.txt question before it is anything else.
     */
    public static function checkAiAccess(): array
    {
        $r = Http::get(self::base() . '/robots.txt');
        if ($r['status'] !== 200) {
            return self::record('AI crawler access', 'fail', 'robots.txt returned HTTP ' . $r['status']);
        }
        $txt = $r['body'];

        $blocked = [];
        foreach (self::AI_AGENTS as $agent => $what) {
            if (self::robotsBlocks($txt, $agent)) $blocked[] = "$agent ($what)";
        }

        $llms = Http::get(self::base() . '/llms.txt');
        $hasLlms = $llms['status'] === 200;

        if ($blocked) {
            return self::record('AI crawler access', 'warn',
                "robots.txt blocks:\n" . implode("\n", $blocked)
                . "\n\nThat is a legitimate choice, but it means the site cannot be quoted in those answers."
                . ($hasLlms ? '' : "\nllms.txt is also absent."));
        }
        return self::record('AI crawler access', $hasLlms ? 'ok' : 'warn',
            'All ' . count(self::AI_AGENTS) . ' AI crawlers are allowed by robots.txt.'
            . ($hasLlms ? ' llms.txt is published.'
                        : ' llms.txt is not published — it is an emerging convention, not a standard, but it costs nothing.'));
    }

    /** Has an AI crawler actually visited? Facts, from our own logs. */
    public static function checkAiSeen(): array
    {
        $rows = DB::all(
            "SELECT source AS k, SUM(views) v, MAX(d) last FROM wwt_daily_rollups
             WHERE device='bot' AND d >= DATE_SUB(UTC_DATE(), INTERVAL 30 DAY)
             GROUP BY source ORDER BY v DESC");
        $ai = [];
        foreach ($rows as $r) {
            if (Analytics::isAiCrawler((string)$r['k'])) {
                $ai[] = sprintf('%s — %d fetches, last %s', $r['k'], (int)$r['v'], (string)$r['last']);
            }
        }
        if (!$ai) {
            return self::record('AI crawlers seen', 'info',
                'No AI crawler visits recorded in the last 30 days. Server-side logging needs the '
              . 'AI-crawler block in .htaccess and serve.php in the web root (see DEPLOY.md); '
              . 'without those this check cannot see them even when they visit.');
        }
        return self::record('AI crawlers seen', 'ok',
            "Seen in the last 30 days:\n" . implode("\n", $ai), count($ai));
    }

    /** Structured data present on the homepage. */
    public static function checkSchema(): array
    {
        $r = Http::get(self::base() . '/');
        if ($r['status'] !== 200) return self::record('structured data', 'fail', 'Could not fetch the homepage.');

        preg_match_all('#<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>#is', $r['body'], $m);
        if (!($m[1] ?? [])) return self::record('structured data', 'warn', 'No JSON-LD found on the homepage.');

        $types = [];
        foreach ($m[1] as $block) {
            $d = json_decode(trim($block), true);
            if (!is_array($d)) {
                return self::record('structured data', 'fail',
                    'A JSON-LD block on the homepage is not valid JSON, so search engines will ignore all of it.');
            }
            foreach (($d['@graph'] ?? [$d]) as $node) {
                $t = $node['@type'] ?? null;
                foreach ((array)$t as $one) $types[] = (string)$one;
            }
        }
        $types = array_values(array_unique($types));
        $wanted = ['Organization', 'LocalBusiness', 'WebSite'];
        $missing = array_values(array_diff($wanted, $types));

        return self::record('structured data', $missing ? 'warn' : 'ok',
            'Homepage declares: ' . implode(', ', $types)
            . ($missing ? '. Missing: ' . implode(', ', $missing) . '.' : '.'));
    }

    /** Security and caching headers that also affect how the site is treated. */
    public static function checkHeaders(): array
    {
        $r = Http::get(self::base() . '/', ['follow' => true]);
        if ($r['status'] !== 200) return self::record('response headers', 'fail', 'Could not fetch the homepage.');
        $h = $r['headers'];
        $notes = [];

        if (!isset($h['x-content-type-options'])) $notes[] = 'no X-Content-Type-Options';
        if (!isset($h['referrer-policy']))        $notes[] = 'no Referrer-Policy';
        if (!isset($h['strict-transport-security'])) $notes[] = 'no HSTS (Strict-Transport-Security)';
        $enc = strtolower((string)($h['content-encoding'] ?? ''));
        if ($enc === '') $notes[] = 'the homepage is served uncompressed';

        return self::record('response headers', $notes ? 'warn' : 'ok',
            $notes ? implode('; ', $notes) . '.' : 'Compression and the basic security headers are in place.');
    }

    /** Core Web Vitals, from Google's own field data. Needs a free API key. */
    public static function checkVitals(string $url, string $strategy = 'mobile'): array
    {
        $key = Secrets::get('pagespeed_key', (string)cfg('pagespeed_api_key', ''));
        if ($key === '') {
            return self::record('core web vitals (' . $strategy . ')', 'info',
                'No PageSpeed API key set, so speed is not being measured. The key is free from '
              . 'the Google Cloud console; add it in Settings.');
        }
        $api = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=' . rawurlencode($url)
             . '&strategy=' . rawurlencode($strategy) . '&category=performance&key=' . rawurlencode($key);
        /* PageSpeed regularly takes most of a minute. Same reasoning as the
           blog generator: do not hold an idle connection across it. */
        DB::disconnect();
        $r = Http::get($api, ['timeout' => 90]);
        if ($r['status'] !== 200) {
            return self::record('core web vitals (' . $strategy . ')', 'warn',
                'PageSpeed API returned HTTP ' . $r['status'] . '. Speed was not measured this run.');
        }
        $d = json_decode($r['body'], true);
        if (!is_array($d)) return self::record('core web vitals (' . $strategy . ')', 'warn',
            'PageSpeed returned an unreadable response.');

        $perf = isset($d['lighthouseResult']['categories']['performance']['score'])
            ? (int)round(100 * (float)$d['lighthouseResult']['categories']['performance']['score']) : null;
        $m    = $d['loadingExperience']['metrics'] ?? [];
        $lcp  = isset($m['LARGEST_CONTENTFUL_PAINT_MS']['percentile']) ? (int)$m['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] : null;
        $inp  = isset($m['INTERACTION_TO_NEXT_PAINT']['percentile']) ? (int)$m['INTERACTION_TO_NEXT_PAINT']['percentile'] : null;
        $cls  = isset($m['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'])
            ? ((int)$m['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile']) / 100 : null;

        DB::run('INSERT INTO wwt_cwv (d, url, strategy, lcp_ms, cls, inp_ms, perf)
                 VALUES (UTC_DATE(), ?, ?, ?, ?, ?, ?)',
                [cut($url, 190), $strategy, $lcp, $cls, $inp, $perf]);

        $bad = [];
        if ($lcp !== null && $lcp > 2500) $bad[] = 'LCP ' . $lcp . 'ms (good is under 2500)';
        if ($inp !== null && $inp > 200)  $bad[] = 'INP ' . $inp . 'ms (good is under 200)';
        if ($cls !== null && $cls > 0.1)  $bad[] = 'CLS ' . $cls . ' (good is under 0.1)';

        $summary = sprintf('%s: performance %s%s', $strategy,
            $perf === null ? 'n/a' : $perf . '/100',
            $lcp === null ? ' (no field data yet — Google needs enough real visitors)'
                          : sprintf(', LCP %dms, INP %s, CLS %s', $lcp,
                              $inp === null ? 'n/a' : $inp . 'ms', $cls === null ? 'n/a' : (string)$cls));

        return self::record('core web vitals (' . $strategy . ')', $bad ? 'warn' : 'ok',
            $summary . ($bad ? "\nOutside Google's thresholds: " . implode('; ', $bad) : ''), $perf);
    }

    /* ── Generated files ───────────────────────────────────── */

    /**
     * llms.txt — a short, plain description of the site for language models.
     * An emerging convention rather than a standard; it costs nothing and
     * gives answer engines something accurate to read.
     */
    public static function writeLlmsTxt(): array
    {
        $base  = self::base();
        $posts = DB::all("SELECT slug, title, dek FROM wwt_posts WHERE status='published'
                          ORDER BY published_at DESC LIMIT 50");

        $lines = [
            '# Wwwebtech',
            '',
            '> Web development, custom CRM systems, SEO, AI search visibility and social media '
            . 'marketing for Indian businesses. Based in East Delhi, Delhi, India.',
            '',
            'Wwwebtech builds websites and the systems behind them: the site itself, the CRM that '
            . 'catches the enquiries, and the search and social work that brings people to it.',
            '',
            '## Services',
            '- [Web development](' . $base . '/services/web-development/): custom websites, web platforms and ecommerce.',
            '- [CRM systems](' . $base . '/services/crm-systems/): lead capture and follow-up for small businesses.',
            '- [Business automation](' . $base . '/services/business-automation/): connecting the tools a business already uses.',
            '- [SEO](' . $base . '/services/seo/): technical SEO, content and local search.',
            '- [AI visibility (GEO)](' . $base . '/services/ai-visibility-geo/): being findable inside AI answers.',
            '- [Social media marketing](' . $base . '/services/social-media-marketing/): organic, paid and social search.',
            '- [Technical support](' . $base . '/services/technical-support/): ongoing maintenance.',
            '',
            '## Key pages',
            '- [About](' . $base . '/about/)',
            '- [Contact](' . $base . '/contact/)',
            '- [Blog](' . $base . '/blog/)',
            '',
        ];

        if ($posts) {
            $lines[] = '## Writing';
            foreach ($posts as $p) {
                $lines[] = '- [' . $p['title'] . '](' . $base . '/blog/' . $p['slug'] . '/): ' . $p['dek'];
            }
            $lines[] = '';
        }

        $lines[] = '## Notes for anyone quoting this site';
        $lines[] = '- Contact: contact@wwwebtech.in';
        $lines[] = '- Location: East Delhi, Delhi, India';
        $lines[] = '- This site publishes no client names, testimonials or result figures, because '
                 . 'it does not have permission to. Please do not attribute any to it.';
        $lines[] = '';
        $lines[] = 'Last updated: ' . gmdate('Y-m-d');
        $lines[] = '';

        $txt = implode("\n", $lines);
        Publisher::put(Blog::webroot() . '/llms.txt', $txt);

        return self::record('llms.txt', 'ok', strlen($txt) . ' bytes written, ' . count($posts) . ' posts listed.');
    }

    /** llms-full.txt — the same, plus the full text of every published post. */
    public static function writeLlmsFullTxt(): array
    {
        $base = self::base();
        $out  = "# Wwwebtech — full text\n\n"
              . "Everything published on " . $base . ", as plain text, for language models.\n"
              . "Generated " . gmdate('Y-m-d') . ".\n\n";

        $posts = DB::all("SELECT slug, title, dek, published_at FROM wwt_posts
                          WHERE status='published' ORDER BY published_at DESC LIMIT 100");
        $n = 0;
        foreach ($posts as $p) {
            $src = Blog::loadSource((string)$p['slug']);
            if (!$src) continue;
            $n++;
            $out .= "\n\n---\n\n## " . $p['title'] . "\n"
                  . $base . '/blog/' . $p['slug'] . "/\n"
                  . 'Published ' . substr((string)$p['published_at'], 0, 10) . "\n\n"
                  . $p['dek'] . "\n\n"
                  . Blog::plainText((string)$src['body']) . "\n";
            foreach ((array)($src['faq'] ?? []) as $f) {
                $out .= "\nQ: " . (string)($f['q'] ?? '') . "\nA: " . (string)($f['a'] ?? '') . "\n";
            }
        }

        Publisher::put(Blog::webroot() . '/llms-full.txt', $out);
        return self::record('llms-full.txt', 'ok', strlen($out) . ' bytes, ' . $n . ' posts included.');
    }

    /* ── IndexNow ──────────────────────────────────────────── */

    /**
     * Tell Bing, Yandex and others that a URL changed. Google does not
     * participate; its equivalent is the sitemap it already reads.
     */
    public static function indexNow(array $urls): array
    {
        $key = (string)Settings::get('indexnow_key', (string)cfg('indexnow_key', ''));
        if ($key === '') {
            $key = bin2hex(random_bytes(16));
            Settings::set('indexnow_key', $key);
        }
        /* The key has to be verifiable as a file at the site root. */
        Publisher::put(Blog::webroot() . '/' . $key . '.txt', $key . "\n");

        $urls = array_values(array_filter($urls));
        if (!$urls) return self::record('indexnow', 'info', 'Nothing new to submit.');

        $host = (string)parse_url(self::base(), PHP_URL_HOST);
        $r = Http::postJson('https://api.indexnow.org/indexnow', [
            'host'        => $host,
            'key'         => $key,
            'keyLocation' => self::base() . '/' . $key . '.txt',
            'urlList'     => array_slice($urls, 0, 100),
        ]);

        $ok = in_array($r['status'], [200, 202], true);
        return self::record('indexnow', $ok ? 'ok' : 'warn',
            $ok ? count($urls) . ' URLs submitted to IndexNow (Bing, Yandex and others).'
                : 'IndexNow returned HTTP ' . $r['status'] . '. Nothing else is affected.');
    }

    /* ── Small HTML helpers ────────────────────────────────── */

    public static function tagText(string $html, string $tag): string
    {
        return preg_match('#<' . $tag . '\b[^>]*>(.*?)</' . $tag . '>#is', $html, $m)
            ? trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
    }

    public static function metaContent(string $html, string $name): string
    {
        return preg_match('#<meta\b[^>]*\bname="' . preg_quote($name, '#') . '"[^>]*\bcontent="([^"]*)"#i', $html, $m)
            || preg_match('#<meta\b[^>]*\bcontent="([^"]*)"[^>]*\bname="' . preg_quote($name, '#') . '"#i', $html, $m)
            ? trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
    }

    public static function metaProperty(string $html, string $prop): string
    {
        return preg_match('#<meta\b[^>]*\bproperty="' . preg_quote($prop, '#') . '"[^>]*\bcontent="([^"]*)"#i', $html, $m)
            || preg_match('#<meta\b[^>]*\bcontent="([^"]*)"[^>]*\bproperty="' . preg_quote($prop, '#') . '"#i', $html, $m)
            ? trim($m[1]) : '';
    }

    public static function linkHref(string $html, string $rel): string
    {
        return preg_match('#<link\b[^>]*\brel="' . preg_quote($rel, '#') . '"[^>]*\bhref="([^"]*)"#i', $html, $m)
            || preg_match('#<link\b[^>]*\bhref="([^"]*)"[^>]*\brel="' . preg_quote($rel, '#') . '"#i', $html, $m)
            ? trim($m[1]) : '';
    }

    /**
     * Does robots.txt disallow this agent from the site root?
     * Deliberately simple: it reads the agent's own group and the wildcard
     * group, which is all that matters for "can this crawler read us".
     */
    public static function robotsBlocks(string $robots, string $agent): bool
    {
        $lines  = preg_split('/\R/', $robots) ?: [];
        $groups = [];
        $current = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');
            if ($line === '') { $current = []; continue; }
            if (preg_match('/^user-agent:\s*(.+)$/i', $line, $m)) {
                $current[] = strtolower(trim($m[1]));
                continue;
            }
            if (preg_match('/^(dis)?allow:\s*(.*)$/i', $line, $m) && $current) {
                foreach ($current as $ua) {
                    $groups[$ua][] = [strtolower($m[1] ?? '') === 'dis', trim($m[2])];
                }
            }
        }
        $rules = $groups[strtolower($agent)] ?? $groups['*'] ?? [];
        foreach ($rules as [$isDisallow, $path]) {
            if ($isDisallow && ($path === '/' || $path === '')) return $path === '/';
        }
        return false;
    }
}
