<?php
/* ============================================================
   whatsapp.php — Meta Cloud API, direct, no BSP.

   Going direct matters: AiSensy, Wati and Interakt charge ₹1,000–2,500 a
   month in platform fees on top of Meta's per-message price. Direct, the
   only cost is Meta's, which at this volume is a few rupees a month.

   The real risk is not cost, it is CATEGORY DRIFT. A marketing-category
   template costs several times a utility one in India and can be rejected
   outright. So the automated path refuses, in code, to send anything whose
   stored category is not `utility` — a "here's why you should choose us"
   template cannot be sent by the sequence engine even if someone creates
   one and points a step at it.

   Everything here degrades to nothing when credentials are absent. The
   funnel falls back to email and the site works exactly as before, which
   is the whole point of the Phase A / Phase B split.
   ============================================================ */

declare(strict_types=1);

final class WhatsApp
{
    public const API_VERSION = 'v21.0';

    /** Indian utility rate, in paise, used for the running cost meter. */
    public const UTILITY_PAISE = 12;

    public static function token(): string
    {
        return Secrets::get('wa_token', (string)cfg('whatsapp.token', ''));
    }

    public static function phoneId(): string
    {
        return trim((string)Settings::get('wa_phone_id', (string)cfg('whatsapp.phone_id', '')));
    }

    public static function configured(): bool
    {
        return self::token() !== '' && self::phoneId() !== '';
    }

    public static function enabled(): bool
    {
        return self::configured() && Settings::bool('wa_enabled', false);
    }

    /* ── The monthly cap (§0.6) ────────────────────────────── */

    public static function capPaise(): int
    {
        return max(0, (int)round(((float)(Settings::get('wa_monthly_cap_inr', '200') ?: 200)) * 100));
    }

    public static function spentPaiseThisMonth(): int
    {
        return (int)DB::val(
            "SELECT COALESCE(SUM(cost_paise), 0) FROM wwt_messages
             WHERE channel = 'whatsapp' AND direction = 'out' AND status = 'sent'
               AND ts >= DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-01')", [], 0);
    }

    public static function capReached(): bool
    {
        $cap = self::capPaise();
        return $cap > 0 && self::spentPaiseThisMonth() >= $cap;
    }

    /**
     * Can the sequence engine use WhatsApp right now?
     * False for any reason at all means the step silently becomes email.
     */
    public static function sendable(): bool
    {
        return self::enabled() && !self::capReached();
    }

    /** Why not, in words, for the panel. */
    public static function whyNot(): string
    {
        if (!self::configured()) return 'No Cloud API credentials yet — steps fall back to email.';
        if (!Settings::bool('wa_enabled', false)) return 'Switched off in Settings.';
        if (self::capReached()) {
            return sprintf('The monthly cap of ₹%s has been reached — WhatsApp steps fall back to email until next month.',
                number_format(self::capPaise() / 100, 0));
        }
        return '';
    }

    /* ── Sending ───────────────────────────────────────────── */

    /**
     * Send an approved template.
     *
     * Refuses anything not categorised `utility`, by design. This is the
     * guard that stops a persuasion message being sent as a template,
     * costing 7x and risking rejection of the whole WABA.
     */
    public static function sendTemplate(array $l, string $templateKey, string $fallbackBody = ''): array
    {
        if (!self::sendable()) {
            return ['ok' => false, 'error' => self::whyNot() ?: 'WhatsApp is not available'];
        }

        $t = DB::one('SELECT * FROM wwt_templates WHERE key_name = ? AND channel = ?',
                     [$templateKey, 'whatsapp']);
        if (!$t) return ['ok' => false, 'error' => 'no WhatsApp template named "' . $templateKey . '"'];

        if ((string)$t['category'] !== 'utility') {
            /* Deliberately a hard refusal, not a warning. */
            wwt_log('whatsapp', 'refused a non-utility template', ['key' => $templateKey,
                'category' => $t['category']]);
            return ['ok' => false, 'error' =>
                'refused: template "' . $templateKey . '" is categorised "' . $t['category']
                . '". Only utility templates may be sent automatically.'];
        }
        if ((string)$t['approval'] !== 'approved' || (string)$t['meta_name'] === '') {
            return ['ok' => false, 'error' => 'template "' . $templateKey . '" is not approved by Meta yet'];
        }

        $to = self::e164((string)$l['phone']);
        if ($to === '') return ['ok' => false, 'error' => 'no usable phone number'];

        $first = trim(explode(' ', trim((string)$l['name']))[0] ?? '') ?: 'there';
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'template',
            'template' => [
                'name'     => (string)$t['meta_name'],
                'language' => ['code' => (string)Settings::get('wa_lang', 'en')],
                'components' => [[
                    'type' => 'body',
                    'parameters' => [['type' => 'text', 'text' => $first]],
                ]],
            ],
        ];

        $url = 'https://graph.facebook.com/' . self::API_VERSION . '/' . self::phoneId() . '/messages';
        $r = self::post($url, $payload);
        if (!$r['ok']) return $r;

        return ['ok' => true, 'error' => '',
                'provider_id' => (string)($r['json']['messages'][0]['id'] ?? ''),
                'cost_paise'  => self::UTILITY_PAISE];
    }

    /**
     * A free-form reply, only valid inside the 24-hour service window.
     * Used by the Conversations inbox, never by the sequence engine.
     */
    public static function sendSession(array $l, string $text): array
    {
        if (!self::sendable()) return ['ok' => false, 'error' => self::whyNot()];
        if (!self::windowOpen((int)$l['id'])) {
            return ['ok' => false, 'error' =>
                'The 24-hour window has closed — an approved template is required to reopen it.'];
        }
        $to = self::e164((string)$l['phone']);
        if ($to === '') return ['ok' => false, 'error' => 'no usable phone number'];

        $r = self::post('https://graph.facebook.com/' . self::API_VERSION . '/' . self::phoneId() . '/messages', [
            'messaging_product' => 'whatsapp',
            'to' => $to, 'type' => 'text',
            'text' => ['body' => cut($text, 4000), 'preview_url' => false],
        ]);
        if (!$r['ok']) return $r;
        return ['ok' => true, 'error' => '',
                'provider_id' => (string)($r['json']['messages'][0]['id'] ?? ''),
                'cost_paise'  => self::windowFree() ? 0 : self::UTILITY_PAISE];
    }

    /**
     * Is a customer-service reply currently free?
     *
     * Meta's stated position: service messages inside the 24-hour window are
     * free until 30 September 2026 and charged at the utility rate after.
     * The date lives in settings so the owner can correct it without a code
     * change when Meta moves it, which they have done before.
     */
    public static function windowFree(): bool
    {
        $until = (string)Settings::get('wa_free_window_until', '2026-09-30');
        return strtotime($until . ' 23:59:59 UTC') > time();
    }

    /** Has the customer messaged us in the last 24 hours? */
    public static function windowOpen(int $leadId): bool
    {
        return DB::val(
            "SELECT 1 FROM wwt_messages WHERE lead_id = ? AND direction = 'in' AND channel = 'whatsapp'
             AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR) LIMIT 1", [$leadId]) !== null;
    }

    /** What the panel must show BEFORE anyone presses send. */
    public static function quoteFor(int $leadId): array
    {
        if (!self::configured()) {
            return ['mode' => 'unavailable', 'cost_paise' => 0,
                    'note' => 'WhatsApp is not connected. Use email, or send them a wa.me link.'];
        }
        if (self::windowOpen($leadId)) {
            $free = self::windowFree();
            return ['mode' => 'session', 'cost_paise' => $free ? 0 : self::UTILITY_PAISE,
                    'note' => $free
                        ? 'Free — they messaged within the last 24 hours, and service replies are free until '
                          . (string)Settings::get('wa_free_window_until', '2026-09-30') . '.'
                        : 'Approximately ₹' . number_format(self::UTILITY_PAISE / 100, 2)
                          . ' — inside the 24-hour window, charged at the utility rate.'];
        }
        return ['mode' => 'template', 'cost_paise' => self::UTILITY_PAISE,
                'note' => 'The 24-hour window has closed, so this must be an approved utility template. '
                        . 'Approximately ₹' . number_format(self::UTILITY_PAISE / 100, 2) . '.'];
    }

    /**
     * India: 10 digits gets a 91; anything else is passed through as digits.
     * Digits only, with no leading '+': that is the form the WhatsApp Cloud
     * API expects in a `to` field. Ads::hashPhone adds the '+' back, because
     * Google's matching wants full E.164.
     */
    public static function e164(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone) ?? '';
        if ($d === '') return '';
        if (strlen($d) === 10) $d = '91' . $d;
        if (str_starts_with($d, '0') && strlen($d) === 11) $d = '91' . substr($d, 1);
        return strlen($d) >= 11 && strlen($d) <= 15 ? $d : '';
    }

    private static function post(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'content-type: application/json',
                'authorization: Bearer ' . self::token(),
            ],
        ]);
        $body = (string)curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($body, true);
        if ($code === 200 && is_array($json)) return ['ok' => true, 'json' => $json, 'error' => ''];

        $msg = is_array($json) ? (string)($json['error']['message'] ?? '') : '';
        wwt_log('whatsapp', 'send failed', ['status' => $code, 'err' => $msg ?: $err]);
        return ['ok' => false, 'error' => cut($msg ?: ($err ?: 'HTTP ' . $code), 240)];
    }
}
