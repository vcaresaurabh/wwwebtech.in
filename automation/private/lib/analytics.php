<?php
/* ============================================================
   analytics.php — first-party, cookieless measurement.

   What is stored, and what deliberately is not:

     · No cookies. Nothing is written to the visitor's device, which is
       why the site needs no consent banner.
     · No IP address. Only a /24 (v4) or /48 (v6) prefix, and even that
       is kept solely so a rate limiter and a country lookup have
       something to work with.
     · `session_hash` is sha256(rotating daily salt | ip prefix | user
       agent). The salt is replaced every day and the old one is thrown
       away, so yesterday's visitor and today's cannot be linked even
       by us, even with full database access. It exists to count people
       rather than page loads, and for nothing else.
     · Bots are recorded separately and never counted as visitors.

   India's DPDP Act is the standard being aimed at here: the data is not
   personal, and it is not made personal by combining it with anything
   else we hold.
   ============================================================ */

declare(strict_types=1);

final class Analytics
{
    /** Events the endpoint will accept. Anything else is dropped. */
    public const EVENTS = ['pageview', 'lead', 'click', 'scroll', 'engaged', 'outbound'];

    /* ── The rotating salt ─────────────────────────────────── */

    /**
     * Today's salt, replaced on first use each day. Rotating it is what
     * stops session_hash becoming a stable identifier: last week's hashes
     * cannot be recomputed once the salt that made them is gone.
     */
    public static function dailySalt(): string
    {
        $today = gmdate('Y-m-d');
        if (Settings::get('analytics_salt_day') === $today) {
            $salt = (string)Settings::get('analytics_salt', '');
            if ($salt !== '') return $salt;
        }
        $salt = bin2hex(random_bytes(32));
        Settings::set('analytics_salt', $salt);
        Settings::set('analytics_salt_day', $today);
        return $salt;
    }

    /** A per-visitor, per-day, non-reversible identifier. */
    public static function sessionHash(string $ipTrunc, string $ua): string
    {
        return hash('sha256', self::dailySalt() . '|' . $ipTrunc . '|' . $ua);
    }

    /* ── Recording ─────────────────────────────────────────── */

    /**
     * Record one hit. Never throws: measurement must not be able to break
     * a page load or an enquiry.
     *
     * @param array{path?:string,ref?:string,utm_source?:string,utm_medium?:string,
     *              utm_campaign?:string,event?:string,detail?:string,test?:bool} $in
     */
    public static function record(array $in): bool
    {
        try {
            if (!Settings::bool('analytics_enabled', true)) return false;

            $ua      = cut((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 500);
            $botName = detect_bot($ua);
            $isBot   = $botName !== '';
            $ipT     = ip_truncate(client_ip());

            $event = (string)($in['event'] ?? 'pageview');
            if (!in_array($event, self::EVENTS, true)) $event = 'pageview';

            $path = clean_path((string)($in['path'] ?? '/'));
            $ref  = (string)($in['ref'] ?? ($_SERVER['HTTP_REFERER'] ?? ''));
            $selfHost = (string)(parse_url((string)cfg('site.url', ''), PHP_URL_HOST) ?: 'wwwebtech.in');

            DB::run(
                'INSERT INTO wwt_hits
                 (ts, path, ref_domain, utm_source, utm_medium, utm_campaign, country,
                  device, is_bot, bot_name, session_hash, event, event_detail, ip_trunc, is_test)
                 VALUES (UTC_TIMESTAMP(), ?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $path,
                    ref_domain($ref, $selfHost),
                    cut((string)($in['utm_source'] ?? ''), 80),
                    cut((string)($in['utm_medium'] ?? ''), 80),
                    cut((string)($in['utm_campaign'] ?? ''), 120),
                    '',                                   // country: filled in later if a lookup is configured
                    $isBot ? 'bot' : detect_device($ua),
                    $isBot ? 1 : 0,
                    cut($botName, 40),
                    // A bot is not a visitor, so it gets no visitor identifier.
                    $isBot ? '' : self::sessionHash($ipT, $ua),
                    $event,
                    cut((string)($in['detail'] ?? ''), 255),
                    $ipT,
                    !empty($in['test']) ? 1 : 0,
                ]
            );
            return true;
        } catch (Throwable $t) {
            wwt_log('analytics', 'record failed', ['err' => $t->getMessage()]);
            return false;
        }
    }

    /* ── Rollups ───────────────────────────────────────────── */

    /**
     * Fold one day of raw hits into wwt_daily_rollups. Idempotent: running
     * it twice for the same day produces the same totals, because the day's
     * rows are replaced rather than added to.
     *
     * Raw hits are pruned on a retention schedule; rollups are kept forever,
     * so bots go in too (as device='bot', source=<crawler name>) or the
     * crawler history would disappear with the raw rows.
     */
    public static function rollup(string $day): int
    {
        $rows = DB::all(
            "SELECT path, is_bot, bot_name, utm_source, utm_medium, ref_domain, device,
                    COUNT(*) AS views,
                    COUNT(DISTINCT NULLIF(session_hash, '')) AS visitors
             FROM wwt_hits
             WHERE DATE(ts) = ? AND is_test = 0 AND event = 'pageview'
             GROUP BY path, is_bot, bot_name, utm_source, utm_medium, ref_domain, device",
            [$day]
        );

        return DB::tx(static function () use ($rows, $day) {
            DB::run('DELETE FROM wwt_daily_rollups WHERE d = ?', [$day]);

            /* Several raw groupings collapse into one rollup key (two referrers
               in the same source group, say), so accumulate in PHP before
               writing rather than fighting a UNIQUE constraint per row. */
            $acc = [];
            foreach ($rows as $r) {
                $source = (int)$r['is_bot'] === 1
                    ? ((string)$r['bot_name'] ?: 'Other-Bot')
                    : source_group((string)$r['utm_medium'], (string)$r['utm_source'], (string)$r['ref_domain']);
                $key = $r['path'] . "\0" . $source . "\0" . $r['device'];
                if (!isset($acc[$key])) {
                    $acc[$key] = ['path' => $r['path'], 'source' => $source,
                                  'device' => $r['device'], 'views' => 0, 'visitors' => 0];
                }
                $acc[$key]['views']    += (int)$r['views'];
                // Bots have no session hash, so this stays 0 for them — correct.
                $acc[$key]['visitors'] += (int)$r['visitors'];
            }

            foreach ($acc as $a) {
                DB::run(
                    'INSERT INTO wwt_daily_rollups (d, path, source, country, device, views, visitors)
                     VALUES (?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE views = views + VALUES(views), visitors = visitors + VALUES(visitors)',
                    [$day, cut((string)$a['path'], 190), cut((string)$a['source'], 120), '',
                     (string)$a['device'], $a['views'], $a['visitors']]
                );
            }
            return count($acc);
        });
    }

    /** How many days of raw, row-per-visit detail are kept. */
    public static function retentionDays(): int
    {
        return max(7, Settings::int('hits_retention_days', 90));
    }

    /** Prune raw hits past the retention window. Rollups are untouched. */
    public static function prune(): int
    {
        $days = self::retentionDays();
        return DB::run('DELETE FROM wwt_hits WHERE ts < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', [$days])
                 ->rowCount();
    }

    /* ── Reading, for the panel ────────────────────────────── */

    /** One row per day for the given window, zero-filled so gaps show as gaps. */
    public static function series(int $days): array
    {
        $rows = DB::all(
            "SELECT d, SUM(views) views, SUM(visitors) visitors
             FROM wwt_daily_rollups
             WHERE d >= DATE_SUB(UTC_DATE(), INTERVAL ? DAY) AND device <> 'bot'
             GROUP BY d ORDER BY d",
            [$days - 1]
        );
        $by = [];
        foreach ($rows as $r) $by[(string)$r['d']] = $r;

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = gmdate('Y-m-d', strtotime("-$i day", strtotime(gmdate('Y-m-d'))));
            $out[] = [
                'd'        => $d,
                'views'    => (int)($by[$d]['views'] ?? 0),
                'visitors' => (int)($by[$d]['visitors'] ?? 0),
            ];
        }
        return $out;
    }

    public static function totals(int $days): array
    {
        $r = DB::one(
            "SELECT COALESCE(SUM(views),0) views, COALESCE(SUM(visitors),0) visitors
             FROM wwt_daily_rollups
             WHERE d >= DATE_SUB(UTC_DATE(), INTERVAL ? DAY) AND device <> 'bot'",
            [$days - 1]
        ) ?: [];
        return ['views' => (int)($r['views'] ?? 0), 'visitors' => (int)($r['visitors'] ?? 0)];
    }

    public static function topBy(string $column, int $days, int $limit = 12): array
    {
        if (!in_array($column, ['path', 'source', 'device'], true)) return [];
        return DB::all(
            "SELECT `$column` AS k, SUM(views) views, SUM(visitors) visitors
             FROM wwt_daily_rollups
             WHERE d >= DATE_SUB(UTC_DATE(), INTERVAL ? DAY) AND device <> 'bot'
             GROUP BY `$column` ORDER BY views DESC LIMIT $limit",
            [$days - 1]
        );
    }

    /** Crawler activity, from the rollups so it survives raw-hit pruning. */
    public static function crawlers(int $days, int $limit = 20): array
    {
        return DB::all(
            "SELECT source AS k, SUM(views) views, COUNT(DISTINCT d) days_seen, MAX(d) last_seen
             FROM wwt_daily_rollups
             WHERE d >= DATE_SUB(UTC_DATE(), INTERVAL ? DAY) AND device = 'bot'
             GROUP BY source ORDER BY views DESC LIMIT $limit",
            [$days - 1]
        );
    }

    /** The crawlers that matter for AI answer engines, named explicitly. */
    public const AI_CRAWLERS = [
        'GPTBot', 'ChatGPT-User', 'OAI-SearchBot', 'ClaudeBot', 'Claude-Web',
        'anthropic-ai', 'PerplexityBot', 'Perplexity-User', 'Google-Extended',
        'Applebot-Extended', 'meta-externalagent', 'CCBot',
    ];

    public static function isAiCrawler(string $name): bool
    {
        return in_array($name, self::AI_CRAWLERS, true);
    }
}
