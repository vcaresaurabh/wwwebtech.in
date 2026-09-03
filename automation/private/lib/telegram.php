<?php
/* ============================================================
   telegram.php — instant internal alerts.

   Telegram, not WhatsApp, and the reason is worth stating because it
   looks backwards in a market where everyone is on WhatsApp:

   This channel is for the OWNER, not the customer. A business cannot
   message a WhatsApp number out of the blue without an approved
   template, and from October 2026 every one of those is billed. Paying
   Meta per message to tell yourself a lead arrived — and waiting weeks
   for business verification before you can — is the wrong trade for
   something one person reads. Telegram is free, instant, unlimited and
   takes ten minutes to set up.

   Customers still get WhatsApp. That is a different file.
   ============================================================ */

declare(strict_types=1);

final class Telegram
{
    public const API = 'https://api.telegram.org/bot';

    public static function token(): string
    {
        return Secrets::get('telegram_token', (string)cfg('telegram.token', ''));
    }

    public static function chatId(): string
    {
        return trim((string)Settings::get('telegram_chat_id', (string)cfg('telegram.chat_id', '')));
    }

    public static function configured(): bool
    {
        return self::token() !== '' && self::chatId() !== '';
    }

    /**
     * Send a message. Never throws: an alert that fails must not take the
     * enquiry down with it, and the enquiry is already saved by this point.
     *
     * @return array{ok:bool, error:string}
     */
    public static function send(string $text, array $opt = []): array
    {
        if (!self::configured()) {
            return ['ok' => false, 'error' => 'Telegram is not set up (Settings → Alerts).'];
        }
        $chat = (string)($opt['chat_id'] ?? self::chatId());

        $payload = [
            'chat_id'    => $chat,
            'text'       => cut($text, 4000),
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];
        if (!empty($opt['buttons'])) {
            $payload['reply_markup'] = ['inline_keyboard' => [array_map(
                static fn(array $b) => ['text' => $b[0], 'url' => $b[1]],
                (array)$opt['buttons'])]];
        }

        try {
            $r = Http::postJson(self::API . self::token() . '/sendMessage', $payload, ['timeout' => 10]);
            $j = json_decode($r['body'], true);
            if ($r['status'] === 200 && !empty($j['ok'])) return ['ok' => true, 'error' => ''];

            /* Telegram's own error text is far more useful than the status
               code — "chat not found" means the owner never messaged the
               bot, which is the single most common setup mistake. */
            $why = (string)($j['description'] ?? $r['error'] ?: 'HTTP ' . $r['status']);
            if (stripos($why, 'chat not found') !== false) {
                $why .= ' — open the bot in Telegram and send it any message first; '
                      . 'a bot cannot start a conversation.';
            }
            wwt_log('telegram', 'send failed', ['err' => $why]);
            return ['ok' => false, 'error' => cut($why, 240)];
        } catch (Throwable $t) {
            wwt_log('telegram', 'send crashed', ['err' => $t->getMessage()]);
            return ['ok' => false, 'error' => cut($t->getMessage(), 240)];
        }
    }

    /** HTML-escape for Telegram's limited HTML parse mode. */
    public static function esc(string $s): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
    }

    /**
     * The new-lead alert. Written to be actionable from a phone in the
     * three seconds someone glances at a notification: who, how hot, what
     * they want, and one tap to reply.
     */
    public static function newLead(array $l): array
    {
        $e = static fn($v) => self::esc((string)$v);
        $band  = (string)($l['band'] ?? 'cold');
        $site  = rtrim((string)cfg('site.url', ''), '/');
        $phone = preg_replace('/\D/', '', (string)($l['phone'] ?? '')) ?? '';
        if (strlen($phone) === 10) $phone = '91' . $phone;

        $lines = [
            Score::emoji($band) . ' <b>' . strtoupper(Score::label($band)) . ' lead — '
                . $e($l['name']) . '</b>',
            '',
        ];
        foreach (['service' => 'Wants', 'budget' => 'Budget', 'timeline' => 'When'] as $k => $label) {
            $v = trim((string)($l[$k] ?? ''));
            if ($v !== '') $lines[] = '<b>' . $label . ':</b> ' . $e(self::humanise($k, $v));
        }
        $lines[] = '<b>Phone:</b> ' . $e($l['phone'] ?? '—');
        $lines[] = '';
        $lines[] = $e(cut((string)($l['message'] ?? ''), 400));
        $lines[] = '';

        $src = (string)($l['landing_page'] ?? '') !== ''
            ? '/lp/' . $l['landing_page'] . '/'
            : ((string)($l['page'] ?? '') ?: 'the site');
        $camp = trim((string)($l['utm_campaign'] ?? '') . ' ' . (string)($l['utm_term'] ?? ''));
        $lines[] = '<i>Converted on ' . $e($src)
                 . ($camp !== '' ? ' · ' . $e($camp) : '')
                 . ' · score ' . (int)($l['score'] ?? 0) . '</i>';

        $buttons = [];
        if ($phone !== '') $buttons[] = ['WhatsApp them', 'https://wa.me/' . $phone];
        $buttons[] = ['Open in panel', $site . '/admin/?p=lead&id=' . (int)($l['id'] ?? 0)];

        return self::send(implode("\n", $lines), ['buttons' => $buttons]);
    }

    /** The +15 minute nudge when a hot lead has not been opened (§6.3 step 1). */
    public static function escalate(array $l, int $minutes): array
    {
        $site = rtrim((string)cfg('site.url', ''), '/');
        return self::send(
            '⏰ <b>Still unopened after ' . $minutes . ' minutes</b>' . "\n\n"
            . self::esc((string)$l['name']) . ' — ' . strtoupper(Score::label((string)$l['band']))
            . ', score ' . (int)$l['score'] . "\n"
            . self::esc(cut((string)$l['message'], 200)),
            ['buttons' => [['Open it now', $site . '/admin/?p=lead&id=' . (int)$l['id']]]]
        );
    }

    /** Used by the Settings page to prove the setup works. */
    public static function test(): array
    {
        return self::send(
            "✅ <b>Wwwebtech alerts are working</b>\n\n"
            . "This is a test from the admin panel. New enquiries will arrive here "
            . "within seconds of someone submitting the form."
        );
    }

    private static function humanise(string $field, string $v): string
    {
        if ($field !== 'timeline') return $v;
        return ['asap' => 'As soon as possible', '1-3m' => 'In 1–3 months',
                'research' => 'Just researching'][$v] ?? $v;
    }
}
