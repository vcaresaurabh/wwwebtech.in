<?php
/* ============================================================
   score.php — lead scoring (§3.3).

   The score exists to answer one question: who gets called first.
   It is not a prediction of revenue and it should never be shown to a
   customer.

   Two decisions worth knowing about:

   · "Not sure yet" on budget scores 10, above "under ₹50k" at 5. Someone
     who has not worked out their budget is earlier in the process, not
     poorer — and forcing a fake answer to avoid an honest one is how you
     end up with a field full of noise.

   · Engagement points (replied, booked, ran the audit) dominate the
     stated intent, because what someone does is better evidence than
     what they ticked.
   ============================================================ */

declare(strict_types=1);

final class Score
{
    /* The brief's thresholds. Kept as the defaults, but readable from
       settings, because where the line sits depends on the real spread of
       leads and that is not knowable in advance.

       Worth watching: with the brief's points, a mid-budget lead on a 1–3
       month timeline who leaves a phone number scores 62 — Hot. If most
       leads come back Hot the band has stopped being useful for triage and
       the threshold wants raising. The panel shows the distribution so the
       owner can see whether that is happening. */
    public const HOT_DEFAULT  = 60;
    public const WARM_DEFAULT = 35;

    public static function hotAt(): int  { return Settings::int('score_hot_at',  self::HOT_DEFAULT); }
    public static function warmAt(): int { return Settings::int('score_warm_at', self::WARM_DEFAULT); }

    /** Budget band → points. Keys match what the form actually submits. */
    public const BUDGET = [
        '₹5L+'            => 30,
        '₹1.5L – ₹5L'     => 25,
        '₹50k – ₹1.5L'    => 15,
        'Under ₹50k'      => 5,
        'Not sure yet'    => 10,
    ];

    public const SERVICE = [
        'CRM' => 20, 'Website' => 15, 'SEO' => 15,
        'Automation' => 15, 'Social' => 10, 'Not sure' => 5,
    ];

    public const TIMELINE = ['asap' => 20, '1-3m' => 12, 'research' => 3];

    public const SOURCE = [
        'paid search' => 15, 'Organic' => 12, 'Direct' => 10,
        'Referral' => 10, 'paid social' => 8, 'Social' => 8,
        'Campaign' => 15, 'AI assistant' => 12,
    ];

    /**
     * Score a lead row (or a prepared array of the same shape).
     *
     * @return array{score:int, band:string, why:array<int,string>}
     */
    public static function of(array $l): array
    {
        $pts = 0;
        $why = [];
        $add = static function (int $n, string $reason) use (&$pts, &$why): void {
            if ($n === 0) return;
            $pts += $n;
            $why[] = sprintf('%+d  %s', $n, $reason);
        };

        /* — Stated intent — */
        $budget = trim((string)($l['budget'] ?? ''));
        $add(self::BUDGET[$budget] ?? 0, 'budget: ' . ($budget !== '' ? $budget : 'not given'));

        /* Services are a multi-select; the strongest one counts, not the sum,
           or ticking every box would outrank a serious single answer. */
        $best = 0; $bestName = '';
        foreach (array_map('trim', explode(',', (string)($l['service'] ?? ''))) as $svc) {
            if (isset(self::SERVICE[$svc]) && self::SERVICE[$svc] > $best) {
                $best = self::SERVICE[$svc]; $bestName = $svc;
            }
        }
        $add($best, 'service: ' . ($bestName ?: 'none given'));

        $tl = (string)($l['timeline'] ?? '');
        $add(self::TIMELINE[$tl] ?? 0, 'timeline: ' . ($tl !== '' ? $tl : 'not given'));

        /* — Where they came from — */
        $paid = (string)($l['gclid'] ?? '') !== '' || (string)($l['msclkid'] ?? '') !== ''
             || (string)($l['gbraid'] ?? '') !== '' || (string)($l['wbraid'] ?? '') !== ''
             || in_array(strtolower((string)($l['utm_medium'] ?? '')), ['cpc', 'ppc', 'paid'], true);
        if ($paid) {
            $add(15, 'arrived from a paid search click');
        } else {
            $group = source_group((string)($l['utm_medium'] ?? ''), (string)($l['utm_source'] ?? ''),
                                  ref_domain((string)($l['referrer'] ?? '')));
            $add(self::SOURCE[$group] ?? 10, 'source: ' . $group);
        }

        /* — Effort they put in — */
        if (trim((string)($l['phone'] ?? '')) !== '')        $add(10, 'gave a phone number');
        if (trim((string)($l['company'] ?? '')) !== '')      $add(5,  'gave a company name');
        $msg = trim((string)($l['message'] ?? ''));
        if (mb_strlen($msg) > 100)                           $add(10, 'wrote more than 100 characters');
        if (trim((string)($l['site_url'] ?? '')) !== '')     $add(8,  'has an existing website');

        /* — Things they did, which beat things they said — */
        if ((string)($l['utm_source'] ?? '') === 'audit_tool'
            || (string)($l['source_tool'] ?? '') === 'audit') $add(15, 'ran the free audit');
        if (!empty($l['booked_at']))                          $add(25, 'booked a call');
        if (!empty($l['replied_at']))                         $add(30, 'replied to a message');

        /* — Signals it is not real — */
        if ($msg !== '' && mb_strlen($msg) < 20)              $add(-10, 'message under 20 characters');
        if (self::looksLikeJunk($l))                          $add(-10, 'looks automated or junk');

        $score = max(0, min(200, $pts));
        return ['score' => $score, 'band' => self::band($score), 'why' => $why];
    }

    public static function band(int $score): string
    {
        if ($score >= self::hotAt())  return 'hot';
        if ($score >= self::warmAt()) return 'warm';
        return 'cold';
    }

    /**
     * How the current thresholds are actually splitting real leads.
     * A band that catches four leads in five is not a priority signal.
     */
    public static function distribution(int $days = 90): array
    {
        $rows = DB::all(
            "SELECT band, COUNT(*) n FROM wwt_leads
             WHERE is_test = 0 AND is_partial = 0
               AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
             GROUP BY band", [$days]);
        $out = ['hot' => 0, 'warm' => 0, 'cold' => 0, 'total' => 0];
        foreach ($rows as $r) { $out[$r['band']] = (int)$r['n']; $out['total'] += (int)$r['n']; }
        return $out;
    }

    /**
     * Cheap junk detection. Deliberately conservative — a false positive
     * costs ten points off a real lead, so it only fires on things a
     * person would not plausibly type.
     */
    public static function looksLikeJunk(array $l): bool
    {
        $name = (string)($l['name'] ?? '');
        $msg  = (string)($l['message'] ?? '');

        if (preg_match('/https?:\/\/|\[url=|<a\s/i', $msg)) return true;      // link spam
        if (preg_match('/(.)\1{6,}/u', $name . $msg)) return true;            // aaaaaaa
        if ($name !== '' && !preg_match('/[aeiouAEIOU]/', $name)
            && mb_strlen($name) > 5) return true;                            // keyboard mash
        if (preg_match('/\b(seo services|backlink|crypto|casino|loan offer)\b/i', $msg)) return true;

        return false;
    }

    /** Recompute and persist. Called on insert and on every engagement event. */
    public static function apply(int $leadId): array
    {
        $l = DB::one('SELECT * FROM wwt_leads WHERE id = ?', [$leadId]);
        if (!$l) return ['score' => 0, 'band' => 'cold', 'why' => []];
        $r = self::of($l);
        DB::run('UPDATE wwt_leads SET score = ?, band = ? WHERE id = ?', [$r['score'], $r['band'], $leadId]);
        return $r;
    }

    /** How the bands read in the panel and in an alert subject line. */
    public static function label(string $band): string
    {
        return ['hot' => 'HOT', 'warm' => 'Warm', 'cold' => 'Cold'][$band] ?? ucfirst($band);
    }

    public static function emoji(string $band): string
    {
        return ['hot' => '🔥', 'warm' => '🙂', 'cold' => '🌱'][$band] ?? '';
    }
}
