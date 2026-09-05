<?php
declare(strict_types=1);

/* Reached only through admin/index.php, which has already checked the session,
   the CSRF token and the role. A direct request must not run any of this. */
if (!defined('WWT_ADMIN')) { http_response_code(404); exit; }

/* Actions run BEFORE layout_top(), because redirect() sends a Location
   header and layout_top() has already begun the response body. */
$action = field($_POST, 'action', 40);
if ($action !== '') {
    Auth::requireAdmin();
    try {
        switch ($action) {
            case 'retry_job': {
                $job = field($_POST, 'task', 40);
                if (!Jobs::exists($job)) throw new InvalidArgumentException('There is no job by that name.');
                $results = Jobs::run($job);
                audit('job_retry', cut(Jobs::describe($results), 200), Auth::email());
                redirect('/admin/', Jobs::allFailed($results) ? 'bad' : 'ok', Jobs::describe($results));
            }
            case 'forget_job': {
                /* Clears the heartbeat record, not the problem. Offered because a
                   one-off manual action that failed once has no schedule to clear
                   it, and would otherwise alarm on this page forever. */
                $job = field($_POST, 'task', 40);
                DB::run('DELETE FROM wwt_task_runs WHERE task = ?', [$job]);
                audit('job_forget', $job, Auth::email());
                redirect('/admin/', 'ok', 'Cleared the record for ' . $job
                    . '. It reappears the next time that job runs.');
            }
        }
    } catch (Throwable $t) {
        redirect('/admin/', 'bad', $t->getMessage());
    }
}

/** The buttons offered beside a failed job: the two things that resolve it. */
function job_alert_actions(string $task): string
{
    $t = e($task);
    $retry = Jobs::exists($task)
        ? '<form method="post" style="display:inline-block;margin-right:.4rem">' . Csrf::field()
          . '<input type="hidden" name="action" value="retry_job">'
          . '<input type="hidden" name="task" value="' . $t . '">'
          . '<button class="btn btn--sm" type="submit">Run it again</button></form>'
        : '';
    return '<div style="margin-top:.6rem">' . $retry
        . '<form method="post" style="display:inline-block">' . Csrf::field()
        . '<input type="hidden" name="action" value="forget_job">'
        . '<input type="hidden" name="task" value="' . $t . '">'
        . '<button class="btn btn--ghost btn--sm" type="submit">Clear this record</button></form>'
        . '</div>';
}

layout_top('Dashboard', 'dashboard');

/* Everything here degrades to an honest zero/empty state before any
   data has been collected — no seeded demo numbers, ever. */
$leads24  = (int)DB::val("SELECT COUNT(*) FROM wwt_leads WHERE ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)", [], 0);
$leads7   = (int)DB::val("SELECT COUNT(*) FROM wwt_leads WHERE ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)", [], 0);
$leadsNew = (int)DB::val("SELECT COUNT(*) FROM wwt_leads WHERE status='new'", [], 0);
$views7   = (int)DB::val("SELECT COALESCE(SUM(views),0) FROM wwt_daily_rollups WHERE d >= DATE_SUB(UTC_DATE(), INTERVAL 7 DAY)", [], 0);
$vis7     = (int)DB::val("SELECT COALESCE(SUM(visitors),0) FROM wwt_daily_rollups WHERE d >= DATE_SUB(UTC_DATE(), INTERVAL 7 DAY)", [], 0);
$posts    = (int)DB::val("SELECT COUNT(*) FROM wwt_posts WHERE status='published'", [], 0);
$topicsLeft = (int)DB::val("SELECT COUNT(*) FROM wwt_topics WHERE used_at IS NULL", [], 0);

require_once WWT_PRIVATE . '/lib/task.php';

$tasks = DB::all("SELECT * FROM wwt_task_runs ORDER BY task");

/* The things worth interrupting someone for, gathered in one place so the
   dashboard leads with them instead of burying them in a table. */
$alerts = [];
$conn = Connections::summary();
if ($conn['errors']) {
    $alerts[] = ['bad', '<b>' . e(implode(', ', $conn['errors'])) . '</b> ' . (count($conn['errors']) === 1 ? 'has' : 'have')
                        . ' stopped working — <a href="/admin/?p=connections" style="text-decoration:underline">open Connections</a>.'];
}
foreach (Task::stalled() as $t) {
    $alerts[] = ['bad', 'The job <b>' . e($t['task']) . '</b> started ' . e(local_time($t['last_start']))
        . ' and never finished. It may be stuck.'];
}
foreach ($tasks as $t) {
    if ($t['last_status'] !== 'fail') continue;
    /* Say WHEN it failed, and offer the two things that actually resolve it.
       A failure with neither is an alarm nobody can switch off — which is
       what a single manual run that failed once looked like. */
    $when = $t['last_end'] ? local_time($t['last_end'], 'd M, H:i') : 'at some point';
    $alerts[] = ['bad',
        'The job <b>' . e($t['task']) . '</b> failed ' . e($when) . ': '
        . e(cut((string)$t['last_error'], 200))
        . (Auth::isAdmin() ? job_alert_actions((string)$t['task']) : '')];
}
$mailStuck = (int)DB::val("SELECT COUNT(*) FROM wwt_leads
                           WHERE mail_status IN ('failed','skipped') AND is_test = 0", [], 0);
if ($mailStuck > 0) {
    $alerts[] = ['warn', '<b>' . $mailStuck . '</b> ' . ($mailStuck === 1 ? 'enquiry was' : 'enquiries were')
        . ' saved but not emailed. They are safe — see <a href="/admin/?p=leads">Leads</a>.'];
}
if (!$tasks) {
    $alerts[] = ['warn', 'No scheduled job has ever run. The three cron entries in DEPLOY.md '
        . 'still need to be created in hPanel.'];
} elseif (Task::overdue('analytics_hourly', 3)) {
    $alerts[] = ['warn', 'The hourly job has not run for over three hours. Check hPanel → Cron Jobs.'];
}
$seoFails = (int)DB::val("SELECT COUNT(*) FROM wwt_seo_checks
                          WHERE d = (SELECT MAX(d) FROM wwt_seo_checks) AND status = 'fail'", [], 0);
if ($seoFails > 0) {
    $alerts[] = ['warn', '<b>' . $seoFails . '</b> SEO ' . ($seoFails === 1 ? 'check is' : 'checks are')
        . ' failing on the live site — see <a href="/admin/?p=seo">SEO health</a>.'];
}
$seo   = DB::all("SELECT * FROM wwt_seo_checks WHERE d = (SELECT MAX(d) FROM wwt_seo_checks) ORDER BY FIELD(status,'fail','warn','info','ok'), check_name");
$recent= DB::all("SELECT * FROM wwt_leads ORDER BY ts DESC LIMIT 5");
?>
<div class="page-head">
  <div>
    <h1>Dashboard</h1>
    <p>Everything the automation did, and everything it found. Numbers are real collected
       data — nothing is seeded or estimated.</p>
  </div>
</div>

<?php foreach ($alerts as [$kind, $msg]): ?>
  <div class="alert alert--<?= e($kind) ?>"><?= $msg /* built above from escaped parts */ ?></div>
<?php endforeach; ?>

<div class="kpis">
  <div class="kpi"><div class="kpi__k">Leads · 24h</div><div class="kpi__v"><?= $leads24 ?></div>
    <div class="kpi__d flat"><?= $leads7 ?> in the last 7 days</div></div>
  <div class="kpi"><div class="kpi__k">Unactioned</div><div class="kpi__v"><?= $leadsNew ?></div>
    <div class="kpi__d <?= $leadsNew > 0 ? 'down' : 'flat' ?>"><?= $leadsNew > 0 ? 'waiting on a reply' : 'all handled' ?></div></div>
  <div class="kpi"><div class="kpi__k">Visitors · 7d</div><div class="kpi__v"><?= number_format($vis7) ?></div>
    <div class="kpi__d flat"><?= number_format($views7) ?> pageviews</div></div>
  <div class="kpi"><div class="kpi__k">Connections</div><div class="kpi__v"><?= (int)$conn['connected'] ?><span class="muted" style="font-size:.6em">/<?= (int)$conn['total'] ?></span></div>
    <div class="kpi__d <?= $conn['errors'] ? 'down' : 'flat' ?>"><a href="/admin/?p=connections" style="text-decoration:underline"><?= e($conn['errors'] ? implode(', ', $conn['errors']) . ' failing' : ($conn['unconfigured'] ? count($conn['unconfigured']) . ' not configured' : 'all connected')) ?></a></div></div>
  <div class="kpi"><div class="kpi__k">Posts live</div><div class="kpi__v"><?= $posts ?></div>
    <div class="kpi__d <?= $topicsLeft < 14 ? 'down' : 'flat' ?>"><?= $topicsLeft ?> topics queued</div></div>
</div>

<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">

  <section class="card card--pad0">
    <div style="padding:.85rem 1.1rem;border-bottom:1px solid var(--rule)">
      <h2>Automation heartbeat</h2>
      <p class="small muted">When each scheduled job last ran.</p>
    </div>
    <?php if (!$tasks): ?>
      <?php empty_state('No jobs have run yet',
        'Once the cron entries in DEPLOY.md are added, every run reports back here.'); ?>
    <?php else: ?>
    <div class="tablewrap" style="border:0">
      <table><thead><tr><th>Task &amp; what it did</th><th>Last run</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($tasks as $t): ?>
        <tr>
          <td class="n"><code class="mono" style="font-size:11px;text-transform:none"><?= e($t['task']) ?></code>
            <?php if ($t['last_error']): ?>
              <?php /* On a successful run this column holds the run's summary, not an
                       error. Colouring it red regardless made every healthy job look
                       broken. */ ?>
              <?php /* Capped and wrapping: without a max-width the summary makes
                       column one as wide as it likes and pushes Status out of
                       the card. */ ?>
              <div class="small" style="margin-top:.2rem;max-width:19rem;white-space:normal;
                   color:var(--<?= $t['last_status'] === 'fail' ? 'bad' : 'ink-3' ?>)">
                <?= e(cut((string)$t['last_error'], 160)) ?></div>
            <?php endif; ?></td>
          <td class="n"><?= e($t['last_end'] ? local_time($t['last_end']) : '—') ?></td>
          <td><span class="pill pill--<?= $t['last_status'] === 'ok' ? 'ok' : ($t['last_status'] === 'running' ? 'info' : 'fail') ?>"><?= e($t['last_status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
    <?php endif; ?>
  </section>

  <section class="card card--pad0">
    <div style="padding:.85rem 1.1rem;border-bottom:1px solid var(--rule)">
      <h2>Latest SEO checks</h2>
      <p class="small muted">From the most recent daily run.</p>
    </div>
    <?php if (!$seo): ?>
      <?php empty_state('No checks yet', 'The daily SEO job posts its findings here.'); ?>
    <?php else: ?>
    <div class="tablewrap" style="border:0">
      <table><thead><tr><th>Check</th><th>Result</th></tr></thead><tbody>
      <?php foreach (array_slice($seo, 0, 10) as $c): ?>
        <tr><td><?= e($c['check_name']) ?><?php if ($c['detail']): ?>
              <div class="small muted"><?= e(cut((string)$c['detail'], 90)) ?></div><?php endif; ?></td>
            <td><span class="pill pill--<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
    <?php endif; ?>
  </section>

  <section class="card card--pad0" style="grid-column:1/-1">
    <div style="padding:.85rem 1.1rem;border-bottom:1px solid var(--rule);display:flex;justify-content:space-between;align-items:center">
      <div><h2>Latest leads</h2><p class="small muted">Newest first.</p></div>
      <a class="btn btn--ghost btn--sm" href="/admin/?p=leads">All leads</a>
    </div>
    <?php if (!$recent): ?>
      <?php empty_state('No leads yet',
        'When someone submits the contact form, they appear here within seconds — and you get an email.'); ?>
    <?php else: ?>
    <div class="tablewrap" style="border:0">
      <table><thead><tr><th>When</th><th>Name</th><th>Service</th><th>Source</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($recent as $l): ?>
        <tr>
          <td class="n"><?= e(local_time($l['ts'])) ?></td>
          <td><a href="/admin/?p=lead&amp;id=<?= (int)$l['id'] ?>" style="text-decoration:underline"><?= e($l['name']) ?></a></td>
          <td><?= e($l['service'] ?: '—') ?></td>
          <td class="small"><?= e($l['utm_source'] ?: ($l['referrer'] ? ref_domain($l['referrer']) : 'direct')) ?></td>
          <td><span class="pill pill--<?= e($l['status']) ?>"><?= e($l['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
    <?php endif; ?>
  </section>
</div>
<?php layout_bottom();
