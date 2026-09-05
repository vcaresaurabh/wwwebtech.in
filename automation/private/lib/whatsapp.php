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

    /* ── The six credentials and their friends (Connections card) ─ */

    public static function apiVersion(): string
    {
        $v = (string)Settings::get('wa_api_version', '');
        return preg_match('/^v\d{1,2}\.\d{1,2}$/', $v) ? $v : self::API_VERSION;
    }
    public static function wabaId(): string        { return trim((string)Settings::get('wa_waba_id', '')); }
    public static function appId(): string         { return trim((string)Settings::get('wa_app_id', '')); }
    public static function appSecret(): string     { return Secrets::get('wa_app_secret', ''); }
    public static function displayNumber(): string { return trim((string)Settings::get('wa_display_number', '')); }
    public static function businessStatus(): string
    {
        $v = (string)Settings::get('wa_business_status', 'not_started');
        return in_array($v, ['not_started', 'submitted', 'verified'], true) ? $v : 'not_started';
    }

    /** Ours to generate; shown to the owner to paste into Meta. */
    public static function verifyToken(): string
    {
        $v = (string)Settings::get('wa_verify_token', '');
        if ($v === '') { $v = bin2hex(random_bytes(20)); Settings::set('wa_verify_token', $v); }
        return $v;
    }

    private static function graph(string $path, array $query = [], string $token = ''): array
    {
        $token = $token ?: self::token();
        $url = 'https://graph.facebook.com/' . self::apiVersion() . '/' . ltrim($path, '/')
             . ($query ? '?' . http_build_query($query) : '');
        $r = Http::get($url, ['timeout' => 20, 'headers' => ['authorization: Bearer ' . $token]]);
        $j = json_decode((string)$r['body'], true);
        if ($r['status'] === 200 && is_array($j)) return ['ok' => true, 'json' => $j, 'error' => ''];
        $msg = is_array($j) ? (string)($j['error']['message'] ?? '') : '';
        $code = is_array($j) ? (string)($j['error']['code'] ?? '') : '';
        return ['ok' => false, 'json' => [], 'error' => cut(($code !== '' ? '(#' . $code . ') ' : '') . ($msg ?: ($r['error'] ?: 'HTTP ' . $r['status'])), 240)];
    }

    /** Proves the token and the Phone number ID together. */
    public static function checkPhone(string $token = ''): array
    {
        if (self::phoneId() === '') return ['ok' => false, 'error' => 'No Phone number ID yet.'];
        $r = self::graph(self::phoneId(), ['fields' => 'display_phone_number,verified_name,quality_rating'], $token);
        if (!$r['ok']) return $r;
        $display = (string)($r['json']['display_phone_number'] ?? '');
        if ($display !== '' && self::displayNumber() === '') Settings::set('wa_display_number', $display);
        return ['ok' => true, 'error' => '', 'display' => $display,
                'verified_name' => (string)($r['json']['verified_name'] ?? ''),
                'quality' => (string)($r['json']['quality_rating'] ?? '')];
    }

    /** Every template Meta knows about, with status and category. */
    public static function listTemplates(string $token = ''): array
    {
        if (self::wabaId() === '') return ['ok' => false, 'templates' => [], 'error' => 'No WhatsApp Business Account ID yet.'];
        $r = self::graph(self::wabaId() . '/message_templates', ['fields' => 'name,status,category,language', 'limit' => 100], $token);
        if (!$r['ok']) return ['ok' => false, 'templates' => [], 'error' => $r['error']];
        $out = [];
        foreach ((array)($r['json']['data'] ?? []) as $t) {
            $out[] = ['name' => (string)($t['name'] ?? ''), 'status' => strtoupper((string)($t['status'] ?? '')),
                      'category' => strtolower((string)($t['category'] ?? '')), 'language' => (string)($t['language'] ?? 'en')];
        }
        return ['ok' => true, 'templates' => $out, 'error' => ''];
    }

    /**
     * Pull Meta's template list into the registry, so the owner never has
     * to read statuses off Meta's UI and the sender can never use a
     * template Meta has not approved.
     */
    public static function syncTemplates(): array
    {
        $r = self::listTemplates();
        if (!$r['ok']) return ['ok' => false, 'count' => 0, 'error' => $r['error']];
        $n = 0;
        foreach ($r['templates'] as $t) {
            if ($t['name'] === '') continue;
            $approval = match ($t['status']) { 'APPROVED' => 'approved', 'PENDING', 'IN_APPEAL' => 'pending', 'REJECTED', 'PAUSED', 'DISABLED' => 'rejected', default => 'none' };
            $category = in_array($t['category'], ['utility', 'marketing', 'authentication'], true) ? $t['category'] : 'marketing';
            $row = DB::one('SELECT id FROM wwt_templates WHERE channel = ? AND (meta_name = ? OR key_name = ?) LIMIT 1', ['whatsapp', $t['name'], $t['name']]);
            if ($row) {
                DB::run('UPDATE wwt_templates SET meta_name = ?, category = ?, approval = ?, language = ?, meta_status = ?, synced_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?',
                        [$t['name'], $category, $approval, cut($t['language'], 10), cut($t['status'], 30), $row['id']]);
            } else {
                DB::run("INSERT INTO wwt_templates (key_name, channel, category, subject, body, is_ai, meta_name, approval, language, meta_status, synced_at, updated_at)
                         VALUES (?, 'whatsapp', ?, '', '', 0, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                        [cut($t['name'], 60), $category, $t['name'], $approval, cut($t['language'], 10), cut($t['status'], 30)]);
            }
            $n++;
        }
        Settings::set('wa_templates_synced_at', gmdate('Y-m-d H:i:s'));
        return ['ok' => true, 'count' => $n, 'error' => ''];
    }

    /** The registry as the card shows it. */
    public static function templates(): array
    {
        return DB::all("SELECT key_name, meta_name, category, approval, language, meta_status, synced_at
                        FROM wwt_templates WHERE channel = 'whatsapp' ORDER BY approval = 'approved' DESC, meta_name");
    }

    /**
     * Send the first approved utility template (or Meta's sample
     * hello_world) to a number the owner typed — the card's optional
     * third sub-check.
     */
    public static function sendTestTemplate(string $to): array
    {
        $to = self::e164($to);
        if ($to === '') return ['ok' => false, 'error' => 'That does not look like a phone number.'];
        $t = DB::one("SELECT meta_name, language FROM wwt_templates WHERE channel = 'whatsapp' AND approval = 'approved'
                      AND category = 'utility' AND synced_at IS NOT NULL ORDER BY meta_name = 'hello_world' DESC, meta_name LIMIT 1");
        if (!$t) return ['ok' => false, 'error' => 'No approved utility template has been synced yet — press Sync from Meta first.'];
        $payload = ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
                    'template' => ['name' => (string)$t['meta_name'], 'language' => ['code' => (string)($t['language'] ?: 'en_US')]]];
        if ((string)$t['meta_name'] !== 'hello_world') {
            $payload['template']['components'] = [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'there']]]];
        }
        $r = self::post('https://graph.facebook.com/' . self::apiVersion() . '/' . self::phoneId() . '/messages', $payload);
        if (!$r['ok']) return $r;
        Connections::touch('whatsapp');
        return ['ok' => true, 'error' => '', 'provider_id' => (string)($r['json']['messages'][0]['id'] ?? ''),
                'cost_paise' => self::UTILITY_PAISE];
    }

    /* ── Inbound (the webhook) ─────────────────────────────── */

    /** Meta signs every POST with the App Secret. No secret, no webhook. */
    public static function verifySignature(string $rawBody, string $header): bool
    {
        $secret = self::appSecret();
        if ($secret === '' || $header === '' || !str_starts_with($header, 'sha256=')) return false;
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $header);
    }

    /**
     * A customer's message lands on their thread and stops their sequence,
     * exactly as an email reply does. Receipts update delivery status.
     *
     * @return array{messages:int, statuses:int, stopped:int, unknown:int}
     */
    public static function handleInbound(array $payload): array
    {
        $out = ['messages' => 0, 'statuses' => 0, 'stopped' => 0, 'unknown' => 0];
        foreach ((array)($payload['entry'] ?? []) as $entry) {
            foreach ((array)($entry['changes'] ?? []) as $change) {
                $v = (array)($change['value'] ?? []);
                foreach ((array)($v['messages'] ?? []) as $m) {
                    $from = preg_replace('/\D/', '', (string)($m['from'] ?? '')) ?? '';
                    $mid  = (string)($m['id'] ?? '');
                    if ($from === '' || $mid === '') continue;
                    if (DB::val('SELECT 1 FROM wwt_messages WHERE provider_id = ? LIMIT 1', [$mid]) !== null) continue;
                    $body = match ((string)($m['type'] ?? '')) {
                        'text'     => (string)($m['text']['body'] ?? ''),
                        'button'   => (string)($m['button']['text'] ?? ''),
                        'interactive' => (string)($m['interactive']['button_reply']['title'] ?? $m['interactive']['list_reply']['title'] ?? ''),
                        default    => '[' . (string)($m['type'] ?? 'message') . ']',
                    };
                    $leadId = self::leadByPhone($from);
                    if ($leadId === 0) { $out['unknown']++; wwt_log('whatsapp', 'inbound from unknown number', ['digits' => substr($from, -4)]); continue; }
                    DB::insert("INSERT INTO wwt_messages (lead_id, ts, direction, channel, subject, body, provider_id, status)
                                VALUES (?, UTC_TIMESTAMP(), 'in', 'whatsapp', '', ?, ?, 'received')",
                               [$leadId, cut($body, 20000), cut($mid, 190)]);
                    Timeline::add($leadId, 'reply_received', 'whatsapp', cut($body, 200), 'them');
                    Score::apply($leadId);
                    if (preg_match('/^\s*(stop|unsubscribe|remove me|opt out)\b/i', $body)) {
                        Funnel::optOut($leadId, 'replied "stop" on WhatsApp'); $out['stopped']++;
                    } elseif (Funnel::checkStop($leadId) !== '') {
                        $out['stopped']++;
                    }
                    $out['messages']++;
                    Connections::touch('whatsapp');
                }
                foreach ((array)($v['statuses'] ?? []) as $st) {
                    $mid = (string)($st['id'] ?? ''); $s = (string)($st['status'] ?? '');
                    if ($mid === '') continue;
                    if ($s === 'failed') {
                        $why = (string)($st['errors'][0]['title'] ?? $st['errors'][0]['message'] ?? 'delivery failed');
                        DB::run("UPDATE wwt_messages SET status = 'failed', error = ? WHERE provider_id = ? AND direction = 'out'", [cut($why, 240), $mid]);
                    }
                    $out['statuses']++;
                }
            }
        }
        return $out;
    }

    /** The lead whose phone ends in these digits — newest first. */
    private static function leadByPhone(string $digits): int
    {
        $last = substr($digits, -10);
        if (strlen($last) < 8) return 0;
        $id = DB::val("SELECT id FROM wwt_leads
                       WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') LIKE ?
                       ORDER BY id DESC LIMIT 1", ['%' . $last]);
        return (int)($id ?? 0);
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

        $url = 'https://graph.facebook.com/' . self::apiVersion() . '/' . self::phoneId() . '/messages';
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

        $r = self::post('https://graph.facebook.com/' . self::apiVersion() . '/' . self::phoneId() . '/messages', [
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
