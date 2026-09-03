<?php
/* ============================================================
   /lp/<slug>/ — the landing page front controller.

   One entry point for all six pages, the same shape as admin/index.php.
   Routing is by path segment against the data files that exist; an
   unknown slug is a 404, never a guess.

   The message-match parameter (§2.5) is looked up against a per-page
   whitelist and NEVER rendered. A URL parameter that reaches the DOM is
   a cross-site scripting hole; a URL parameter used as an array key
   cannot be one. Unmatched keys are logged so the owner can add a
   mapping from the panel rather than lose the signal.
   ============================================================ */

declare(strict_types=1);

define('WWT_SOFT_BOOT', true);
require dirname(__DIR__) . '/_wwt.php';
$ready = wwt_boot(__DIR__, true);

/* ── Route ─────────────────────────────────────────────────
   Apache rewrites /lp/<slug>/ here; the original path is still in
   REQUEST_URI. Accept a ?p= form too so the page is testable on a
   server with no rewrite rules. */
$path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$slug = '';
if (preg_match('#^/lp/([a-z0-9][a-z0-9-]{1,60})/?$#', $path, $m)) {
    $slug = $m[1];
} elseif (isset($_GET['p']) && preg_match('#^[a-z0-9][a-z0-9-]{1,60}$#', (string)$_GET['p'])) {
    $slug = (string)$_GET['p'];
}

$dataDir = $ready ? WWT_PRIVATE . '/lp/data' : dirname(__DIR__, 2) . '/private/lp/data';
$file    = $dataDir . '/' . $slug . '.php';

/* realpath both sides: a slug is already pattern-restricted, but the file
   that ends up being included should be proven to sit in the data folder. */
$real = $slug !== '' ? realpath($file) : false;
$root = realpath($dataDir);
if ($real === false || $root === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    $notFound = rtrim((string)($ready ? cfg('site.webroot', '') : ''), '/') . '/404.html';
    if (is_file($notFound)) { header('Content-Type: text/html; charset=utf-8'); readfile($notFound); }
    else { header('Content-Type: text/plain'); echo "Not found\n"; }
    exit;
}

$lp = require $real;
if (!is_array($lp)) { http_response_code(500); exit('Landing page data is malformed.'); }

/* ── Message match (§2.5) ──────────────────────────────────*/
$matchLine = (string)$lp['sub'];
$rawKey = '';
foreach (['kw', 'utm_term', 'utm_campaign'] as $param) {
    $v = (string)($_GET[$param] ?? '');
    if ($v === '') continue;
    $rawKey = $v;
    /* Normalise to the same shape the whitelist keys use, then look it up.
       The value is used ONLY as an array key — it never reaches the page. */
    $key = strtolower(trim($v));
    $key = preg_replace('/[^a-z0-9]+/', '-', $key) ?? $key;
    $key = trim($key, '-');
    if ($key !== '' && isset($lp['match'][$key])) { $matchLine = (string)$lp['match'][$key]; $rawKey = ''; }
    break;
}
/* An unmatched key is a real signal: it is a keyword sending traffic with no
   tailored line. Record it so the owner can add one. */
if ($rawKey !== '' && $ready) {
    try {
        $seen = Settings::json('lp_unmatched_kw', []);
        $k = cut(strtolower(trim($rawKey)), 80);
        $seen[$k] = ['n' => (int)($seen[$k]['n'] ?? 0) + 1, 'lp' => $slug, 'last' => gmdate('Y-m-d')];
        if (count($seen) > 200) $seen = array_slice($seen, -200, null, true);
        Settings::set('lp_unmatched_kw', json_encode($seen, JSON_UNESCAPED_SLASHES) ?: '{}');
    } catch (Throwable) { /* never let analytics break a page */ }
}

/* ── A/B variant (§2.6) ────────────────────────────────────
   Sticky per visitor for the life of the cookie, so someone who
   returns sees the page they saw before. */
$variant = 'a';
if (!empty($lp['variant_b'])) {
    $split = $ready ? max(0, min(100, Settings::int('lp_ab_split', 0))) : 0;
    if ($split > 0) {
        $cookie = 'wwtv_' . preg_replace('/[^a-z0-9]/', '', $slug);
        $existing = (string)($_COOKIE[$cookie] ?? '');
        if ($existing === 'a' || $existing === 'b') {
            $variant = $existing;
        } else {
            $variant = random_int(1, 100) <= $split ? 'b' : 'a';
            setcookie($cookie, $variant, [
                'expires' => time() + 60 * 60 * 24 * 30, 'path' => '/lp/',
                'samesite' => 'Lax', 'secure' => true, 'httponly' => false,
            ]);
        }
        if ($variant === 'b' && is_array($lp['variant_b'])) $lp = array_replace($lp, $lp['variant_b']);
    }
}

/* ── Headers ───────────────────────────────────────────────*/
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
/* Paid traffic arrives repeatedly from the same people during a campaign;
   revalidation keeps the page fresh without a full re-download. */
header('Cache-Control: public, max-age=0, must-revalidate');

/* ── Count the visit ───────────────────────────────────────
   Server-side, so it is recorded even for the visitors who block
   client-side analytics — which on paid traffic is a lot of them. */
if ($ready) {
    try {
        Analytics::record([
            'path'         => '/lp/' . $slug . '/',
            'event'        => 'pageview',
            'detail'       => 'lp:' . $variant,
            'utm_source'   => (string)($_GET['utm_source'] ?? ''),
            'utm_medium'   => (string)($_GET['utm_medium'] ?? ''),
            'utm_campaign' => (string)($_GET['utm_campaign'] ?? ''),
        ]);
    } catch (Throwable) { /* measurement never blocks delivery */ }
}

/* The template needs these helpers; without a booted layer it still has to
   render, because a broken database must not take the landing page down. */
if (!function_exists('e')) {
    function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('cfg')) {
    function cfg(string $p, mixed $d = null): mixed { return $d; }
}
/* The wordmark, from the one definition all three front ends share. */
require_once ($ready ? WWT_PRIVATE : dirname(__DIR__, 2) . '/private') . '/templates/logo.php';

$tplDir = $ready ? WWT_PRIVATE . '/templates' : dirname(__DIR__, 2) . '/private/templates';
require $tplDir . '/lp.php';
