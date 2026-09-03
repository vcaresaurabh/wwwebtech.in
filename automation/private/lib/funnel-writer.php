<?php
/* ============================================================
   funnel-writer.php — composing the message (§6.6, §6.7).

   Mirrors the blog engine deliberately: the same idea of a system prompt
   with hard prohibitions, mechanical gates before anything is sent, and a
   fallback that is good enough to ship alone.

   The important difference from the blog: this writes to ONE person, who
   wrote to us first, in their own words. Getting that wrong is worse than
   a bad blog post, because they will read it as a person lying to them
   rather than as a company being generic.

   Two gate failures fall back to the static template. Never send nothing,
   never send something broken.
   ============================================================ */

declare(strict_types=1);

final class FunnelWriter
{
    /* 90 was the first guess and it was wrong: a good follow-up email is
       often 60-70 words, and a floor of 90 does not raise quality, it just
       teaches the model to pad. The gate exists to catch a one-line stub,
       so it sits just above one. */
    public const MIN_WORDS_EMAIL = 55;
    public const MAX_WORDS_EMAIL = 160;
    public const MAX_WORDS_WA    = 60;

    /* ── The system prompt ─────────────────────────────────── */

    public static function systemPrompt(string $channel): string
    {
        $sender = self::senderName();
        $site   = rtrim((string)cfg('site.url', 'https://wwwebtech.in'), '/');
        $len = $channel === 'whatsapp'
            ? 'Under ' . self::MAX_WORDS_WA . ' words. One short paragraph.'
            : self::MIN_WORDS_EMAIL . ' to ' . self::MAX_WORDS_EMAIL . ' words.';

        return <<<TXT
        You write follow-up messages for Wwwebtech, a small web, SEO and social
        agency in East Delhi, India. You are writing AS {$sender}, a real person
        who works there, to ONE person who has just enquired.

        They wrote to us first. Everything you write must sound like a reply to
        what they actually said, not like a campaign that happens to have their
        name in it.

        HOW TO WRITE
        - Plain Indian-English business register. Warm, direct, not chummy.
        - {$len}
        - Exactly ONE call to action. Not two, not a PS with another.
        - Refer to their actual words and their actual situation.
        - Short sentences. No preamble, no "I hope this email finds you well".
        - British spelling. Rupees, not dollars.
        - Sign off as {$sender}.

        ABSOLUTE PROHIBITIONS — breaking any of these makes the message unusable:
        1. NEVER invent a statistic, percentage, survey, benchmark or "X% of
           businesses" claim. If you have no checkable figure, make the point
           without a number.
        2. NEVER name a client, invent a case study, or describe a result
           Wwwebtech achieved. You have not been given any client information,
           so anything you wrote would be false.
        3. NEVER promise a ranking, a timeline, a revenue outcome or a price.
        4. NEVER invent a quotation, testimonial, review or award.
        5. No buzzwords: synergy, cutting-edge, unleash, revolutionary,
           leverage, game-changer, seamless, robust, best-in-class.
        6. Do not claim to have looked at their website unless the context
           below says an audit was run.

        Write ONLY the message body. No subject line unless asked, no
        greeting line like "Subject:", no markdown, no signature block beyond
        your first name. The site is {$site}.
        TXT;
    }

    /** The per-step instruction, built from what we actually know. */
    public static function userPrompt(array $l, array $step, string $channel, array $prior): string
    {
        $bits = [];
        $bits[] = 'Their name: ' . (string)$l['name'];
        if ($l['company'] !== '')  $bits[] = 'Company: ' . (string)$l['company'];
        if ($l['service'] !== '')  $bits[] = 'They asked about: ' . (string)$l['service'];
        if ($l['budget'] !== '')   $bits[] = 'Budget they indicated: ' . (string)$l['budget'];
        if ($l['timeline'] !== '') $bits[] = 'Their timeline: ' . (string)$l['timeline'];
        if ($l['site_url'] !== '') $bits[] = 'Their website: ' . (string)$l['site_url'];
        if ($l['landing_page'] !== '') $bits[] = 'They arrived on the ' . (string)$l['landing_page'] . ' page';
        if ($l['utm_term'] !== '') $bits[] = 'They searched for something like: ' . (string)$l['utm_term'];

        $msg = trim((string)$l['message']);
        $context = implode("\n", $bits);
        $theirs  = $msg !== '' ? "\n\nTheir own words, verbatim:\n\"" . $msg . "\"" : '';

        $history = '';
        if ($prior) {
            $history = "\n\nWhat we have already sent them (do not repeat any of it):\n";
            foreach ($prior as $p) {
                $history .= '- ' . cut(trim(strip_tags((string)$p['body'])), 220) . "\n";
            }
        }

        $booking = self::bookingLink();
        $bookLine = $booking !== ''
            ? "\n\nIf the step calls for a booking link, use exactly this URL: " . $booking
            : "\n\nThere is no booking link configured, so ask them to reply with two times that suit them instead.";

        return "Write the " . ($channel === 'whatsapp' ? 'WhatsApp message' : 'email')
             . " for this step.\n\nThe step's purpose: " . (string)$step['purpose']
             . "\n\nWho they are:\n" . $context . $theirs . $history . $bookLine;
    }

    /* ── Composition ───────────────────────────────────────── */

    /**
     * Produce the message for a step.
     *
     * Tries the model twice; two gate failures fall through to the static
     * template, which is written to be good enough to send on its own.
     *
     * @return array{ok:bool, subject:string, body:string, is_ai:bool, error:string}
     */
    public static function compose(array $l, array $step, string $channel): array
    {
        $key = (string)$step['template_key'];
        $useAi = Settings::bool('funnel_ai_enabled', true) && Claude::configured();

        if ($useAi) {
            $prior = DB::all(
                "SELECT body FROM wwt_messages WHERE lead_id = ? AND direction = 'out'
                 AND status = 'sent' ORDER BY id DESC LIMIT 4", [(int)$l['id']]);

            for ($attempt = 1; $attempt <= 2; $attempt++) {
                $r = Claude::message(
                    self::systemPrompt($channel),
                    self::userPrompt($l, $step, $channel, $prior),
                    ['max_tokens' => 1200, 'effort' => 'low']);

                if (!$r['ok']) {
                    wwt_log('funnel', 'ai call failed', ['step' => $key, 'err' => $r['error']]);
                    break;
                }
                $body = self::tidy((string)$r['text']);
                $fails = self::gates($body, $l, $channel, $prior);
                if (!$fails) {
                    return ['ok' => true, 'is_ai' => true, 'error' => '',
                            'subject' => self::subjectFor($key, $l),
                            'body' => $body];
                }
                wwt_log('funnel', 'gates rejected a draft',
                    ['step' => $key, 'attempt' => $attempt, 'why' => implode('; ', $fails)]);
            }
        }

        /* The safety net. Not an apology for the AI being unavailable — it
           is a written message that should stand on its own. */
        $t = self::template($key, $channel);
        if ($t === null) {
            return ['ok' => false, 'is_ai' => false, 'subject' => '', 'body' => '',
                    'error' => 'no template for step "' . $key . '"'];
        }
        return ['ok' => true, 'is_ai' => false, 'error' => '',
                'subject' => self::fill((string)$t['subject'], $l),
                'body'    => self::fill((string)$t['body'], $l)];
    }

    /* ── The gates (§6.6) ──────────────────────────────────── */

    /** @return string[] reasons the draft cannot be sent; empty means fine */
    public static function gates(string $body, array $l, string $channel, array $prior): array
    {
        $fail = [];
        $plain = trim(strip_tags($body));
        $words = $plain === '' ? 0 : count(preg_split('/\s+/u', $plain) ?: []);

        if ($plain === '') return ['the model returned nothing'];

        if ($channel === 'whatsapp') {
            if ($words > self::MAX_WORDS_WA) $fail[] = $words . ' words, WhatsApp limit ' . self::MAX_WORDS_WA;
        } else {
            if ($words < self::MIN_WORDS_EMAIL) $fail[] = $words . ' words, minimum ' . self::MIN_WORDS_EMAIL;
            if ($words > self::MAX_WORDS_EMAIL) $fail[] = $words . ' words, maximum ' . self::MAX_WORDS_EMAIL;
        }

        /* Exactly one call to action. Two is the most common way an
           automated message reads as automated. */
        preg_match_all('#https?://[^\s<>"\')]+#i', $plain, $urls);
        $unique = array_values(array_unique(array_map(
            static fn($u) => rtrim($u, '.,;:'), $urls[0] ?? [])));
        if (count($unique) > 1) $fail[] = 'contains ' . count($unique) . ' links; exactly one is allowed';

        /* Any link must be ours. A model inventing a URL is how a customer
           is sent somewhere we do not control. */
        $host = (string)parse_url((string)cfg('site.url', ''), PHP_URL_HOST);
        $allowed = array_filter([$host, 'cal.com', 'wa.me',
            (string)parse_url(self::bookingLink(), PHP_URL_HOST)]);
        foreach ($unique as $u) {
            $h = strtolower((string)parse_url($u, PHP_URL_HOST));
            $okHost = false;
            foreach ($allowed as $a) {
                if ($a !== '' && ($h === strtolower($a) || str_ends_with($h, '.' . strtolower($a)))) $okHost = true;
            }
            if (!$okHost) $fail[] = 'links somewhere we do not control: ' . cut($u, 60);
        }

        if (preg_match('/\{\{[a-z_]+\}\}/i', $body, $m)) {
            $fail[] = 'unfilled placeholder ' . $m[0];
        }

        /* The same invented-evidence lint the blog engine uses. One list,
           one definition of what we will not say. */
        foreach (Blog::BANNED as $re => $why) {
            if (preg_match($re, $plain, $m)) $fail[] = $why . ' — "' . cut(trim($m[0]), 50) . '"';
        }
        foreach (Blog::BANNED_UNLESS_WARNING as $re => $why) {
            if (!preg_match($re, $plain, $m, PREG_OFFSET_CAPTURE)) continue;
            [$hit, $at] = $m[0];
            if (Blog::readsAsWarning($plain, (int)$at, strlen($hit))) continue;
            $fail[] = $why . ' — "' . cut(trim($hit), 50) . '"';
        }

        foreach (['synergy', 'cutting-edge', 'unleash', 'revolutionary',
                  'best-in-class', 'game-chang', 'seamless', 'leverage'] as $bw) {
            if (stripos($plain, $bw) !== false) $fail[] = 'buzzword: ' . $bw;
        }

        /* Not a rewrite of the last thing we sent them. */
        foreach ($prior as $p) {
            $sim = Blog::similarity($plain, trim(strip_tags((string)$p['body'])));
            if ($sim >= 0.5) {
                $fail[] = sprintf('%.0f%% similar to a message we already sent', $sim * 100);
                break;
            }
        }

        /* Their name must appear. A follow-up that does not use it reads
           as a broadcast, which is exactly what it must not be. */
        $first = trim(explode(' ', trim((string)$l['name']))[0] ?? '');
        if ($first !== '' && mb_stripos($plain, $first) === false) {
            $fail[] = 'does not address them by name';
        }

        return $fail;
    }

    /* ── Templates and tokens (§6.7) ───────────────────────── */

    public static function template(string $key, string $channel): ?array
    {
        $t = DB::one('SELECT * FROM wwt_templates WHERE key_name = ?', [$key]);
        if ($t) return $t;
        return DB::one('SELECT * FROM wwt_templates WHERE key_name = ?',
                       [$channel === 'whatsapp' ? 'fallback_wa' : 'fallback_email']);
    }

    public static function fill(string $s, array $l): string
    {
        $first = trim(explode(' ', trim((string)$l['name']))[0] ?? '') ?: 'there';
        return strtr($s, [
            '{{first_name}}'   => $first,
            '{{full_name}}'    => (string)$l['name'],
            '{{service}}'      => (string)$l['service'] !== '' ? (string)$l['service'] : 'what you asked about',
            '{{company}}'      => (string)$l['company'],
            '{{booking_link}}' => self::bookingLink(),
            '{{sender}}'       => self::senderName(),
            '{{site}}'         => rtrim((string)cfg('site.url', ''), '/'),
            '{{audit_link}}'   => rtrim((string)cfg('site.url', ''), '/') . '/tools/free-website-audit/',
        ]);
    }

    public static function senderName(): string
    {
        return trim((string)Settings::get('funnel_sender_name', 'the Wwwebtech team')) ?: 'the Wwwebtech team';
    }

    public static function bookingLink(): string
    {
        return trim((string)Settings::get('booking_link', ''));
    }

    private static function subjectFor(string $key, array $l): string
    {
        $t = DB::one('SELECT subject FROM wwt_templates WHERE key_name = ?', [$key]);
        $s = $t ? (string)$t['subject'] : 'About your enquiry';
        return self::fill($s !== '' ? $s : 'About your enquiry', $l);
    }

    /** Strip anything the model added around the message itself. */
    public static function tidy(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $s) ?? $s;
        $s = preg_replace('/^(subject|re)\s*:.*$/im', '', $s) ?? $s;
        return trim($s);
    }

    /** A plain, readable HTML wrapper. Deliberately not a designed template —
        a follow-up from a person should look like one. */
    public static function html(string $body, array $l, string $unsub): string
    {
        $paras = array_filter(array_map('trim', preg_split('/\n{2,}/', $body) ?: []));
        $out = '';
        foreach ($paras as $p) {
            $out .= '<p style="margin:0 0 14px;color:#131614;font-size:15.5px;line-height:1.62">'
                  . nl2br(e($p)) . '</p>';
        }
        return '<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;'
             . 'max-width:560px;margin:0 auto;padding:8px 4px">' . $out
             . '<p style="margin:26px 0 0;color:#9AA09B;font-size:11.5px;line-height:1.5">'
             . 'Wwwebtech · East Delhi, Delhi, India<br>'
             . 'You are getting this because you enquired through our website. '
             . '<a href="' . e($unsub) . '" style="color:#9AA09B">Stop these emails</a>.'
             . '</p></div>';
    }
}
