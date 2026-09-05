<?php
/* ============================================================
   connections.php — one place for every credential the site needs.

   The panel had credentials in five places: a Settings page with four
   sections, and a key field each on the Blog, SEO and Integrations pages.
   Each saved into the same encrypted store, so none of it was wrong — it
   was just impossible for the owner to answer "what is connected and what
   is not" without opening five pages. This class answers that question.

   What it does NOT do: hold secrets. Storage stays in Settings and
   Secrets, and the transports keep reading the keys they always read.
   This file knows which keys make up a connection, how to test one for
   real, how to describe a failure to a person, and how to remember the
   result.

   The rules it enforces, from the brief:
     · secrets are write-only — the page sees a hint, never a value
     · a Test is a real call, never "saved successfully"
     · a failed test never overwrites a working credential — new values
       are held as pending until the test passes or the owner says
       "save anyway"
     · one test per connection per 30 seconds
     · everything is audited by who and when, never by value
   ============================================================ */

declare(strict_types=1);

final class Connections
{
    /** Card order on the page. */
    public const CARDS = ['mail_send', 'mail_read', 'recipients', 'telegram', 'whatsapp', 'claude', 'pagespeed', 'keys'];

    public const STATES = ['unconfigured', 'untested', 'connected', 'error'];

    /** One test per connection per this many seconds. */
    public const TEST_EVERY = 30;

    /* ── Presentation data ─────────────────────────────────── */

    public static function title(string $card): string
    {
        return [
            'mail_send'  => 'Email — sending',
            'mail_read'  => 'Email — reading replies',
            'recipients' => 'Alert recipients',
            'telegram'   => 'Telegram',
            'whatsapp'   => 'WhatsApp',
            'claude'     => 'Claude (Anthropic)',
            'pagespeed'  => 'PageSpeed Insights',
            'keys'       => 'Feed & test keys',
        ][$card] ?? $card;
    }

    /** What the connection is used for, in the owner's words. */
    public static function blurb(string $card): string
    {
        return [
            'mail_send'  => 'Every email the site sends: the copy of each enquiry to you, the acknowledgement to the '
                          . 'person who wrote, and the follow-up messages signed with your name. Deliverability lives '
                          . 'or dies on the three DNS records at the top of this card.',
            'mail_read'  => 'The panel reads this mailbox every five minutes looking for replies to follow-up messages. '
                          . 'A reply is what stops a sequence — so this is the part that keeps the automation polite.',
            'recipients' => 'Who is told when something happens. Each address chooses what it receives. '
                          . 'Add your phone\'s email and a colleague here without touching anything else.',
            'telegram'   => 'Instant alerts to you when a lead arrives, escalation pings for hot leads, and '
                          . '"a message is waiting for your approval" notices. Internal only — customers never see '
                          . 'it. Free, unlimited.',
            'whatsapp'   => 'Short utility messages to customers — the instant acknowledgement and appointment '
                          . 'nudges. Replies inside the 24-hour window are free until 30 September 2026; from '
                          . '1 October roughly ₹0.115 a message in India. The monthly cap below pauses sending '
                          . 'when reached, and marketing-category templates are refused by the system.',
            'claude'     => 'Writes blog drafts and follow-up messages. Every draft passes quality gates before '
                          . 'a person sees it. The spend cap below is checked before every call.',
            'pagespeed'  => 'Google\'s own speed measurement, used by the daily SEO check and the free audit tool. '
                          . 'The key is free.',
            'keys'       => 'Keys this site generates for itself: the one Google and Microsoft use to fetch '
                          . 'offline conversions, and the one that marks a form submission as a test. Rotate either '
                          . 'at any time.',
        ][$card] ?? '';
    }

    /**
     * The numbered walkthrough inside each card. Written for someone who
     * has never opened the external site. Each step is HTML (links).
     */
    public static function guide(string $card): array
    {
        $e = static fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $site = $e(rtrim((string)cfg('site.url', 'https://wwwebtech.in'), '/'));
        return match ($card) {
            'mail_send' => [
                'Pick your email provider from the list — the server, port and encryption fill in. If the mailbox is on Hostinger (most likely), choose Hostinger.',
                'The mailbox address is the full address, e.g. <code>info@wwwebtech.in</code>. The password is that mailbox\'s password, from <a href="https://hpanel.hostinger.com/emails" rel="noopener">hPanel → Emails</a> → the mailbox → Manage. If you do not know it, set a new one there and paste it here.',
                'Gmail or Google Workspace: a normal password will not work. Create an <strong>App Password</strong> at <a href="https://myaccount.google.com/apppasswords" rel="noopener">myaccount.google.com/apppasswords</a> (two-step verification has to be on) and paste that.',
                'Press <strong>Send me a test</strong>. A real email goes to the address you are signed in with. The result tells you whether the server accepted the login and — where the reading mailbox is set up — whether the message passed SPF and DKIM.',
                'Look at the DNS strip at the top. For every ✗ it shows the exact record to add in <a href="https://hpanel.hostinger.com/domains" rel="noopener">hPanel → Domains → DNS</a>. Most inboxes decide "spam or not" on those three records.',
            ],
            'mail_read' => [
                'Use the same mailbox the follow-up messages are sent from. Replies land there.',
                'Pick the provider — host, port and encryption fill in (Hostinger: <code>imap.hostinger.com</code>, 993, SSL).',
                'Paste the mailbox password. Same password as for sending.',
                'Press <strong>Check the mailbox</strong>. It signs in, counts unread mail and shows the three most recent subjects, so you can see it is the right mailbox.',
                'That is all. The five-minute job reads new mail from here and attaches replies to the right lead.',
            ],
            'recipients' => [
                'Add any address that should hear about leads: yours, your phone\'s email, a colleague\'s.',
                'Choose what each one receives. <em>Every lead</em> is the full brief for each enquiry. <em>Hot leads only</em> is just the ones worth interrupting someone for. <em>Daily digest</em> is one email each morning. <em>System errors</em> is only when something here breaks.',
                'Press <strong>Send test</strong> next to an address to prove it arrives.',
            ],
            'telegram' => [
                'In Telegram, open <a href="https://t.me/BotFather" rel="noopener"><strong>@BotFather</strong></a>. Send <code>/newbot</code>. Give it a name (e.g. <em>Wwwebtech Alerts</em>) and a username ending in <code>bot</code> (e.g. <em>wwwebtech_alerts_bot</em>). Copy the token it replies with. <span class="muted">Looks like: <code>123456789:AAH…</code></span>',
                'Paste the token below and press <strong>Save</strong>.',
                '<strong>Open your new bot in Telegram and press Start</strong>, or send it any message. Until you do, Telegram will not let the bot message you — that is the "chat not found" error.',
                'Press <strong>Detect my chat</strong>. The panel lists everyone who has messaged the bot in the last day. Click yourself.',
                'For a team group instead: create the group, add the bot to it, send one message in the group, then press Detect. Group IDs are negative numbers — that is normal.',
                'Press <strong>Send a test</strong>. A message should arrive on your phone within a second.',
            ],
            'whatsapp' => [
                '<strong>Business verification.</strong> In <a href="https://business.facebook.com/settings/security" rel="noopener">Meta Business Settings → Security Centre</a> press <em>Start verification</em>. This needs a registered business document and takes days to weeks. Nothing below can send until it clears. Record where you are in the field below so the switch stays off until then.',
                '<strong>A dedicated number.</strong> The number must not be registered on the WhatsApp app. If your business number is on the app today, it has to be removed there first — and it cannot go back to the app afterwards. Most businesses buy a new SIM for this.',
                '<strong>Create the app.</strong> At <a href="https://developers.facebook.com/apps" rel="noopener">developers.facebook.com → My Apps → Create App</a> choose <em>Business</em>, then add the <em>WhatsApp</em> product. On <em>Settings → Basic</em> copy the <strong>App ID</strong> (15–16 digits) and the <strong>App Secret</strong> (32 letters and digits — press Show).',
                '<strong>System user token.</strong> In <a href="https://business.facebook.com/settings/system-users" rel="noopener">Business Settings → Users → System users</a> press <em>Add</em>, role <em>Admin</em>. Then <em>Assign assets</em> → your app (full control) and your WhatsApp account. Then <em>Generate new token</em>, pick your app, tick <code>whatsapp_business_messaging</code> and <code>whatsapp_business_management</code>, expiry <em>Never</em>. Copy it. <span class="muted">Looks like: <code>EAAG…</code>, about 200 characters.</span> The token the Graph API Explorer shows expires in 24 hours — do not paste that one.',
                '<strong>The two IDs.</strong> <a href="https://business.facebook.com/settings/whatsapp-business-accounts" rel="noopener">Business Settings → Accounts → WhatsApp accounts</a> shows the <strong>WhatsApp Business Account ID</strong>. In <a href="https://business.facebook.com/wa/manage/phone-numbers" rel="noopener">WhatsApp Manager → Phone numbers</a> the <strong>Phone number ID</strong> is shown under the number — it is not the phone number itself.',
                '<strong>Webhook.</strong> Copy the Callback URL and Verify token shown below into <em>your app → WhatsApp → Configuration → Webhooks → Edit</em>, press <em>Verify and save</em>, then under <em>Webhook fields</em> subscribe to <code>messages</code>. This card flips to "Webhook verified" the moment Meta calls it.',
                '<strong>Templates.</strong> Press <strong>Sync from Meta</strong>. Every template Meta knows about appears below with its approval status and category. Only <em>approved</em> templates in the <em>utility</em> category can be sent automatically.',
            ],
            'claude' => [
                'Go to <a href="https://console.anthropic.com/settings/keys" rel="noopener">console.anthropic.com → API keys</a> and create a key named <em>wwwebtech-panel</em>. Copy it. <span class="muted">Looks like: <code>sk-ant-api03-…</code></span>',
                'While you are there, set a monthly spend limit under <em>Billing → Limits</em>. This card has a cap too; two caps are better than one.',
                'Paste the key, press <strong>Save</strong>, then <strong>Test</strong>. The test costs a fraction of a cent and reports the model that answered.',
                'Model names change; the current list is at <a href="https://docs.claude.com/en/docs/about-claude/models" rel="noopener">docs.claude.com</a>.',
            ],
            'pagespeed' => [
                'Go to <a href="https://console.cloud.google.com/apis/credentials" rel="noopener">Google Cloud → APIs & Services → Credentials</a>. If you have no project yet, create one (any name).',
                'Press <em>Create credentials → API key</em>. Copy it. <span class="muted">Looks like: <code>AIza…</code>, 39 characters.</span>',
                'Press <em>Edit API key</em> → under <em>API restrictions</em> choose <em>Restrict key</em> and tick only <em>PageSpeed Insights API</em>. Save. This key is free and has no billing.',
                'Paste it below, <strong>Save</strong>, then <strong>Test</strong>. The test measures this site\'s homepage and shows the score.',
            ],
            'keys' => [
                'The <strong>conversions feed key</strong> is part of the URLs Google Ads and Microsoft Advertising fetch on a schedule. Rotating it invalidates the old URLs at once — the new ones are shown on the Integrations page; paste them into both platforms.',
                'The <strong>test-submission key</strong> marks a form submission as a test so it is stored but never notified or followed up. The QA scripts use it. Rotate it if it was ever shared.',
                'The cron lines below are for reference only — they do not contain a key and never need changing. They are the four scheduled jobs in ' . '<a href="https://hpanel.hostinger.com" rel="noopener">hPanel → Advanced → Cron Jobs</a>.',
            ],
            default => [],
        };
    }

    /** Provider presets for the two email cards. */
    public const MAIL_PRESETS = [
        'hostinger' => ['label' => 'Hostinger', 'smtp' => ['smtp.hostinger.com', 465, 'ssl'], 'imap' => ['imap.hostinger.com', 993, 'ssl'],
                        'dkim' => ['hostingermail-a', 'hostingermail-b']],
        'google'    => ['label' => 'Google Workspace / Gmail', 'smtp' => ['smtp.gmail.com', 587, 'tls'], 'imap' => ['imap.gmail.com', 993, 'ssl'],
                        'dkim' => ['google'], 'note' => 'Needs an App Password, not your normal password.'],
        'zoho'      => ['label' => 'Zoho Mail', 'smtp' => ['smtp.zoho.in', 465, 'ssl'], 'imap' => ['imap.zoho.in', 993, 'ssl'],
                        'dkim' => ['zmail', 'zoho']],
        'microsoft' => ['label' => 'Microsoft 365', 'smtp' => ['smtp.office365.com', 587, 'tls'], 'imap' => ['outlook.office365.com', 993, 'ssl'],
                        'dkim' => ['selector1', 'selector2']],
        'other'     => ['label' => 'Other (type the details)', 'smtp' => ['', 465, 'ssl'], 'imap' => ['', 993, 'ssl'], 'dkim' => ['default', 'mail']],
    ];

    /** Paste-time validation. The message is what the owner sees. */
    public const FORMATS = [
        'telegram_token'  => ['/^\d{8,12}:[A-Za-z0-9_-]{35}$/', 'A bot token is 8–12 digits, a colon, then 35 letters, digits, _ or -. Copy the whole line BotFather sent.'],
        'anthropic_key'   => ['/^sk-ant-[A-Za-z0-9_-]{20,}$/', 'Anthropic keys start with sk-ant-.'],
        'pagespeed_key'   => ['/^AIza[A-Za-z0-9_-]{35}$/', 'A Google API key starts with AIza and is 39 characters.'],
        'wa_token'        => ['/^EAA[A-Za-z0-9]{40,}$/', 'A permanent access token starts with EAA and is long — about 200 characters. The short one from the Graph API Explorer expires in 24 hours.'],
        'wa_phone_id'     => ['/^\d{10,20}$/', 'The Phone number ID is a number of 10–20 digits — not the phone number itself.'],
        'wa_waba_id'      => ['/^\d{10,20}$/', 'The WhatsApp Business Account ID is a number of 10–20 digits.'],
        'wa_app_id'       => ['/^\d{10,20}$/', 'The App ID is a number of 10–20 digits.'],
        'wa_app_secret'   => ['/^[a-f0-9]{32}$/i', 'The App Secret is 32 letters and digits.'],
        'wa_business_id'  => ['/^\d{10,20}$/', 'The Business ID is a number of 10–20 digits.'],
        'wa_api_version'  => ['/^v\d{1,2}\.\d{1,2}$/', 'Looks like v21.0.'],
        'email'           => ['/^[^@\s]+@[^@\s]+\.[^@\s]+$/', 'That does not look like an email address.'],
        'host'            => ['/^([a-z0-9-]+\.)+[a-z]{2,}$|^(\d{1,3}\.){3}\d{1,3}$|^localhost$/i', 'That does not look like a server name (like smtp.hostinger.com) or an IP address.'],
    ];

    public static function validate(string $format, string $value): string
    {
        [$re, $msg] = self::FORMATS[$format] ?? ['/.*/s', ''];
        return preg_match($re, $value) ? '' : $msg;
    }

    /* ── Status ────────────────────────────────────────────── */

    /** Is enough entered to attempt a test? */
    public static function isConfigured(string $card): bool
    {
        return match ($card) {
            'mail_send'  => Mailer::configured(),
            'mail_read'  => Inbox::configured(),
            'recipients' => count(Notify::recipients()) > 0,
            'telegram'   => Telegram::token() !== '',
            'whatsapp'   => WhatsApp::configured(),
            'claude'     => Claude::configured(),
            'pagespeed'  => Secrets::get('pagespeed_key', '') !== '',
            'keys'       => Secrets::get('conversions_key', '') !== '' || Secrets::get('cron_key', '') !== '',
            default      => false,
        };
    }

    /**
     * @return array{state:string, checked_at:?string, reason:string, checks:array, last_used:?string}
     */
    public static function status(string $card): array
    {
        if (!self::isConfigured($card)) {
            return ['state' => 'unconfigured', 'checked_at' => null, 'reason' => '', 'checks' => [], 'step' => null, 'last_used' => null, 'passed_once' => false];
        }
        $s = Settings::json('conn_status_' . $card, []);
        $state = (string)($s['state'] ?? 'untested');
        if (!in_array($state, self::STATES, true) || $state === 'unconfigured') $state = 'untested';
        return [
            'state'       => $state,
            'checked_at'  => $s['checked_at'] ?? null,
            'reason'      => (string)($s['reason'] ?? ''),
            'checks'      => (array)($s['checks'] ?? []),
            'step'        => isset($s['step']) ? (int)$s['step'] : null,
            'last_used'   => $s['last_used'] ?? null,
            'passed_once' => !empty($s['passed_once']),
        ];
    }

    public static function setStatus(string $card, string $state, string $reason = '', array $checks = [], ?int $step = null): void
    {
        $prev = Settings::json('conn_status_' . $card, []);
        $s = ['state' => $state, 'checked_at' => gmdate('Y-m-d H:i:s'), 'reason' => cut($reason, 300),
              'checks' => array_map(static fn($c) => [(string)$c[0], (bool)$c[1], cut((string)($c[2] ?? ''), 240)], $checks),
              'step' => $step, 'last_used' => $prev['last_used'] ?? null,
              /* The toggle on a card unlocks the first time a test passes. */
              'passed_once' => !empty($prev['passed_once']) || $state === 'connected'];
        Settings::set('conn_status_' . $card, json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        self::refreshAttention();
    }

    /** Transports call this after a real send, so "last used" is honest. */
    public static function touch(string $card): void
    {
        try {
            $s = Settings::json('conn_status_' . $card, []);
            $s['last_used'] = gmdate('Y-m-d H:i:s');
            Settings::set('conn_status_' . $card, json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        } catch (Throwable) { /* never let bookkeeping break a send */ }
    }

    /** The red dot on the nav: any card in Error. */
    public static function refreshAttention(): void
    {
        $bad = 0;
        foreach (self::CARDS as $c) if (self::status($c)['state'] === 'error') $bad++;
        Settings::set('conn_attention', $bad > 0 ? '1' : '0');
    }

    /** "7 of 8 connected — WhatsApp not configured". */
    public static function summary(): array
    {
        $connected = 0; $errors = []; $unconf = [];
        foreach (self::CARDS as $c) {
            $st = self::status($c)['state'];
            if ($st === 'connected') $connected++;
            elseif ($st === 'error') $errors[] = self::title($c);
            elseif ($st === 'unconfigured') $unconf[] = self::title($c);
        }
        $parts = [];
        if ($errors) $parts[] = implode(', ', $errors) . ($errors ? (count($errors) === 1 ? ' has a problem' : ' have problems') : '');
        if ($unconf) $parts[] = implode(', ', $unconf) . ' not configured';
        $line = sprintf('%d of %d connected', $connected, count(self::CARDS)) . ($parts ? ' — ' . implode('; ', $parts) : '');
        return ['connected' => $connected, 'total' => count(self::CARDS), 'errors' => $errors,
                'unconfigured' => $unconf, 'line' => $line];
    }

    /* ── Secrets: hints, never values ──────────────────────── */

    /**
     * What the page may show about a stored secret: that it exists, its
     * last four characters, and who set it when — from the audit log,
     * which records "card:field" and never the value.
     */
    public static function hint(string $secretKey, string $card, string $field): array
    {
        $plain = Secrets::get($secretKey, '');
        if ($plain === '') return ['set' => false, 'mask' => '', 'by' => '', 'at' => ''];
        $row = DB::one("SELECT user, ts FROM wwt_audit_log WHERE action = 'conn_save' AND detail = ?
                        ORDER BY id DESC LIMIT 1", [$card . ':' . $field]);
        $at = $row['ts'] ?? (string)DB::val('SELECT updated_at FROM wwt_settings WHERE k = ?', [$secretKey], '');
        return ['set' => true, 'mask' => Secrets::maskLast4($plain),
                'by' => (string)($row['user'] ?? ''), 'at' => (string)$at];
    }

    /* ── Pending values (rule 4) ───────────────────────────── */

    public static function pending(string $card): ?array
    {
        $p = Settings::json('conn_pending_' . $card, []);
        return $p ? $p : null;
    }

    /** Hold new values until a test passes. Secrets are encrypted inside. */
    public static function setPending(string $card, array $fields, array $secrets): void
    {
        $enc = [];
        foreach ($secrets as $k => $v) if ($v !== '') $enc[$k] = Secrets::encrypt($v);
        Settings::set('conn_pending_' . $card, json_encode(
            ['fields' => $fields, 'secrets' => $enc, 'at' => gmdate('Y-m-d H:i:s')],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
    }

    public static function discardPending(string $card): void
    {
        Settings::set('conn_pending_' . $card, '');
    }

    /** Write the held values into the live keys. */
    public static function commitPending(string $card): bool
    {
        $p = self::pending($card);
        if (!$p) return false;
        foreach ((array)($p['fields'] ?? []) as $k => $v) Settings::set((string)$k, (string)$v);
        foreach ((array)($p['secrets'] ?? []) as $k => $v) Settings::set((string)$k, (string)$v);   // already encrypted
        self::discardPending($card);
        return true;
    }

    /** Decrypted view of a pending secret, for running the test against it. */
    public static function pendingSecret(string $card, string $key): string
    {
        $p = self::pending($card);
        $v = (string)($p['secrets'][$key] ?? '');
        return $v === '' ? '' : Secrets::decrypt($v);
    }

    /* ── Tests ─────────────────────────────────────────────── */

    /** Seconds until the next test is allowed, or 0. */
    public static function throttled(string $card): int
    {
        $row = DB::one('SELECT window_at FROM wwt_rate_limit WHERE bucket = ?', ['conntest:' . $card]);
        if (!$row) return 0;
        $left = self::TEST_EVERY - (time() - strtotime((string)$row['window_at'] . ' UTC'));
        return max(0, $left);
    }

    private static function claimTest(string $card): bool
    {
        return RateLimit::allow('conntest:' . $card, 1, self::TEST_EVERY);
    }

    /**
     * Run the real test for a card. Never throws; every outcome becomes a
     * status and a list of sub-checks the page can render.
     *
     * @return array{ok:bool, checks:array<int,array{0:string,1:bool,2:string}>, reason:string}
     */
    public static function test(string $card, array $opt = []): array
    {
        if (!self::claimTest($card)) {
            return ['ok' => false, 'checks' => [], 'throttled' => true,
                    'reason' => 'Tested a moment ago — wait ' . self::TEST_EVERY . ' seconds between tests.'];
        }
        try {
            $r = match ($card) {
                'mail_send'  => self::testMailSend($opt),
                'mail_read'  => self::testMailRead($opt),
                'recipients' => self::testRecipients($opt),
                'telegram'   => self::testTelegram($opt),
                'whatsapp'   => self::testWhatsApp($opt),
                'claude'     => self::testClaude($opt),
                'pagespeed'  => self::testPageSpeed($opt),
                'keys'       => self::testKeys(),
                default      => ['ok' => false, 'checks' => [], 'reason' => 'Unknown connection.'],
            };
        } catch (Throwable $t) {
            wwt_log('connections', 'test crashed', ['card' => $card, 'err' => $t->getMessage()]);
            $r = ['ok' => false, 'checks' => [], 'reason' => self::translate($card, $t->getMessage())['text']];
        }
        /* The recipients card tests one address at a time; a single failure
           does not mean the list is broken. */
        /* Which guide step to open: from the first failing sub-check's raw note. */
        $step = null;
        if (!$r['ok']) {
            $bad = array_values(array_filter($r['checks'], static fn($c) => empty($c[1])));
            $step = self::translate($card, (string)($bad[0][2] ?? $r['reason']))['step'];
        }
        $r['step'] = $step;
        if (($card !== 'recipients' || !empty($opt['record'])) && empty($opt['pending'])) {
            self::setStatus($card, $r['ok'] ? 'connected' : 'error', $r['ok'] ? '' : $r['reason'], $r['checks'], $step);
        }
        return $r;
    }

    private static function testMailSend(array $opt): array
    {
        $to = (string)($opt['to'] ?? '');
        $id = $opt['identity_array'] ?? (string)($opt['identity'] ?? 'default');
        $r  = Mailer::testIdentity($id, $to, (string)($opt['password'] ?? ''));
        return ['ok' => $r['ok'], 'checks' => $r['checks'],
                'reason' => $r['ok'] ? '' : self::translate('mail_send', $r['error'])['text']];
    }

    private static function testMailRead(array $opt = []): array
    {
        $r = Inbox::test((string)($opt['password'] ?? ''));
        $checks = [
            ['Connected to ' . Inbox::host(), $r['connected'] ?? false, $r['connected'] ? '' : (string)$r['error']],
            ['Signed in as ' . Inbox::user(), $r['ok'], $r['ok'] ? '' : (string)$r['error']],
        ];
        if ($r['ok']) {
            $checks[] = ['Mailbox readable', true, (int)$r['unread'] . ' unread' . ($r['subjects'] ? ' · latest: ' . implode(' · ', array_map(static fn($s) => '"' . cut($s, 40) . '"', $r['subjects'])) : '')];
        }
        return ['ok' => $r['ok'], 'checks' => $checks,
                'reason' => $r['ok'] ? '' : self::translate('mail_read', (string)$r['error'])['text']];
    }

    private static function testRecipients(array $opt): array
    {
        $to = (string)($opt['to'] ?? '');
        if ($to === '') return ['ok' => false, 'checks' => [], 'reason' => 'Choose an address to test.'];
        $r = Notify::testRecipient($to);
        return ['ok' => $r['ok'], 'checks' => [['Delivered a one-line test to ' . $to, $r['ok'], (string)($r['error'] ?? '')]],
                'reason' => $r['ok'] ? '' : self::translate('mail_send', (string)$r['error'])['text']];
    }

    private static function testTelegram(array $opt): array
    {
        $token = (string)($opt['token'] ?? '') ?: Telegram::token();
        $me = Telegram::getMe($token);
        $checks = [['Token accepted by Telegram', $me['ok'], $me['ok'] ? 'bot @' . $me['username'] : (string)$me['error']]];
        if (!$me['ok']) {
            return ['ok' => false, 'checks' => $checks, 'reason' => self::translate('telegram', (string)$me['error'])['text']];
        }
        $chat = (string)($opt['chat_id'] ?? '') ?: Telegram::chatId();
        if ($chat === '') {
            $checks[] = ['A chat to send to', false, 'No chat chosen yet — press Detect my chat.'];
            return ['ok' => false, 'checks' => $checks, 'reason' => 'The token works, but no chat is chosen yet. Message the bot, then press Detect my chat.'];
        }
        $s = Telegram::send('✓ Wwwebtech alerts connected — ' . local_time(gmdate('Y-m-d H:i:s'), 'j M H:i'),
                            ['chat_id' => $chat, 'token' => $token]);
        $checks[] = ['Message delivered to the chosen chat', $s['ok'], $s['ok'] ? '' : (string)$s['error']];
        return ['ok' => $s['ok'], 'checks' => $checks,
                'reason' => $s['ok'] ? '' : self::translate('telegram', (string)$s['error'])['text']];
    }

    private static function testWhatsApp(array $opt): array
    {
        $checks = [];
        $tok = (string)($opt['token'] ?? '');
        $p = WhatsApp::checkPhone($tok);
        $checks[] = ['Token and Phone number ID accepted', $p['ok'],
                     $p['ok'] ? trim(($p['display'] ?? '') . ' ' . ($p['verified_name'] ? '· ' . $p['verified_name'] : '')) : (string)$p['error']];
        if (!$p['ok']) {
            return ['ok' => false, 'checks' => $checks, 'reason' => self::translate('whatsapp', (string)$p['error'])['text']];
        }
        $t = WhatsApp::listTemplates($tok);
        $checks[] = ['Template management permission', $t['ok'],
                     $t['ok'] ? count($t['templates']) . ' template(s) on the account' : (string)$t['error']];
        $ok = $t['ok'];
        if (!empty($opt['send_to']) && $ok) {
            $s = WhatsApp::sendTestTemplate((string)$opt['send_to']);
            $checks[] = ['Test template sent to ' . $opt['send_to'], $s['ok'],
                         $s['ok'] ? 'message id ' . cut((string)$s['provider_id'], 30) . ' · would cost ₹' . number_format(($s['cost_paise'] ?? 0) / 100, 2) : (string)$s['error']];
            $ok = $ok && $s['ok'];
        }
        $bad = array_values(array_filter($checks, static fn($c) => !$c[1]));
        return ['ok' => $ok, 'checks' => $checks,
                'reason' => $ok ? '' : self::translate('whatsapp', (string)($bad[0][2] ?? ''))['text']];
    }

    private static function testClaude(array $opt = []): array
    {
        $r = Claude::testKey((string)($opt['key'] ?? ''));
        return ['ok' => $r['ok'], 'checks' => [['Key accepted, model answered', $r['ok'], $r['ok'] ? (string)($r['detail'] ?? '') : (string)$r['error']]],
                'reason' => $r['ok'] ? '' : self::translate('claude', (string)$r['error'])['text']];
    }

    private static function testPageSpeed(array $opt = []): array
    {
        $key = (string)($opt['key'] ?? '') ?: Secrets::get('pagespeed_key', '');
        $url = rtrim((string)cfg('site.url', 'https://wwwebtech.in'), '/') . '/';
        DB::disconnect();
        $r = Http::get('https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=' . rawurlencode($url)
                     . '&strategy=mobile&category=performance&key=' . rawurlencode($key), ['timeout' => 90]);
        $d = json_decode((string)$r['body'], true);
        if ($r['status'] === 200 && isset($d['lighthouseResult']['categories']['performance']['score'])) {
            $score = (int)round(100 * (float)$d['lighthouseResult']['categories']['performance']['score']);
            return ['ok' => true, 'checks' => [['Key accepted; homepage measured', true, 'mobile performance ' . $score . '/100']], 'reason' => ''];
        }
        $err = (string)($d['error']['message'] ?? ('HTTP ' . $r['status'] . ($r['error'] ? ' ' . $r['error'] : '')));
        return ['ok' => false, 'checks' => [['Key accepted', false, cut($err, 160)]],
                'reason' => self::translate('pagespeed', $err)['text']];
    }

    private static function testKeys(): array
    {
        $conv = Secrets::get('conversions_key', '');
        $test = Secrets::get('cron_key', (string)cfg('cron_key', ''));
        $checks = [
            ['Conversions feed key present', $conv !== '', $conv !== '' ? strlen($conv) . ' characters' : 'Generate one on the Integrations page or here.'],
            ['Test-submission key present', $test !== '', $test !== '' ? strlen($test) . ' characters' : 'Rotate to generate one.'],
        ];
        if ($conv !== '') {
            $r = Http::get(rtrim((string)cfg('site.url', ''), '/') . '/api/conversions.php?type=google&key=' . rawurlencode($conv), ['timeout' => 15]);
            $checks[] = ['Feed answers with the key', $r['status'] === 200, $r['status'] === 200 ? 'HTTP 200' : 'HTTP ' . $r['status']];
        }
        $ok = !in_array(false, array_map(static fn($c) => $c[1], $checks), true);
        return ['ok' => $ok, 'checks' => $checks, 'reason' => $ok ? '' : 'One of the keys is missing or the feed did not answer.'];
    }

    /* ── Plain English ─────────────────────────────────────── */

    /**
     * Turn a raw error into what happened and what to do, with the guide
     * step to open. The raw text is never shown to the owner unless nothing
     * here matches — and even then, without status codes.
     */
    public static function translate(string $card, string $raw): array
    {
        $r = strtolower($raw);
        $has = static fn(string ...$n) => (bool)array_filter($n, static fn($x) => str_contains($r, $x));
        $out = static fn(string $text, ?int $step = null) => ['text' => $text, 'step' => $step];

        if ($has('no imap extension')) {
            return $out('PHP on this server cannot read mailboxes — the IMAP extension is not installed. On Hostinger it is; on another host, ask them to enable it. Sending is unaffected.');
        }
        if ($has('could not resolve', 'name or service not known', 'getaddrinfo', 'no such host')) {
            return $out('The server name could not be found. Check the host — for Hostinger it is smtp.hostinger.com / imap.hostinger.com.', 1);
        }
        if ($has('timed out', 'timeout', 'connection refused', 'could not connect', "can't connect", 'cannot connect',
                 "couldn't open stream", 'could not open stream', 'connection failed', 'network is unreachable')) {
            return $out('Could not reach the server. The host or port is wrong, or the provider is blocking the connection. Try the preset for your provider.', 1);
        }
        switch ($card) {
            case 'mail_send':
            case 'mail_read':
                if ($has('authentication failed', 'auth', 'login failed', 'invalid credentials', '535', 'username and password not accepted', 'authenticationfailed'))
                    return $out('The mailbox refused the password. Set a new password in hPanel → Emails and paste it here. Gmail needs an App Password, not the account password.', $card === 'mail_send' ? 2 : 3);
                if ($has('starttls', 'ssl', 'tls', 'certificate'))
                    return $out('The encryption setting does not match the port. Use SSL with 465 or STARTTLS with 587 (IMAP: SSL with 993).', 1);
                if ($has('mailbox', 'no such folder', 'select failed'))
                    return $out('Signed in, but the folder was not found. Leave the folder as INBOX unless you know otherwise.', 2);
                break;
            case 'telegram':
                if ($has('chat not found', 'bot was blocked', 'forbidden', 'user is deactivated'))
                    return $out('The token works, but the bot cannot message this chat. Open the bot in Telegram, press Start (or send it anything), then press Detect my chat again.', 3);
                if ($has('unauthorized', '401', 'not found', 'no token'))
                    return $out('Telegram rejected this token. Copy the whole line BotFather sent — it looks like 123456789:AAH… If the bot was deleted, create a new one.', 1);
                if ($has('too many requests', '429'))
                    return $out('Telegram is rate-limiting the bot. Wait a minute and try again.');
                break;
            case 'whatsapp':
                if ($has('session has expired', 'expired', 'error validating access token', 'invalid oauth', '190'))
                    return $out('Meta rejected this token. Tokens from the Graph API Explorer expire in 24 hours — you need a permanent System User token (step 4).', 4);
                if ($has('permission', '(#10)', '(#200)', 'does not have permission', 'insufficient'))
                    return $out('The token is valid but is missing a permission. When generating it, tick both whatsapp_business_messaging and whatsapp_business_management, and assign the WhatsApp account to the system user (step 4).', 4);
                if ($has('unsupported get request', 'does not exist', '(#100)', 'invalid parameter', 'object with id'))
                    return $out('Meta does not recognise that ID. Check the Phone number ID and the WhatsApp Business Account ID — both are numbers shown in WhatsApp Manager, not the phone number (step 5).', 5);
                if ($has('not verified', 'business verification', 'unverified'))
                    return $out('The business is not verified yet. Nothing can send until Meta\'s verification clears (step 1).', 1);
                if ($has('not registered', 'phone number not', 'registration'))
                    return $out('That number is not registered on the Cloud API. Complete the number setup in WhatsApp Manager → Phone numbers (step 2).', 2);
                if ($has('template', 'not approved', 'paused', '132001', '132015'))
                    return $out('The template is not approved, or is paused. Press Sync from Meta and use a template shown as approved (step 7).', 7);
                if ($has('(#131030)', 'recipient phone number not in allowed list'))
                    return $out('The account is still in test mode: only numbers added under "To" in the app\'s WhatsApp → API Setup can receive messages until the business is verified (step 1).', 1);
                break;
            case 'claude':
                if ($has('authentication', 'invalid x-api-key', '401', 'invalid api key'))
                    return $out('Anthropic rejected this key. Create a new one at console.anthropic.com → API keys and paste it here.', 1);
                if ($has('permission', '403'))
                    return $out('The key is valid but its workspace does not allow this. Create the key in the default workspace.', 1);
                if ($has('credit', 'billing', 'insufficient', '402'))
                    return $out('The account has no credit. Add credit at console.anthropic.com → Billing.', 2);
                if ($has('not_found', 'model'))
                    return $out('The model name is not recognised. Check docs.claude.com for current names and edit it below.', 4);
                if ($has('rate', '429', 'overloaded', '529'))
                    return $out('Anthropic is busy or rate-limited right now. Wait a minute and test again.');
                break;
            case 'pagespeed':
                if ($has('api key not valid', 'invalid', 'api_key_invalid', '400'))
                    return $out('Google rejected this key. Copy it again from Google Cloud → Credentials — it starts with AIza.', 2);
                if ($has('blocked', 'restricted', 'referer', '403', 'permission_denied'))
                    return $out('The key exists but is restricted in a way that blocks this server. Under API restrictions allow PageSpeed Insights API; under Application restrictions choose None.', 3);
                if ($has('quota', '429'))
                    return $out('The key\'s daily quota is used up. It resets at midnight Pacific time.');
                break;
        }
        $clean = trim(preg_replace('/\b(https?:\/\/\S+|HTTP\s*\d{3}|\d{3}\s*[A-Z][a-z]+)\b/', '', $raw) ?? $raw);
        return $out($clean !== '' ? 'It did not work: ' . cut($clean, 160) : 'It did not work, and the service gave no reason. Check every field and test again.');
    }

    /* ── Health (§6) ───────────────────────────────────────── */

    /**
     * The daily non-sending check of every configured connection.
     * A card that goes from Connected to Error fires an alert on every
     * channel that still works.
     */
    public static function health(): string
    {
        $lines = []; $broke = [];
        foreach (self::CARDS as $card) {
            if (!self::isConfigured($card)) continue;
            $before = self::status($card)['state'];
            try {
                [$ok, $reason] = self::probe($card);
            } catch (Throwable $t) {
                $ok = false; $reason = self::translate($card, $t->getMessage())['text'];
            }
            if ($ok) self::setStatus($card, 'connected', '', self::status($card)['checks']);
            else     self::setStatus($card, 'error', $reason, self::status($card)['checks']);
            $lines[] = self::title($card) . ': ' . ($ok ? 'ok' : 'ERROR');
            if (!$ok && $before === 'connected') $broke[] = [$card, $reason];
        }
        /* DNS is not a connection, but the sending card shows it. */
        try { Mailer::dnsHealth(true); } catch (Throwable) {}

        foreach ($broke as [$card, $reason]) {
            Notify::systemAlert(
                self::title($card) . ' stopped working',
                self::title($card) . " failed its daily check.\n\n" . $reason
                . "\n\nOpen the panel → Connections to fix it.",
                $card === 'telegram' ? 'telegram' : ($card === 'mail_send' ? 'mail' : ''));
        }
        return $lines ? implode(' · ', $lines) . ($broke ? ' · alerted on ' . count($broke) : '') : 'nothing configured';
    }

    /** A non-sending check per card: [ok, plain-English reason]. */
    private static function probe(string $card): array
    {
        return match ($card) {
            'mail_send'  => (static function () { $r = Mailer::smtpProbe('default'); return [$r['ok'], $r['ok'] ? '' : self::translate('mail_send', $r['error'])['text']]; })(),
            'mail_read'  => (static function () { $r = Inbox::probe(); return [$r['ok'], $r['ok'] ? '' : self::translate('mail_read', $r['error'])['text']]; })(),
            'recipients' => [true, ''],
            'telegram'   => (static function () { $r = Telegram::getMe(); return [$r['ok'], $r['ok'] ? '' : self::translate('telegram', $r['error'])['text']]; })(),
            'whatsapp'   => (static function () { $r = WhatsApp::checkPhone(); return [$r['ok'], $r['ok'] ? '' : self::translate('whatsapp', $r['error'])['text']]; })(),
            'claude'     => (static function () { $r = Claude::testKey(); return [$r['ok'], $r['ok'] ? '' : self::translate('claude', $r['error'])['text']]; })(),
            'pagespeed'  => (static function () {
                                $key = Secrets::get('pagespeed_key', '');
                                /* A deliberately invalid URL: the API validates the key before the URL, so a
                                   key error reads as 400 "API key not valid" and a good key reads as a URL error. */
                                $r = Http::get('https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=' . rawurlencode('https://example.invalid/') . '&key=' . rawurlencode($key), ['timeout' => 25]);
                                $bad = str_contains(strtolower((string)$r['body']), 'api key not valid') || $r['status'] === 403;
                                return [!$bad, $bad ? self::translate('pagespeed', (string)$r['body'])['text'] : ''];
                             })(),
            'keys'       => [true, ''],
            default      => [false, 'unknown'],
        };
    }
}
