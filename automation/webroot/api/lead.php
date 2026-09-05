<?php
/* ============================================================
   api/lead.php — the contact form's one endpoint.

   Order of business, deliberately:
     1. Save the lead.
     2. Then try to email it.

   Not the other way round. SMTP is the part most likely to break
   (expired password, host throttling, DNS), and if it ran first a
   failure would lose the enquiry. Saved first, a mail failure is a
   red badge in the panel next to a lead you still have.

   Answers JSON to fetch() and a readable HTML page to a browser with
   JavaScript switched off. Both paths are exercised by the QA gate.
   ============================================================ */

declare(strict_types=1);

define('WWT_SOFT_BOOT', true);
require dirname(__DIR__) . '/_wwt.php';

/* If the automation layer is not configured yet, an enquiry must still reach
   a person. A bare 500 tells the visitor nothing and loses the message; this
   tells them exactly where to send it. Self-contained on purpose — none of
   the helper functions are loaded on this path. */
if (!wwt_boot(__DIR__, true)) {
    $to   = 'contact@wwwebtech.in';
    $json = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    http_response_code(503);
    header('Retry-After: 3600');
    header('Content-Type: ' . ($json ? 'application/json' : 'text/html') . '; charset=utf-8');
    $msg = 'The form is temporarily unavailable. Please email ' . $to . ' and we will pick it up.';
    echo $json
        ? json_encode(['ok' => false, 'error' => $msg])
        : '<!DOCTYPE html><html lang="en-IN"><head><meta charset="utf-8">'
          . '<meta name="viewport" content="width=device-width,initial-scale=1">'
          . '<meta name="robots" content="noindex"><title>Please email us | Wwwebtech</title>'
          . '<style>body{background:#FAF8F4;color:#4E5450;font:17px/1.6 system-ui,sans-serif;'
          . 'margin:0;min-height:100vh;display:grid;place-items:center;padding:2rem}main{max-width:34rem}'
          . 'h1{font:500 2rem/1.1 Georgia,serif;color:#131614;margin:0 0 1rem}'
          . 'a{color:#A34E00}</style></head><body><main><h1>Please email us instead</h1>'
          . '<p>' . htmlspecialchars($msg, ENT_QUOTES) . '</p>'
          . '<p><a href="mailto:' . htmlspecialchars($to, ENT_QUOTES) . '">'
          . htmlspecialchars($to, ENT_QUOTES) . '</a></p></main></body></html>';
    exit;
}

/* ── What kind of answer does the caller want? ─────────────── */
$accept    = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
$wantsJson = str_contains($accept, 'application/json')
          || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header('Content-Type: ' . ($wantsJson ? 'application/json' : 'text/html') . '; charset=utf-8');

/* ── Same-origin only ──────────────────────────────────────── */
/* A browser sends Origin on cross-site POSTs. We never answer one, and we
   never send an Access-Control-Allow-Origin header, so no other site can
   read a response. Absent Origin (ordinary form post) is fine. */
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && !same_origin($origin)) {
    respond(403, ['ok' => false, 'error' => 'Cross-site posts are not accepted.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'error' => 'Send this form with POST.']);
}

/* ── Honeypot ──────────────────────────────────────────────
   `company` is hidden from people by CSS and has tabindex="-1", so only
   an automated filler reaches it. Report success and drop the message:
   telling a bot it was caught only teaches the next one. */
if (trim((string)($_POST['company'] ?? '')) !== '') {
    wwt_log('lead', 'honeypot', ['ip' => ip_truncate(client_ip())]);
    respond(200, ['ok' => true, 'message' => 'Thanks — we’ll be in touch.']);
}

/* ── Per-IP rate limit ─────────────────────────────────────
   Keyed on an HMAC of the address, not the address itself, so the limiter
   works per visitor without the rate-limit table becoming a log of who
   visited. Truncating instead would put a whole office behind one counter. */
$ipKey = 'lead:' . substr(hash_hmac('sha256', client_ip(), (string)cfg('geo_salt', 'wwt')), 0, 40);
if (!RateLimit::allow($ipKey, Leads::rateMax(), Leads::RATE_WINDOW)) {
    audit('lead_rate_limited', 'ip=' . ip_truncate(client_ip()), 'public');
    respond(429, ['ok' => false, 'error' =>
        'That is a lot of messages in one hour. Please email ' . Mailer::leadRecipient() . '.']);
}

/* A submission flagged as a test is stored and delivered exactly like a real
   one, but tagged so QA traffic can be filtered and purged without ever
   touching a genuine enquiry. Decided here because the partial-lead path
   below needs it too. */
$isTestFlag = wwt_test_key() !== '' && ((string)($_POST['_test'] ?? '')) === wwt_test_key();

/* ── Time trap (§3.4) ──────────────────────────────────────
   A form rendered and submitted inside three seconds was not read by a
   person. Cheaper and less hostile than a CAPTCHA, which costs real
   conversions. Only applied when the form told us when it was rendered. */
$started = (int)($_POST['_started'] ?? 0);
if ($started > 0 && (time() - $started) < 3 && empty($_POST['_partial'])) {
    wwt_log('lead', 'time trap', ['elapsed' => time() - $started]);
    respond(200, ['ok' => true, 'message' => 'Thanks — we’ll be in touch.']);
}

/* ── Validate ──────────────────────────────────────────────── */
$v = Leads::validate($_POST, $_SERVER);

/* ── Partial lead (§3.1) ───────────────────────────────────
   An abandoned form still leaves something recoverable, provided there is
   a way to reach them. It is stored, flagged, and deliberately NOT
   notified or enrolled — nobody consented to be contacted yet. Answered
   204 because sendBeacon ignores the body and the visitor is still on
   the page filling it in. */
if (!empty($_POST['_partial'])) {
    $d = $v['data'];
    if (trim($d['email']) === '' && trim($d['phone']) === '') { http_response_code(204); exit; }
    try {
        $existing = DB::val(
            'SELECT id FROM wwt_leads WHERE is_partial = 1 AND session_hash = ?
             AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY) ORDER BY id DESC LIMIT 1',
            [Analytics::sessionHash(ip_truncate(client_ip()), (string)($_SERVER['HTTP_USER_AGENT'] ?? ''))]);
        if ($existing) {
            /* Same visitor, same session — update rather than pile up a row
               per keystroke-triggered beacon. */
            DB::run('UPDATE wwt_leads SET name=?, email=?, phone=?, service=?, budget=?,
                     message=?, timeline=?, updated_at=UTC_TIMESTAMP() WHERE id=?',
                [$d['name'], $d['email'], $d['phone'], $d['service'], $d['budget'],
                 $d['message'], (string)($d['timeline'] ?? ''), (int)$existing]);
            Score::apply((int)$existing);
        } else {
            $d['is_partial'] = true;
            Leads::store($d, $isTestFlag);
        }
    } catch (Throwable $t) {
        wwt_log('lead', 'partial store failed', ['err' => $t->getMessage()]);
    }
    http_response_code(204);
    exit;
}
if (!$v['ok']) {
    respond(422, ['ok' => false, 'error' => reset($v['errors']), 'fields' => $v['errors']]);
}
$d = $v['data'];

/* A submission flagged as a test is stored and delivered exactly like a
   real one, but tagged so QA traffic can be filtered out and purged
   without ever touching a genuine enquiry. */
$isTest = $isTestFlag;

/* ── Save first ────────────────────────────────────────────── */
try {
    $id = Leads::store($d, $isTest);
} catch (Throwable $t) {
    wwt_log('lead', 'store failed', ['err' => $t->getMessage()]);
    respond(500, ['ok' => false, 'error' =>
        'We could not save that. Please email ' . Mailer::leadRecipient() . ' directly.']);
}

/* ── Record what happened, then tell everyone ───────────────
   Order matters. The lead is stored. Everything below is a copy, and a
   copy failing must never cost the enquiry. */
Timeline::add($id, 'form_submitted', 'form',
    ($d['landing_page'] ?? '') !== '' ? '/lp/' . $d['landing_page'] . '/' : (string)$d['page'],
    'visitor');

if (!empty($d['consent'])) {
    try {
        DB::run('INSERT INTO wwt_consents (lead_id, ts, channel, granted, text_version, wording, ip_trunc, source)
                 VALUES (?, UTC_TIMESTAMP(), ?, 1, ?, ?, ?, ?)',
            [$id, 'all', Leads::CONSENT_VERSION, Leads::consentWording(),
             ip_truncate(client_ip()), ($d['landing_page'] ?? '') ?: 'contact form']);
    } catch (Throwable $t) {
        wwt_log('lead', 'consent record failed', ['id' => $id, 'err' => $t->getMessage()]);
    }
}

/* The fan-out: company mailbox, personal mailbox, Telegram (§4). Each is
   independent and none of them can break the response below. */
$to = Mailer::leadRecipient();
if (!Mailer::configured()) {
    Leads::markMail($id, 'skipped', 'SMTP not configured');
    wwt_log('lead', 'saved but SMTP unconfigured', ['id' => $id]);
    Telegram::newLead(DB::one('SELECT * FROM wwt_leads WHERE id = ?', [$id]) ?? []);
} else {
    $fan = Notify::newLead($id);
    /* Recipients are a list now, each its own attempt. The lead's mail
       status is "sent" if any address got it, "failed" with the first
       reason if none did, and "skipped" if nobody is set up to receive it. */
    $emails = array_filter((array)($fan['channels'] ?? []), static fn($k) => str_starts_with((string)$k, 'email:'), ARRAY_FILTER_USE_KEY);
    $sentOk = (bool)array_filter($emails, static fn($r) => !empty($r['ok']) && empty($r['skipped']));
    $firstErr = '';
    foreach ($emails as $r) { if (empty($r['ok'])) { $firstErr = (string)($r['error'] ?? 'failed'); break; } }
    if (!$emails)      Leads::markMail($id, 'skipped', 'no alert recipients set up');
    elseif ($sentOk)   Leads::markMail($id, 'sent', '');
    else               Leads::markMail($id, 'failed', $firstErr ?: 'not delivered');

    /* The acknowledgement is best-effort and never affects the answer the
       visitor sees — their message is already saved and already with us. */
    if ($sentOk && Settings::bool('lead_ack_enabled', true)) {
        $a = Leads::ackEmail($d);
        $ack = Mailer::send([
            'to'       => $d['email'],
            'to_name'  => $d['name'],
            'subject'  => $a['subject'],
            'text'     => $a['text'],
            'html'     => $a['html'],
            'reply_to' => $to,
        ]);
        if (!empty($ack['ok'])) Timeline::add($id, 'acknowledged', 'email', 'automatic acknowledgement sent');
    }
}

/* Enrol in the nurture sequence for this lead's band (§6.2). Never for a
   test submission, and never for a partial — neither asked to be worked. */
if (!$isTest) {
    try { Funnel::enrol($id); }
    catch (Throwable $t) { wwt_log('lead', 'enrol failed', ['id' => $id, 'err' => $t->getMessage()]); }
}

/* Queue the ad-platform conversion (§10). Queued, never sent inline —
   the platforms fetch on their own schedule. */
try { Ads::queue($id, 'Lead form submit'); }
catch (Throwable $t) { wwt_log('lead', 'ads queue failed', ['id' => $id, 'err' => $t->getMessage()]); }

audit('lead_new', 'id=' . $id . ($isTest ? ' (test)' : ''), 'public');

respond(200, ['ok' => true, 'message' =>
    'Got it. A real person replies within ' . Leads::replyPromise() . '.']);


/* ============================================================ */

/** Answer in the caller's preferred format and stop. */
function respond(int $code, array $payload): never
{
    global $wantsJson;
    http_response_code($code);

    if ($wantsJson) {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $ok    = !empty($payload['ok']);
    $title = $ok ? 'Message sent' : 'That didn’t send';
    $body  = (string)($payload['message'] ?? $payload['error'] ?? '');
    $link  = $ok ? '/' : '/contact/';
    $label = $ok ? 'Back to the site' : 'Back to the form';

    echo '<!DOCTYPE html><html lang="en-IN"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex"><title>' . e($title) . ' | Wwwebtech</title><style>'
       . 'body{background:#FAF8F4;color:#4E5450;font:17px/1.6 Archivo,-apple-system,"Segoe UI",sans-serif;'
       . 'margin:0;min-height:100vh;display:grid;place-items:center;padding:2rem}'
       . 'main{max-width:34rem}h1{font:500 clamp(2rem,5vw,3rem)/1.05 Fraunces,Georgia,serif;'
       . 'color:#131614;letter-spacing:-.02em;margin:0 0 1rem}'
       . 'a{display:inline-block;margin-top:2rem;background:#E07000;color:#131614;font-weight:600;'
       . 'text-decoration:none;padding:.8rem 1.4rem;border-radius:2px}'
       . '</style></head><body><main><h1>' . e($title) . '</h1><p>' . e($body) . '</p>'
       . '<a href="' . e($link) . '">' . e($label) . '</a></main></body></html>';
    exit;
}
