#!/usr/bin/env php
<?php
/* ============================================================
   connections_test.php — the parts of the hub that must not drift.

   What a raw error is translated into, what a secret hint may reveal,
   whether an unsigned webhook is refused, how a message ID that arrives
   twice is treated, and whether the DNS strip tells the truth about the
   real domain. The HTTP gate proves the page; this proves the rules.

     php automation/tests/connections_test.php
   ============================================================ */

declare(strict_types=1);
require_once dirname(__DIR__) . '/private/bootstrap.php';

$pass = 0; $fail = 0;
function grp(string $t): void { echo "\n\033[1m── $t\033[0m\n"; }
function ok(string $w): void { global $pass; $pass++; printf("  \033[32mPASS\033[0m  %s\n", $w); }
function no(string $w, string $why): void { global $fail; $fail++; printf("  \033[31mFAIL\033[0m  %s — %s\n", $w, $why); }
function is_same(string $w, mixed $got, mixed $want): void { $got === $want ? ok($w) : no($w, sprintf('got %s, want %s', var_export($got, true), var_export($want, true))); }
function is_true(string $w, mixed $v): void { $v ? ok($w) : no($w, 'got false'); }
function is_false(string $w, mixed $v): void { !$v ? ok($w) : no($w, 'got true'); }
function has(string $w, string $hay, string $needle): void { str_contains($hay, $needle) ? ok($w) : no($w, "missing '$needle' in: " . substr($hay, 0, 100)); }
function hasnt(string $w, string $hay, string $needle): void { !str_contains($hay, $needle) ? ok($w) : no($w, "found '$needle'"); }

/* ── Masking ─────────────────────────────────────────────── */
grp('What a hint may reveal');
is_same('long secret shows dots and the last four', Secrets::maskLast4('sk-ant-api03-abcdefghijklmnop7Fk2'), '••••••••7Fk2');
is_same('a short secret shows nothing but dots', Secrets::maskLast4('abc123'), '••••••••');
is_same('an empty value is empty', Secrets::maskLast4(''), '');

/* ── Paste-time validation ───────────────────────────────── */
grp('Formats');
is_same('a real-shaped bot token passes', Connections::validate('telegram_token', '1234567890:AAHabcdefghijklmnopqrstuvwxyz123456'), '');
is_true('a token missing the colon is refused', Connections::validate('telegram_token', '1234567890AAHabcdefghijklmnopqrstuvwxyz123456') !== '');
is_same('an Anthropic key passes', Connections::validate('anthropic_key', 'sk-ant-api03-' . str_repeat('x', 40)), '');
is_true('a Meta explorer-style short token is refused', Connections::validate('wa_token', 'EAAshort') !== '');
is_same('a Google key passes', Connections::validate('pagespeed_key', 'AIza' . str_repeat('A', 35)), '');
is_true('a phone number is not a Phone number ID', Connections::validate('wa_phone_id', '+91 98765 43210') !== '');

/* ── Plain English ───────────────────────────────────────── */
grp('Raw errors become instructions');
$t = Connections::translate('telegram', 'Unauthorized');
has('a bad bot token says what to do', $t['text'], 'BotFather'); is_same('and points at step 1', $t['step'], 1);
$t = Connections::translate('telegram', 'Bad Request: chat not found');
has('chat not found explains Start', $t['text'], 'press Start'); is_same('and points at step 3', $t['step'], 3);
$t = Connections::translate('whatsapp', '(#190) Error validating access token: Session has expired');
has('an expired Meta token names the System User token', $t['text'], 'System User'); is_same('step 4', $t['step'], 4);
$t = Connections::translate('whatsapp', '(#10) Application does not have permission for this action');
has('a permission error names the two permissions', $t['text'], 'whatsapp_business_management');
$t = Connections::translate('mail_send', 'SMTP Error: Could not authenticate.');
has('a refused password says where to reset it', $t['text'], 'hPanel');
$t = Connections::translate('mail_read', 'Can not authenticate to IMAP server: [AUTHENTICATIONFAILED] Authentication failed.');
has('an IMAP auth failure is the same instruction', $t['text'], 'password');
$t = Connections::translate('claude', 'authentication_error: invalid x-api-key');
has('a rejected Anthropic key names the console', $t['text'], 'console.anthropic.com');
$t = Connections::translate('pagespeed', 'API key not valid. Please pass a valid API key.');
has('a rejected Google key names Credentials', $t['text'], 'Credentials');
$t = Connections::translate('claude', 'Something entirely novel happened at https://example.com/x HTTP 503');
hasnt('an unknown error drops the URL', $t['text'], 'https://'); hasnt('and the status code', $t['text'], '503');

/* ── Webhook signature ───────────────────────────────────── */
grp('WhatsApp webhook signatures');
$prevSecret = Secrets::get('wa_app_secret', '');
Secrets::put('wa_app_secret', 'deadbeefdeadbeefdeadbeefdeadbeef');
$body = '{"object":"whatsapp_business_account","entry":[]}';
$good = 'sha256=' . hash_hmac('sha256', $body, 'deadbeefdeadbeefdeadbeefdeadbeef');
is_true('a correctly signed body is accepted', WhatsApp::verifySignature($body, $good));
is_false('an unsigned body is refused', WhatsApp::verifySignature($body, ''));
is_false('a wrong signature is refused', WhatsApp::verifySignature($body, 'sha256=' . str_repeat('0', 64)));
is_false('a tampered body is refused', WhatsApp::verifySignature($body . ' ', $good));
Secrets::put('wa_app_secret', '');
is_false('with no app secret nothing is accepted', WhatsApp::verifySignature($body, $good));
Secrets::put('wa_app_secret', $prevSecret);

/* ── Inbound → thread → stop ─────────────────────────────── */
grp('A WhatsApp reply stops the sequence');
$id = DB::insert("INSERT INTO wwt_leads (ts,name,email,phone,service,budget,message,page,consent_at,consent_text,status)
                  VALUES (UTC_TIMESTAMP(),'Wa Test','wa-test-" . bin2hex(random_bytes(3)) . "@example.com','+91 98765 00042','SEO','₹5L+','m','/lp/seo/',UTC_TIMESTAMP(),'t','new')");
Funnel::enrol($id, 'standard');
$payload = ['entry' => [['changes' => [['value' => ['messages' => [
    ['from' => '919876500042', 'id' => 'wamid.TEST' . bin2hex(random_bytes(4)), 'type' => 'text', 'text' => ['body' => 'Yes, call me tomorrow']],
]]]]]]];
$r = WhatsApp::handleInbound($payload);
is_same('one message recorded', $r['messages'], 1);
is_same('the sequence stopped', (string)DB::val('SELECT status FROM wwt_sequence_enrollments WHERE lead_id = ?', [$id]), 'stopped');
is_same('it is on the thread as an inbound WhatsApp message',
    (int)DB::val("SELECT COUNT(*) FROM wwt_messages WHERE lead_id = ? AND direction = 'in' AND channel = 'whatsapp'", [$id], 0), 1);
$r2 = WhatsApp::handleInbound($payload);
is_same('the same message id a second time is ignored', $r2['messages'], 0);
$r3 = WhatsApp::handleInbound(['entry' => [['changes' => [['value' => ['messages' => [
    ['from' => '919999999999', 'id' => 'wamid.UNKNOWN' . bin2hex(random_bytes(4)), 'type' => 'text', 'text' => ['body' => 'hi']]]]]]]]]);
is_same('an unknown number creates nothing', $r3['unknown'], 1);
$stopId = DB::insert("INSERT INTO wwt_leads (ts,name,email,phone,service,budget,message,page,consent_at,consent_text,status)
                      VALUES (UTC_TIMESTAMP(),'Wa Stop','wa-stop-" . bin2hex(random_bytes(3)) . "@example.com','9876500043','SEO','','m','/',UTC_TIMESTAMP(),'t','new')");
WhatsApp::handleInbound(['entry' => [['changes' => [['value' => ['messages' => [
    ['from' => '919876500043', 'id' => 'wamid.STOP' . bin2hex(random_bytes(4)), 'type' => 'text', 'text' => ['body' => 'STOP']]]]]]]]]);
is_same('"STOP" on WhatsApp opts the person out', (int)DB::val('SELECT do_not_contact FROM wwt_leads WHERE id = ?', [$stopId]), 1);
foreach ([$id, $stopId] as $i) {
    foreach (['wwt_messages', 'wwt_sequence_enrollments', 'wwt_lead_events'] as $t) DB::run("DELETE FROM $t WHERE lead_id = ?", [$i]);
    DB::run('DELETE FROM wwt_leads WHERE id = ?', [$i]);
}

/* ── Status model ────────────────────────────────────────── */
grp('Status rules');
$prevTok = Secrets::get('telegram_token', '');
Secrets::put('telegram_token', '');
is_same('no token → not configured', Connections::status('telegram')['state'], 'unconfigured');
Secrets::put('telegram_token', '1234567890:AAHabcdefghijklmnopqrstuvwxyz123456');
Settings::set('conn_status_telegram', '');
is_same('token but never tested → untested', Connections::status('telegram')['state'], 'untested');
Connections::setStatus('telegram', 'error', 'Telegram rejected this token.', [['Token accepted', false, 'Unauthorized']], 1);
$st = Connections::status('telegram');
is_same('a failed test → error', $st['state'], 'error'); is_same('with its fix step', $st['step'], 1);
is_false('the toggle stays locked', $st['passed_once']);
Connections::setStatus('telegram', 'connected');
is_true('a passing test unlocks the toggle', Connections::status('telegram')['passed_once']);
is_same('the nav dot follows the worst card', Settings::get('conn_attention'), '0');
Connections::setStatus('telegram', 'error', 'x');
is_same('and lights when something is red', Settings::get('conn_attention'), '1');
has('the summary names the failing card', Connections::summary()['line'], 'Telegram');
Settings::set('conn_status_telegram', ''); Secrets::put('telegram_token', $prevTok); Connections::refreshAttention();

/* ── Pending values ──────────────────────────────────────── */
grp('Held values');
Connections::setPending('claude', ['blog_model' => 'claude-opus-5'], ['anthropic_key' => 'sk-ant-pending-test']);
$p = Connections::pending('claude');
is_true('pending values are stored', $p !== null);
is_true('the secret inside is encrypted', Secrets::isEncrypted((string)$p['secrets']['anthropic_key']));
is_same('and can be read back for a test', Connections::pendingSecret('claude', 'anthropic_key'), 'sk-ant-pending-test');
Connections::discardPending('claude');
is_true('discard clears it', Connections::pending('claude') === null);

/* ── DNS strip against the real domain ───────────────────── */
grp('DNS health of wwwebtech.in');
if (function_exists('dns_get_record')) {
    $prevId = Settings::get('mail_identities', '');
    Mailer::saveIdentities([['id' => 'default', 'label' => 't', 'name' => 'T', 'email' => 'info@wwwebtech.in', 'host' => 'smtp.hostinger.com', 'port' => 465, 'secure' => 'ssl', 'user' => 'info@wwwebtech.in', 'use' => ['system']]]);
    $d = Mailer::dnsHealth(true);
    is_true('SPF found', $d['spf']['ok'] ?? false);
    has('SPF names Hostinger', (string)($d['spf']['record'] ?? ''), 'hostinger');
    is_true('DKIM found at a real selector', $d['dkim']['ok'] ?? false);
    is_same('the selector is Hostinger\'s, not "default"', $d['dkim']['selector'] ?? '', 'hostingermail-a');
    is_true('DMARC found', $d['dmarc']['ok'] ?? false);
    is_same('and its policy is read', $d['dmarc']['policy'] ?? '', 'none');
    has('p=none gets honest advice, not a pass', (string)$d['dmarc']['fix'], 'quarantine');
    Settings::set('mail_identities', (string)$prevId);
} else {
    no('dns_get_record', 'not available here');
}

printf("\n\033[1m%d passed, %d failed\033[0m\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
