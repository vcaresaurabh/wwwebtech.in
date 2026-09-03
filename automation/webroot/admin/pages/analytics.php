<?php
/* ============================================================
   Analytics — what actually happened on the site.

   Everything on this page is counted from rows this site collected
   itself. Where a number cannot be known (country, without a lookup
   source) the panel says so rather than showing a plausible guess.
   ============================================================ */
declare(strict_types=1);

/* Reached only through admin/index.php, which has already checked the session,
   the CSRF token and the role. A direct request must not run any of this. */
if (!defined('WWT_ADMIN')) { http_response_code(404); exit; }


$action = field($_POST, 'action', 40);
if ($action !== '') {
    Auth::requireAdmin();
    try {
        switch ($action) {
            case 'purge_tests': {
                $n = DB::run('DELETE FROM wwt_hits WHERE is_test = 1')->rowCount();
                audit('hits_purge_tests', (string)$n, Auth::email());
                redirect('/admin/?p=analytics', 'ok', $n . ' test hits removed.');
            }
            case 'rollup_now': {
                /* The same job the hourly cron runs — one definition, so what
                   this button does and what the schedule does cannot drift. */
                $results = Jobs::run('analytics_hourly');
                redirect('/admin/?p=analytics', Jobs::allFailed($results) ? 'bad' : 'ok',
                         Jobs::describe($results));
            }
        }
    } catch (Throwable $t) {
        redirect('/admin/?p=analytics', 'bad', $t->getMessage());
    }
}

$days   = (int)gets('days', '30', ['7', '30', '90', '365']);
$series = Analytics::series($days);
$tot    = Analytics::totals($days);
$pages  = Analytics::topBy('path', $days);
$srcs   = Analytics::topBy('source', $days);
$devs   = Analytics::topBy('device', $days);
$bots   = Analytics::crawlers($days);

/* Engagement and conversions come from raw hits, so they only reach back
   as far as the retention window — said plainly under the numbers. */
$retention = Analytics::retentionDays();
$rawDays   = min($days, $retention);
$engaged   = (int)DB::val("SELECT COUNT(DISTINCT session_hash) FROM wwt_hits
                           WHERE event='engaged' AND is_test=0 AND session_hash<>''
                             AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)", [$rawDays], 0);
$sessions  = (int)DB::val("SELECT COUNT(DISTINCT session_hash) FROM wwt_hits
                           WHERE is_bot=0 AND is_test=0 AND session_hash<>''
                             AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)", [$rawDays], 0);
$clicks    = DB::all("SELECT event_detail k, COUNT(*) n FROM wwt_hits
                      WHERE event IN ('click','outbound') AND is_test=0
                        AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
                      GROUP BY event_detail ORDER BY n DESC LIMIT 8", [$rawDays]);

$leads     = (int)DB::val("SELECT COUNT(*) FROM wwt_leads WHERE is_test=0
                           AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)", [$days], 0);
$leadSrc   = DB::all("SELECT COALESCE(NULLIF(utm_source,''), 'direct or unknown') k, COUNT(*) n
                      FROM wwt_leads WHERE is_test=0
                        AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
                      GROUP BY k ORDER BY n DESC LIMIT 8", [$days]);

$testHits  = (int)DB::val('SELECT COUNT(*) FROM wwt_hits WHERE is_test = 1', [], 0);
$rawTotal  = (int)DB::val('SELECT COUNT(*) FROM wwt_hits', [], 0);
$collecting = Settings::bool('analytics_enabled', true);
$lastRoll  = DB::one("SELECT last_end, last_status FROM wwt_task_runs WHERE task='analytics_hourly'");

$aiSeen = 0;
foreach ($bots as $b) if (Analytics::isAiCrawler((string)$b['k'])) $aiSeen += (int)$b['views'];

$maxPage = $pages ? max(array_map(static fn($r) => (int)$r['views'], $pages)) : 0;
$maxSrc  = $srcs  ? max(array_map(static fn($r) => (int)$r['views'], $srcs))  : 0;

layout_top('Analytics', 'analytics');
?>
<div class="page-head">
  <div>
    <h1>Analytics</h1>
    <p>First-party and cookieless. No cookie is set, no IP address is stored, and
       nothing is shared with anyone — which is why this site needs no cookie banner.</p>
  </div>
  <div class="chipbar">
    <?php foreach (['7' => '7 days', '30' => '30 days', '90' => '90 days', '365' => '12 months'] as $k => $v): ?>
      <a href="<?= e(qs(['days' => $k])) ?>"<?= $days === (int)$k ? ' aria-current="true"' : '' ?>><?= e($v) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!$collecting): ?>
  <div class="alert alert--warn">
    <b>Collection is switched off.</b> Nothing new is being recorded, so these numbers
    stop at whatever was gathered before. Turn it back on in
    <a href="/admin/?p=settings" style="text-decoration:underline">Settings</a>.
  </div>
<?php endif; ?>

<?php if ($rawTotal === 0 && $tot['views'] === 0): ?>
  <div class="card">
    <?php empty_state('Nothing recorded yet',
      'Once assets/js/wa.js is live on the site, visits appear here within the hour. '
    . 'There is no sample data — this page only ever shows what was actually collected.'); ?>
  </div>
<?php else: ?>

<div class="kpis">
  <div class="kpi"><div class="kpi__k">Visitors</div>
    <div class="kpi__v"><?= number_format($tot['visitors']) ?></div>
    <div class="kpi__d flat">last <?= $days ?> days</div></div>
  <div class="kpi"><div class="kpi__k">Pageviews</div>
    <div class="kpi__v"><?= number_format($tot['views']) ?></div>
    <div class="kpi__d flat"><?= e($tot['visitors'] > 0
      ? number_format($tot['views'] / $tot['visitors'], 1) . ' per visitor' : '—') ?></div></div>
  <div class="kpi"><div class="kpi__k">Read, not bounced</div>
    <div class="kpi__v"><?= $sessions > 0 ? round($engaged / $sessions * 100) . '%' : '—' ?></div>
    <div class="kpi__d flat"><?= number_format($engaged) ?> of <?= number_format($sessions) ?> sessions</div></div>
  <?php
  /* A conversion rate is only meaningful when both halves were counted over
     the same traffic. More enquiries than visitors means analytics started
     after the form did — printing "200%" would be arithmetic dressed up as
     a finding. */
  $conversion = $tot['visitors'] <= 0
      ? 'no visitors recorded yet'
      : ($leads > $tot['visitors']
          ? 'more than the visitors recorded — analytics started later'
          : number_format($leads / $tot['visitors'] * 100, 2) . '% of visitors');
  ?>
  <div class="kpi"><div class="kpi__k">Enquiries</div>
    <div class="kpi__v"><?= number_format($leads) ?></div>
    <div class="kpi__d flat"><?= e($conversion) ?></div></div>
</div>

<section class="card" style="margin-bottom:1rem">
  <div class="row" style="justify-content:space-between;align-items:baseline">
    <h2>Traffic</h2>
    <span class="small muted">
      <?php if ($lastRoll && $lastRoll['last_end']): ?>
        totals rebuilt <?= e(local_time($lastRoll['last_end'], 'd M, H:i')) ?>
      <?php else: ?>
        the hourly rollup has not run yet
      <?php endif; ?>
    </span>
  </div>
  <?= svg_timeseries($series) ?>
  <div class="chart-key">
    <span><i></i>Pageviews</span>
    <span class="k2"><i></i>Visitors</span>
  </div>
</section>

<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">

  <section class="card card--pad0">
    <div style="padding:.85rem 1.1rem;border-bottom:1px solid var(--rule)">
      <h2>Pages</h2><p class="small muted">Most read, this period.</p></div>
    <?php if (!$pages): ?><?php empty_state('No pageviews yet', 'They appear after the first rollup.'); ?>
    <?php else: ?>
    <div class="tablewrap" style="border:0"><table>
      <thead><tr><th>Page</th><th class="r">Views</th><th class="r">Visitors</th></tr></thead><tbody>
      <?php foreach ($pages as $r): ?>
        <tr><td><a href="<?= e(rtrim((string)cfg('site.url', ''), '/') . $r['k']) ?>" target="_blank"
                   rel="noopener" style="text-decoration:underline"><?= e($r['k']) ?></a>
              <?= bar((int)$r['views'], $maxPage) ?></td>
            <td class="r"><?= number_format((int)$r['views']) ?></td>
            <td class="r muted"><?= number_format((int)$r['visitors']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="card card--pad0">
    <div style="padding:.85rem 1.1rem;border-bottom:1px solid var(--rule)">
      <h2>Where they came from</h2>
      <p class="small muted">Grouped from the referrer and any campaign tags.</p></div>
    <?php if (!$srcs): ?><?php empty_state('No sources yet', 'They appear after the first rollup.'); ?>
    <?php else: ?>
    <div class="tablewrap" style="border:0"><table>
      <thead><tr><th>Source</th><th class="r">Views</th><th class="r">Visitors</th></tr></thead><tbody>
      <?php foreach ($srcs as $r): ?>
        <tr><td><?= e($r['k']) ?><?= bar((int)$r['views'], $maxSrc) ?></td>
            <td class="r"><?= number_format((int)$r['views']) ?></td>
            <td class="r muted"><?= number_format((int)$r['visitors']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="card card--pad0">
    <div style="padding:.85rem 1.1rem;border-bottom:1px solid var(--rule)">
      <h2>AI crawlers</h2>
      <p class="small muted">The agents behind ChatGPT, Claude, Perplexity and Google's AI
         answers. None of them run JavaScript, so these are counted on the server.</p></div>
    <?php if (!$bots): ?>
      <?php empty_state('No crawler visits recorded',
        'Server-side logging needs the AI-crawler block in .htaccess and serve.php in the web root — see DEPLOY.md.'); ?>
    <?php else: ?>
    <div class="tablewrap" style="border:0"><table>
      <thead><tr><th>Crawler</th><th class="r">Fetches</th><th class="r">Days seen</th><th>Last</th></tr></thead><tbody>
      <?php foreach ($bots as $r): $ai = Analytics::isAiCrawler((string)$r['k']); ?>
        <tr><td><?= e($r['k']) ?>
              <?php if ($ai): ?> <span class="pill pill--qualified">AI</span><?php endif; ?></td>
            <td class="r"><?= number_format((int)$r['views']) ?></td>
            <td class="r muted"><?= (int)$r['days_seen'] ?></td>
            <td class="small muted"><?= e(date('j M', strtotime((string)$r['last_seen']))) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="card card--pad0">
    <div style="padding:.85rem 1.1rem;border-bottom:1px solid var(--rule)">
      <h2>Enquiries by source</h2>
      <p class="small muted">Which campaigns actually produced a message.</p></div>
    <?php if (!$leadSrc): ?><?php empty_state('No enquiries in this period', 'They appear here as soon as one arrives.');
    else: ?>
    <div class="tablewrap" style="border:0"><table>
      <thead><tr><th>Source</th><th class="r">Enquiries</th></tr></thead><tbody>
      <?php foreach ($leadSrc as $r): ?>
        <tr><td><?= e($r['k']) ?></td><td class="r"><?= (int)$r['n'] ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="card card--pad0">
    <div style="padding:.85rem 1.1rem;border-bottom:1px solid var(--rule)">
      <h2>Devices</h2></div>
    <?php if (!$devs): ?><?php empty_state('No device data yet', ''); else: ?>
    <div class="tablewrap" style="border:0"><table>
      <thead><tr><th>Device</th><th class="r">Views</th><th class="r">Share</th></tr></thead><tbody>
      <?php $dTot = array_sum(array_map(static fn($r) => (int)$r['views'], $devs));
            foreach ($devs as $r): ?>
        <tr><td><?= e(ucfirst((string)$r['k'])) ?></td>
            <td class="r"><?= number_format((int)$r['views']) ?></td>
            <td class="r muted"><?= $dTot ? round((int)$r['views'] / $dTot * 100) . '%' : '—' ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="card card--pad0">
    <div style="padding:.85rem 1.1rem;border-bottom:1px solid var(--rule)">
      <h2>Intent clicks</h2>
      <p class="small muted">Email, phone and outbound links — the actions before an enquiry.</p></div>
    <?php if (!$clicks): ?><?php empty_state('No clicks recorded yet', ''); else: ?>
    <div class="tablewrap" style="border:0"><table>
      <thead><tr><th>Target</th><th class="r">Clicks</th></tr></thead><tbody>
      <?php foreach ($clicks as $r): ?>
        <tr><td><?= e((string)$r['k'] ?: '—') ?></td><td class="r"><?= (int)$r['n'] ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="card" style="grid-column:1/-1">
    <h2>Geography</h2>
    <p class="small muted" style="margin-top:.4rem;max-width:70ch">
      Not available. This server provides no country header and has no GeoIP database,
      so the only honest answer is that we do not know where visitors are. Rather than
      guess from the browser's language — which is not location — this panel stays empty
      until a lookup source is configured. The column and the cache table are already in
      place, so switching one on later fills this in without any other change.
    </p>
  </section>
</div>

<?php if (Auth::isAdmin()): ?>
<div class="card" style="margin-top:1rem">
  <div class="row">
    <form method="post" style="margin:0">
      <?= Csrf::field() ?><input type="hidden" name="action" value="rollup_now">
      <button class="btn btn--ghost btn--sm" type="submit">Rebuild totals now</button>
    </form>
    <span class="small muted">Normally hourly, from cron. This runs the same job by hand.</span>
    <span class="spacer"></span>
    <?php if ($testHits > 0): ?>
    <form method="post" style="margin:0"
          onsubmit="return confirm('Delete <?= $testHits ?> test hits? Real traffic is untouched.')">
      <?= Csrf::field() ?><input type="hidden" name="action" value="purge_tests">
      <button class="btn btn--danger btn--sm" type="submit">Delete <?= number_format($testHits) ?> test hits</button>
    </form>
    <?php endif; ?>
  </div>
  <p class="small muted" style="margin-top:.7rem">
    Raw visit rows are kept for <?= $retention ?> days and then deleted; the daily totals
    above are kept indefinitely. Engagement and click figures are read from the raw rows,
    so they only reach back <?= $retention ?> days.
  </p>
</div>
<?php endif; ?>

<?php endif; ?>
<?php layout_bottom();
