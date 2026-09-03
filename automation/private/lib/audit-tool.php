<?php
/* ============================================================
   audit-tool.php — the free website audit (§7).

   This is the lead magnet, and it is the one piece of marketing on the
   site that has to be *true* to be worth anything. Everything below is
   measured against the visitor's real page: no scores invented to look
   alarming, no "critical issues" that are really preferences. If a site
   is in good shape the report says so — a clean report from an honest
   tool is better advertising than a red one from a rigged one.

   It runs out of band because a full audit takes 30–90 seconds and no
   one should watch a spinner for that. The visitor submits, gets a
   "checking your site" page, and the report arrives by email a minute
   later — which also means we now have a confirmed-deliverable address.
   ============================================================ */

declare(strict_types=1);

final class AuditTool
{
    /** Anything above this is fine; the report says so plainly. */
    private const GOOD = 80;

    /* ── Intake ────────────────────────────────────────────── */

    /**
     * Queue an audit. Returns [ok, token|error].
     * Validation is deliberately forgiving about how a URL is typed —
     * people write "mysite.in", not "https://mysite.in/".
     */
    public static function request(string $url, string $name, string $email, string $phone = '', array $server = []): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if (mb_strlen($name) < 2) return [false, 'Please tell us your name — the report is written to a person, not an address.'];
        if (preg_match('/[\r\n]/', $name)) return [false, 'Please tell us your name.'];
        $name = cut($name, 100);

        $url = self::normaliseUrl($url);
        if ($url === '') return [false, 'That does not look like a website address. Try something like mysite.in'];

        $host = (string)parse_url($url, PHP_URL_HOST);
        if ($host === '' || !str_contains($host, '.')) return [false, 'That does not look like a website address.'];
        if (self::isPrivateHost($host)) return [false, 'That address is not reachable from the public internet.'];

        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'Please give a working email address — that is where the report goes.'];

        /* One audit per host per day is plenty, and it stops the tool
           being used as a free crawler. */
        $recent = DB::val("SELECT COUNT(*) FROM wwt_audits WHERE host = ? AND ts > UTC_TIMESTAMP() - INTERVAL 1 DAY", [$host]);
        if ((int)$recent >= 3) return [false, 'This site has already been audited a few times today. Try again tomorrow, or reply to the report you were sent.'];

        if (!RateLimit::allow('audit:' . self::ipTrunc($server), 5, 3600)) {
            return [false, 'Too many audits from this connection in the last hour.'];
        }

        $token = bin2hex(random_bytes(16));
        $id = DB::insert(
            "INSERT INTO wwt_audits (ts, url, host, name, email, phone, token, status, ip_trunc)
             VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?, 'queued', ?)",
            [cut($url, 255), cut($host, 190), $name, cut($email, 150), cut(trim($phone), 30), $token, self::ipTrunc($server)]);

        audit('audit_requested', 'audit', (string)$id, ['host' => $host]);
        return [true, $token];
    }

    /* ── Runner (called from cron) ─────────────────────────── */

    /** Run whatever is queued. One at a time: each takes up to 90s. */
    public static function runQueued(int $max = 2): string
    {
        $rows = DB::all("SELECT * FROM wwt_audits WHERE status = 'queued' ORDER BY id ASC LIMIT ?", [$max]);
        if (!$rows) return 'nothing queued';

        $done = 0; $failed = 0;
        foreach ($rows as $row) {
            /* Claim it first, so two overlapping cron runs cannot both
               spend 90 seconds on the same site. */
            $claimed = DB::run("UPDATE wwt_audits SET status='running', started_at=UTC_TIMESTAMP()
                                WHERE id = ? AND status='queued'", [$row['id']])->rowCount();
            if ($claimed === 0) continue;

            try {
                $res = self::run((string)$row['url']);
                DB::run("UPDATE wwt_audits SET status='done', score=?, results=?, finished_at=UTC_TIMESTAMP() WHERE id=?",
                        [$res['score'], json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $row['id']]);
                self::afterRun((int)$row['id']);
                $done++;
            } catch (Throwable $t) {
                DB::run("UPDATE wwt_audits SET status='failed', error=?, finished_at=UTC_TIMESTAMP() WHERE id=?",
                        [cut($t->getMessage(), 250), $row['id']]);
                wwt_log('audit', 'run failed', ['id' => $row['id'], 'err' => $t->getMessage()]);
                $failed++;
            }
        }
        return sprintf('%d audited%s', $done, $failed ? ", {$failed} failed" : '');
    }

    /**
     * The audit itself. Every check returns
     *   [key, label, state(ok|warn|bad|info), finding, fix, weight]
     * and the score is the weighted proportion passed — so a site that
     * fails one heavy check does not end up at 12/100 for effect.
     */
    public static function run(string $url): array
    {
        DB::disconnect();                      // the fetches below are slow
        $t0 = microtime(true);
        $r  = Http::get($url, ['timeout' => 25, 'ua' => self::ua(), 'follow' => true]);
        $ttfbMs = (int)round((microtime(true) - $t0) * 1000);

        if ($r['status'] === 0)  throw new RuntimeException('Could not reach the site.');
        if ($r['status'] >= 400) throw new RuntimeException('The site returned HTTP ' . $r['status'] . '.');

        return self::analyse((string)($r['url'] ?? $url), (string)$r['body'], $r, $ttfbMs);
    }

    /**
     * Everything except the fetch.
     * Split out so the scoring can be exercised against a known page
     * without depending on some third party being up — a gate that fails
     * when someone else's server hiccups stops being read.
     *
     * @param array $opts  ['network' => false] skips the robots.txt and
     *                     sitemap lookups, for offline testing.
     */
    public static function analyse(string $final, string $html, array $r, int $ttfbMs, array $opts = []): array
    {
        $host  = (string)parse_url($final, PHP_URL_HOST);
        $bytes = strlen($html);
        $net   = (bool)($opts['network'] ?? true);

        $checks = array_merge(
            self::checkTransport($final, $r),
            self::checkTitleAndDescription($html),
            self::checkHeadings($html),
            self::checkMobile($html),
            self::checkImages($html),
            self::checkStructuredData($html),
            self::checkContact($html),
            self::checkWeight($bytes, $ttfbMs, $html),
            self::checkIndexability($final, $html, $host, $net),
            $net ? self::checkVitals($final) : []
        );

        $earned = 0.0; $possible = 0.0;
        foreach ($checks as $c) {
            if ($c['state'] === 'info') continue;      // unmeasured, so unscored
            $possible += $c['weight'];
            $earned   += $c['weight'] * ($c['state'] === 'ok' ? 1.0 : ($c['state'] === 'warn' ? 0.5 : 0.0));
        }
        $score = $possible > 0 ? (int)round(100 * $earned / $possible) : 0;

        return [
            'url'      => $final,
            'host'     => $host,
            'score'    => $score,
            'checked'  => gmdate('c'),
            'bytes'    => $bytes,
            'ttfb_ms'  => $ttfbMs,
            'checks'   => $checks,
            'headline' => self::headline($score, $checks),
        ];
    }

    /* ── Individual checks ─────────────────────────────────── */

    private static function checkTransport(string $url, array $r): array
    {
        $https = str_starts_with(strtolower($url), 'https://');
        $out = [self::c('https', 'Secure connection (HTTPS)', $https ? 'ok' : 'bad', 5,
            $https ? 'The site loads over HTTPS.'
                   : 'The site loads over plain HTTP. Browsers mark it "Not secure" and Google ranks it below secure competitors.',
            $https ? '' : 'Install a TLS certificate — free with Let\'s Encrypt, and one click on most Indian hosts — then redirect all HTTP traffic to HTTPS.')];

        $h = $r['headers'] ?? [];
        $enc = strtolower((string)($h['content-encoding'] ?? ''));
        $ok  = str_contains($enc, 'gzip') || str_contains($enc, 'br') || str_contains($enc, 'zstd');
        $out[] = self::c('compress', 'Text compression', $ok ? 'ok' : 'warn', 3,
            $ok ? 'Pages are compressed before they are sent (' . $enc . ').'
                : 'The HTML is being sent uncompressed, so every visitor downloads several times more than they need to.',
            $ok ? '' : 'Turn on gzip or Brotli compression at the server. On LiteSpeed and Apache this is a few lines in .htaccess.');
        return $out;
    }

    private static function checkTitleAndDescription(string $html): array
    {
        $title = self::tag($html, 'title');
        $len   = mb_strlen($title);
        if ($title === '')            $st = 'bad';
        elseif ($len < 15 || $len > 65) $st = 'warn';
        else                           $st = 'ok';

        $out = [self::c('title', 'Page title', $st, 8,
            $title === '' ? 'The homepage has no title tag at all, so search results show the bare address.'
                          : sprintf('Title is %d characters: "%s"', $len, cut($title, 120))
                            . ($len > 65 ? ' — Google will cut it off in results.' : ($len < 15 ? ' — too short to say what you do or where you are.' : '')),
            $st === 'ok' ? '' : 'Write a title of roughly 50–60 characters covering what you sell and the city you sell it in.')];

        $desc = self::meta($html, 'description');
        $dlen = mb_strlen($desc);
        $dst  = $desc === '' ? 'bad' : (($dlen < 70 || $dlen > 165) ? 'warn' : 'ok');
        $out[] = self::c('description', 'Meta description', $dst, 5,
            $desc === '' ? 'There is no meta description, so Google writes its own snippet from whatever text it finds.'
                         : sprintf('Description is %d characters.', $dlen) . ($dlen > 165 ? ' It will be truncated.' : ($dlen < 70 ? ' There is room to say more.' : '')),
            $dst === 'ok' ? '' : 'Write 140–160 characters that read like an ad: what you do, who for, and a reason to click.');

        $og = self::metaProp($html, 'og:title') !== '' || self::metaProp($html, 'og:image') !== '';
        $out[] = self::c('social', 'Link preview (Open Graph)', $og ? 'ok' : 'warn', 2,
            $og ? 'Links shared on WhatsApp and social show a proper preview.'
                : 'Links shared on WhatsApp, LinkedIn or Facebook show no image or title preview.',
            $og ? '' : 'Add og:title, og:description and a 1200×630 og:image to the page head.');
        return $out;
    }

    private static function checkHeadings(string $html): array
    {
        preg_match_all('/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $m);
        $n = count($m[0]);
        $st = $n === 1 ? 'ok' : ($n === 0 ? 'bad' : 'warn');
        $first = $n ? trim(html_entity_decode(strip_tags($m[1][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
        return [self::c('h1', 'Main heading', $st, 5,
            $n === 0 ? 'The page has no H1, so neither readers nor search engines get a clear statement of what it is.'
                     : ($n === 1 ? 'One clear H1: "' . cut($first, 90) . '"'
                                 : $n . ' H1 headings on one page, which splits the signal.'),
            $st === 'ok' ? '' : 'Use exactly one H1 stating the offer and the place; make the rest H2s.')];
    }

    private static function checkMobile(string $html): array
    {
        $vp = self::meta($html, 'viewport');
        $ok = $vp !== '' && str_contains(strtolower($vp), 'width=device-width');
        $out = [self::c('viewport', 'Mobile layout', $ok ? 'ok' : 'bad', 8,
            $ok ? 'The page declares a mobile viewport.'
                : 'There is no mobile viewport tag, so phones render the desktop layout zoomed out. Most Indian traffic is mobile.',
            $ok ? '' : 'Add <meta name="viewport" content="width=device-width, initial-scale=1"> and check the layout at 360px wide.')];

        $blocked = str_contains(strtolower($vp), 'user-scalable=no') || preg_match('/maximum-scale=1(\.0)?\b/i', $vp);
        if ($blocked) {
            $out[] = self::c('zoom', 'Pinch zoom', 'warn', 2,
                'Zooming is disabled, which fails accessibility guidelines and frustrates anyone reading small text.',
                'Remove user-scalable=no and maximum-scale from the viewport tag.');
        }
        return $out;
    }

    private static function checkImages(string $html): array
    {
        preg_match_all('/<img\b[^>]*>/i', $html, $m);
        $imgs = $m[0];
        $total = count($imgs);
        if ($total === 0) {
            return [self::c('alt', 'Image alt text', 'info', 0, 'No images found on the homepage.', '')];
        }
        $noAlt = 0; $noDims = 0; $lazy = 0;
        foreach ($imgs as $tag) {
            if (!preg_match('/\balt\s*=/i', $tag)) $noAlt++;
            if (!preg_match('/\bwidth\s*=/i', $tag) || !preg_match('/\bheight\s*=/i', $tag)) $noDims++;
            if (preg_match('/loading\s*=\s*["\']?lazy/i', $tag)) $lazy++;
        }
        $out = [self::c('alt', 'Image alt text', $noAlt === 0 ? 'ok' : ($noAlt <= $total / 4 ? 'warn' : 'bad'), 4,
            $noAlt === 0 ? "All {$total} images have alt text."
                         : "{$noAlt} of {$total} images have no alt text — invisible to screen readers and to image search.",
            $noAlt === 0 ? '' : 'Describe each image in a few words; leave alt="" only on purely decorative ones.')];

        $out[] = self::c('dims', 'Image dimensions', $noDims === 0 ? 'ok' : ($noDims <= $total / 3 ? 'warn' : 'bad'), 3,
            $noDims === 0 ? 'Images reserve their space, so the page does not jump while loading.'
                          : "{$noDims} of {$total} images have no width/height, so content shifts as they load. Google measures this as CLS.",
            $noDims === 0 ? '' : 'Set width and height attributes on every img (CSS can still resize them).');
        return $out;
    }

    private static function checkStructuredData(string $html): array
    {
        preg_match_all('/<script[^>]+application\/ld\+json[^>]*>(.*?)<\/script>/is', $html, $m);
        $types = [];
        foreach ($m[1] as $json) {
            $d = json_decode(trim($json), true);
            if (is_array($d)) self::collectTypes($d, $types);
        }
        $types = array_values(array_unique($types));
        $local = (bool)array_intersect(array_map('strtolower', $types),
            ['localbusiness', 'organization', 'professionalservice', 'store']);

        return [self::c('schema', 'Structured data', $types ? ($local ? 'ok' : 'warn') : 'bad', 5,
            $types === [] ? 'There is no structured data, so Google has to guess your business name, address and phone number.'
                          : 'Structured data found: ' . implode(', ', array_slice($types, 0, 6))
                            . ($local ? '.' : ' — but nothing describing the business itself.'),
            $local ? '' : 'Add LocalBusiness JSON-LD with your exact name, address, phone and opening hours, matching your Google Business Profile character for character.')];
    }

    private static function checkContact(string $html): array
    {
        $text = strip_tags($html);
        $hasTel   = (bool)preg_match('/href\s*=\s*["\']tel:/i', $html);
        $hasWa    = (bool)preg_match('#(wa\.me/|api\.whatsapp\.com|web\.whatsapp)#i', $html);
        $hasMail  = (bool)preg_match('/href\s*=\s*["\']mailto:/i', $html);
        $hasForm  = (bool)preg_match('/<form\b/i', $html);
        $phoneVis = (bool)preg_match('/(?:\+91[\s-]?)?[6-9]\d{4}[\s-]?\d{5}/', $text);

        $ways = ($hasTel ? 1 : 0) + ($hasWa ? 1 : 0) + ($hasMail ? 1 : 0) + ($hasForm ? 1 : 0);
        $st = $ways >= 2 ? 'ok' : ($ways === 1 ? 'warn' : 'bad');

        $found = [];
        if ($hasTel)  $found[] = 'tap-to-call';
        if ($hasWa)   $found[] = 'WhatsApp';
        if ($hasForm) $found[] = 'a form';
        if ($hasMail) $found[] = 'email link';

        return [self::c('contact', 'Ways to get in touch', $st, 6,
            $ways === 0
                ? ($phoneVis ? 'A phone number appears as plain text but is not a tap-to-call link, and there is no form or WhatsApp link.'
                             : 'No phone link, WhatsApp link, email link or contact form was found on the homepage.')
                : 'Found ' . implode(' and ', $found) . ' on the homepage.',
            $st === 'ok' ? '' : 'Put a tap-to-call link and a WhatsApp link in the header, and a short form above the fold. On mobile, every extra tap costs enquiries.')];
    }

    private static function checkWeight(int $bytes, int $ttfbMs, string $html): array
    {
        $kb = (int)round($bytes / 1024);
        $st = $kb < 120 ? 'ok' : ($kb < 300 ? 'warn' : 'bad');
        $out = [self::c('weight', 'Page weight (HTML)', $st, 3,
            "The HTML alone is {$kb} KB" . ($kb >= 120 ? ' — heavy before a single image or script is counted.' : '.'),
            $st === 'ok' ? '' : 'Move inline styles and scripts to cached files, and cut anything the page does not use.')];

        $tst = $ttfbMs < 600 ? 'ok' : ($ttfbMs < 1500 ? 'warn' : 'bad');
        $out[] = self::c('ttfb', 'Server response time', $tst, 4,
            "The server took {$ttfbMs} ms to send the first byte" . ($ttfbMs >= 600 ? ' — slow enough that visitors feel it.' : '.'),
            $tst === 'ok' ? '' : 'Enable page caching, and check whether the hosting plan or a heavy plugin stack is the bottleneck.');

        preg_match_all('/<script\b[^>]*\bsrc=/i', $html, $m);
        $scripts = count($m[0]);
        if ($scripts > 12) {
            $out[] = self::c('scripts', 'Third-party scripts', 'warn', 2,
                "{$scripts} external scripts load on the homepage. Each one is another connection before the page can settle.",
                'Audit what each script earns you. Chat widgets, heat maps and unused pixels are usually the first to go.');
        }
        return $out;
    }

    private static function checkIndexability(string $url, string $html, string $host, bool $net = true): array
    {
        $out = [];
        $robots = self::meta($html, 'robots');
        $blocked = str_contains(strtolower($robots), 'noindex');
        $out[] = self::c('noindex', 'Search engine access', $blocked ? 'bad' : 'ok', 8,
            $blocked ? 'The homepage carries a noindex tag — it is telling Google to keep the site out of search results entirely.'
                     : 'Nothing on the page blocks search engines from indexing it.',
            $blocked ? 'Remove the noindex meta tag. This one line can be the whole reason a site gets no organic traffic.' : '');

        $canon = '';
        if (preg_match('/<link[^>]+rel=["\']?canonical["\']?[^>]*>/i', $html, $m)
            && preg_match('/href=["\']([^"\']+)["\']/i', $m[0], $h)) $canon = trim($h[1]);
        $out[] = self::c('canonical', 'Canonical URL', $canon !== '' ? 'ok' : 'warn', 2,
            $canon !== '' ? 'A canonical URL is declared.' : 'No canonical tag, so the same page reachable at several addresses can compete with itself.',
            $canon !== '' ? '' : 'Add a self-referencing <link rel="canonical"> to every page.');

        if (!$net) return $out;

        $scheme = (string)parse_url($url, PHP_URL_SCHEME) ?: 'https';
        $root   = $scheme . '://' . $host;

        $rob = Http::get($root . '/robots.txt', ['timeout' => 10, 'ua' => self::ua(), 'follow' => true]);
        $robOk = $rob['status'] === 200 && trim((string)$rob['body']) !== '';
        $disallowAll = $robOk && (bool)preg_match('/^\s*Disallow:\s*\/\s*$/mi', (string)$rob['body']);
        $out[] = self::c('robotstxt', 'robots.txt', $disallowAll ? 'bad' : ($robOk ? 'ok' : 'warn'), 3,
            $disallowAll ? 'robots.txt blocks the entire site from crawlers.'
                         : ($robOk ? 'robots.txt is present and readable.' : 'No robots.txt was found.'),
            $disallowAll ? 'Remove the site-wide Disallow: / rule.' : ($robOk ? '' : 'Add a robots.txt that points to your sitemap.'));

        $sm = Http::get($root . '/sitemap.xml', ['timeout' => 10, 'ua' => self::ua(), 'follow' => true]);
        $smOk = $sm['status'] === 200 && str_contains((string)$sm['body'], '<url');
        if (!$smOk && $robOk && preg_match('/^\s*Sitemap:\s*(\S+)/mi', (string)$rob['body'], $s)) {
            $alt = Http::get(trim($s[1]), ['timeout' => 10, 'ua' => self::ua(), 'follow' => true]);
            $smOk = $alt['status'] === 200 && str_contains((string)$alt['body'], '<url');
        }
        $out[] = self::c('sitemap', 'XML sitemap', $smOk ? 'ok' : 'warn', 3,
            $smOk ? 'An XML sitemap is published.' : 'No XML sitemap was found at /sitemap.xml or in robots.txt.',
            $smOk ? '' : 'Publish a sitemap and submit it in Google Search Console so new pages get found quickly.');

        return $out;
    }

    /** Google's own field data, when a key is configured. */
    private static function checkVitals(string $url): array
    {
        $key = Secrets::get('pagespeed_key', (string)cfg("pagespeed_api_key", ""));
        if ($key === '') {
            return [self::c('cwv', 'Core Web Vitals', 'info', 0,
                'Speed was not measured this run (no PageSpeed key configured).', '')];
        }
        DB::disconnect();
        $api = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=' . rawurlencode($url)
             . '&strategy=mobile&category=performance&key=' . rawurlencode($key);
        $r = Http::get($api, ['timeout' => 90]);
        if ($r['status'] !== 200) {
            return [self::c('cwv', 'Core Web Vitals', 'info', 0,
                'Google\'s speed API did not answer in time, so speed is not scored here.', '')];
        }
        $d = json_decode((string)$r['body'], true);
        $perf = isset($d['lighthouseResult']['categories']['performance']['score'])
            ? (int)round(100 * (float)$d['lighthouseResult']['categories']['performance']['score']) : null;
        $m   = $d['loadingExperience']['metrics'] ?? [];
        $lcp = isset($m['LARGEST_CONTENTFUL_PAINT_MS']['percentile']) ? (int)$m['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] : null;
        $inp = isset($m['INTERACTION_TO_NEXT_PAINT']['percentile']) ? (int)$m['INTERACTION_TO_NEXT_PAINT']['percentile'] : null;
        $cls = isset($m['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile']) ? ((int)$m['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile']) / 100 : null;

        if ($perf === null) {
            return [self::c('cwv', 'Core Web Vitals', 'info', 0, 'Speed data was unavailable for this address.', '')];
        }
        $st = $perf >= 80 ? 'ok' : ($perf >= 50 ? 'warn' : 'bad');
        $detail = "Google scores the mobile homepage {$perf}/100.";
        $bad = [];
        if ($lcp !== null) { $detail .= sprintf(' Real visitors see the main content at %.1fs (LCP).', $lcp / 1000); if ($lcp > 2500) $bad[] = 'LCP'; }
        if ($inp !== null) { $detail .= " Taps respond in {$inp} ms (INP)."; if ($inp > 200) $bad[] = 'INP'; }
        if ($cls !== null) { $detail .= " Layout shift is {$cls} (CLS)."; if ($cls > 0.1) $bad[] = 'CLS'; }
        if ($lcp === null) $detail .= ' Google has no field data for this site yet, so this is a lab score only.';

        return [self::c('cwv', 'Core Web Vitals (mobile)', $st, 10, $detail,
            $st === 'ok' ? '' : 'Start with the largest image on the screen: compress it, size it, and load it early. That single change usually moves LCP more than everything else combined.'
                              . ($bad ? ' Failing: ' . implode(', ', $bad) . '.' : ''))];
    }

    /* ── Reporting ─────────────────────────────────────────── */

    /** Honest one-liner. A good site is told it is good. */
    public static function headline(int $score, array $checks): string
    {
        $bad = array_values(array_filter($checks, static fn($c) => $c['state'] === 'bad'));
        /* Name the heaviest problem, not whichever check happened to run
           first. "Your meta description is missing" is a poor opening line
           when the same page is also telling Google not to index it. */
        usort($bad, static fn($x, $y) => $y['weight'] <=> $x['weight']);
        if ($score >= 90) return 'This site is in good shape — there is little here worth paying anyone to fix.';
        if ($score >= self::GOOD) return 'The fundamentals are sound. What is left is tuning, not repair.';
        if (count($bad) === 1) return 'One thing is doing real damage: ' . strtolower($bad[0]['label']) . '.';
        if ($bad) return count($bad) . ' issues are costing you enquiries, starting with ' . strtolower($bad[0]['label']) . '.';
        return 'Nothing is broken, but several things are working at half strength.';
    }

    /** After a run: email the report, and create a lead if the score warrants a conversation. */
    private static function afterRun(int $id): void
    {
        $a = DB::one('SELECT * FROM wwt_audits WHERE id = ?', [$id]);
        if (!$a) return;
        $res = json_decode((string)$a['results'], true);
        if (!is_array($res)) return;

        /* The audit is itself an enquiry: someone gave a real address to
           learn about their site. It becomes a lead with its own source,
           so the funnel can treat it differently from a quote request. */
        $leadId = (int)($a['lead_id'] ?? 0);
        if ($leadId === 0) {
            $v = Leads::validate([
                'name'      => (string)$a['name'],
                'email'     => (string)$a['email'],
                'phone'     => (string)$a['phone'],
                'message'   => sprintf('Asked for a free audit of %s. It scored %d/100. %s',
                                  $a['host'], (int)$a['score'], (string)($res['headline'] ?? '')),
                'site_url'  => (string)$a['url'],
                'has_site'  => 'yes',
                '_lp'       => 'free-website-audit',
                '_page'     => '/tools/free-website-audit/',
                'consent'   => 1,   // the form's consent box is required to submit
            ]);
            $leadId = $v['ok'] ? Leads::store($v['data']) : 0;
            if ($leadId > 0) DB::run('UPDATE wwt_audits SET lead_id = ? WHERE id = ?', [$leadId, $id]);
        }

        self::emailReport($id);

        if ($leadId > 0) {
            Timeline::add($leadId, 'audit_completed', 'tool',
                sprintf('Website audit for %s scored %d/100', $a['host'], (int)$a['score']), 'system');
            /* Only tell the owner about the ones worth a call. A clean
               site that scored 94 does not need chasing. */
            if ((int)$a['score'] < self::GOOD) {
                try { Notify::newLead($leadId); } catch (Throwable $t) { wwt_log('audit', 'notify failed', ['err' => $t->getMessage()]); }
            }
        }
    }

    public static function emailReport(int $id): bool
    {
        $a = DB::one('SELECT * FROM wwt_audits WHERE id = ?', [$id]);
        if (!$a || $a['status'] !== 'done') return false;
        $res = json_decode((string)$a['results'], true);
        if (!is_array($res)) return false;

        $r = Mailer::send([
            'to'      => (string)$a['email'],
            'subject' => sprintf('Your website audit: %s scored %d/100', $res['host'], (int)$a['score']),
            'text'    => self::reportText($res, (string)$a['token']),
            'html'    => self::reportHtml($res, (string)$a['token']),
        ]);

        if (!empty($r['ok'])) DB::run('UPDATE wwt_audits SET emailed_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
        else wwt_log('audit', 'report email failed', ['id' => $id, 'err' => (string)($r['error'] ?? '')]);
        return !empty($r['ok']);
    }

    public static function byToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
        $row = DB::one('SELECT * FROM wwt_audits WHERE token = ?', [$token]);
        return $row ?: null;
    }

    /* ── Rendering ─────────────────────────────────────────── */

    private static function bands(array $checks): array
    {
        $order = ['bad' => 0, 'warn' => 1, 'ok' => 2, 'info' => 3];
        usort($checks, static fn($x, $y) =>
            [$order[$x['state']], -$x['weight']] <=> [$order[$y['state']], -$y['weight']]);
        return $checks;
    }

    public static function reportText(array $res, string $token): string
    {
        $l = [];
        $l[] = 'Website audit — ' . $res['host'];
        $l[] = str_repeat('=', 40);
        $l[] = 'Score: ' . $res['score'] . '/100';
        $l[] = $res['headline'];
        $l[] = '';
        foreach (self::bands($res['checks']) as $c) {
            $mark = ['ok' => '[ok]  ', 'warn' => '[fix] ', 'bad' => '[!!]  ', 'info' => '[--]  '][$c['state']];
            $l[] = $mark . $c['label'];
            $l[] = '      ' . $c['finding'];
            if ($c['fix'] !== '') $l[] = '      → ' . $c['fix'];
            $l[] = '';
        }
        $l[] = 'Full report: ' . self::base() . '/tools/free-website-audit/report/?t=' . $token;
        $l[] = '';
        $l[] = 'Every point above was measured on your live page on ' . gmdate('j M Y', strtotime((string)$res['checked'])) . '.';
        $l[] = 'Reply to this email if you want any of it explained — a person reads these.';
        $l[] = 'Wwwebtech · Delhi';
        return implode("\n", $l);
    }

    public static function reportHtml(array $res, string $token): string
    {
        $colour = ['ok' => '#0f7b3f', 'warn' => '#9a6400', 'bad' => '#b3261e', 'info' => '#5b6472'];
        $word   = ['ok' => 'Good', 'warn' => 'Worth fixing', 'bad' => 'Fix this', 'info' => 'Not measured'];
        $score  = (int)$res['score'];
        $ring   = $score >= self::GOOD ? '#0f7b3f' : ($score >= 55 ? '#9a6400' : '#b3261e');

        $rows = '';
        foreach (self::bands($res['checks']) as $c) {
            $rows .= '<tr><td style="padding:14px 0;border-top:1px solid #e6e8ec;vertical-align:top">'
                . '<div style="font:600 15px/1.4 system-ui,sans-serif;color:#12151a">' . e($c['label'])
                . ' <span style="font:600 11px/1 system-ui,sans-serif;color:' . $colour[$c['state']]
                . ';border:1px solid currentColor;border-radius:3px;padding:2px 5px;vertical-align:2px">'
                . e($word[$c['state']]) . '</span></div>'
                . '<div style="font:400 14px/1.6 system-ui,sans-serif;color:#3a414b;margin-top:5px">' . e($c['finding']) . '</div>'
                . ($c['fix'] !== '' ? '<div style="font:400 14px/1.6 system-ui,sans-serif;color:#12151a;margin-top:6px;padding-left:11px;border-left:2px solid #d8dbe0">'
                    . e($c['fix']) . '</div>' : '')
                . '</td></tr>';
        }

        $url = self::base() . '/tools/free-website-audit/report/?t=' . $token;

        return '<!doctype html><html><body style="margin:0;background:#f4f5f7;padding:24px 12px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:10px;padding:28px">'
            . '<tr><td>'
            . '<div style="font:400 13px/1 system-ui,sans-serif;color:#5b6472;letter-spacing:.06em;text-transform:uppercase">Website audit</div>'
            . '<h1 style="font:700 26px/1.25 Georgia,serif;color:#12151a;margin:8px 0 0">' . e($res['host']) . '</h1>'
            . '<div style="margin:18px 0 6px;font:700 40px/1 system-ui,sans-serif;color:' . $ring . '">' . $score
            . '<span style="font:400 18px/1 system-ui,sans-serif;color:#5b6472">/100</span></div>'
            . '<p style="font:400 16px/1.6 system-ui,sans-serif;color:#3a414b;margin:6px 0 0">' . e($res['headline']) . '</p>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px">' . $rows . '</table>'
            . '<p style="font:400 13px/1.6 system-ui,sans-serif;color:#5b6472;margin:22px 0 0">'
            . 'Every point above was measured on your live page on ' . e(gmdate('j M Y', strtotime((string)$res['checked'])))
            . '. Nothing here is a template.</p>'
            . '<p style="margin:18px 0 0"><a href="' . e($url) . '" style="display:inline-block;background:#12151a;color:#fff;'
            . 'font:600 15px/1 system-ui,sans-serif;padding:13px 20px;border-radius:6px;text-decoration:none">Open the full report</a></p>'
            . '<p style="font:400 14px/1.6 system-ui,sans-serif;color:#3a414b;margin:20px 0 0">'
            . 'If you want any of this explained, just reply — a person reads these.</p>'
            . '<p style="font:400 13px/1.6 system-ui,sans-serif;color:#5b6472;margin:16px 0 0;border-top:1px solid #e6e8ec;padding-top:14px">'
            . 'Wwwebtech · Delhi</p>'
            . '</td></tr></table></td></tr></table></body></html>';
    }

    /* ── Small helpers ─────────────────────────────────────── */

    /* Seo has extractors, but they assume double-quoted attributes —
       fine for our own output, wrong for the open web. Telling a site it
       has no meta description because it used single quotes would be a
       false accusation, and this report has to be true. */

    /**
     * Every @type anywhere in the document.
     * Real pages nest their types inside @graph, inside arrays, and
     * inside properties like publisher or mainEntity — a shallow read
     * reports "no structured data" on a page that is full of it, which
     * is exactly the kind of false alarm this tool must not raise.
     */
    private static function collectTypes(array $node, array &$out, int $depth = 0): void
    {
        if ($depth > 8) return;
        foreach ($node as $k => $v) {
            if ($k === '@type') { foreach ((array)$v as $t) if (is_string($t)) $out[] = $t; }
            elseif (is_array($v)) self::collectTypes($v, $out, $depth + 1);
        }
    }

    public static function tag(string $html, string $tag): string
    {
        return preg_match('#<' . $tag . '\b[^>]*>(.*?)</' . $tag . '>#is', $html, $m)
            ? trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
    }

    public static function meta(string $html, string $name): string
    {
        return self::attrPair($html, 'meta', 'name', $name, 'content');
    }

    public static function metaProp(string $html, string $prop): string
    {
        return self::attrPair($html, 'meta', 'property', $prop, 'content');
    }

    /**
     * Find <$el> where $keyAttr equals $keyVal, and return $wantAttr.
     * Attribute values may be double-quoted, single-quoted or bare, and
     * the two attributes may appear in either order.
     */
    private static function attrPair(string $html, string $el, string $keyAttr, string $keyVal, string $wantAttr): string
    {
        if (!preg_match_all('#<' . $el . '\b[^>]*>#i', $html, $tags)) return '';
        $v = '#\b' . $wantAttr . '\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s">]+))#i';
        $k = '#\b' . $keyAttr . '\s*=\s*("|\'|)' . preg_quote($keyVal, '#') . '\1(?=[\s>/])#i';
        foreach ($tags[0] as $tag) {
            if (!preg_match($k, $tag)) continue;
            if (!preg_match($v, $tag, $m)) continue;
            $val = ($m[2] ?? '') !== '' ? $m[2] : ((($m[3] ?? '') !== '') ? $m[3] : ($m[4] ?? ''));
            return trim(html_entity_decode((string)$val, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return '';
    }

    private static function c(string $key, string $label, string $state, int $weight, string $finding, string $fix): array
    {
        return ['key' => $key, 'label' => $label, 'state' => $state,
                'weight' => $weight, 'finding' => $finding, 'fix' => $fix];
    }

    public static function normaliseUrl(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || mb_strlen($raw) > 200) return '';
        if (!preg_match('#^https?://#i', $raw)) $raw = 'https://' . ltrim($raw, '/');
        $p = parse_url($raw);
        if (!$p || empty($p['host'])) return '';
        $host = strtolower($p['host']);
        if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $host)) return '';
        return strtolower($p['scheme']) . '://' . $host
             . (isset($p['port']) ? ':' . (int)$p['port'] : '')
             . ($p['path'] ?? '/');
    }

    /** Never let the tool be pointed at the LAN it is running on. */
    public static function isPrivateHost(string $host): bool
    {
        $host = strtolower($host);
        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) return true;
        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal')) return true;
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        return !filter_var($ip, FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private static function ipTrunc(array $server): string
    {
        $ip = (string)($server['REMOTE_ADDR'] ?? '');
        if (str_contains($ip, ':')) return implode(':', array_slice(explode(':', $ip), 0, 3)) . '::';
        $p = explode('.', $ip);
        return count($p) === 4 ? "{$p[0]}.{$p[1]}.{$p[2]}.0" : '';
    }

    private static function ua(): string
    {
        return 'Mozilla/5.0 (compatible; WwwebtechAudit/1.0; +https://wwwebtech.in/tools/free-website-audit/)';
    }

    private static function base(): string
    {
        return rtrim((string)cfg("site.url", "https://wwwebtech.in"), '/');
    }
}
