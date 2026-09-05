<?php
/* ============================================================
   Connections — every credential the site needs, in one place.

   One card per connection. Every card has the same anatomy: a status
   pill that means one of four things, what the connection is for, a
   numbered guide written for someone who has never opened the external
   site, the fields with paste-time validation, a Test that makes a real
   call, and a result panel that lists each sub-check in plain English.

   Secrets are write-only. The page shows a hint (last four characters),
   who set it and when — never the value. Replace or Remove, never edit.

   A failed test never overwrites a working credential: when a card is
   Connected and new values arrive, they are tested first; if the test
   fails they wait as "pending" until the owner says Save anyway.

   Viewers see the status column and nothing else.
   ============================================================ */
declare(strict_types=1);
if (!defined('WWT_ADMIN')) { http_response_code(404); exit; }

$me      = Auth::email();
$card    = field($_POST, 'card', 20);
$action  = field($_POST, 'action', 40);
$back    = static fn(string $c = '') => '/admin/?p=connections' . ($c ? '#c-' . $c : '');

/** Hold a test result for the redirect, then show it once under its card. */
$remember = static function (string $card, array $r): void { $_SESSION['conn_result'] = ['card' => $card, 'r' => $r]; };

/** Save or hold: the rule from the brief, in one place. */
$protect = static fn(string $card): bool => Connections::status($card)['state'] === 'connected';

if ($action !== '') {
    Auth::requireAdmin();
    try {
        switch ($action) {

            /* ── Email — sending ───────────────────────────── */
            case 'save_identity': {
                $id = preg_replace('/[^a-z0-9_-]/', '', strtolower(field($_POST, 'id', 30))) ?: 'default';
                $preset = field($_POST, 'preset', 20);
                $i = [
                    'id' => $id, 'label' => field($_POST, 'label', 60), 'name' => field($_POST, 'name', 80),
                    'email' => strtolower(field($_POST, 'email', 150)), 'host' => field($_POST, 'host', 120),
                    'port' => (int)field($_POST, 'port', 6), 'secure' => field($_POST, 'secure', 5),
                    'user' => strtolower(field($_POST, 'user', 150)) ?: strtolower(field($_POST, 'email', 150)),
                    'use' => array_values(array_intersect((array)($_POST['use'] ?? []), ['system', 'funnel', 'manual'])),
                ];
                if (isset(Connections::MAIL_PRESETS[$preset]) && $preset !== 'other' && $i['host'] === '') {
                    [$i['host'], $i['port'], $i['secure']] = Connections::MAIL_PRESETS[$preset]['smtp'];
                }
                foreach (['email' => $i['email'], 'host' => $i['host']] as $fmt => $val) {
                    $err = Connections::validate($fmt, $val);
                    if ($err !== '') throw new InvalidArgumentException($err);
                }
                if ($i['port'] < 1 || $i['port'] > 65535) throw new InvalidArgumentException('Port must be between 1 and 65535.');
                $pass = (string)($_POST['password'] ?? '');
                $list = Mailer::identities();
                $found = false;
                foreach ($list as &$x) if ($x['id'] === $id) { $x = $i; $found = true; }
                unset($x);
                if (!$found) $list[] = $i;
                if ($pass === '' && Mailer::identityPass($id) === '' && !$found) {
                    throw new InvalidArgumentException('A new mailbox needs its password.');
                }

                $hold = $protect('mail_send');
                if ($pass !== '' || $hold) {
                    /* Prove the login before anything is written. */
                    $r = Connections::test('mail_send', ['identity_array' => $i, 'to' => $me, 'password' => $pass, 'pending' => true]);
                    if (!empty($r['throttled'])) { $remember('mail_send', $r); redirect($back('mail_send'), 'warn', $r['reason']); }
                    if (!$r['ok']) {
                        Connections::setPending('mail_send', ['mail_identities' => json_encode($list, JSON_UNESCAPED_UNICODE) ?: '[]'],
                                                             $pass !== '' ? ['mail_identity_' . $id . '_pass' => $pass] : []);
                        $remember('mail_send', $r);
                        redirect($back('mail_send'), 'warn', 'Not saved yet — the test failed. Fix it and try again, or choose "Save anyway" below.');
                    }
                    $remember('mail_send', $r);
                }
                Mailer::saveIdentities($list);
                if ($pass !== '') {
                    Secrets::put('mail_identity_' . $id . '_pass', $pass);
                    if ($id === 'default') Secrets::put('smtp_pass', $pass);
                    audit('conn_save', 'mail_send:password:' . $id, $me);
                }
                audit('conn_save', 'mail_send:identity:' . $id, $me);
                if ($pass !== '' || $hold) Connections::setStatus('mail_send', 'connected', '', $_SESSION['conn_result']['r']['checks'] ?? []);
                redirect($back('mail_send'), 'ok', 'Saved' . ($pass !== '' ? ' and tested — the mailbox works.' : '.'));
            }
            case 'test_identity': {
                $r = Connections::test('mail_send', ['identity' => field($_POST, 'id', 30) ?: 'default', 'to' => $me]);
                $remember('mail_send', $r);
                redirect($back('mail_send'), $r['ok'] ? 'ok' : (!empty($r['throttled']) ? 'warn' : 'bad'),
                    $r['ok'] ? 'A test email is on its way to ' . $me . '.' : $r['reason']);
            }
            case 'remove_identity': {
                $id = field($_POST, 'id', 30);
                if ($id === 'default') throw new InvalidArgumentException('The default mailbox cannot be removed — replace its details instead.');
                Mailer::saveIdentities(array_values(array_filter(Mailer::identities(), static fn($x) => $x['id'] !== $id)));
                Secrets::put('mail_identity_' . $id . '_pass', '');
                audit('conn_remove', 'mail_send:identity:' . $id, $me);
                redirect($back('mail_send'), 'ok', 'Removed.');
            }
            case 'refresh_dns': {
                Mailer::dnsHealth(true);
                redirect($back('mail_send'), 'ok', 'DNS re-checked.');
            }

            /* ── Email — reading replies ───────────────────── */
            case 'save_mail_read': {
                $preset = field($_POST, 'preset', 20);
                $f = ['imap_host' => field($_POST, 'host', 120), 'imap_port' => (string)max(1, min(65535, (int)field($_POST, 'port', 6))),
                      'imap_secure' => in_array(field($_POST, 'secure', 5), ['ssl', 'tls', 'none'], true) ? field($_POST, 'secure', 5) : 'ssl',
                      'imap_user' => strtolower(field($_POST, 'user', 150)), 'imap_folder' => field($_POST, 'folder', 60) ?: 'INBOX'];
                if (isset(Connections::MAIL_PRESETS[$preset]) && $preset !== 'other' && $f['imap_host'] === '') {
                    [$f['imap_host'], $port, $f['imap_secure']] = Connections::MAIL_PRESETS[$preset]['imap']; $f['imap_port'] = (string)$port;
                }
                foreach (['email' => $f['imap_user'], 'host' => $f['imap_host']] as $fmt => $val) {
                    $err = Connections::validate($fmt, $val); if ($err !== '') throw new InvalidArgumentException($err);
                }
                $pass = (string)($_POST['password'] ?? '');
                if ($pass === '' && Inbox::pass() === '') throw new InvalidArgumentException('The mailbox password is needed.');
                $hold = $protect('mail_read');
                if ($hold) {
                    /* Test against the new values without saving: write them, test, roll back on failure. */
                    $old = []; foreach ($f as $k => $v) { $old[$k] = (string)Settings::get($k, ''); Settings::set($k, $v); }
                    $r = Connections::test('mail_read', ['password' => $pass, 'pending' => true]);
                    if (!$r['ok']) {
                        foreach ($old as $k => $v) Settings::set($k, $v);
                        Connections::setPending('mail_read', $f, $pass !== '' ? ['imap_pass' => $pass] : []);
                        $remember('mail_read', $r);
                        redirect($back('mail_read'), 'warn', 'Not saved yet — the test failed. Fix it and try again, or choose "Save anyway" below.');
                    }
                    $remember('mail_read', $r);
                }
                foreach ($f as $k => $v) Settings::set($k, $v);
                if ($pass !== '') { Secrets::put('imap_pass', $pass); audit('conn_save', 'mail_read:password', $me); }
                audit('conn_save', 'mail_read:settings', $me);
                if ($hold) Connections::setStatus('mail_read', 'connected', '', $_SESSION['conn_result']['r']['checks'] ?? []);
                redirect($back('mail_read'), 'ok', 'Saved.');
            }
            case 'test_mail_read': {
                $r = Connections::test('mail_read');
                $remember('mail_read', $r);
                redirect($back('mail_read'), $r['ok'] ? 'ok' : (!empty($r['throttled']) ? 'warn' : 'bad'), $r['ok'] ? 'The mailbox is readable.' : $r['reason']);
            }

            /* ── Alert recipients ──────────────────────────── */
            case 'add_recipient': {
                $email = strtolower(field($_POST, 'email', 150));
                $err = Connections::validate('email', $email); if ($err !== '') throw new InvalidArgumentException($err);
                $roles = array_values(array_intersect((array)($_POST['roles'] ?? []), array_keys(Notify::ROLES)));
                if (!$roles) throw new InvalidArgumentException('Choose at least one thing for this address to receive.');
                $list = array_values(array_filter(Notify::recipients(), static fn($r) => $r['email'] !== $email));
                $list[] = ['id' => substr(md5($email), 0, 8), 'email' => $email, 'label' => field($_POST, 'label', 60), 'roles' => $roles];
                Notify::saveRecipients($list);
                audit('conn_save', 'recipients:' . $email, $me);
                redirect($back('recipients'), 'ok', 'Added. Press Send test to prove it arrives.');
            }
            case 'remove_recipient': {
                $id = field($_POST, 'id', 20);
                $list = array_values(array_filter(Notify::recipients(), static fn($r) => $r['id'] !== $id));
                Notify::saveRecipients($list);
                audit('conn_remove', 'recipients:' . $id, $me);
                redirect($back('recipients'), 'ok', 'Removed.');
            }
            case 'test_recipient': {
                $id = field($_POST, 'id', 20);
                $to = ''; foreach (Notify::recipients() as $r) if ($r['id'] === $id) $to = $r['email'];
                $r = Connections::test('recipients', ['to' => $to, 'record' => true]);
                $remember('recipients', $r);
                redirect($back('recipients'), $r['ok'] ? 'ok' : 'bad', $r['ok'] ? 'Sent to ' . $to . '.' : $r['reason']);
            }

            /* ── Telegram ──────────────────────────────────── */
            case 'save_telegram': {
                $tok = trim((string)($_POST['token'] ?? ''));
                if ($tok === '') throw new InvalidArgumentException('Paste the token BotFather gave you.');
                $err = Connections::validate('telegram_token', $tok); if ($err !== '') throw new InvalidArgumentException($err);
                if ($protect('telegram')) {
                    $me2 = Telegram::getMe($tok);
                    if (!$me2['ok']) {
                        Connections::setPending('telegram', [], ['telegram_token' => $tok]);
                        $r = ['ok' => false, 'checks' => [['Token accepted by Telegram', false, $me2['error']]], 'reason' => Connections::translate('telegram', $me2['error'])['text']];
                        $remember('telegram', $r);
                        redirect($back('telegram'), 'warn', 'Not saved yet — Telegram rejected the new token. The old one still works. Fix it, or choose "Save anyway".');
                    }
                }
                Secrets::put('telegram_token', $tok);
                audit('conn_save', 'telegram:token', $me);
                redirect($back('telegram'), 'ok', 'Token saved. Now open your bot in Telegram, press Start, then Detect my chat.');
            }
            case 'detect_chats': {
                $r = Telegram::recentChats();
                if (!$r['ok']) {
                    $remember('telegram', ['ok' => false, 'checks' => [['Asked Telegram who has messaged the bot', false, $r['error']]], 'reason' => Connections::translate('telegram', $r['error'])['text']]);
                    redirect($back('telegram'), 'bad', Connections::translate('telegram', $r['error'])['text']);
                }
                $_SESSION['conn_chats'] = $r['chats'];
                redirect($back('telegram'), $r['chats'] ? 'ok' : 'warn',
                    $r['chats'] ? count($r['chats']) . ' chat(s) found — pick yours below.'
                                : 'Nobody has messaged the bot yet. Open it in Telegram, press Start, then try again.');
            }
            case 'add_chat': {
                $chat = field($_POST, 'chat_id', 30);
                if (!preg_match('/^-?\d{4,20}$/', $chat)) throw new InvalidArgumentException('That is not a chat id.');
                $roles = array_values(array_intersect((array)($_POST['roles'] ?? ['all']), ['all', 'hot', 'approvals'])) ?: ['all'];
                $list = array_values(array_filter(Telegram::recipients(), static fn($r) => $r['chat_id'] !== $chat));
                $list[] = ['chat_id' => $chat, 'title' => field($_POST, 'title', 60) ?: $chat, 'kind' => field($_POST, 'kind', 20) ?: 'private chat', 'roles' => $roles];
                Telegram::saveRecipients($list);
                unset($_SESSION['conn_chats']);
                audit('conn_save', 'telegram:chat', $me);
                $r = Connections::test('telegram', ['chat_id' => $chat]);
                $remember('telegram', $r);
                redirect($back('telegram'), $r['ok'] ? 'ok' : 'bad', $r['ok'] ? 'Connected — a test message was sent.' : $r['reason']);
            }
            case 'remove_chat': {
                $chat = field($_POST, 'chat_id', 30);
                Telegram::saveRecipients(array_values(array_filter(Telegram::recipients(), static fn($r) => $r['chat_id'] !== $chat)));
                audit('conn_remove', 'telegram:chat', $me);
                redirect($back('telegram'), 'ok', 'Removed.');
            }
            case 'test_telegram': {
                $r = Connections::test('telegram');
                $remember('telegram', $r);
                redirect($back('telegram'), $r['ok'] ? 'ok' : (!empty($r['throttled']) ? 'warn' : 'bad'), $r['ok'] ? 'Sent. Check the chat.' : $r['reason']);
            }

            /* ── WhatsApp ──────────────────────────────────── */
            case 'save_whatsapp': {
                $f = [
                    'wa_phone_id' => field($_POST, 'phone_id', 25), 'wa_waba_id' => field($_POST, 'waba_id', 25),
                    'wa_app_id' => field($_POST, 'app_id', 25), 'wa_business_id' => field($_POST, 'business_id', 25),
                    'wa_display_number' => field($_POST, 'display_number', 25), 'wa_api_version' => field($_POST, 'api_version', 8) ?: 'v21.0',
                    'wa_business_status' => in_array(field($_POST, 'business_status', 12), ['not_started', 'submitted', 'verified'], true) ? field($_POST, 'business_status', 12) : 'not_started',
                    'wa_monthly_cap_inr' => (string)max(0, min(100000, (int)field($_POST, 'cap', 7))),
                    'wa_lang' => field($_POST, 'lang', 8) ?: 'en',
                ];
                foreach (['wa_phone_id', 'wa_waba_id', 'wa_app_id', 'wa_business_id', 'wa_api_version'] as $k) {
                    if ($f[$k] !== '') { $err = Connections::validate($k, $f[$k]); if ($err !== '') throw new InvalidArgumentException($err); }
                }
                $secrets = [];
                foreach (['wa_token' => 'token', 'wa_app_secret' => 'app_secret'] as $k => $field) {
                    $v = trim((string)($_POST[$field] ?? ''));
                    if ($v !== '') { $err = Connections::validate($k, $v); if ($err !== '') throw new InvalidArgumentException($err); $secrets[$k] = $v; }
                }
                $hold = $protect('whatsapp') && (isset($secrets['wa_token']) || $f['wa_phone_id'] !== WhatsApp::phoneId());
                if ($hold) {
                    $old = []; foreach ($f as $k => $v) { $old[$k] = (string)Settings::get($k, ''); Settings::set($k, $v); }
                    $r = Connections::test('whatsapp', ['token' => $secrets['wa_token'] ?? '', 'pending' => true]);
                    if (!$r['ok']) {
                        foreach ($old as $k => $v) Settings::set($k, $v);
                        Connections::setPending('whatsapp', $f, $secrets);
                        $remember('whatsapp', $r);
                        redirect($back('whatsapp'), 'warn', 'Not saved yet — Meta rejected the new details. The old ones still work. Fix it, or choose "Save anyway".');
                    }
                    $remember('whatsapp', $r);
                }
                foreach ($f as $k => $v) Settings::set($k, $v);
                foreach ($secrets as $k => $v) { Secrets::put($k, $v); audit('conn_save', 'whatsapp:' . $k, $me); }
                audit('conn_save', 'whatsapp:settings', $me);
                if ($hold) Connections::setStatus('whatsapp', 'connected', '', $_SESSION['conn_result']['r']['checks'] ?? []);
                redirect($back('whatsapp'), 'ok', 'Saved.');
            }
            case 'test_whatsapp': {
                $r = Connections::test('whatsapp', ['send_to' => field($_POST, 'send_to', 30)]);
                $remember('whatsapp', $r);
                redirect($back('whatsapp'), $r['ok'] ? 'ok' : (!empty($r['throttled']) ? 'warn' : 'bad'), $r['ok'] ? 'WhatsApp is connected.' : $r['reason']);
            }
            case 'sync_templates': {
                $r = WhatsApp::syncTemplates();
                audit('conn_sync', 'whatsapp:templates ' . ($r['ok'] ? $r['count'] : 'failed'), $me);
                redirect($back('whatsapp'), $r['ok'] ? 'ok' : 'bad', $r['ok'] ? $r['count'] . ' template(s) synced from Meta.' : Connections::translate('whatsapp', $r['error'])['text']);
            }
            case 'rotate_verify_token': {
                Settings::set('wa_verify_token', bin2hex(random_bytes(20)));
                Settings::set('wa_webhook_verified_at', '');
                audit('conn_rotate', 'whatsapp:verify_token', $me);
                redirect($back('whatsapp'), 'warn', 'New verify token. Paste it into Meta again — the webhook is unverified until you do.');
            }
            case 'toggle_whatsapp': {
                $on = !empty($_POST['on']);
                if ($on && !Connections::status('whatsapp')['passed_once']) throw new InvalidArgumentException('Test the connection first — the switch unlocks after one passing test.');
                if ($on && WhatsApp::businessStatus() !== 'verified') throw new InvalidArgumentException('Record the business as verified (step 1) before switching on — Meta will not deliver until it is.');
                Settings::set('wa_enabled', $on ? '1' : '0');
                audit('conn_toggle', 'whatsapp:' . ($on ? 'on' : 'off'), $me);
                redirect($back('whatsapp'), 'ok', $on ? 'WhatsApp is on for customers.' : 'WhatsApp is off — steps fall back to email.');
            }

            /* ── Claude ────────────────────────────────────── */
            case 'save_claude': {
                $key = trim((string)($_POST['key'] ?? ''));
                $model = field($_POST, 'model', 60);
                $cap = (float)($_POST['cap'] ?? 15);
                if ($cap < 0 || $cap > 500) throw new InvalidArgumentException('Set a cap between 0 and 500 USD.');
                if ($key !== '') { $err = Connections::validate('anthropic_key', $key); if ($err !== '') throw new InvalidArgumentException($err); }
                if ($model !== '' && !isset(Claude::MODELS[$model])) throw new InvalidArgumentException('That model is not one this panel knows: ' . implode(', ', array_keys(Claude::MODELS)));
                if ($key !== '' && $protect('claude')) {
                    $r = Connections::test('claude', ['key' => $key, 'pending' => true]);
                    if (!$r['ok']) {
                        Connections::setPending('claude', [], ['anthropic_key' => $key]);
                        $remember('claude', $r);
                        redirect($back('claude'), 'warn', 'Not saved yet — Anthropic rejected the new key. The old one still works. Fix it, or choose "Save anyway".');
                    }
                    $remember('claude', $r);
                }
                if ($key !== '') { Secrets::put('anthropic_key', $key); audit('conn_save', 'claude:key', $me); }
                if ($model !== '') Settings::set('blog_model', $model);
                Settings::set('blog_monthly_cap_usd', (string)$cap);
                audit('conn_save', 'claude:settings', $me);
                redirect($back('claude'), 'ok', 'Saved.');
            }
            case 'test_claude': {
                $r = Connections::test('claude');
                $remember('claude', $r);
                redirect($back('claude'), $r['ok'] ? 'ok' : (!empty($r['throttled']) ? 'warn' : 'bad'), $r['ok'] ? 'The key works.' : $r['reason']);
            }

            /* ── PageSpeed ─────────────────────────────────── */
            case 'save_pagespeed': {
                $key = trim((string)($_POST['key'] ?? ''));
                if ($key === '') throw new InvalidArgumentException('Paste the key from Google Cloud.');
                $err = Connections::validate('pagespeed_key', $key); if ($err !== '') throw new InvalidArgumentException($err);
                if ($protect('pagespeed')) {
                    $r = Connections::test('pagespeed', ['key' => $key, 'pending' => true]);
                    if (!$r['ok']) {
                        Connections::setPending('pagespeed', [], ['pagespeed_key' => $key]);
                        $remember('pagespeed', $r);
                        redirect($back('pagespeed'), 'warn', 'Not saved yet — Google rejected the new key. The old one still works. Fix it, or choose "Save anyway".');
                    }
                    $remember('pagespeed', $r);
                }
                Secrets::put('pagespeed_key', $key);
                audit('conn_save', 'pagespeed:key', $me);
                redirect($back('pagespeed'), 'ok', 'Saved. Press Test to measure the homepage with it.');
            }
            case 'test_pagespeed': {
                $r = Connections::test('pagespeed');
                $remember('pagespeed', $r);
                redirect($back('pagespeed'), $r['ok'] ? 'ok' : (!empty($r['throttled']) ? 'warn' : 'bad'), $r['ok'] ? 'The key works.' : $r['reason']);
            }

            /* ── Keys ──────────────────────────────────────── */
            case 'rotate_conversions_key': {
                Secrets::put('conversions_key', bin2hex(random_bytes(24)));
                audit('conn_rotate', 'keys:conversions', $me);
                redirect($back('keys'), 'warn', 'New feed key. The old URLs stopped working just now — copy the new ones from the Integrations page into Google and Microsoft.');
            }
            case 'rotate_test_key': {
                Secrets::put('cron_key', bin2hex(random_bytes(16)));
                audit('conn_rotate', 'keys:test', $me);
                redirect($back('keys'), 'ok', 'New test-submission key.');
            }
            case 'test_keys': {
                $r = Connections::test('keys');
                $remember('keys', $r);
                redirect($back('keys'), $r['ok'] ? 'ok' : 'bad', $r['ok'] ? 'Both keys are in place.' : $r['reason']);
            }

            /* ── Shared ────────────────────────────────────── */
            case 'remove_secret': {
                $allowed = ['telegram' => ['telegram_token'], 'whatsapp' => ['wa_token', 'wa_app_secret'], 'claude' => ['anthropic_key'],
                            'pagespeed' => ['pagespeed_key'], 'mail_read' => ['imap_pass'], 'keys' => ['conversions_key', 'cron_key']];
                $key = field($_POST, 'key', 40);
                if (!in_array($key, $allowed[$card] ?? [], true)) throw new InvalidArgumentException('Not a removable secret.');
                Secrets::put($key, '');
                if ($key === 'telegram_token') { Telegram::saveRecipients([]); }
                if ($key === 'wa_token') Settings::set('wa_enabled', '0');
                Settings::set('conn_status_' . $card, '');
                Connections::refreshAttention();
                audit('conn_remove', $card . ':' . $key, $me);
                redirect($back($card), 'ok', 'Removed. The connection is not configured until a new value is saved.');
            }
            case 'commit_pending': {
                $p = Connections::pending($card);
                if (!$p) throw new InvalidArgumentException('Nothing is pending.');
                if ($card === 'mail_send' && isset($p['fields']['mail_identities'])) {
                    Mailer::saveIdentities(json_decode((string)$p['fields']['mail_identities'], true) ?: []);
                    foreach ((array)($p['secrets'] ?? []) as $k => $v) {
                        Settings::set((string)$k, (string)$v);
                        if (str_starts_with((string)$k, 'mail_identity_default_')) Settings::set('smtp_pass', (string)$v);
                    }
                    Connections::discardPending('mail_send');
                } else {
                    Connections::commitPending($card);
                }
                Settings::set('conn_status_' . $card, '');
                Connections::refreshAttention();
                audit('conn_save', $card . ':saved-anyway', $me);
                redirect($back($card), 'warn', 'Saved anyway. It is marked untested until a test passes.');
            }
            case 'discard_pending': {
                Connections::discardPending($card);
                redirect($back($card), 'ok', 'Discarded. The previous values are unchanged.');
            }
            case 'run_health': {
                $out = Jobs::run('connections_health');
                redirect($back(), 'ok', 'Checked every connection: ' . cut(Jobs::describe($out), 200));
            }
        }
        throw new InvalidArgumentException('Unknown action.');
    } catch (Throwable $t) {
        redirect($back($card), 'bad', $t->getMessage());
    }
}

/* ── Page data ─────────────────────────────────────────── */
$isAdmin = Auth::isAdmin();
$result  = $_SESSION['conn_result'] ?? null; unset($_SESSION['conn_result']);
$chats   = $_SESSION['conn_chats'] ?? null;
$summary = Connections::summary();

$pillOf = static function (array $st): string {
    return match ($st['state']) {
        'connected'    => '<span class="pill pill--ok">Connected</span>',
        'error'        => '<span class="pill pill--fail">Error</span>',
        'untested'     => '<span class="pill pill--warn">Configured — untested</span>',
        default        => '<span class="pill pill--off">Not configured</span>',
    };
};
$when = static fn(?string $ts) => $ts ? local_time($ts, 'j M H:i') : '';

/** The secret row: hint, who, when, replace/remove — never the value. */
$secretRow = static function (string $card, string $key, string $field, string $label) use ($isAdmin): void {
    $h = Connections::hint($key, $card, $field);
    echo '<div class="secret"><b>' . e($label) . ':</b> ';
    if ($h['set']) {
        echo '<code>' . e($h['mask']) . '</code> <span class="muted">set ' . e($h['at'] ? local_time($h['at'], 'j M Y') : '') . ($h['by'] ? ' by ' . e($h['by']) : '') . '</span>';
        if ($isAdmin) {
            echo ' <form method="post" style="display:inline" onsubmit="return confirm(\'Remove this ' . e($label) . '? The connection stops working until a new one is saved.\')">'
               . Csrf::field() . '<input type="hidden" name="action" value="remove_secret"><input type="hidden" name="card" value="' . e($card) . '">'
               . '<input type="hidden" name="key" value="' . e($key) . '"><button class="linkbtn" type="submit">Remove</button></form>';
        }
    } else {
        echo '<span class="muted">not set</span>';
    }
    echo '</div>';
};

$checksList = static function (array $checks): void {
    if (!$checks) return;
    echo '<ul class="checks">';
    foreach ($checks as $c) {
        echo '<li class="' . (empty($c[1]) ? 'no' : '') . '">' . e((string)$c[0]);
        if ((string)($c[2] ?? '') !== '') echo ' <span class="why">— ' . e((string)$c[2]) . '</span>';
        echo '</li>';
    }
    echo '</ul>';
};

$resultPanel = static function (string $card) use ($result, $checksList): void {
    if (!$result || $result['card'] !== $card) return;
    $r = $result['r'];
    echo '<div class="alert alert--' . ($r['ok'] ? 'ok' : (!empty($r['throttled']) ? 'warn' : 'bad')) . '" style="margin-top:.8rem">';
    echo '<b>' . ($r['ok'] ? 'Test passed' : (!empty($r['throttled']) ? 'Too soon' : 'Test failed')) . '</b>';
    if (!$r['ok'] && (string)($r['reason'] ?? '') !== '') echo '<div style="margin-top:.25rem">' . e((string)$r['reason']) . '</div>';
    if (!$r['ok'] && !empty($r['step'])) echo '<div style="margin-top:.25rem"><a href="#g-' . e($card) . '-' . (int)$r['step'] . '" style="text-decoration:underline">How to fix — step ' . (int)$r['step'] . '</a></div>';
    $checksList((array)($r['checks'] ?? []));
    echo '</div>';
};

$pendingPanel = static function (string $card) use ($isAdmin): void {
    $p = Connections::pending($card);
    if (!$p || !$isAdmin) return;
    echo '<div class="alert alert--warn" style="margin-top:.8rem"><b>New values are waiting.</b> They failed their test at '
       . e(local_time((string)$p['at'], 'H:i')) . ' and were not saved, so the previous, working ones are still in use. '
       . '<div class="row" style="gap:.4rem;margin-top:.5rem">'
       . '<form method="post">' . Csrf::field() . '<input type="hidden" name="action" value="commit_pending"><input type="hidden" name="card" value="' . e($card) . '"><button class="btn btn--sm btn--danger" type="submit">Save anyway</button></form>'
       . '<form method="post">' . Csrf::field() . '<input type="hidden" name="action" value="discard_pending"><input type="hidden" name="card" value="' . e($card) . '"><button class="btn btn--ghost btn--sm" type="submit">Discard</button></form>'
       . '</div></div>';
};

$cardTop = static function (string $card) use ($pillOf, $when): void {
    $st = Connections::status($card);
    echo '<section class="card conn" id="c-' . e($card) . '"><div class="conn__head"><h2>' . e(Connections::title($card)) . '</h2>' . $pillOf($st);
    if ($st['state'] === 'connected' && $st['checked_at']) echo '<span class="muted small">verified ' . e($when($st['checked_at'])) . '</span>';
    if ($st['last_used']) echo '<span class="conn__when">last used ' . e($when($st['last_used'])) . '</span>';
    echo '</div>';
    if ($st['state'] === 'error') {
        echo '<div class="alert alert--bad" style="margin:.3rem 0 .8rem"><b>' . e($st['reason'] ?: 'The last check failed.') . '</b>';
        if ($st['step']) echo ' <a href="#g-' . e($card) . '-' . (int)$st['step'] . '" style="text-decoration:underline">How to fix</a>';
        echo '</div>';
    }
    echo '<p class="conn__blurb">' . e(Connections::blurb($card)) . '</p>';
    $steps = Connections::guide($card);
    if ($steps) {
        echo '<details' . ($st['state'] === 'unconfigured' ? ' open' : '') . '><summary>How to set this up — ' . count($steps) . ' steps</summary><ol class="guide">';
        foreach ($steps as $i => $html) echo '<li id="g-' . e($card) . '-' . ($i + 1) . '">' . $html . '</li>';
        echo '</ol></details>';
    }
};
$cardEnd = static function (string $card) use ($resultPanel, $pendingPanel): void { $pendingPanel($card); $resultPanel($card); echo '</section>'; };

$presetOptions = static function (string $kind): string {
    $o = '';
    foreach (Connections::MAIL_PRESETS as $k => $p) $o .= '<option value="' . e($k) . '">' . e($p['label']) . '</option>';
    return $o;
};

layout_top('Connections', 'connections');
?>
<div class="page-head">
  <div>
    <h1>Connections</h1>
    <p><?= e($summary['line']) ?>. Every credential the site needs is entered, tested and rotated here — and
       nothing here shows a saved secret back to you.</p>
  </div>
  <?php if ($isAdmin): ?>
  <form method="post" style="margin:0"><?= Csrf::field() ?><input type="hidden" name="action" value="run_health">
    <button class="btn btn--ghost btn--sm" type="submit">Check everything now</button></form>
  <?php endif; ?>
</div>

<?php if (!$isAdmin): ?>
  <div class="card card--pad0"><div class="tablewrap" style="border:0"><table>
    <thead><tr><th>Connection</th><th>Status</th><th>Last verified</th></tr></thead><tbody>
    <?php foreach (Connections::CARDS as $c): $st = Connections::status($c); ?>
      <tr><td><b><?= e(Connections::title($c)) ?></b></td><td><?= $pillOf($st) ?></td><td class="small muted"><?= e($when($st['checked_at'])) ?: '—' ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
  <p class="muted small">Read-only accounts see the status of each connection and nothing else.</p>
<?php layout_bottom(); return; endif; ?>

<?php /* ═══════════════ Email — sending ═══════════════ */ $cardTop('mail_send');
  $dns = Mailer::dnsHealth();
  if (!empty($dns['available']) && !empty($dns['domain'])): ?>
  <div class="dns">
    <?php foreach (['spf' => 'SPF', 'dkim' => 'DKIM', 'dmarc' => 'DMARC'] as $k => $label): $d = $dns[$k] ?? []; ?>
      <div><b><?= $label ?></b>
        <?= !empty($d['ok']) ? '<span class="pill pill--ok">present</span>' : '<span class="pill pill--fail">missing</span>' ?>
        <?php if ($k === 'dkim' && !empty($d['selector'])): ?><span class="small muted">selector <?= e((string)$d['selector']) ?></span><?php endif; ?>
        <?php if ($k === 'dmarc' && !empty($d['policy'])): ?><span class="small muted">p=<?= e((string)$d['policy']) ?></span><?php endif; ?>
        <?php if (!empty($d['fix'])): ?><div class="fix"><?= e((string)$d['fix']) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div><b><?= e((string)$dns['domain']) ?></b><span class="small muted">checked <?= e($when((string)$dns['checked_at'])) ?></span>
      <form method="post" style="margin-top:.3rem"><?= Csrf::field() ?><input type="hidden" name="action" value="refresh_dns"><button class="linkbtn" type="submit">Re-check</button></form></div>
  </div>
  <?php endif; ?>

  <?php foreach (Mailer::identities() as $i): $h = Connections::hint($i['id'] === 'default' ? 'smtp_pass' : 'mail_identity_' . $i['id'] . '_pass', 'mail_send', 'password:' . $i['id']); ?>
  <form method="post" class="stack" style="padding:.7rem 0;border-top:1px solid var(--rule)">
    <?= Csrf::field() ?><input type="hidden" name="action" value="save_identity"><input type="hidden" name="id" value="<?= e($i['id']) ?>">
    <div class="row" style="justify-content:space-between;flex-wrap:wrap;gap:.5rem">
      <b><?= e($i['label'] ?: $i['email']) ?></b>
      <span class="small muted">sends as <?= e($i['name']) ?> &lt;<?= e($i['email']) ?>&gt; · used for <?= e(implode(', ', $i['use']) ?: 'nothing yet') ?></span>
    </div>
    <div class="row" style="gap:.6rem;flex-wrap:wrap">
      <div class="field" style="flex:1 1 150px"><label>Label</label><input name="label" value="<?= e($i['label']) ?>" placeholder="Company mailbox"></div>
      <div class="field" style="flex:1 1 150px"><label>Sender name</label><input name="name" value="<?= e($i['name']) ?>" placeholder="Saurabh"></div>
      <div class="field" style="flex:1 1 200px"><label>Mailbox address</label><input name="email" type="email" value="<?= e($i['email']) ?>" required></div>
    </div>
    <div class="row" style="gap:.6rem;flex-wrap:wrap">
      <div class="field" style="flex:0 0 190px"><label>Provider</label><select name="preset"><?= $presetOptions('smtp') ?></select></div>
      <div class="field" style="flex:1 1 180px"><label>SMTP server</label><input name="host" value="<?= e($i['host']) ?>" placeholder="smtp.hostinger.com"></div>
      <div class="field" style="flex:0 0 90px"><label>Port</label><input name="port" type="number" min="1" max="65535" value="<?= (int)$i['port'] ?>"></div>
      <div class="field" style="flex:0 0 120px"><label>Encryption</label><select name="secure">
        <?php foreach (['ssl' => 'SSL (465)', 'tls' => 'STARTTLS (587)', 'none' => 'None'] as $k => $v): ?><option value="<?= $k ?>"<?= $i['secure'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
      <div class="field" style="flex:1 1 200px"><label>Login (usually the address)</label><input name="user" value="<?= e($i['user']) ?>"></div>
    </div>
    <div class="row" style="gap:.6rem;flex-wrap:wrap;align-items:flex-end">
      <div class="field" style="flex:1 1 220px"><label>Mailbox password</label>
        <input name="password" type="password" autocomplete="new-password" placeholder="<?= $h['set'] ? 'saved ' . e($h['mask']) . ' — leave blank to keep' : 'paste it' ?>">
        <p class="hint"><?= $h['set'] ? 'Set ' . e($h['at'] ? local_time($h['at'], 'j M Y') : '') . ($h['by'] ? ' by ' . e($h['by']) : '') . '. Type a new one to replace it.' : 'Write-only: never shown after saving.' ?></p></div>
      <div class="field" style="flex:1 1 260px"><label>Use this mailbox for</label>
        <div class="row" style="gap:.8rem;flex-wrap:wrap">
          <?php foreach (['system' => 'system notices', 'funnel' => 'follow-up messages', 'manual' => 'replies you send'] as $k => $v): ?>
            <label style="text-transform:none;font-family:var(--font);font-size:13px;letter-spacing:0;display:flex;gap:.3rem;align-items:center"><input type="checkbox" name="use[]" value="<?= $k ?>" style="width:auto"<?= in_array($k, $i['use'], true) ? ' checked' : '' ?>> <?= $v ?></label>
          <?php endforeach; ?></div></div>
    </div>
    <div class="row" style="gap:.4rem;flex-wrap:wrap">
      <button class="btn btn--sm" type="submit">Save</button>
      <button class="btn btn--ghost btn--sm" type="submit" name="action" value="test_identity">Send me a test</button>
      <?php if ($i['id'] !== 'default'): ?><button class="linkbtn" type="submit" name="action" value="remove_identity" onclick="return confirm('Remove this mailbox?')">Remove</button><?php endif; ?>
    </div>
  </form>
  <?php endforeach; ?>

  <details style="margin-top:.6rem"><summary>Add another mailbox (for example a no-reply@ for system notices)</summary>
  <form method="post" class="stack" style="padding:.7rem .8rem">
    <?= Csrf::field() ?><input type="hidden" name="action" value="save_identity">
    <div class="row" style="gap:.6rem;flex-wrap:wrap">
      <div class="field" style="flex:0 0 140px"><label>Short id</label><input name="id" placeholder="noreply" pattern="[a-z0-9_-]+" required></div>
      <div class="field" style="flex:1 1 150px"><label>Label</label><input name="label" placeholder="System notices"></div>
      <div class="field" style="flex:1 1 150px"><label>Sender name</label><input name="name" placeholder="Wwwebtech"></div>
      <div class="field" style="flex:1 1 200px"><label>Mailbox address</label><input name="email" type="email" required></div>
    </div>
    <div class="row" style="gap:.6rem;flex-wrap:wrap">
      <div class="field" style="flex:0 0 190px"><label>Provider</label><select name="preset"><?= $presetOptions('smtp') ?></select></div>
      <div class="field" style="flex:1 1 180px"><label>SMTP server (blank = from provider)</label><input name="host"></div>
      <div class="field" style="flex:0 0 90px"><label>Port</label><input name="port" type="number" value="465"></div>
      <div class="field" style="flex:0 0 120px"><label>Encryption</label><select name="secure"><option value="ssl">SSL (465)</option><option value="tls">STARTTLS (587)</option><option value="none">None</option></select></div>
      <div class="field" style="flex:1 1 200px"><label>Mailbox password</label><input name="password" type="password" autocomplete="new-password" required></div>
    </div>
    <div class="row" style="gap:.8rem;flex-wrap:wrap"><?php foreach (['system' => 'system notices', 'funnel' => 'follow-up messages', 'manual' => 'replies you send'] as $k => $v): ?>
      <label style="text-transform:none;font-family:var(--font);font-size:13px;letter-spacing:0;display:flex;gap:.3rem;align-items:center"><input type="checkbox" name="use[]" value="<?= $k ?>" style="width:auto"> <?= $v ?></label><?php endforeach; ?></div>
    <div><button class="btn btn--sm" type="submit">Add and test</button></div>
  </form></details>
<?php $cardEnd('mail_send'); ?>

<?php /* ═══════════════ Email — reading ═══════════════ */ $cardTop('mail_read'); $h = Connections::hint('imap_pass', 'mail_read', 'password'); ?>
  <form method="post" class="stack">
    <?= Csrf::field() ?><input type="hidden" name="action" value="save_mail_read">
    <div class="row" style="gap:.6rem;flex-wrap:wrap">
      <div class="field" style="flex:0 0 190px"><label>Provider</label><select name="preset"><?= $presetOptions('imap') ?></select></div>
      <div class="field" style="flex:1 1 180px"><label>IMAP server</label><input name="host" value="<?= e(Inbox::user() !== '' ? Inbox::host() : '') ?>" placeholder="imap.hostinger.com"></div>
      <div class="field" style="flex:0 0 90px"><label>Port</label><input name="port" type="number" value="<?= Inbox::port() ?>"></div>
      <div class="field" style="flex:0 0 120px"><label>Encryption</label><select name="secure">
        <?php foreach (['ssl' => 'SSL (993)', 'tls' => 'STARTTLS (143)', 'none' => 'None'] as $k => $v): ?><option value="<?= $k ?>"<?= Inbox::secure() === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
      <div class="field" style="flex:0 0 120px"><label>Folder</label><input name="folder" value="<?= e(Inbox::folder()) ?>"></div>
    </div>
    <div class="row" style="gap:.6rem;flex-wrap:wrap;align-items:flex-end">
      <div class="field" style="flex:1 1 220px"><label>Mailbox</label><input name="user" type="email" value="<?= e(Inbox::user()) ?>" placeholder="info@wwwebtech.in"></div>
      <div class="field" style="flex:1 1 220px"><label>Password</label>
        <input name="password" type="password" autocomplete="new-password" placeholder="<?= $h['set'] ? 'saved ' . e($h['mask']) . ' — leave blank to keep' : 'paste it' ?>">
        <p class="hint"><?= $h['set'] ? 'Set ' . e($h['at'] ? local_time($h['at'], 'j M Y') : '') . ($h['by'] ? ' by ' . e($h['by']) : '') : 'Write-only: never shown after saving.' ?></p></div>
      <div class="field" style="flex:0 0 auto"><label>Checked every</label><input value="5 minutes (cron)" readonly style="background:var(--wash)"></div>
    </div>
    <p class="small muted" style="margin:0">Reader watermark: <?= Settings::int('imap_last_uid', 0) > 0 ? 'last read message #' . Settings::int('imap_last_uid') : 'nothing read yet' ?>.
      <?php $ip = DB::one("SELECT last_start, last_error FROM wwt_task_runs WHERE task = 'inbox_poll'"); if ($ip): ?>Last poll <?= e($when((string)$ip['last_start'])) ?><?= $ip['last_error'] ? ' — ' . e((string)$ip['last_error']) : '' ?>.<?php endif; ?></p>
    <div class="row" style="gap:.4rem"><button class="btn btn--sm" type="submit">Save</button>
      <button class="btn btn--ghost btn--sm" type="submit" name="action" value="test_mail_read">Check the mailbox</button></div>
  </form>
  <div style="margin-top:.6rem"><?php $secretRow('mail_read', 'imap_pass', 'password', 'Password'); ?></div>
<?php $cardEnd('mail_read'); ?>

<?php /* ═══════════════ Alert recipients ═══════════════ */ $cardTop('recipients'); ?>
  <div class="tablewrap" style="border:0"><table>
    <thead><tr><th>Address</th><th>Receives</th><th></th></tr></thead><tbody>
    <?php foreach (Notify::recipients() as $r): ?>
      <tr><td><b><?= e($r['email']) ?></b><?= $r['label'] ? '<div class="small muted">' . e($r['label']) . '</div>' : '' ?></td>
        <td class="small"><?= e(implode(' · ', array_map(static fn($k) => Notify::ROLES[$k] ?? $k, $r['roles']))) ?></td>
        <td class="small"><form method="post" style="display:inline"><?= Csrf::field() ?><input type="hidden" name="action" value="test_recipient"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="linkbtn" type="submit">Send test</button></form>
          · <form method="post" style="display:inline"><?= Csrf::field() ?><input type="hidden" name="action" value="remove_recipient"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="linkbtn" type="submit">Remove</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <form method="post" class="row" style="gap:.6rem;flex-wrap:wrap;align-items:flex-end;margin-top:.7rem">
    <?= Csrf::field() ?><input type="hidden" name="action" value="add_recipient">
    <div class="field" style="flex:1 1 220px"><label>Email</label><input name="email" type="email" required></div>
    <div class="field" style="flex:1 1 140px"><label>Label</label><input name="label" placeholder="My phone"></div>
    <div class="field" style="flex:2 1 300px"><label>Receives</label><div class="row" style="gap:.7rem;flex-wrap:wrap">
      <?php foreach (Notify::ROLES as $k => $v): ?><label style="text-transform:none;font-family:var(--font);font-size:13px;letter-spacing:0;display:flex;gap:.3rem;align-items:center"><input type="checkbox" name="roles[]" value="<?= $k ?>" style="width:auto"<?= $k === 'every_lead' ? ' checked' : '' ?>> <?= e($v) ?></label><?php endforeach; ?></div></div>
    <button class="btn btn--sm" type="submit">Add</button>
  </form>
<?php $cardEnd('recipients'); ?>

<?php /* ═══════════════ Telegram ═══════════════ */ $cardTop('telegram'); $h = Connections::hint('telegram_token', 'telegram', 'token'); ?>
  <form method="post" class="row" style="gap:.6rem;flex-wrap:wrap;align-items:flex-end">
    <?= Csrf::field() ?><input type="hidden" name="action" value="save_telegram">
    <div class="field" style="flex:1 1 320px"><label>Bot token</label>
      <input name="token" type="password" autocomplete="off" placeholder="<?= $h['set'] ? 'saved ' . e($h['mask']) . ' — paste a new one to replace' : '123456789:AAH…' ?>" pattern="\d{8,12}:[A-Za-z0-9_-]{35}">
      <p class="hint">Looks like 123456789:AAH… — the whole line BotFather sent.</p></div>
    <button class="btn btn--sm" type="submit"><?= $h['set'] ? 'Replace token' : 'Save token' ?></button>
  </form>
  <div style="margin:.4rem 0 .8rem"><?php $secretRow('telegram', 'telegram_token', 'token', 'Token'); ?></div>

  <?php if ($h['set']): ?>
  <div class="row" style="gap:.4rem;flex-wrap:wrap">
    <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="detect_chats"><button class="btn btn--sm" type="submit">Detect my chat</button></form>
    <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="test_telegram"><button class="btn btn--ghost btn--sm" type="submit">Send a test</button></form>
  </div>
  <?php if (is_array($chats)): ?>
    <div class="alert alert--info" style="margin-top:.7rem">
      <?php if (!$chats): ?>Nobody has messaged the bot in the last day. Open it in Telegram, press <b>Start</b>, then Detect again.
      <?php else: ?><b>Who has messaged the bot:</b>
        <?php foreach ($chats as $c): ?>
          <form method="post" class="row" style="gap:.6rem;align-items:center;margin-top:.45rem;flex-wrap:wrap"><?= Csrf::field() ?>
            <input type="hidden" name="action" value="add_chat"><input type="hidden" name="chat_id" value="<?= e($c['id']) ?>"><input type="hidden" name="title" value="<?= e($c['title']) ?>"><input type="hidden" name="kind" value="<?= e($c['kind']) ?>">
            <span><b><?= e($c['title']) ?></b> <span class="muted small">(<?= e($c['kind']) ?><?= str_starts_with($c['id'], '-') ? ', a group id is negative — that is normal' : '' ?>)</span></span>
            <select name="roles[]" style="width:auto"><option value="all">all alerts</option><option value="hot">hot leads only</option><option value="approvals">approval requests only</option></select>
            <button class="btn btn--sm" type="submit">Use this</button></form>
        <?php endforeach; ?>
      <?php endif; ?></div>
  <?php endif; ?>
  <?php $tr = Telegram::recipients(); if ($tr): ?>
    <div class="tablewrap" style="border:0;margin-top:.7rem"><table><thead><tr><th>Chat</th><th>Receives</th><th></th></tr></thead><tbody>
      <?php foreach ($tr as $r): ?><tr><td><b><?= e($r['title']) ?></b> <span class="muted small"><?= e($r['kind']) ?> · <code><?= e($r['chat_id']) ?></code></span></td>
        <td class="small"><?= e(implode(' · ', array_map(static fn($k) => ['all' => 'all alerts', 'hot' => 'hot leads only', 'approvals' => 'approval requests'][$k] ?? $k, $r['roles']))) ?></td>
        <td class="small"><form method="post" style="display:inline"><?= Csrf::field() ?><input type="hidden" name="action" value="remove_chat"><input type="hidden" name="chat_id" value="<?= e($r['chat_id']) ?>"><button class="linkbtn" type="submit">Remove</button></form></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <?php endif; endif; ?>
<?php $cardEnd('telegram'); ?>

<?php /* ═══════════════ WhatsApp ═══════════════ */ $cardTop('whatsapp'); $st = Connections::status('whatsapp');
  $ht = Connections::hint('wa_token', 'whatsapp', 'wa_token'); $hs = Connections::hint('wa_app_secret', 'whatsapp', 'wa_app_secret');
  $spent = WhatsApp::spentPaiseThisMonth(); $cap = WhatsApp::capPaise(); ?>
  <div class="row" style="gap:1.2rem;flex-wrap:wrap;margin-bottom:.6rem">
    <div><span class="small muted">This month</span><div><b>₹<?= number_format($spent / 100, 2) ?></b> of ₹<?= number_format($cap / 100, 0) ?></div>
      <div class="meter" style="width:180px"><i class="<?= $cap > 0 && $spent >= $cap ? 'full' : '' ?>" style="width:<?= $cap > 0 ? min(100, (int)round(100 * $spent / $cap)) : 0 ?>%"></i></div></div>
    <div><span class="small muted">Switch</span><div>
      <form method="post" style="display:inline"><?= Csrf::field() ?><input type="hidden" name="action" value="toggle_whatsapp"><input type="hidden" name="on" value="<?= WhatsApp::enabled() ? '0' : '1' ?>">
        <button class="btn btn--sm <?= WhatsApp::enabled() ? '' : 'btn--ghost' ?>" type="submit"<?= (!$st['passed_once'] && !WhatsApp::enabled()) ? ' disabled title="Unlocks after one passing test"' : '' ?>><?= WhatsApp::enabled() ? 'On — switch off' : 'Off — switch on' ?></button></form>
      <?php if (!$st['passed_once']): ?><span class="small muted">unlocks after one passing test</span><?php endif; ?></div></div>
    <div><span class="small muted">Webhook</span><div><?php $wv = (string)Settings::get('wa_webhook_verified_at', ''); ?>
      <?= $wv ? '<span class="pill pill--ok">verified ' . e($when($wv)) . '</span>' : '<span class="pill pill--warn">waiting for Meta…</span>' ?></div></div>
  </div>

  <form method="post" class="stack">
    <?= Csrf::field() ?><input type="hidden" name="action" value="save_whatsapp">
    <div class="row" style="gap:.6rem;flex-wrap:wrap">
      <div class="field" style="flex:1 1 200px"><label>Business verification</label><select name="business_status">
        <?php foreach (['not_started' => 'Not started', 'submitted' => 'Submitted, waiting', 'verified' => 'Verified'] as $k => $v): ?><option value="<?= $k ?>"<?= WhatsApp::businessStatus() === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
        <p class="hint">Step 1. The switch stays off until this says Verified.</p></div>
      <div class="field" style="flex:1 1 200px"><label>Sender's phone number (for links)</label><input name="display_number" value="<?= e(WhatsApp::displayNumber()) ?>" placeholder="+91 …"></div>
      <div class="field" style="flex:0 0 110px"><label>API version</label><input name="api_version" value="<?= e(WhatsApp::apiVersion()) ?>"></div>
    </div>
    <div class="row" style="gap:.6rem;flex-wrap:wrap">
      <div class="field" style="flex:1 1 200px"><label>WhatsApp Business Account ID</label><input name="waba_id" value="<?= e(WhatsApp::wabaId()) ?>" inputmode="numeric" placeholder="15–16 digits"><p class="hint">Step 5 · Business Settings → WhatsApp accounts</p></div>
      <div class="field" style="flex:1 1 200px"><label>Phone number ID</label><input name="phone_id" value="<?= e(WhatsApp::phoneId()) ?>" inputmode="numeric" placeholder="15–16 digits"><p class="hint">Step 5 · not the phone number itself</p></div>
      <div class="field" style="flex:1 1 200px"><label>App ID</label><input name="app_id" value="<?= e(WhatsApp::appId()) ?>" inputmode="numeric" placeholder="15–16 digits"><p class="hint">Step 3 · developers.facebook.com → Settings → Basic</p></div>
      <div class="field" style="flex:1 1 200px"><label>Business ID (optional)</label><input name="business_id" value="<?= e((string)Settings::get('wa_business_id', '')) ?>" inputmode="numeric"><p class="hint">Lets the panel read the verification status itself.</p></div>
    </div>
    <div class="row" style="gap:.6rem;flex-wrap:wrap">
      <div class="field" style="flex:2 1 320px"><label>Permanent access token</label>
        <input name="token" type="password" autocomplete="off" placeholder="<?= $ht['set'] ? 'saved ' . e($ht['mask']) . ' — paste a new one to replace' : 'EAAG… (about 200 characters)' ?>">
        <p class="hint">Step 4 · a System User token, never the 24-hour one from the Explorer.</p></div>
      <div class="field" style="flex:1 1 220px"><label>App Secret</label>
        <input name="app_secret" type="password" autocomplete="off" placeholder="<?= $hs['set'] ? 'saved ' . e($hs['mask']) . ' — paste to replace' : '32 letters and digits' ?>">
        <p class="hint">Step 3 · proves incoming webhooks are really from Meta.</p></div>
      <div class="field" style="flex:0 0 130px"><label>Monthly cap (₹)</label><input name="cap" type="number" min="0" max="100000" value="<?= (int)round($cap / 100) ?>"></div>
      <div class="field" style="flex:0 0 90px"><label>Language</label><input name="lang" value="<?= e((string)Settings::get('wa_lang', 'en')) ?>"></div>
    </div>
    <div class="row" style="gap:.4rem;flex-wrap:wrap;align-items:center">
      <button class="btn btn--sm" type="submit">Save</button>
      <button class="btn btn--ghost btn--sm" type="submit" name="action" value="test_whatsapp">Test</button>
      <span class="small muted">optionally send a test template to</span><input name="send_to" placeholder="+91 …" style="width:150px">
    </div>
  </form>
  <div class="stack" style="gap:.3rem;margin-top:.5rem"><?php $secretRow('whatsapp', 'wa_token', 'wa_token', 'Access token'); $secretRow('whatsapp', 'wa_app_secret', 'wa_app_secret', 'App Secret'); ?></div>

  <h3 style="margin-top:1rem">Webhook — paste these into Meta (step 6)</h3>
  <div class="row" style="gap:.6rem;flex-wrap:wrap">
    <div style="flex:1 1 300px"><span class="small muted">Callback URL</span><div class="copy"><?= e(rtrim((string)cfg('site.url', ''), '/') . '/api/whatsapp-webhook.php') ?></div></div>
    <div style="flex:1 1 300px"><span class="small muted">Verify token</span><div class="copy"><?= e(WhatsApp::verifyToken()) ?></div>
      <form method="post" style="margin-top:.3rem"><?= Csrf::field() ?><input type="hidden" name="action" value="rotate_verify_token"><button class="linkbtn" type="submit" onclick="return confirm('Meta will need the new token pasted again. Continue?')">Generate a new one</button></form></div>
  </div>
  <p class="small muted">Subscribe the <code>messages</code> field. Messages from customers then land on their thread in Conversations and stop their sequence, exactly as an email reply does.</p>

  <h3 style="margin-top:1rem">Templates <span class="small muted">(step 7)</span></h3>
  <?php $tpl = WhatsApp::templates(); $syncedAt = (string)Settings::get('wa_templates_synced_at', ''); ?>
  <div class="row" style="gap:.6rem;align-items:center;flex-wrap:wrap">
    <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="sync_templates"><button class="btn btn--ghost btn--sm" type="submit"<?= WhatsApp::wabaId() === '' ? ' disabled' : '' ?>>Sync from Meta</button></form>
    <span class="small muted"><?= $syncedAt ? 'last synced ' . e($when($syncedAt)) : 'never synced' ?> · only approved utility templates can be sent automatically</span>
  </div>
  <?php if ($tpl): ?>
  <div class="tablewrap" style="border:0;margin-top:.5rem"><table><thead><tr><th>Name</th><th>Language</th><th>Category</th><th>Meta status</th><th>Synced</th></tr></thead><tbody>
    <?php foreach ($tpl as $t): $okRow = $t['approval'] === 'approved' && $t['category'] === 'utility'; ?>
      <tr><td><b><?= e((string)($t['meta_name'] ?: $t['key_name'])) ?></b></td><td class="small"><?= e((string)$t['language']) ?></td>
        <td class="small"><span class="pill pill--<?= $t['category'] === 'utility' ? 'ok' : 'warn' ?>"><?= e((string)$t['category']) ?></span></td>
        <td class="small"><span class="pill pill--<?= $t['approval'] === 'approved' ? 'ok' : ($t['approval'] === 'pending' ? 'warn' : 'fail') ?>"><?= e((string)($t['meta_status'] ?: $t['approval'])) ?></span><?= $okRow ? '' : ' <span class="muted">not sendable</span>' ?></td>
        <td class="small muted"><?= $t['synced_at'] ? e($when((string)$t['synced_at'])) : 'local only' ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
<?php $cardEnd('whatsapp'); ?>

<?php /* ═══════════════ Claude ═══════════════ */ $cardTop('claude'); $h = Connections::hint('anthropic_key', 'claude', 'key');
  $spent = Claude::spentThisMonth(); $capUsd = Claude::monthlyCap(); ?>
  <div class="row" style="gap:1.2rem;flex-wrap:wrap;margin-bottom:.6rem">
    <div><span class="small muted">This month</span><div><b>$<?= number_format($spent, 2) ?></b> of $<?= number_format($capUsd, 0) ?></div>
      <div class="meter" style="width:180px"><i class="<?= $capUsd > 0 && $spent >= $capUsd ? 'full' : '' ?>" style="width:<?= $capUsd > 0 ? min(100, (int)round(100 * $spent / $capUsd)) : 0 ?>%"></i></div></div>
  </div>
  <form method="post" class="stack">
    <?= Csrf::field() ?><input type="hidden" name="action" value="save_claude">
    <div class="row" style="gap:.6rem;flex-wrap:wrap;align-items:flex-end">
      <div class="field" style="flex:2 1 300px"><label>API key</label>
        <input name="key" type="password" autocomplete="off" placeholder="<?= $h['set'] ? 'saved ' . e($h['mask']) . ' — paste a new one to replace' : 'sk-ant-…' ?>"></div>
      <div class="field" style="flex:1 1 200px"><label>Model</label><select name="model">
        <?php foreach (Claude::MODELS as $k => $m): ?><option value="<?= e($k) ?>"<?= Claude::model() === $k ? ' selected' : '' ?>><?= e($m['label']) ?></option><?php endforeach; ?></select>
        <p class="hint">Names change — check docs.claude.com.</p></div>
      <div class="field" style="flex:0 0 130px"><label>Monthly cap (USD)</label><input name="cap" type="number" min="0" max="500" step="1" value="<?= e((string)$capUsd) ?>"><p class="hint">Set one in the Anthropic console too.</p></div>
    </div>
    <div class="row" style="gap:.4rem"><button class="btn btn--sm" type="submit">Save</button>
      <button class="btn btn--ghost btn--sm" type="submit" name="action" value="test_claude">Test</button><span class="small muted">costs a fraction of a cent</span></div>
  </form>
  <div style="margin-top:.5rem"><?php $secretRow('claude', 'anthropic_key', 'key', 'API key'); ?></div>
<?php $cardEnd('claude'); ?>

<?php /* ═══════════════ PageSpeed ═══════════════ */ $cardTop('pagespeed'); $h = Connections::hint('pagespeed_key', 'pagespeed', 'key'); ?>
  <form method="post" class="row" style="gap:.6rem;flex-wrap:wrap;align-items:flex-end">
    <?= Csrf::field() ?><input type="hidden" name="action" value="save_pagespeed">
    <div class="field" style="flex:1 1 320px"><label>API key</label>
      <input name="key" type="password" autocomplete="off" placeholder="<?= $h['set'] ? 'saved ' . e($h['mask']) . ' — paste a new one to replace' : 'AIza… (39 characters)' ?>"></div>
    <button class="btn btn--sm" type="submit">Save</button>
    <button class="btn btn--ghost btn--sm" type="submit" name="action" value="test_pagespeed">Test</button>
  </form>
  <div style="margin-top:.5rem"><?php $secretRow('pagespeed', 'pagespeed_key', 'key', 'API key'); ?></div>
<?php $cardEnd('pagespeed'); ?>

<?php /* ═══════════════ Keys ═══════════════ */ $cardTop('keys'); ?>
  <div class="stack" style="gap:.5rem">
    <div class="row" style="gap:.6rem;align-items:center;flex-wrap:wrap"><?php $secretRow('keys', 'conversions_key', 'conversions', 'Conversions feed key'); ?>
      <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="rotate_conversions_key"><button class="btn btn--ghost btn--sm" type="submit" onclick="return confirm('The URLs Google and Microsoft fetch stop working immediately. Continue?')">Rotate</button></form>
      <a class="small" href="/admin/?p=integrations" style="text-decoration:underline">the URLs are on the Integrations page</a></div>
    <div class="row" style="gap:.6rem;align-items:center;flex-wrap:wrap"><?php $secretRow('keys', 'cron_key', 'test', 'Test-submission key'); ?>
      <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="rotate_test_key"><button class="btn btn--ghost btn--sm" type="submit">Rotate</button></form></div>
    <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="test_keys"><button class="btn btn--ghost btn--sm" type="submit">Check both</button></form>
  </div>
  <h3 style="margin-top:1rem">The scheduled jobs, for reference</h3>
  <p class="small muted">These are what hPanel → Advanced → Cron Jobs should contain. They contain no key and never change.</p>
  <pre class="copy" style="white-space:pre-wrap">/usr/bin/php /home/&lt;your-account&gt;/wwt_private/cron/run.php frequent    every 5 minutes
/usr/bin/php /home/&lt;your-account&gt;/wwt_private/cron/run.php hourly      once an hour
/usr/bin/php /home/&lt;your-account&gt;/wwt_private/cron/run.php daily       02:30
/usr/bin/php /home/&lt;your-account&gt;/wwt_private/cron/run.php weekly      Monday 03:30</pre>
<?php $cardEnd('keys'); ?>

<?php layout_bottom();
