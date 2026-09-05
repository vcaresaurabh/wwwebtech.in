<?php
/* ============================================================
   bootstrap.php — the single entry point every PHP file uses.

   require __DIR__ . '/../../wwt_private/bootstrap.php';

   Loads config, wires the DB, sets error handling and the timezone.
   Nothing below ever echoes: a fatal in production goes to the log,
   and the caller decides what the visitor sees.
   ============================================================ */

declare(strict_types=1);

if (defined('WWT_BOOTSTRAPPED')) return;

define('WWT_PRIVATE', __DIR__);

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    error_log('wwt: config.php missing at ' . $configFile);
    /* WWT_BOOTSTRAPPED is deliberately NOT defined, so a soft caller
       (serve.php, the beacon) can see that boot failed and carry on doing
       its real job. A hard caller has already decided to exit on false. */
    if (defined('WWT_SOFT_BOOT')) return;
    http_response_code(500);
    exit('Configuration missing. Copy config.sample.php to config.php — see DEPLOY.md.');
}

/** @var array $CONFIG */
$CONFIG = require $configFile;

/* Pinned to the global scope on purpose. This file is loaded from inside
   wwt_boot(), so a plain `$CONFIG` would be a LOCAL variable there and every
   `global $CONFIG` in cfg() would see null — silently handing back defaults
   for the SMTP password, the session salt and the API key. */
$GLOBALS['CONFIG'] = $CONFIG;

/* Defined only now that there IS a configuration. wwt_boot() reports success
   by this constant, so defining it earlier would tell a soft caller that the
   layer is ready when it has no database credentials at all. */
define('WWT_BOOTSTRAPPED', true);
define('WWT_DEBUG', (bool)($CONFIG['debug'] ?? false));

/* Errors: logged always, displayed never (unless explicitly debugging). */
error_reporting(E_ALL);
ini_set('display_errors', WWT_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0750, true);
ini_set('error_log', $logDir . '/php-error.log');

date_default_timezone_set('UTC');            // store UTC, render local
define('WWT_TZ_DISPLAY', $CONFIG['site']['timezone'] ?? 'Asia/Kolkata');

require_once __DIR__ . '/lib/util.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/migrate.php';
require_once __DIR__ . '/lib/secrets.php';
require_once __DIR__ . '/lib/mailer.php';
require_once __DIR__ . '/lib/leads.php';
require_once __DIR__ . '/lib/score.php';
require_once __DIR__ . '/lib/telegram.php';
require_once __DIR__ . '/lib/notify.php';
require_once __DIR__ . '/lib/ads.php';
require_once __DIR__ . '/lib/whatsapp.php';
require_once __DIR__ . '/lib/funnel-writer.php';
require_once __DIR__ . '/lib/funnel.php';
require_once __DIR__ . '/lib/analytics.php';
require_once __DIR__ . '/lib/claude.php';
require_once __DIR__ . '/lib/blog.php';
require_once __DIR__ . '/lib/publisher.php';
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/seo.php';
require_once __DIR__ . '/lib/tags.php';
require_once __DIR__ . '/lib/task.php';
require_once __DIR__ . '/lib/inbox.php';
require_once __DIR__ . '/lib/connections.php';
require_once __DIR__ . '/lib/audit-tool.php';
require_once __DIR__ . '/lib/jobs.php';

DB::configure($CONFIG['db'] ?? []);

/** Config accessor: cfg('smtp.host', 'default'). */
function cfg(string $path, mixed $default = null): mixed
{
    global $CONFIG;
    $node = $CONFIG;
    foreach (explode('.', $path) as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) return $default;
        $node = $node[$part];
    }
    return $node;
}

/** Format a stored UTC datetime in the owner's timezone. */
function local_time(?string $utc, string $fmt = 'd M Y, H:i'): string
{
    if (!$utc) return '';
    try {
        $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        return $dt->setTimezone(new DateTimeZone(WWT_TZ_DISPLAY))->format($fmt);
    } catch (Throwable) { return (string)$utc; }
}

/** Current time in the owner's timezone (for cron scheduling decisions). */
function local_now(): DateTimeImmutable
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone(WWT_TZ_DISPLAY));
}

/* Uncaught throwables: log with context, show nothing useful to an attacker. */
set_exception_handler(static function (Throwable $t): void {
    wwt_log('fatal', $t->getMessage(), [
        'file' => $t->getFile() . ':' . $t->getLine(),
        'uri'  => $_SERVER['REQUEST_URI'] ?? 'cli',
    ]);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'FATAL: ' . $t->getMessage() . PHP_EOL . $t->getTraceAsString() . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    if (WWT_DEBUG) {
        echo '<pre>' . e($t->getMessage() . "\n" . $t->getTraceAsString()) . '</pre>';
    } else {
        echo 'Something went wrong. It has been logged.';
    }
});
