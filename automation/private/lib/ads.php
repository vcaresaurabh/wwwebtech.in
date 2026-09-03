<?php
/* ============================================================
   ads.php — the conversion feedback loop (§10).

   The point, stated plainly: Google and Microsoft optimise toward the
   outcomes you report. Report only form fills and they will get very good
   at producing form fills from people who never buy. Report Qualified and
   Won, and the bidding starts hunting for revenue.

   Upload is by SCHEDULED FETCH, not the Ads API. The API needs a developer
   token and an OAuth app, which is a lot of machinery for a business
   uploading a handful of rows a week. Both platforms can pull a CSV from
   an HTTPS URL on a schedule, and that is all this needs to be.

   A lead with no click ID cannot be attributed by click. Those go out via
   Enhanced Conversions instead — hashed email and phone — and the panel
   shows how many fell into each bucket rather than quietly dropping them.
   ============================================================ */

declare(strict_types=1);

final class Ads
{
    /** The conversion action names. These must match what the owner
        created in Google Ads exactly — the CSV is matched on them. */
    public const ACTIONS = [
        'Lead form submit' => 'lead',
        'Qualified lead'   => 'qualified',
        'Won deal'         => 'won',
    ];

    /** Queue a conversion. Idempotent per (lead, platform, action). */
    public static function queue(int $leadId, string $action, float $valueInr = 0.0): array
    {
        if (!isset(self::ACTIONS[$action])) {
            return ['ok' => false, 'error' => 'unknown conversion action: ' . $action];
        }
        $l = DB::one('SELECT * FROM wwt_leads WHERE id = ?', [$leadId]);
        if (!$l) return ['ok' => false, 'error' => 'no such lead'];
        if ((int)$l['is_test'] === 1 || (int)$l['is_partial'] === 1) {
            return ['ok' => true, 'skipped' => 'test or partial lead'];
        }

        $out = [];
        foreach ([
            'google'    => (string)$l['gclid'] ?: (string)$l['gbraid'] ?: (string)$l['wbraid'],
            'microsoft' => (string)$l['msclkid'],
        ] as $platform => $clickId) {
            /* No click ID is not a reason to skip: that row is exactly what
               Enhanced Conversions exists to match, on a hashed email
               instead. Storing it as 'skipped' meant the enhanced export —
               which looks for queued rows — was permanently empty. The
               reason is recorded; the row stays in the queue. */
            $skip = $clickId === '' ? 'no click ID — goes out via enhanced conversions' : '';
            try {
                DB::run(
                    'INSERT INTO wwt_ad_conversion_queue
                     (lead_id, ts, platform, action, click_id, value_inr, occurred_at, status, skip_reason)
                     VALUES (?, UTC_TIMESTAMP(), ?, ?, ?, ?, UTC_TIMESTAMP(), ?, ?)
                     ON DUPLICATE KEY UPDATE value_inr = VALUES(value_inr),
                       occurred_at = VALUES(occurred_at)',
                    [$leadId, $platform, $action, $clickId, $valueInr,
                     ($platform === 'microsoft' && $clickId === '') ? 'skipped' : 'queued', $skip]);
                $out[$platform] = $skip === ''
                    ? 'queued'
                    : ($platform === 'microsoft'
                        ? 'skipped — Microsoft has no enhanced-conversion fallback'
                        : 'queued for enhanced conversions');
            } catch (Throwable $t) {
                wwt_log('ads', 'queue failed', ['lead' => $leadId, 'err' => $t->getMessage()]);
                $out[$platform] = 'error';
            }
        }
        Timeline::add($leadId, 'conversion_queued', 'ads', $action);
        return ['ok' => true, 'platforms' => $out];
    }

    /**
     * Google Ads offline conversion import format.
     * Google requires the timezone declared on the first line.
     */
    public static function googleCsv(): string
    {
        $tz = WWT_TZ_DISPLAY;
        $rows = DB::all(
            "SELECT q.*, l.name FROM wwt_ad_conversion_queue q
             JOIN wwt_leads l ON l.id = q.lead_id
             WHERE q.platform = 'google' AND q.status = 'queued' AND q.click_id <> ''
             ORDER BY q.occurred_at LIMIT 5000");

        $out = "Parameters:TimeZone=" . $tz . "\n";
        $out .= "Google Click ID,Conversion Name,Conversion Time,Conversion Value,Conversion Currency\n";
        foreach ($rows as $r) {
            $out .= implode(',', [
                self::csv((string)$r['click_id']),
                self::csv((string)$r['action']),
                self::csv(self::stamp((string)$r['occurred_at'])),
                (string)(float)$r['value_inr'],
                'INR',
            ]) . "\n";
        }
        return $out;
    }

    /** Microsoft Advertising offline conversions. */
    public static function microsoftCsv(): string
    {
        $rows = DB::all(
            "SELECT * FROM wwt_ad_conversion_queue
             WHERE platform = 'microsoft' AND status = 'queued' AND click_id <> ''
             ORDER BY occurred_at LIMIT 5000");

        $out = "Microsoft Click Id,Conversion Name,Conversion Time,Conversion Value,Conversion Currency Code\n";
        foreach ($rows as $r) {
            $out .= implode(',', [
                self::csv((string)$r['click_id']),
                self::csv((string)$r['action']),
                self::csv(self::stamp((string)$r['occurred_at'])),
                (string)(float)$r['value_inr'],
                'INR',
            ]) . "\n";
        }
        return $out;
    }

    /**
     * Enhanced Conversions for Leads — for the leads with no click ID.
     *
     * Google's hashing rules, exactly: lowercase, trim, phone in E.164 with
     * the leading +, then SHA-256 hex. Getting any part of that wrong means
     * the row is silently unmatched rather than rejected, which is why the
     * normalisation is spelled out rather than inlined.
     */
    public static function enhancedCsv(): string
    {
        $rows = DB::all(
            "SELECT q.*, l.email, l.phone FROM wwt_ad_conversion_queue q
             JOIN wwt_leads l ON l.id = q.lead_id
             WHERE q.platform = 'google' AND q.status = 'queued' AND q.click_id = ''
             ORDER BY q.occurred_at LIMIT 5000");

        $out = "Email,Phone Number,Conversion Name,Conversion Time,Conversion Value,Conversion Currency\n";
        foreach ($rows as $r) {
            $out .= implode(',', [
                self::hashEmail((string)$r['email']),
                self::hashPhone((string)$r['phone']),
                self::csv((string)$r['action']),
                self::csv(self::stamp((string)$r['occurred_at'])),
                (string)(float)$r['value_inr'],
                'INR',
            ]) . "\n";
        }
        return $out;
    }

    public static function hashEmail(string $email): string
    {
        $e = strtolower(trim($email));
        if ($e === '') return '';
        /* Google normalises gmail addresses by removing dots in the local
           part. Not doing so is a silent non-match. */
        if (preg_match('/^([^@]+)@(gmail\.com|googlemail\.com)$/', $e, $m)) {
            $e = str_replace('.', '', $m[1]) . '@gmail.com';
        }
        return hash('sha256', $e);
    }

    public static function hashPhone(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone) ?? '';
        if ($d === '') return '';
        if (strlen($d) === 10) $d = '91' . $d;
        return hash('sha256', '+' . $d);
    }

    /** Mark rows as fetched once a platform has pulled them. */
    public static function markFetched(string $platform, bool $enhanced = false): int
    {
        $sql = "UPDATE wwt_ad_conversion_queue SET status = 'fetched', fetched_at = UTC_TIMESTAMP()
                WHERE platform = ? AND status = 'queued' AND click_id " . ($enhanced ? "= ''" : "<> ''");
        // SAFE-SQL: the only interpolation is a fixed clause chosen by a bool.
        return DB::run($sql, [$platform])->rowCount();
    }

    /**
     * One row per platform, ready to render.
     * A nested status map read well in a var_dump and badly in a table,
     * and the panel is the only caller.
     *
     * @return list<array{platform:string,queued:int,fetched:int,noclick:int}>
     */
    public static function summary(): array
    {
        return DB::all(
            "SELECT platform,
                    SUM(status = 'queued')  AS queued,
                    SUM(status = 'fetched') AS fetched,
                    SUM(click_id = '')      AS noclick
             FROM wwt_ad_conversion_queue GROUP BY platform ORDER BY platform");
    }

    private static function stamp(string $utc): string
    {
        try {
            return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone(WWT_TZ_DISPLAY))
                ->format('Y-m-d H:i:s');
        } catch (Throwable) { return $utc; }
    }

    /** RFC 4180 quoting. A comma in a conversion name would otherwise
        shift every column after it. */
    private static function csv(string $v): string
    {
        if ($v === '') return '';
        if (preg_match('/[",\r\n]/', $v)) return '"' . str_replace('"', '""', $v) . '"';
        return $v;
    }
}
