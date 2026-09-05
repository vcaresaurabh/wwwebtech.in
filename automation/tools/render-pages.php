#!/usr/bin/env php
<?php
/* ============================================================
   render-pages.php — render every admin page and report anything a
   visitor would see as an error.

   Runs the real page files against the real database, so it catches what
   only appears in production: a function the host disables, a query the
   live MySQL rejects, a template that is missing.

   Creates no account, writes nothing, leaves no session behind.

     php automation/tools/render-pages.php

   Every local variable here is prefixed __ because the page files are
   included into this scope and freely use $page, $p, $posts and so on —
   an unprefixed name here would be silently overwritten by the page under
   test, which is how an earlier version reported "1 of 1 pages".
   ============================================================ */
declare(strict_types=1);

/* Find the bootstrap by walking up, so this runs from the repo
   (automation/tools/) and from the server (wwt_private/) alike. */
$__boot = '';
for ($__d = __DIR__, $__i = 0; $__i < 5; $__i++, $__d = dirname($__d)) {
    foreach (["$__d/private/bootstrap.php", "$__d/bootstrap.php",
              dirname($__d) . '/wwt_private/bootstrap.php'] as $__c) {
        if (is_file($__c)) { $__boot = $__c; break 2; }
    }
}
if ($__boot === '') { fwrite(STDERR, "cannot find bootstrap.php\n"); exit(2); }
require_once $__boot;

/* The admin directory: the repo's copy if present, otherwise the deployed
   one named by the configured web root. */
$__adminDir = '';
foreach ([dirname(WWT_PRIVATE) . '/webroot/admin',
          rtrim((string)cfg('site.webroot', ''), '/') . '/admin'] as $__c) {
    if (is_dir($__c)) { $__adminDir = $__c; break; }
}
if ($__adminDir === '') { fwrite(STDERR, "cannot find the admin directory\n"); exit(2); }
fwrite(STDOUT, "  using " . $__adminDir . "\n\n");

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST']      = (string)parse_url((string)cfg('site.url', ''), PHP_URL_HOST) ?: 'localhost';
$_SERVER['REQUEST_URI']    = '/admin/';
$_SESSION = ['uid' => 1, 'email' => 'audit@local', 'role' => 'admin',
             'csrf' => str_repeat('a', 64), 'last_seen' => time(), 'login_at' => time()];

define('WWT_ADMIN', true);
require_once $__adminDir . '/_layout.php';

/* The handful of helpers _boot.php would normally provide. It is not used
   here because it starts a real session and sends headers. */
if (!function_exists('flash'))    { function flash(): ?array { return null; } }
if (!function_exists('redirect')) { function redirect(string $t, string $a = '', string $b = ''): never { echo "REDIRECT:$t"; exit; } }
if (!function_exists('qs'))       { function qs(array $o = [], array $d = []): string { return '?'; } }
if (!function_exists('gets')) {
    function gets(string $k, string $def = '', array $allowed = []): string {
        $v = (string)($_GET[$k] ?? $def);
        return ($allowed && !in_array($v, $allowed, true)) ? $def : $v;
    }
}

$__pages = ['dashboard', 'leads', 'analytics', 'conversations', 'funnel', 'landing', 'audits', 'connections', 'blog', 'seo', 'integrations', 'settings'];
$__bad   = 0;
$__total = count($__pages);

/* The detail pages need a row to render. Without one they short-circuit to
   "not found" and the tool reports a pass on a page it never exercised. */
$__someLead  = (int)DB::val('SELECT id FROM wwt_leads ORDER BY id DESC LIMIT 1', [], 0);
if ($__someLead > 0) { $__pages[] = 'lead'; $__pages[] = 'conversations:thread'; $__total = count($__pages); }

foreach ($__pages as $__label) {
    $__name = $__label;
    $_GET   = ['p' => $__name];
    if ($__label === 'lead')                 { $_GET = ['p' => 'lead', 'id' => (string)$__someLead]; }
    if ($__label === 'conversations:thread') { $__name = 'conversations';
                                               $_GET = ['p' => 'conversations', 'id' => (string)$__someLead]; }
    $__err = '';
    ob_start();
    try {
        require $__adminDir . '/pages/' . $__name . '.php';
    } catch (Throwable $__t) {
        $__err = get_class($__t) . ': ' . $__t->getMessage()
               . ' @' . basename($__t->getFile()) . ':' . $__t->getLine();
    }
    $__html = (string)ob_get_clean();

    $__signs = [];
    foreach (['Fatal error', 'Call to undefined', 'Warning:', 'Notice:', 'Deprecated:',
              'Something went wrong', 'Uncaught', '{{'] as $__needle) {
        if (str_contains($__html, $__needle)) $__signs[] = $__needle;
    }
    if ($__err !== '') $__signs[] = $__err;
    if (strlen($__html) < 500) $__signs[] = 'suspiciously short (' . strlen($__html) . ' bytes)';

    if ($__signs) {
        $__bad++;
        printf("  FAIL  %-21s %s\n", $__label, implode(' | ', array_slice($__signs, 0, 2)));
    } else {
        printf("  ok    %-21s %6d bytes\n", $__label, strlen($__html));
    }
}

printf("\n  %d of %d pages render cleanly\n", $__total - $__bad, $__total);
exit($__bad ? 1 : 0);
