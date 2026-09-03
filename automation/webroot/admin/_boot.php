<?php
/* Shared bootstrap for every admin page. */
declare(strict_types=1);

/* Not a page. Requested directly it must 404, not execute: this file is an
   include, and on a misconfigured host a direct hit would otherwise run its
   side effects (or, with the wrong handler, print its own source). */
if (realpath(__FILE__) === realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    http_response_code(404);
    exit;
}


/* Locate and load the private bootstrap (see _wwt.php for why it is
   outside the web root, and why the path is searched rather than fixed). */
/* Pages under pages/ check for this, so none of them can be reached by
   requesting the file directly — only through index.php. */
define('WWT_ADMIN', true);

require_once dirname(__DIR__) . '/_wwt.php';
wwt_boot(__DIR__);

/* Bring the database to the current schema — creating the tables on a fresh
   install. Doing it here rather than in the public endpoints means the owner
   never runs SQL, and a hot path never pays for a migration check.
   
   A connection failure here is almost always a first-run typo in config.php,
   and "Something went wrong" would send the owner hunting through logs. Say
   what to check — without echoing the credentials that failed. */
try {
    wwt_migrate();
} catch (Throwable $t) {
    wwt_log('boot', 'database unavailable', ['err' => $t->getMessage()]);
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en-IN"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex"><title>Database not reachable</title>'
       . '<style>body{background:#FAF8F4;color:#4E5450;font:16px/1.6 system-ui,sans-serif;'
       . 'margin:0;min-height:100vh;display:grid;place-items:center;padding:2rem}'
       . 'main{max-width:38rem}h1{font:500 1.6rem/1.2 Georgia,serif;color:#131614;margin:0 0 .8rem}'
       . 'ol{padding-left:1.2rem}li{margin:.4rem 0}code{background:#F2EEE6;padding:.1rem .3rem;'
       . 'border-radius:2px;font-size:.9em}</style></head><body><main>'
       . '<h1>The panel cannot reach its database</h1>'
       . '<p>The files are installed correctly — this is a configuration problem, and the '
       . 'website itself is unaffected.</p><ol>'
       . '<li>In hPanel, create the MySQL database and user (DEPLOY.md, step 1).</li>'
       . '<li>Open <code>wwt_private/config.php</code> and put the database name, user and '
       . 'password into the <code>db</code> block — exactly as hPanel shows them, including '
       . 'the account prefix.</li>'
       . '<li>Reload this page. The tables are created automatically.</li>'
       . '</ol><p style="color:#686D69;font-size:.9em">The exact error has been written to '
       . '<code>wwt_private/logs/</code>.</p></main></body></html>';
    exit;
}

/* The panel must never be indexed, framed, or sniffed. */
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
     . "style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; "
     . "form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

Auth::startSession((string)cfg('session_salt', ''));

/** Redirect helper that always ends the request. */
function redirect(string $to, string $flashType = '', string $flashMsg = ''): never {
    if ($flashMsg !== '') $_SESSION['flash'] = ['type' => $flashType, 'msg' => $flashMsg];
    header('Location: ' . $to, true, 303);
    exit;
}

/** Pop the one-shot flash message. */
function flash(): ?array {
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash']; unset($_SESSION['flash']);
    return $f;
}

/** Build a URL for the current page with some query params replaced. */
function qs(array $overrides = [], array $drop = []): string {
    $q = array_merge($_GET, $overrides);
    foreach ($drop as $d) unset($q[$d]);
    foreach ($q as $k => $v) if ($v === '' || $v === null) unset($q[$k]);
    return '?' . http_build_query($q);
}

/** Read a whitelisted GET value. */
function gets(string $key, string $default = '', array $allowed = []): string {
    $v = (string)($_GET[$key] ?? $default);
    if ($allowed && !in_array($v, $allowed, true)) return $default;
    return cut($v, 120);
}
