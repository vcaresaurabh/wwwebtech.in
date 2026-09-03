<?php
/* ============================================================
   SEO health — what the checks found, in the order that matters.

   Failures first, then warnings, then facts, then the things that are
   fine. Nothing is scored out of 100: a single number hides exactly the
   detail that makes a finding fixable.
   ============================================================ */
declare(strict_types=1);

/* Reached only through admin/index.php, which has already checked the session,
   the CSRF token and the role. A direct request must not run any of this. */
if (!defined('WWT_ADMIN')) { http_response_code(404); exit; }


require_once WWT_PRIVATE . '/lib/task.php';

$action = field($_POST, 'action', 40);
if ($action !== '') {
    Auth::requireAdmin();
    try {
        switch ($action) {
            case 'run_daily':
            case 'run_weekly': {
                /* Run here, in this process.
                
                   This used to shell out to the CLI. That works on a
                   developer's machine and not on this host, where exec,
                   shell_exec, system, passthru and popen are all in
                   disable_functions — so the button answered "Call to
                   undefined function exec()". Jobs::run() raises the time
                   limit and keeps going if the browser disconnects. */
                $job = $action === 'run_daily' ? 'seo_daily' : 'seo_weekly';
                $results = Jobs::run($job);
                $line = Jobs::describe($results);
                audit('seo_run', cut($line, 200), Auth::email());
                redirect('/admin/?p=seo', Jobs::allFailed($results) ? 'bad' : 'ok', cut($line, 240));
            }
            case 'save_key': {
                $k = trim((string)($_POST['pagespeed_key'] ?? ''));
                Secrets::put('pagespeed_key', $k);
                audit('seo_key', $k === '' ? 'cleared' : 'set', Auth::email());
                redirect('/admin/?p=seo', 'ok', $k === '' ? 'PageSpeed key cleared.' : 'PageSpeed key saved.');
            }
        }
    } catch (Throwable $t) {
        redirect('/admin/?p=seo', 'bad', $t->getMessage());
    }
}

$latestDay = DB::val('SELECT MAX(d) FROM wwt_seo_checks');
$checks = $latestDay
    ? DB::all("SELECT * FROM wwt_seo_checks WHERE d = ?
               ORDER BY FIELD(status,'fail','warn','info','ok'), check_name", [$latestDay])
    : [];

$counts = ['fail' => 0, 'warn' => 0, 'info' => 0, 'ok' => 0];
foreach ($checks as $c) $counts[$c['status']] = ($counts[$c['status']] ?? 0) + 1;

$daily  = DB::one("SELECT * FROM wwt_task_runs WHERE task='seo_daily'");
$weekly = DB::one("SELECT * FROM wwt_task_runs WHERE task='seo_weekly'");
$vitals = DB::all("SELECT * FROM wwt_cwv ORDER BY d DESC, id DESC LIMIT 6");
$hasKey = Secrets::get('pagespeed_key', (string)cfg('pagespeed_api_key', '')) !== '';

/* Trend: how many things were wrong on each of the last 14 days. */
$trend = DB::all("SELECT d, SUM(status IN ('fail','warn')) bad, COUNT(*) total
                  FROM wwt_seo_checks WHERE d >= DATE_SUB(UTC_DATE(), INTERVAL 13 DAY)
                  GROUP BY d ORDER BY d");

layout_top('SEO health', 'seo');
?>
<div class="page-head">
  <div>
    <h1>SEO health</h1>
    <p>Run against the live site over the internet, so these are the pages a visitor
       and a search engine actually get — not what the build intended to produce.</p>
  </div>
  <?php if (Auth::isAdmin()): ?>
  <div class="row" style="gap:.4rem">
    <form method="post" style="margin:0"><?= Csrf::field() ?>
      <input type="hidden" name="action" value="run_daily">
      <button class="btn btn--ghost btn--sm" type="submit">Run the daily checks</button></form>
    <form method="post" style="margin:0"><?= Csrf::field() ?>
      <input type="hidden" name="action" value="run_weekly">
      <button class="btn btn--ghost btn--sm" type="submit">Run the full crawl</button></form>
  </div>
  <?php endif; ?>
</div>

<?php if (!$checks): ?>
  <div class="card">
    <?php empty_state('No checks have run yet',
      'The daily cron entry in DEPLOY.md runs these automatically. Use the buttons above to run them now.'); ?>
  </div>
<?php else: ?>

<div class="kpis">
  <div class="kpi"><div class="kpi__k">Failing</div>
    <div class="kpi__v"><?= (int)$counts['fail'] ?></div>
    <div class="kpi__d <?= $counts['fail'] ? 'down' : 'flat' ?>">
      <?= $counts['fail'] ? 'costing you something now' : 'nothing broken' ?></div></div>
  <div class="kpi"><div class="kpi__k">Warnings</div>
    <div class="kpi__v"><?= (int)$counts['warn'] ?></div>
    <div class="kpi__d flat">worth fixing</div></div>
  <?php
  /* "9 of 10" with zero failing and zero warnings does not add up for a
     reader — the tenth was an INFO note, which is neither passing nor a
     problem. Name it rather than leave the arithmetic broken. */
  $passingNote = $counts['info'] > 0
      ? $counts['info'] . ($counts['info'] === 1 ? ' note' : ' notes') . ', nothing to fix'
      : 'of ' . count($checks) . ' checks';
  ?>
  <div class="kpi"><div class="kpi__k">Passing</div>
    <div class="kpi__v"><?= (int)$counts['ok'] ?></div>
    <div class="kpi__d flat"><?= e($passingNote) ?></div></div>
  <div class="kpi"><div class="kpi__k">Last run</div>
    <div class="kpi__v" style="font-size:1.15rem"><?= e(local_time(($daily['last_end'] ?? '') ?: '', 'd M, H:i') ?: '—') ?></div>
    <div class="kpi__d flat">
      <?= $weekly && $weekly['last_end'] ? 'full crawl ' . e(local_time($weekly['last_end'], 'd M')) : 'full crawl not yet run' ?></div></div>
</div>

<?php if (count($trend) > 1): ?>
<section class="card" style="margin-bottom:1rem">
  <h2>Things needing attention, by day</h2>
  <?= svg_timeseries(array_map(static fn($r) => [
        'd' => (string)$r['d'], 'views' => (int)$r['bad'], 'visitors' => 0], $trend), 120) ?>
  <p class="small muted" style="margin-top:.3rem">Lower is better. A flat line at zero is the goal.</p>
</section>
<?php endif; ?>

<div class="stack">
<?php foreach ($checks as $c):
  $pill = ['fail' => 'fail', 'warn' => 'warn', 'info' => 'info', 'ok' => 'ok'][$c['status']] ?? 'info';
  $edge = ['fail' => 'var(--bad)', 'warn' => 'var(--warn)', 'info' => 'var(--info)', 'ok' => 'var(--rule)'][$c['status']];
?>
  <section class="card" style="border-left:3px solid <?= $edge ?>">
    <div class="row" style="justify-content:space-between;align-items:baseline">
      <h2 style="text-transform:capitalize"><?= e($c['check_name']) ?></h2>
      <span class="pill pill--<?= e($pill) ?>"><?= e($c['status']) ?></span>
    </div>
    <?php if ($c['detail']): ?>
      <?php $lines = preg_split('/\R/', (string)$c['detail']) ?: []; ?>
      <?php if (count($lines) > 1): ?>
        <pre class="small" style="white-space:pre-wrap;margin-top:.5rem;color:var(--ink-2);
             font-family:var(--font);line-height:1.55"><?= e((string)$c['detail']) ?></pre>
      <?php else: ?>
        <p class="small" style="margin-top:.4rem;color:var(--ink-2)"><?= e((string)$c['detail']) ?></p>
      <?php endif; ?>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>

<?php endif; ?>

<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr));margin-top:1rem">
  <section class="card">
    <h2>Speed, from Google's own data</h2>
    <?php if (!$hasKey): ?>
      <p class="small muted" style="margin:.4rem 0 .8rem">
        Not being measured. Core Web Vitals come from Google's record of real Chrome visitors,
        which needs a free PageSpeed API key. Without one this panel stays empty rather than
        showing a lab number that is not what your visitors experience.</p>
    <?php elseif (!$vitals): ?>
      <p class="small muted" style="margin:.4rem 0 .8rem">
        A key is set but no measurement has been taken yet. It runs with the weekly crawl.</p>
    <?php else: ?>
      <div class="tablewrap" style="margin-top:.6rem"><table>
        <thead><tr><th>Date</th><th>Where</th><th class="r">Score</th><th class="r">LCP</th>
          <th class="r">INP</th><th class="r">CLS</th></tr></thead><tbody>
        <?php foreach ($vitals as $v): ?>
          <tr><td class="n small"><?= e((string)$v['d']) ?></td>
              <td class="small"><?= e($v['strategy']) ?></td>
              <td class="r"><?= $v['perf'] === null ? '—' : (int)$v['perf'] ?></td>
              <td class="r <?= $v['lcp_ms'] !== null && (int)$v['lcp_ms'] > 2500 ? '' : '' ?>">
                <?= $v['lcp_ms'] === null ? '—' : number_format((int)$v['lcp_ms']) . 'ms' ?></td>
              <td class="r"><?= $v['inp_ms'] === null ? '—' : (int)$v['inp_ms'] . 'ms' ?></td>
              <td class="r"><?= $v['cls'] === null ? '—' : number_format((float)$v['cls'], 3) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>

    <?php if (Auth::isAdmin()): ?>
    <form method="post" style="margin-top:.8rem;padding-top:.8rem;border-top:1px solid var(--rule)">
      <?= Csrf::field() ?><input type="hidden" name="action" value="save_key">
      <div class="field"><label for="psk">PageSpeed API key</label>
        <input id="psk" name="pagespeed_key" type="password" autocomplete="off"
               placeholder="<?= $hasKey ? 'saved — leave blank to keep it' : 'free, from the Google Cloud console' ?>">
        <p class="hint">Stored encrypted. Blank clears it.</p></div>
      <button class="btn btn--sm" type="submit">Save</button>
    </form>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>What these checks are</h2>
    <dl class="dl" style="margin-top:.6rem">
      <dt>Daily</dt><dd>Site up, sitemap valid, every listed page loads, titles and
        descriptions within limits, one h1 per page, canonical present, structured data valid,
        AI crawlers allowed, security headers present.</dd>
      <dt>Weekly</dt><dd>A full internal link crawl, llms.txt and llms-full.txt regenerated,
        and Core Web Vitals from Google.</dd>
      <dt>On publish</dt><dd>llms.txt refreshed and new URLs submitted to IndexNow, which
        Bing and Yandex read. Google does not take IndexNow; it reads the sitemap, which is
        updated at the same moment.</dd>
    </dl>
    <p class="small muted" style="margin-top:.8rem">
      Nothing here is scored out of 100. A single number would hide the one line that tells
      you what to fix.</p>
  </section>
</div>
<?php layout_bottom();
