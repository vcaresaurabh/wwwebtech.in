<?php
/* ============================================================
   blog.php — generate, check, and publish a post.

   The generator is the least trustworthy part of this system, so it is
   the most heavily fenced. Nothing reaches the site until it has passed
   every mechanical check in gates(). A post that fails is stored with
   the reason and never published — the failure is visible in the panel
   rather than swallowed.

   The one rule everything here serves: the site must not publish a
   claim nobody can check. That is why the banned-pattern list is mostly
   about invented evidence — statistics with no source, "studies show",
   client results we never had — rather than about style.
   ============================================================ */

declare(strict_types=1);

final class Blog
{
    public const MIN_WORDS   = 800;
    public const MAX_TITLE   = 60;
    public const MIN_H2      = 3;
    public const SIMILARITY  = 0.6;    // reject at or above this, against the last 60
    public const COMPARE_N   = 60;

    public const CLUSTERS = [
        'web'    => 'Websites, ecommerce, CRM and automation',
        'seo'    => 'SEO, local search and AI visibility',
        'social' => 'Social media, ads and creative',
    ];

    /* ── Prompting ─────────────────────────────────────────── */

    /**
     * The system prompt. The prohibitions are stated as absolutes and
     * repeated in the output contract, because a single mention in a long
     * prompt is the thing most likely to be lost.
     */
    public static function systemPrompt(): string
    {
        $site = rtrim((string)cfg('site.url', 'https://wwwebtech.in'), '/');
        return <<<TXT
        You write for Wwwebtech, a small web, SEO and social agency in East Delhi, India.
        The reader is the owner of a small or mid-sized Indian business. They are intelligent
        and busy, they are not technical, and they are the person who signs the cheque.

        HOW TO WRITE
        - Plain English. Every acronym gets a plain translation the first time it appears.
        - Explain mechanisms, not vocabulary. The reader should finish able to make a decision.
        - Concrete and specific. Prefer "a photo straight off a phone is often four megabytes"
          to "large images can slow your site".
        - Say what you would not buy. Every piece names something commonly sold in this
          category that does not work, even when it is something an agency could sell.
        - Where something is genuinely uncertain — most of AI search is — say so plainly
          instead of picking a confident answer.
        - British spelling. Indian context, Indian examples, rupees not dollars.
        - No headline hype, no "in today's fast-paced digital landscape", no listicle padding.

        ABSOLUTE PROHIBITIONS — a piece breaking any of these is worthless to us:
        1. NEVER invent a statistic, percentage, survey result, or "X% of businesses" claim.
           If you do not have a specific, checkable, publicly known figure, make the argument
           without a number. Do not write "studies show", "research indicates", "data suggests",
           "experts agree" or any similar unsourced appeal.
        2. NEVER name a client, invent a case study, or describe a result Wwwebtech achieved.
           We have not given you any client information, so anything you wrote would be false.
           Do not write "one of our clients", "we helped a business in Delhi", or similar.
        3. NEVER invent a quotation, testimonial, review, or award.
        4. NEVER state a price for Wwwebtech's services, or promise a timeline or a result.
        5. Do not claim anything about Google's algorithm as certain fact unless it is publicly
           documented by Google. Describe observable mechanics instead.

        Publicly documented technical thresholds ARE allowed and encouraged — Core Web Vitals
        boundaries, HTTP status codes, character limits in Google's own guidance. Those are
        checkable. Invented market research is not.

        OUTPUT CONTRACT
        Reply with a single JSON object and nothing else. No prose before or after, no code
        fence. The object has exactly these keys:

        {
          "title": "under 60 characters, specific, no colon-subtitle padding",
          "dek":   "one or two sentences, 120-200 characters, what the reader will get",
          "read":  <integer minutes, honest for the length>,
          "body":  "<the article as HTML>",
          "faq":   [{"q": "...", "a": "..."}, ...]   // 3 to 5 real questions
        }

        Rules for "body":
        - HTML fragment only. No <html>, <head>, <body>, and NO <h1> — the page supplies it.
        - At least 3 <h2> sections, each with id="kebab-case-slug" matching its text.
        - Use <p>, <h2>, <h3>, <ul>, <ol>, <li>, <strong>, <em>, <blockquote>, <table>. No
          <script>, <style>, <iframe>, <img>, or inline style attributes.
        - At least 800 words of actual prose.
        - Internal links only to these paths, and only where genuinely relevant:
          /services/web-development/, /services/crm-systems/, /services/business-automation/,
          /services/seo/, /services/ai-visibility-geo/, /services/social-media-marketing/,
          /services/technical-support/, /contact/, /blog/
          Link with descriptive anchor text. Never link to any external site.
        - End the body with a short closing section that tells the reader what to do next.

        Rules for "faq": questions a real owner would type into Google, answered in two to four
        sentences each. Same prohibitions apply.

        The site is {$site}. Do not mention that this was written by an AI.
        TXT;
    }

    /** The per-topic instruction. */
    public static function userPrompt(array $topic, array $recentTitles): string
    {
        $avoid = $recentTitles
            ? "\n\nAlready published recently — do not repeat these angles, and do not write a\n"
              . "piece that would overlap substantially with any of them:\n- "
              . implode("\n- ", array_map(static fn($t) => cut((string)$t, 120), $recentTitles))
            : '';

        $cluster = self::CLUSTERS[$topic['cluster']] ?? $topic['cluster'];

        return "Write one article.\n\n"
             . "Topic: {$topic['title_seed']}\n"
             . "Angle: {$topic['angle']}\n"
             . "Category: {$cluster}\n\n"
             . "The topic line is a brief, not a title — write the best title you can for the "
             . "piece you actually produce, under 60 characters.\n"
             . "The angle is what the article has to argue. Follow it."
             . $avoid;
    }

    /* ── Quality gates ─────────────────────────────────────── */

    /**
     * Patterns that mean the piece invented its evidence. Deliberately
     * blunt: a false positive costs one regeneration, a false negative
     * puts a made-up statistic on a site whose entire argument is that it
     * does not do that.
     */
    public const BANNED = [
        // Unsourced appeals to evidence.
        '/\b(studies|research|surveys|reports|data|statistics)\s+(show|shows|indicate|indicates|suggest|suggests|reveal|reveals|found|find)\b/i'
            => 'unsourced appeal to research',
        '/\baccording to (a |recent |several )?(study|studies|research|survey|report)\b/i'
            => 'unsourced "according to a study"',
        '/\b(experts|analysts|marketers) (agree|say|believe)\b/i'
            => 'unsourced appeal to experts',
        '/\bit is (well[- ])?known that\b/i' => 'unsourced "it is well known"',

        // Invented population statistics.
        '/\b\d{1,3}(\.\d+)?\s*(%|per ?cent)\s+of\s+(businesses|companies|customers|users|people|consumers|websites|marketers|shoppers|visitors|searches|indian)/i'
            => 'invented statistic about a population',
        '/\b(\d{1,2}|one|two|three|four|five|six|seven|eight|nine)\s+(in|out of)\s+(\d{1,2}|ten|five|four|three)\s+(businesses|companies|customers|users|people|websites|consumers)/i'
            => 'invented "X in Y" statistic',
        '/\b(over|nearly|almost|more than|around|about)\s+\d{1,3}(\.\d+)?\s*(%|per ?cent)\s+of\b/i'
            => 'invented percentage claim',

        // Client and result fabrication.
        '/\b(one|a) of our clients\b/i'                  => 'invented client',
        '/\bwe (helped|worked with|built for) (a|an|one) [a-z ]{3,40}(business|company|client|brand|shop|store)\b/i'
            => 'invented client engagement',
        '/\bour clients? (saw|got|achieved|reported|increased|grew)\b/i' => 'invented client result',
        '/\bwe (increased|grew|doubled|tripled|boosted) [a-z ]{0,20}(traffic|revenue|sales|rankings|leads)\b/i'
            => 'invented result claim',
        /* Also narrowed: an article that tells the reader to ASK an agency for
           a case study is giving good advice. Claiming we have one is not. */
        '/\bour (?:case stud(?:y|ies)|success stor(?:y|ies)|portfolio of results)\b/i'
            => 'a case study we do not have',

        // AI tells and filler.
        /* Two or three stacked adjectives are the norm here ("today's
           fast-paced digital world"), so allow a run of them rather than
           exactly one — the single-adjective version never matched. */
        '/\bin (?:today\'s|the)\b[a-z\s\-]{0,40}\b(?:world|landscape|age|era|marketplace)\b/i'
            => 'opening cliché',
        '/\b(delve|delves|delving) into\b/i'             => 'AI filler ("delve into")',
        '/\b(rich |vibrant )?tapestry\b/i'               => 'AI filler ("tapestry")',
        '/\b(a )?testament to\b/i'                       => 'AI filler ("testament to")',
        '/\bunlock the (power|potential|secrets)\b/i'    => 'AI filler ("unlock the power")',
        '/\bgame[- ]chang(er|ing)\b/i'                   => 'AI filler ("game-changer")',
        '/\bin conclusion,/i'                            => 'AI filler ("in conclusion")',
        '/\bas an AI\b/i'                                => 'model broke character',
        '/\bit\'s important to note that\b/i'            => 'AI filler',

        /* Promises we must not make.
        
           Narrow on purpose. The first version matched the word "guarantee"
           anywhere, which rejected a piece for the sentence "nobody can
           guarantee a ranking" — the exact honest point the brief wants made.
           What must be caught is US making the promise, not the word. */
        '/\b(?:we|wwwebtech)\s+(?:will\s+|can\s+|do\s+)?guarantee\b/i'
            => 'a guarantee we cannot make',
        '/\b(?:we|our team|wwwebtech)\b[^.!?]{0,40}\bguarantee(?:s|d)?\b/i'
            => 'a guarantee we cannot make',
        '/\bmoney[-\s]back guarantee\b/i'                => 'a guarantee we cannot make',
        '/\bwe (will|can) (get|rank) you (to |at )?(number one|#1|first|top)\b/i'
            => 'ranking promise',
    ];

    /**
     * Phrases that are only a problem when the piece is OFFERING the thing,
     * rather than warning the reader against it.
     *
     * The system prompt REQUIRES every article to name something commonly
     * sold in this category that does not work, and for SEO that thing is
     * almost always "guaranteed rankings". A flat ban on the phrase therefore
     * rejects the article for containing the warning it was told to write —
     * two real generations were thrown away proving exactly that. So these
     * are checked against their surrounding sentence instead.
     */
    public const BANNED_UNLESS_WARNING = [
        '/\bguaranteed\s+(?:results|rankings?|traffic|leads|placement|positions?|roi|success|growth)\b/i'
            => 'a guaranteed outcome',
        '/\bfirst[- ]page guarantee\b/i' => 'a first-page guarantee',
        '/\binstant results?\b/i'        => 'a promise of instant results',
    ];

    /** How much text either side of a match counts as "the surrounding sentence". */
    public const WARNING_WINDOW = 170;

    /** Words that show the phrase is being warned about, not offered. */
    public const WARNING_CUES =
        '/\b(?:no|not|never|nobody|none|cannot|can\'t|won\'t|avoid|beware|wary|
             warning|red flag|walk away|lying|lie|lies|impossible|myth|scam|
             nonsense|does ?n[o\']t exist|sceptical|skeptical|promise[sd]?|
             claims?|sell|sold|selling|offer(?:s|ed|ing)?|anyone who|
             should make you|steer clear|do ?n[o\']t buy)\b/ix';

    /** True when $text around $pos reads as a warning rather than an offer. */
    public static function readsAsWarning(string $text, int $pos, int $len): bool
    {
        $from = max(0, $pos - self::WARNING_WINDOW);
        $window = substr($text, $from, $len + 2 * self::WARNING_WINDOW);
        return (bool)preg_match(self::WARNING_CUES, $window);
    }

    /** Internal paths a generated post is allowed to link to. */
    public const ALLOWED_LINKS = [
        '/', '/blog/', '/contact/', '/about/', '/work/', '/services/',
        '/services/web-development/', '/services/crm-systems/', '/services/business-automation/',
        '/services/seo/', '/services/ai-visibility-geo/', '/services/social-media-marketing/',
        '/services/technical-support/',
    ];

    /**
     * Run every mechanical check. Returns a list of failure reasons; an
     * empty list means the piece may be published.
     *
     * @param array $p decoded generator output
     */
    public static function gates(array $p, array $recentBodies = []): array
    {
        $fail  = [];
        $title = trim((string)($p['title'] ?? ''));
        $dek   = trim((string)($p['dek'] ?? ''));
        $body  = (string)($p['body'] ?? '');
        $faq   = is_array($p['faq'] ?? null) ? $p['faq'] : [];

        /* -- Shape -- */
        if ($title === '') $fail[] = 'no title';
        elseif (mb_strlen($title) > self::MAX_TITLE) {
            $fail[] = sprintf('title is %d characters, limit %d', mb_strlen($title), self::MAX_TITLE);
        }
        if ($dek === '') $fail[] = 'no description';
        elseif (mb_strlen($dek) > 400) $fail[] = 'description is too long for a meta description';

        if ($body === '') { $fail[] = 'no body'; return $fail; }

        /* -- Structure -- */
        if (preg_match('/<h1\b/i', $body)) $fail[] = 'body contains an <h1>; the page supplies the only one';
        $h2 = preg_match_all('/<h2\b[^>]*>/i', $body);
        if ($h2 < self::MIN_H2) $fail[] = sprintf('only %d <h2> sections, minimum %d', $h2, self::MIN_H2);

        /* Every h2 needs an id, or the contents list on the page is empty. */
        $withId = preg_match_all('/<h2\b[^>]*\bid="[^"]+"/i', $body);
        if ($withId < $h2) $fail[] = sprintf('%d of %d <h2> headings have no id', $h2 - $withId, $h2);

        if (count($faq) < 3) $fail[] = 'fewer than 3 FAQ entries';
        foreach ($faq as $i => $f) {
            if (trim((string)($f['q'] ?? '')) === '' || trim((string)($f['a'] ?? '')) === '') {
                $fail[] = 'FAQ entry ' . ($i + 1) . ' is incomplete';
            }
        }

        /* -- Length -- */
        $words = self::wordCount($body);
        if ($words < self::MIN_WORDS) {
            $fail[] = sprintf('%d words, minimum %d', $words, self::MIN_WORDS);
        }

        /* -- Forbidden markup -- */
        if (preg_match('/<(script|style|iframe|object|embed|form|input)\b/i', $body, $m)) {
            $fail[] = 'body contains a <' . strtolower($m[1]) . '> tag';
        }
        if (preg_match('/\bon[a-z]+\s*=\s*["\']/i', $body)) $fail[] = 'body contains an inline event handler';
        if (preg_match('/javascript:/i', $body)) $fail[] = 'body contains a javascript: URL';

        /* -- Links resolve -- */
        preg_match_all('/<a\b[^>]*href="([^"]*)"/i', $body . self::faqText($faq), $links);
        foreach (array_unique($links[1] ?? []) as $href) {
            if ($href === '' || str_starts_with($href, '#')) continue;
            if (preg_match('#^https?://#i', $href)) {
                $fail[] = 'links to an external site: ' . cut($href, 60);
                continue;
            }
            $path = '/' . trim((string)parse_url($href, PHP_URL_PATH), '/');
            if ($path !== '/') $path .= '/';
            if (!in_array($path, self::ALLOWED_LINKS, true)) {
                $fail[] = 'links to a page that does not exist: ' . cut($href, 60);
            }
        }

        /* -- Invented evidence and filler -- */
        $prose = self::plainText($body) . ' ' . self::faqText($faq) . ' ' . $title . ' ' . $dek;
        foreach (self::BANNED as $re => $why) {
            if (preg_match($re, $prose, $m)) {
                $fail[] = $why . ' — "' . cut(trim($m[0]), 60) . '"';
            }
        }

        /* The ambiguous ones: a match only counts if the sentence around it is
           not warning the reader off the very thing it names. */
        foreach (self::BANNED_UNLESS_WARNING as $re => $why) {
            if (!preg_match($re, $prose, $m, PREG_OFFSET_CAPTURE)) continue;
            [$hit, $at] = $m[0];
            if (self::readsAsWarning($prose, (int)$at, strlen($hit))) continue;
            $fail[] = $why . ' — "' . cut(trim($hit), 60) . '"';
        }

        /* -- Not a rewrite of something already published -- */
        foreach ($recentBodies as $prev) {
            $sim = self::similarity($prose, (string)$prev['text']);
            if ($sim >= self::SIMILARITY) {
                $fail[] = sprintf('%.0f%% similar to "%s"', $sim * 100, cut((string)$prev['title'], 60));
                break;
            }
        }

        return $fail;
    }

    /* ── Text helpers ──────────────────────────────────────── */

    public static function plainText(string $html): string
    {
        $t = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
    }

    public static function faqText(array $faq): string
    {
        $s = '';
        foreach ($faq as $f) $s .= ' ' . (string)($f['q'] ?? '') . ' ' . (string)($f['a'] ?? '');
        return $s;
    }

    public static function wordCount(string $html): int
    {
        $t = self::plainText($html);
        return $t === '' ? 0 : count(preg_split('/\s+/u', $t) ?: []);
    }

    /**
     * Jaccard overlap of word trigrams. Word trigrams rather than
     * character ones because the thing worth catching is the same article
     * written again in different words, not a shared turn of phrase.
     */
    public static function similarity(string $a, string $b): float
    {
        $tri = static function (string $s): array {
            $w = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $out = [];
            for ($i = 0, $n = count($w) - 2; $i < $n; $i++) {
                $out[$w[$i] . ' ' . $w[$i + 1] . ' ' . $w[$i + 2]] = true;
            }
            return $out;
        };
        $A = $tri($a); $B = $tri($b);
        if (!$A || !$B) return 0.0;
        $inter = count(array_intersect_key($A, $B));
        $union = count($A) + count($B) - $inter;
        return $union > 0 ? $inter / $union : 0.0;
    }

    /** The last N published posts, as comparison material. */
    public static function recentForComparison(int $n = self::COMPARE_N): array
    {
        $rows = DB::all(
            "SELECT title, first_para FROM wwt_posts
             WHERE status IN ('published','unpublished') ORDER BY id DESC LIMIT $n");
        return array_map(static fn($r) => [
            'title' => (string)$r['title'],
            'text'  => (string)$r['first_para'],
        ], $rows);
    }

    public static function recentTitles(int $n = 20): array
    {
        return array_column(
            DB::all("SELECT title FROM wwt_posts WHERE status='published' ORDER BY id DESC LIMIT $n"),
            'title');
    }

    /* ── Slugs ─────────────────────────────────────────────── */

    public static function uniqueSlug(string $title): string
    {
        $base = slugify($title) ?: 'post-' . gmdate('Ymd');
        $slug = $base;
        $i = 2;
        while (DB::val('SELECT 1 FROM wwt_posts WHERE slug = ?', [$slug]) !== null
            || is_dir(self::webroot() . '/blog/' . $slug)) {
            $slug = cut($base, 70) . '-' . $i;
            if (++$i > 50) { $slug = $base . '-' . substr(bin2hex(random_bytes(3)), 0, 5); break; }
        }
        return $slug;
    }

    public static function webroot(): string
    {
        return rtrim((string)cfg('site.webroot', dirname(WWT_PRIVATE) . '/webroot'), '/');
    }

    /* ── Topic bank ────────────────────────────────────────── */

    /** Load the seed file into wwt_topics. Existing topics are left alone. */
    public static function seedTopics(): int
    {
        $file = WWT_PRIVATE . '/data/topics.php';
        if (!is_file($file)) throw new RuntimeException('Topic seed file is missing.');
        $seed = require $file;

        $added = 0;
        foreach ($seed as $cluster => $rows) {
            foreach (array_values($rows) as $i => $row) {
                [$title, $angle] = $row;
                $exists = DB::val('SELECT 1 FROM wwt_topics WHERE cluster = ? AND title_seed = ?',
                                  [$cluster, $title]);
                if ($exists !== null) continue;
                DB::run('INSERT INTO wwt_topics (cluster, title_seed, angle, sort_order)
                         VALUES (?,?,?,?)', [$cluster, cut($title, 200), cut($angle, 400), $i]);
                $added++;
            }
        }
        if ($added) audit('topics_seed', $added . ' topics added');
        return $added;
    }

    /**
     * The next topic to write.
     *
     * Rotates clusters by which one has gone longest without a published
     * post, so the blog does not become thirty pieces about SEO and none
     * about anything else.
     */
    public static function nextTopic(): ?array
    {
        /* The aggregate is wrapped in a derived table so the ORDER BY sorts a
           plain column. Referring to an aggregate ALIAS inside an expression
           ("ORDER BY (last_used IS NOT NULL)") is accepted by MariaDB 10 and
           rejected by MariaDB 11 with error 1247 — which meant this worked in
           development and failed on the live server. This form is portable
           across MySQL 5.7/8 and every MariaDB. */
        $order = DB::all(
            "SELECT cluster, last_used FROM (
               SELECT c.cluster AS cluster, MAX(p.published_at) AS last_used
               FROM (SELECT DISTINCT cluster FROM wwt_topics) c
               LEFT JOIN wwt_posts p ON p.cluster = c.cluster AND p.status = 'published'
               GROUP BY c.cluster
             ) x
             ORDER BY (last_used IS NOT NULL), last_used ASC");

        foreach ($order as $row) {
            $t = DB::one(
                'SELECT * FROM wwt_topics WHERE used_at IS NULL AND cluster = ?
                 ORDER BY sort_order, id LIMIT 1', [$row['cluster']]);
            if ($t) return $t;
        }
        // Every cluster is exhausted; fall back to anything unused.
        return DB::one('SELECT * FROM wwt_topics WHERE used_at IS NULL ORDER BY sort_order, id LIMIT 1');
    }

    public static function topicsLeft(): int
    {
        return (int)DB::val('SELECT COUNT(*) FROM wwt_topics WHERE used_at IS NULL', [], 0);
    }

    /* ── Generation ────────────────────────────────────────── */

    public static function enabled(): bool
    {
        return Settings::bool('blog_enabled', false);
    }

    /** When the daily cron is configured to run, as HH:MM in server (UTC) time. */
    public static function dailyCronAt(): string
    {
        $v = (string)Settings::get('cron_daily_at', '02:30');
        return preg_match('/^\d{2}:\d{2}$/', $v) ? $v : '02:30';
    }

    /**
     * A plain description of when the next automatic post is due, and whether
     * anything is actually going to produce one.
     *
     * This exists because "why has nothing been published?" has four possible
     * answers — the switch is off, no key, the schedule has not come round
     * yet, or cron is not firing — and a panel that shows none of them leaves
     * the owner guessing.
     *
     * @return array{due:string, blocked:string, cron_ok:bool}
     */
    public static function scheduleStatus(): array
    {
        $blocked = '';
        if (!self::enabled())            $blocked = 'Automatic publishing is switched off.';
        elseif (!Claude::configured())   $blocked = 'No API key is set.';
        elseif (self::perDay() === 0)    $blocked = 'Posts a day is set to zero.';
        elseif (self::topicsLeft() === 0) $blocked = 'The topic bank is empty.';
        elseif (Claude::budgetLeft() <= 0) $blocked = 'The monthly cap has been reached.';

        [$h, $m] = array_map('intval', explode(':', self::dailyCronAt()));
        $now  = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $next = $now->setTime($h, $m);
        if ($next <= $now) $next = $next->modify('+1 day');
        $due = $next->setTimezone(new DateTimeZone(WWT_TZ_DISPLAY))->format('D d M, H:i');

        /* Has the hourly job run recently? If not, cron is not firing at all
           and nothing scheduled will happen whatever the settings say. */
        $lastHourly = DB::val("SELECT last_end FROM wwt_task_runs WHERE task = 'analytics_hourly'", [], null);
        $cronOk = $lastHourly !== null
            && strtotime((string)$lastHourly . ' UTC') > time() - 3 * 3600;

        return ['due' => $due, 'blocked' => $blocked, 'cron_ok' => $cronOk];
    }

    public static function perDay(): int
    {
        return max(0, min(4, Settings::int('blog_per_day', 2)));
    }

    /**
     * Generate, check, and (if it passes) publish one post.
     *
     * Returns a result array in every case. A rejected post is STILL
     * recorded, with its reasons, so the panel can show what was wrong and
     * the topic is not silently burned.
     *
     * @param bool $publish false runs the whole pipeline but leaves the post
     *                      queued — used by "generate a draft" in the panel.
     */
    public static function generateOne(bool $publish = true, ?array $topic = null): array
    {
        $topic ??= self::nextTopic();
        if (!$topic) return ['ok' => false, 'error' => 'The topic bank is empty. Add topics in the panel.'];

        $recentTitles = self::recentTitles();
        $recentBodies = self::recentForComparison();

        $res = Claude::message(
            self::systemPrompt(),
            self::userPrompt($topic, $recentTitles),
            ['max_tokens' => 16000, 'effort' => Settings::get('blog_effort', 'medium')]
        );

        if (!$res['ok']) {
            self::recordFailure($topic, $res, 'api: ' . $res['error']);
            return ['ok' => false, 'error' => $res['error'], 'cost' => $res['cost']];
        }

        $parsed = self::parse($res['text']);
        if ($parsed === null) {
            self::recordFailure($topic, $res, 'the reply was not the JSON object the prompt asked for');
            return ['ok' => false, 'error' => 'Unparseable reply from the model.', 'cost' => $res['cost']];
        }

        $fails = self::gates($parsed, $recentBodies);
        if ($fails) {
            self::recordFailure($topic, $res, implode('; ', array_slice($fails, 0, 6)), $parsed);
            return ['ok' => false, 'error' => 'Quality gates rejected it: ' . implode('; ', $fails),
                    'cost' => $res['cost'], 'gates' => $fails];
        }

        /* Passed. Store, then publish. */
        $slug  = self::uniqueSlug((string)$parsed['title']);
        $words = self::wordCount((string)$parsed['body']);
        $id = DB::insert(
            "INSERT INTO wwt_posts
             (slug, title, dek, cluster, status, created_at, model, tokens_in, tokens_out,
              cost_usd, word_count, topic_id, first_para)
             VALUES (?,?,?,?, 'queued', UTC_TIMESTAMP(), ?,?,?,?,?,?,?)",
            [$slug, cut((string)$parsed['title'], 200), cut((string)$parsed['dek'], 400),
             (string)$topic['cluster'], $res['model'], $res['in'], $res['out'], $res['cost'],
             $words, (int)$topic['id'], self::plainText((string)$parsed['body'])]);

        DB::run('UPDATE wwt_topics SET used_at = UTC_TIMESTAMP() WHERE id = ?', [(int)$topic['id']]);

        /* The full article is kept on disk, not in the database: it is the
           thing being served, and a re-publish must reproduce it byte for
           byte without another API call. */
        self::saveSource($slug, $parsed);

        if ($publish) {
            Publisher::publish($id, [
                'slug' => $slug, 'title' => $parsed['title'], 'dek' => $parsed['dek'],
                'date' => gmdate('Y-m-d'), 'read' => (int)($parsed['read'] ?? max(1, (int)round($words / 220))),
                'body' => $parsed['body'],
            ], (array)$parsed['faq']);
        }

        return ['ok' => true, 'id' => $id, 'slug' => $slug, 'title' => $parsed['title'],
                'words' => $words, 'cost' => $res['cost'], 'published' => $publish];
    }

    /** Where a generated article's source of truth lives. */
    public static function sourcePath(string $slug): string
    {
        return WWT_PRIVATE . '/posts/' . $slug . '.json';
    }

    public static function saveSource(string $slug, array $parsed): void
    {
        $dir = WWT_PRIVATE . '/posts';
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        @file_put_contents(self::sourcePath($slug),
            json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public static function loadSource(string $slug): ?array
    {
        $f = self::sourcePath($slug);
        if (!is_file($f)) return null;
        $d = json_decode((string)file_get_contents($f), true);
        return is_array($d) ? $d : null;
    }

    private static function recordFailure(array $topic, array $res, string $why, ?array $parsed = null): void
    {
        $title = $parsed['title'] ?? ('[rejected] ' . $topic['title_seed']);
        $slug  = 'rejected-' . gmdate('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 5);

        /* Keep the whole article, not just the excerpt that goes in the
           database. A rejection is a judgement, and judging whether it was
           the right one needs the text that was actually written — which is
           also the only way to tell a real catch from an over-eager rule. */
        if ($parsed) self::saveSource($slug, $parsed + ['_rejected_for' => $why]);
        try {
            DB::insert(
                "INSERT INTO wwt_posts
                 (slug, title, dek, cluster, status, created_at, model, tokens_in, tokens_out,
                  cost_usd, word_count, topic_id, first_para, reject_reason)
                 VALUES (?,?,?,?, 'failed', UTC_TIMESTAMP(), ?,?,?,?,?,?,?,?)",
                [$slug, cut((string)$title, 200), cut((string)($parsed['dek'] ?? ''), 400),
                 (string)$topic['cluster'], $res['model'], $res['in'], $res['out'], $res['cost'],
                 $parsed ? self::wordCount((string)($parsed['body'] ?? '')) : 0,
                 (int)$topic['id'], cut(self::plainText((string)($parsed['body'] ?? '')), 4000),
                 cut($why, 255)]);
        } catch (Throwable $t) {
            wwt_log('blog', 'could not record failure', ['err' => $t->getMessage()]);
        }
        /* The topic is NOT marked used: a rejection is our failure, not the
           topic's, and it should be attempted again. */
        wwt_log('blog', 'rejected', ['topic' => $topic['title_seed'], 'why' => $why]);
    }

    /**
     * Pull the JSON object out of the reply. The prompt asks for bare JSON,
     * but a stray code fence or a sentence of preamble is a cheap thing to
     * survive and an expensive thing to regenerate over.
     */
    public static function parse(string $text): ?array
    {
        $t = trim($text);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $t, $m)) $t = trim($m[1]);
        $d = json_decode($t, true);
        if (!is_array($d)) {
            $a = strpos($t, '{');
            $b = strrpos($t, '}');
            if ($a === false || $b === false || $b <= $a) return null;
            $d = json_decode(substr($t, $a, $b - $a + 1), true);
        }
        if (!is_array($d) || !isset($d['title'], $d['body'])) return null;
        $d['faq'] = is_array($d['faq'] ?? null) ? $d['faq'] : [];
        $d['dek'] = (string)($d['dek'] ?? '');
        $d['read'] = (int)($d['read'] ?? 0);
        return $d;
    }
}
