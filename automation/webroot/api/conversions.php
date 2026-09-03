<?php
/* ============================================================
   api/conversions.php — offline conversions, as a file the ad
   platforms can fetch on a schedule (§9).

   Google Ads and Microsoft Advertising both accept a scheduled HTTPS
   fetch of a CSV. That is far less fragile than an API integration on
   shared hosting: no OAuth refresh to expire quietly, no token to
   rotate, and if it breaks the platform tells the owner.

   Access is by a long random key in the URL, because the fetching
   robot cannot log in. That makes the key a credential:
     · it is compared with hash_equals, never ==
     · it is never logged, and never echoed back in an error
     · the response is noindex, no-store, and never cacheable
     · a wrong key gets 404, not 403 — a 403 confirms the URL exists

   ?type=google | microsoft | enhanced
   &key=<conversions_key from Settings>
   &mark=1 to mark the rows fetched (the platform's scheduled pull
          should use mark=1; a human checking the file should not)
   ============================================================ */

declare(strict_types=1);

define('WWT_SOFT_BOOT', true);
require dirname(__DIR__) . '/_wwt.php';

if (!wwt_boot(__DIR__, true)) { http_response_code(503); header('Retry-After: 600'); exit("unavailable\n"); }

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

/** Refuse without confirming anything about the URL. */
function nope(): never { http_response_code(404); header('Content-Type: text/plain'); exit("Not found\n"); }

$expected = Secrets::get('conversions_key', '');
$given    = (string)($_GET['key'] ?? '');
if ($expected === '' || $given === '' || strlen($given) < 24) nope();
if (!hash_equals($expected, $given)) {
    /* Rate-limited so the key cannot be searched for, and logged without
       the attempted value — a log full of near-miss keys is a liability. */
    RateLimit::allow('conv:' . ip_truncate(client_ip()), 10, 3600);
    wwt_log('ads', 'conversions fetch rejected', ['ip' => ip_truncate(client_ip())]);
    nope();
}

if (!RateLimit::allow('conv-ok:' . ip_truncate(client_ip()), 60, 3600)) {
    http_response_code(429);
    header('Retry-After: 600');
    exit("Too many requests\n");
}

$type = (string)($_GET['type'] ?? 'google');
if (!in_array($type, ['google', 'microsoft', 'enhanced'], true)) nope();

$csv = match ($type) {
    'google'    => Ads::googleCsv(),
    'microsoft' => Ads::microsoftCsv(),
    'enhanced'  => Ads::enhancedCsv(),
};

/* Marking is a side effect, so it only happens when asked for. A person
   opening the URL to see what is in it must not consume the queue. */
$marked = 0;
if (($_GET['mark'] ?? '') === '1') {
    $marked = Ads::markFetched($type === 'enhanced' ? 'google' : $type, $type === 'enhanced');
    audit('ads_fetch', $type . ', ' . $marked . ' row(s) marked');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="wwwebtech-' . $type . '-conversions.csv"');
header('X-Rows-Marked: ' . $marked);
echo $csv;
