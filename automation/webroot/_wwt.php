<?php
/* ============================================================
   _wwt.php — find and load the private bootstrap.

   Every PHP entry point in the web root starts with:
       require dirname(__DIR__) . '/_wwt.php';   (or __DIR__ at the top level)

   The private folder lives OUTSIDE the web root: config.php holds the
   database password and the SMTP password, and anything inside
   public_html can be served as plain text if a rewrite ever misfires.

   Its depth differs between the repo (automation/private) and the
   server (/home/USER/wwt_private, beside public_html), so this walks
   up rather than hard-coding a level and breaking on one of them.
   ============================================================ */

declare(strict_types=1);

/* Not a page. Requested directly it must 404, not execute: this file is an
   include, and on a misconfigured host a direct hit would otherwise run its
   side effects (or, with the wrong handler, print its own source). */
if (realpath(__FILE__) === realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    http_response_code(404);
    exit;
}


if (!function_exists('wwt_private_candidates')) {
    function wwt_private_candidates(string $from): array
    {
        $out = [];
        $dir = $from;
        for ($i = 0; $i < 6; $i++) {
            $dir = dirname($dir);
            if ($dir === '/' || $dir === '' || $dir === '.') break;
            $out[] = $dir . '/wwt_private/bootstrap.php';   // production name
            $out[] = $dir . '/private/bootstrap.php';       // repo name
        }
        return $out;
    }
}

if (!function_exists('wwt_boot')) {
    /**
     * Load the private bootstrap.
     *
     * $soft is for callers whose real job is not the automation layer —
     * serve.php delivers a page to a crawler, the beacon endpoint records a
     * visit. Those must carry on when the configuration is absent (the
     * window between uploading the files and creating the database, for
     * one), so they get false instead of a dead request.
     *
     * The admin panel is the opposite: a human is looking at it and needs to
     * be told what is wrong, so it uses the hard form.
     */
    function wwt_boot(string $from, bool $soft = false): bool
    {
        if (defined('WWT_BOOTSTRAPPED')) return true;
        foreach (wwt_private_candidates($from) as $c) {
            if (is_file($c)) {
                require_once $c;
                return defined('WWT_BOOTSTRAPPED');
            }
        }
        error_log('wwt: private/bootstrap.php not found from ' . $from);
        if ($soft) return false;
        http_response_code(500);
        exit('Configuration missing — see DEPLOY.md.');
    }
}
