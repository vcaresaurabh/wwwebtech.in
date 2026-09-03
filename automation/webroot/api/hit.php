<?php
/* ============================================================
   api/hit.php — where assets/wa.js posts.

   Answers 204 with an empty body: the browser needs nothing back, and
   sendBeacon ignores the response anyway. Everything here is written to
   fail quietly — a measurement endpoint that throws is worse than one
   that misses a hit.

   Not CSRF-protected on purpose. There is nothing to protect: the
   endpoint only appends a row that contains no identifier and grants no
   privilege. It is same-origin checked and rate limited so it cannot be
   used to inflate someone's numbers from elsewhere.
   ============================================================ */

declare(strict_types=1);

define('WWT_SOFT_BOOT', true);
require dirname(__DIR__) . '/_wwt.php';
/* Measurement must never be the reason a request fails. With no configuration
   yet (files uploaded, database not created), answer 204 and record nothing. */
if (!wwt_boot(__DIR__, true)) { http_response_code(204); exit; }

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

/* No Access-Control-Allow-Origin is ever sent, so a cross-site page cannot
   read the response — but it could still fire one. Refuse those outright.
   Origin decides when present; otherwise fall back to Referer. */
$claim = (string)($_SERVER['HTTP_ORIGIN'] ?? '') ?: (string)($_SERVER['HTTP_REFERER'] ?? '');
if ($claim !== '' && !same_origin($claim)) {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

/* Generous, but bounded. A real reader might fire a pageview, an engagement
   ping and a handful of clicks per page; 400 an hour from one address is
   already far beyond that. */
$ipKey = 'hit:' . substr(hash_hmac('sha256', client_ip(), (string)cfg('geo_salt', 'wwt')), 0, 40);
if (!RateLimit::allow($ipKey, 400, 3600)) { http_response_code(204); exit; }

Analytics::record([
    'path'         => field($_POST, 'p', 190),
    'ref'          => field($_POST, 'r', 255),
    'utm_source'   => field($_POST, 'utm_source', 80),
    'utm_medium'   => field($_POST, 'utm_medium', 80),
    'utm_campaign' => field($_POST, 'utm_campaign', 120),
    'event'        => field($_POST, 'e', 40),
    'detail'       => field($_POST, 'd', 255),
    'test'         => field($_POST, 't', 80) === (string)cfg('cron_key', "\0"),
]);

http_response_code(204);
