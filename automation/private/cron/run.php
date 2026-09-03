#!/usr/bin/env php
<?php
/* ============================================================
   cron/run.php — the entry point for scheduled work.

   From cron (this is what DEPLOY.md sets up in hPanel):

     /usr/bin/php /home/USER/wwt_private/cron/run.php hourly
     /usr/bin/php /home/USER/wwt_private/cron/run.php daily
     /usr/bin/php /home/USER/wwt_private/cron/run.php weekly

   The jobs themselves live in lib/jobs.php, because the admin panel runs
   the same ones from its "run it now" buttons and two copies of that list
   would drift apart. This file is only the command line around them.
   ============================================================ */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once WWT_PRIVATE . '/lib/jobs.php';

if (PHP_SAPI !== 'cli') {
    /* This file lives outside the web root, so arriving here over HTTP means
       something is misconfigured. Say nothing useful about it. */
    http_response_code(404);
    exit;
}

$name = (string)($argv[1] ?? '');
if ($name === '' || $name === '--list' || $name === '-h' || $name === '--help') {
    fwrite(STDOUT, "Jobs:   " . implode(', ', array_keys(Jobs::registry())) . "\n");
    fwrite(STDOUT, "Groups: " . implode(', ', array_keys(Jobs::groups())) . "\n");
    exit($name === '' ? 2 : 0);
}

if (!Jobs::exists($name)) {
    fwrite(STDERR, "unknown job: $name\n");
    fwrite(STDERR, "try one of: " . implode(', ', array_keys(Jobs::registry())) . "\n");
    exit(1);
}

$bad = 0;
foreach (Jobs::run($name) as $r) {
    fwrite(STDOUT, sprintf("%-18s %-7s %s\n", $r['task'], $r['status'], $r['summary']));
    if ($r['status'] !== 'ok') $bad = 1;
}
exit($bad);
