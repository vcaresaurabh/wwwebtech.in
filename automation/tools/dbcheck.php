#!/usr/bin/env php
<?php
/* ============================================================
   dbcheck.php — the database layer survives an idle timeout.

   Shared hosting closes idle MySQL connections, and several jobs hold one
   open across a slow HTTP call: writing a blog post takes over a minute,
   PageSpeed can take ninety seconds. Before this was handled, the query
   AFTER the wait failed with "MySQL server has gone away" — so an article
   was generated, paid for, and then lost.

   Killing the connection from a SECOND handle is what an idle timeout
   actually looks like; killing it from our own does not reproduce it.
   ============================================================ */
declare(strict_types=1);
require_once dirname(__DIR__) . '/private/bootstrap.php';

function killer(): PDO
{
    $c = $GLOBALS['CONFIG']['db'];
    return new PDO("mysql:host={$c['host']};dbname={$c['name']}", $c['user'], $c['pass']);
}

$fail = 0;
$say  = function (bool $ok, string $what) use (&$fail): void {
    if (!$ok) $fail++;
    echo ($ok ? '  PASS  ' : '  FAIL  '), $what, "\n";
};

/* 1 — a plain query reconnects */
$id = (int)DB::val('SELECT CONNECTION_ID()', [], 0);
killer()->exec('KILL ' . $id);
usleep(400000);
try {
    DB::val('SELECT COUNT(*) FROM wwt_settings', [], -1);
    $new = (int)DB::val('SELECT CONNECTION_ID()', [], 0);
    $say($new !== $id, 'a query after an idle timeout reconnects (was ' . $id . ', now ' . $new . ')');
} catch (Throwable $t) {
    $say(false, 'a query after an idle timeout reconnects — ' . $t->getMessage());
}

/* 2 — a transaction does NOT silently reconnect */
$id = (int)DB::val('SELECT CONNECTION_ID()', [], 0);
$threw = false;
try {
    DB::tx(function () use ($id) {
        DB::run('INSERT INTO wwt_settings (k,v,updated_at) VALUES (?,?,UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE v=VALUES(v)', ['__dbcheck', 'a']);
        killer()->exec('KILL ' . $id);
        usleep(400000);
        DB::run('INSERT INTO wwt_settings (k,v,updated_at) VALUES (?,?,UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE v=VALUES(v)', ['__dbcheck2', 'b']);
    });
} catch (Throwable) { $threw = true; }
$say($threw, 'a transaction that loses its connection fails loudly, not silently');
$left = (int)DB::val('SELECT COUNT(*) FROM wwt_settings WHERE k LIKE ?', ['__dbcheck%'], 0);
$say($left === 0, 'and leaves no half-written rows behind');
DB::run('DELETE FROM wwt_settings WHERE k LIKE ?', ['__dbcheck%']);

/* 3 — a genuine SQL error is still an error, not a reconnect loop */
$threw = false;
try { DB::run('SELECT * FROM a_table_that_does_not_exist'); }
catch (Throwable) { $threw = true; }
$say($threw, 'a real SQL error still raises rather than retrying');

echo $fail ? "\n  $fail failed\n" : "\n  all checks passed\n";
exit($fail ? 1 : 0);
