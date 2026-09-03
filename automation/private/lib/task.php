<?php
/* ============================================================
   task.php — how every scheduled job runs.

   One wrapper around all of them so that each one, without having to
   remember to:

     · records a heartbeat the dashboard can show;
     · cannot overlap with a previous run of itself that is still going;
     · records its failure instead of dying silently into a cron log
       nobody reads;
     · leaves the database in a state that says what happened.

   An unattended job that fails quietly is worse than no job at all, so
   the failure path here gets the same care as the success path.
   ============================================================ */

declare(strict_types=1);

final class Task
{
    /** A run still marked "running" after this long is treated as dead. */
    public const STALE_MINUTES = 55;

    /**
     * Run $fn under the name $task. Returns the callable's own return value,
     * or null when it was skipped or it failed.
     *
     * @param callable(): (string|null) $fn  return a short summary line
     */
    public static function run(string $task, callable $fn, bool $force = false): ?string
    {
        $task = cut($task, 40);

        /* File lock first: two overlapping cron invocations are the normal
           way a job that got slow turns into a job that corrupts something. */
        $lockDir = WWT_PRIVATE . '/logs';
        if (!is_dir($lockDir)) @mkdir($lockDir, 0750, true);
        $lockPath = $lockDir . '/.lock-' . preg_replace('/[^a-z0-9_]/i', '', $task);
        $lock = @fopen($lockPath, 'c');
        if ($lock === false) {
            wwt_log('task', 'cannot open lock file', ['task' => $task]);
        } elseif (!flock($lock, LOCK_EX | LOCK_NB)) {
            wwt_log('task', 'already running, skipped', ['task' => $task]);
            return null;
        }

        /* Getting the lock proves any previous run is dead — flock releases
           when the process ends, however it ended. So a row still saying
           "running" is the record of a run that was killed part-way, and it
           would otherwise sit on the dashboard as a stalled job forever,
           because a job nobody schedules never runs again to clear it. */
        self::closeAbandoned($task);

        self::mark($task, 'running', null);
        $started = microtime(true);

        try {
            $summary = $fn();
            $secs = round(microtime(true) - $started, 1);
            self::mark($task, 'ok', null, (string)$summary . ' · ' . $secs . 's');
            wwt_log('task', 'ok', ['task' => $task, 'secs' => $secs, 'summary' => $summary]);
            return (string)$summary;
        } catch (Throwable $t) {
            $msg = $t->getMessage() . ' (' . basename($t->getFile()) . ':' . $t->getLine() . ')';
            self::mark($task, 'fail', $msg);
            wwt_log('task', 'FAILED', ['task' => $task, 'err' => $msg]);
            audit('task_fail', $task . ': ' . cut($msg, 300));
            return null;
        } finally {
            if ($lock !== false) { @flock($lock, LOCK_UN); @fclose($lock); }
        }
    }

    /**
     * Record a previous run that was left marked "running" as what it was:
     * a run that did not finish. Called only once the lock has been taken,
     * which is the proof that nothing is still running.
     */
    private static function closeAbandoned(string $task): void
    {
        try {
            $row = DB::one('SELECT last_status, last_start FROM wwt_task_runs WHERE task = ?', [$task]);
            if (!$row || $row['last_status'] !== 'running') return;
            DB::run(
                "UPDATE wwt_task_runs SET last_status = 'fail', last_end = UTC_TIMESTAMP(),
                 last_error = ? WHERE task = ?",
                ['Did not finish — the run was interrupted (started ' . (string)$row['last_start'] . ' UTC).', $task]
            );
            wwt_log('task', 'closed an abandoned run', ['task' => $task]);
        } catch (Throwable $t) {
            wwt_log('task', 'could not close abandoned run', ['task' => $task, 'err' => $t->getMessage()]);
        }
    }

    private static function mark(string $task, string $status, ?string $error, ?string $summary = null): void
    {
        try {
            if ($status === 'running') {
                DB::run(
                    'INSERT INTO wwt_task_runs (task, last_start, last_status, run_count)
                     VALUES (?, UTC_TIMESTAMP(), \'running\', 1)
                     ON DUPLICATE KEY UPDATE last_start = UTC_TIMESTAMP(),
                       last_status = \'running\', run_count = run_count + 1',
                    [$task]
                );
                return;
            }
            DB::run(
                'UPDATE wwt_task_runs SET last_end = UTC_TIMESTAMP(), last_status = ?, last_error = ?
                 WHERE task = ?',
                [$status, $status === 'ok' ? $summary : $error, $task]
            );
        } catch (Throwable $t) {
            wwt_log('task', 'heartbeat write failed', ['task' => $task, 'err' => $t->getMessage()]);
        }
    }

    /**
     * Jobs that say they are running but have not finished in a long time.
     * Shown on the dashboard, because "running" forever looks like "fine"
     * on a status page and is anything but.
     */
    public static function stalled(): array
    {
        try {
            return DB::all(
                "SELECT * FROM wwt_task_runs
                 WHERE last_status = 'running'
                   AND last_start < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE)",
                [self::STALE_MINUTES]
            );
        } catch (Throwable) { return []; }
    }

    /** True when $task has not completed successfully within $hours. */
    public static function overdue(string $task, int $hours): bool
    {
        try {
            $row = DB::one('SELECT last_end, last_status FROM wwt_task_runs WHERE task = ?', [$task]);
            if (!$row || $row['last_end'] === null) return true;
            return strtotime((string)$row['last_end'] . ' UTC') < time() - $hours * 3600;
        } catch (Throwable) { return false; }
    }
}
