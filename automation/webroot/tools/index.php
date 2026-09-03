<?php
/* ============================================================
   /tools/free-website-audit/ — the audit tool front controller.

   Three views, one file:
     form    — the page itself
     pending — "we are checking your site", after a successful POST
     report  — the finished report, at a token URL

   The audit runs out of band because it takes 30–90 seconds, and no one
   should watch a spinner that long. The visitor gets a token URL that
   refreshes itself, and the report also arrives by email — which is the
   part that matters, because it means the address is deliverable.

   Post/Redirect/Get throughout: a refresh must never re-queue an audit.
   ============================================================ */

declare(strict_types=1);

define('WWT_SOFT_BOOT', true);
require dirname(__DIR__) . '/_wwt.php';
$ready = wwt_boot(__DIR__, true);

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
/* Revalidate rather than re-download: the form is the same for everyone.
   The report and pending views override this with no-store below, because
   they are personal. */
header('Cache-Control: public, max-age=0, must-revalidate');

/* Without the automation layer there is no queue to join, so say so
   rather than taking an address we cannot act on. */
if (!$ready) {
    http_response_code(503);
    header('Retry-After: 3600');
    echo '<!DOCTYPE html><html lang="en-IN"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex"><title>Back shortly | Wwwebtech</title>'
       . '<style>body{background:#FAF8F4;color:#4E5450;font:17px/1.6 system-ui,sans-serif;margin:0;'
       . 'min-height:100vh;display:grid;place-items:center;padding:2rem}main{max-width:34rem}'
       . 'h1{font:500 2rem/1.1 Georgia,serif;color:#131614;margin:0 0 1rem}a{color:#A34E00}</style>'
       . '</head><body><main><h1>The audit tool is briefly offline</h1>'
       . '<p>Email <a href="mailto:contact@wwwebtech.in">contact@wwwebtech.in</a> with your web '
       . 'address and we will send the same report by hand.</p></main></body></html>';
    exit;
}

$path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$base = '/tools/free-website-audit/';

$view   = 'form';
$errors = [];
$old    = ['url' => '', 'name' => '', 'email' => '', 'phone' => ''];
$audit  = null;
$report = null;

/* ── Submit ────────────────────────────────────────────────*/
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Cache-Control: no-store');

    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '' && !same_origin($origin)) {
        http_response_code(403);
        exit('Cross-site posts are not accepted.');
    }

    /* Same honeypot as the contact form: hidden, tabindex -1, so only a
       filler reaches it. Report success and drop it. */
    if (trim((string)($_POST['company'] ?? '')) !== '') {
        header('Location: ' . $base . '?queued=1', true, 303);
        exit;
    }

    $old = [
        'url'   => cut(trim((string)($_POST['url'] ?? '')), 200),
        'name'  => cut(trim((string)($_POST['name'] ?? '')), 100),
        'email' => cut(trim((string)($_POST['email'] ?? '')), 150),
        'phone' => cut(trim((string)($_POST['phone'] ?? '')), 30),
    ];

    if (empty($_POST['consent'])) {
        $errors[] = 'Please tick the box so we may email you the report.';
    } else {
        [$ok, $result] = AuditTool::request($old['url'], $old['name'], $old['email'], $old['phone'], $_SERVER);
        if ($ok) {
            /* Redirect, so a refresh cannot queue a second audit. */
            header('Location: ' . $base . 'report/?t=' . $result, true, 303);
            exit;
        }
        $errors[] = $result;
    }
}

/* ── Report / pending ──────────────────────────────────────*/
$token = (string)($_GET['t'] ?? '');
if ($token !== '' || str_contains($path, '/report')) {
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');       // a personal report is not a search result
    $audit = AuditTool::byToken($token);
    if (!$audit) {
        http_response_code(404);
        $errors[] = 'That report link is not one of ours, or it has been removed.';
    } elseif ($audit['status'] === 'done') {
        $report = json_decode((string)$audit['results'], true);
        $view   = is_array($report) ? 'report' : 'pending';
    } elseif ($audit['status'] === 'failed') {
        $view = 'failed';
    } else {
        $view = 'pending';
    }
}

$tplDir = WWT_PRIVATE . '/templates';
require_once $tplDir . '/logo.php';
require $tplDir . '/audit.php';
