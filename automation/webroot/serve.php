<?php
/* ============================================================
   serve.php — logs the crawlers that never run JavaScript, then
   serves them the page exactly as Apache would have.

   WHY THIS EXISTS
   GPTBot, ClaudeBot, PerplexityBot and Google-Extended are how the site
   gets into AI answers, and not one of them executes assets/wa.js. They
   are invisible to any client-side analytics. Seeing them requires
   logging on the server.

   WHY IT ONLY HANDLES BOTS
   The .htaccess rule routes a request here only when the User-Agent
   matches a known crawler. Human traffic is served straight off disk by
   LiteSpeed and never touches PHP. That matters: this site is sold on
   Core Web Vitals, and putting an interpreter in front of every page
   load would cost real LCP and add a way for the whole site to go down.
   The cost of the narrow rule is that a human with JavaScript disabled
   is not counted. That is a small, known slice, and it is the right
   trade — measurement must never be able to break delivery.

   FAILURE BEHAVIOUR
   Every step is wrapped. If the database is unreachable, if the private
   folder has moved, if anything at all throws, the page is still
   served. Logging is best-effort by construction.

   TO SWITCH IT OFF
   Delete the "AI crawler logging" block from .htaccess. Nothing else
   depends on this file.
   ============================================================ */

declare(strict_types=1);

/* ── Resolve the requested file, before anything else can fail ─────── */
$root = __DIR__;
$path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = rawurldecode($path);

/* Reject traversal before touching the filesystem. */
if (str_contains($path, "\0") || str_contains($path, '..')) {
    http_response_code(400);
    exit;
}

$rel  = ltrim($path, '/');
$file = $root . '/' . $rel;
if ($rel === '' || is_dir($file)) $file = rtrim($file, '/') . '/index.html';

/* Only ever hand back documents.
 *
 * The .htaccess rule is supposed to send nothing else here, but this file
 * must not depend on that being true: a rewrite is one edit away from
 * being wrong, and the consequence would be this script reading out PHP
 * source — including its own — as plain bytes. So the allowlist lives
 * here, next to the readfile() it protects.
 */
const WWT_SERVE_TYPES = ['html' => 'text/html; charset=utf-8',
                         'xml'  => 'application/xml; charset=utf-8',
                         'txt'  => 'text/plain; charset=utf-8'];

$real     = realpath($file);
$rootReal = realpath($root);
$ext      = strtolower(pathinfo((string)$real, PATHINFO_EXTENSION));

$ok = $real !== false && $rootReal !== false
   && str_starts_with($real, $rootReal . DIRECTORY_SEPARATOR)
   && is_file($real)
   && isset(WWT_SERVE_TYPES[$ext])                 // documents only, never code
   && $real !== realpath(__FILE__)                 // never itself
   && !preg_match('#(^|/)[._]#', $rel)             // no dotfiles, no _private helpers
   && !preg_match('#^(admin|api)(/|$)#i', $rel);   // the panel serves itself

if (!$ok) {
    $notFound = $rootReal . '/404.html';
    http_response_code(404);
    if (is_file($notFound)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($notFound);
    }
    exit;
}

/* ── Log. Best effort, never fatal, never slow enough to matter. ───── */
try {
    $boot = __DIR__ . '/_wwt.php';
    if (is_file($boot)) {
        define('WWT_SOFT_BOOT', true);
        require_once $boot;
        if (wwt_boot(__DIR__, true)) {
            Analytics::record(['path' => $path, 'event' => 'pageview']);
        }
    }
} catch (Throwable $t) {
    // Deliberately swallowed. The page below is what the request came for.
    @error_log('wwt serve.php: ' . $t->getMessage());
}

/* ── Serve, with the caching semantics Apache would have used. ─────── */
$type = WWT_SERVE_TYPES[$ext];

$mtime = (int)filemtime($real);
$etag  = '"' . dechex($mtime) . '-' . dechex((int)filesize($real)) . '"';

header('Content-Type: ' . $type);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('ETag: ' . $etag);
header('Cache-Control: public, max-age=0, must-revalidate');
header('X-Robots-Tag: all');

/* Honour conditional requests so a returning crawler gets a cheap 304
   rather than the whole document again. */
$ifNone = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
$ifMod  = strtotime((string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '')) ?: 0;
if ($ifNone === $etag || ($ifMod && $ifMod >= $mtime)) {
    http_response_code(304);
    exit;
}

header('Content-Length: ' . filesize($real));
readfile($real);
