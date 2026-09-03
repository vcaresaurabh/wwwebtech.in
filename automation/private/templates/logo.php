<?php
/* ============================================================
   logo.php — the wordmark, in one place.

   It was defined three times (admin layout, landing pages, tools) with
   three slightly different fallbacks, which is three chances for the
   pages the company pays to send traffic to to show a different logo
   from the site. One definition, guarded, required by all of them.

   Colours are arguments because the mark sits on dark in the admin
   panel and on paper everywhere else.
   ============================================================ */

declare(strict_types=1);

if (!function_exists('wwt_logo_svg')) {
    function wwt_logo_svg(string $wordFill = '#131614', string $tailFill = '#7C817B'): string
    {
        static $paths = null;
        if ($paths === null) {
            $paths = ['word' => '', 'tail' => ''];
            /* Wherever this is running: installed server, or the repo. */
            foreach ([(defined('WWT_PRIVATE') ? WWT_PRIVATE . '/templates/logo-paths.php' : ''),
                      __DIR__ . '/logo-paths.php'] as $f) {
                if ($f !== '' && is_file($f)) { $paths = require $f; break; }
            }
        }
        if (($paths['word'] ?? '') === '') {
            return '<span style="font:600 17px/1 Archivo,sans-serif;color:'
                 . htmlspecialchars($wordFill, ENT_QUOTES) . '">wwwebtech</span>';
        }
        return '<svg viewBox="0 28 568 78" role="img" aria-label="Wwwebtech">'
             . '<path fill="' . htmlspecialchars($wordFill, ENT_QUOTES) . '" d="' . $paths['word'] . '"/>'
             . '<path fill="' . htmlspecialchars($tailFill, ENT_QUOTES) . '" d="' . $paths['tail'] . '"/>'
             . '<circle fill="#E07000" cx="554" cy="87" r="10"/></svg>';
    }
}
