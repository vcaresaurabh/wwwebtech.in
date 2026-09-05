<?php
/* ============================================================
   api/whatsapp-webhook.php — where Meta delivers WhatsApp events.

   Two requests arrive here and nothing else:

     GET  — Meta's one-time verification handshake. It sends the verify
            token the owner pasted into the app; we echo the challenge
            back if it matches ours. That flips the Connections card to
            "Webhook verified".
     POST — a customer's message, or a delivery/read receipt. Every POST
            is signed with the App Secret (X-Hub-Signature-256); anything
            unsigned or mis-signed is refused before it is even parsed.

   An inbound message is the single most valuable event this site can
   receive: it means a customer is talking. It lands on the lead's
   thread and, exactly as an email reply does, stops their sequence.

   Always answers quickly. Meta retries a webhook that does not return
   200 within seconds, which would double every message.
   ============================================================ */

declare(strict_types=1);

define('WWT_SOFT_BOOT', true);
require dirname(__DIR__) . '/_wwt.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=utf-8');

if (!wwt_boot(__DIR__, true)) { http_response_code(503); exit("unavailable\n"); }

$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');

/* ── Verification handshake ──────────────────────────────── */
if ($method === 'GET') {
    $mode  = (string)($_GET['hub_mode'] ?? '');
    $token = (string)($_GET['hub_verify_token'] ?? '');
    $chal  = (string)($_GET['hub_challenge'] ?? '');
    $ours  = WhatsApp::verifyToken();
    if ($mode === 'subscribe' && $ours !== '' && $token !== '' && hash_equals($ours, $token)) {
        Settings::set('wa_webhook_verified_at', gmdate('Y-m-d H:i:s'));
        audit('wa_webhook_verified', 'Meta completed the handshake', 'meta');
        http_response_code(200);
        echo $chal;
        exit;
    }
    /* A wrong token is logged without the value, and answered as if the
       endpoint were not here — the same reasoning as the conversions feed. */
    wwt_log('whatsapp', 'webhook verification refused', ['ip' => ip_truncate(client_ip())]);
    http_response_code(403);
    exit("forbidden\n");
}

if ($method !== 'POST') { http_response_code(405); header('Allow: GET, POST'); exit("method\n"); }

/* ── Signed events ───────────────────────────────────────── */
$raw = (string)file_get_contents('php://input');
$sig = (string)($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
if (!WhatsApp::verifySignature($raw, $sig)) {
    RateLimit::allow('wa-webhook-bad:' . ip_truncate(client_ip()), 20, 3600);
    wwt_log('whatsapp', 'webhook signature refused', ['ip' => ip_truncate(client_ip()), 'signed' => $sig !== '']);
    http_response_code(403);
    exit("forbidden\n");
}

$payload = json_decode($raw, true);
if (!is_array($payload)) { http_response_code(400); exit("bad json\n"); }

/* Acknowledge first, work second: the client connection is closed before
   any database work, so a slow query cannot make Meta retry. */
http_response_code(200);
echo "ok\n";
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
else { @ob_end_flush(); flush(); }
ignore_user_abort(true);

try {
    $r = WhatsApp::handleInbound($payload);
    if ($r['messages'] > 0 || $r['statuses'] > 0) {
        wwt_log('whatsapp', 'webhook processed', $r);
    }
} catch (Throwable $t) {
    wwt_log('whatsapp', 'webhook crashed', ['err' => $t->getMessage()]);
}
