<?php
/* ============================================================
   Audits — the free website audit, and what it has found.

   Every audit here is also a lead, so the useful column is not the
   score, it is what happened next. A tool that generates addresses
   nobody follows up is a cost, not a lead magnet.
   ============================================================ */
declare(strict_types=1);
if (!defined('WWT_ADMIN')) { http_response_code(404); exit; }

$action = field($_POST, 'action', 40);
if ($action !== '') {
    Auth::requireAdmin();
    try {
        switch ($action) {
            case 'run': {
                $out = Jobs::run('audit_run');
                redirect('/admin/?p=audits', 'ok', 'Ran now: ' . cut($out, 180));
            }
            case 'requeue': {
                $id = (int)($_POST['id'] ?? 0);
                DB::run("UPDATE wwt_audits SET status = 'queued', error = '', started_at = NULL,
                         finished_at = NULL WHERE id = ? AND status IN ('failed','running')", [$id]);
                redirect('/admin/?p=audits', 'ok', 'Queued again — it runs on the next tick.');
            }
            case 'resend': {
                $id = (int)($_POST['id'] ?? 0);
                AuditTool::emailReport($id)
                    ? redirect('/admin/?p=audits', 'ok', 'Report sent again.')
                    : redirect('/admin/?p=audits', 'bad', 'That report could not be sent.');
            }
            case 'test': {
                $url = trim((string)($_POST['url'] ?? ''));
                $r = AuditTool::run(AuditTool::normaliseUrl($url) ?: $url);
                $_SESSION['audit_test'] = $r;
                redirect('/admin/?p=audits', 'ok', $r['host'] . ' scored ' . $r['score'] . '/100.');
            }
        }
    } catch (Throwable $t) {
        redirect('/admin/?p=audits', 'bad', $t->getMessage());
    }
}

$rows = DB::all('SELECT * FROM wwt_audits ORDER BY id DESC LIMIT 60');
$test = $_SESSION['audit_test'] ?? null;
unset($_SESSION['audit_test']);

$stat = DB::one("SELECT COUNT(*) n, SUM(status='done') done, SUM(status='failed') failed,
                        SUM(status IN ('queued','running')) pending, ROUND(AVG(score)) avg_score
                 FROM wwt_audits WHERE ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)") ?: [];

layout_top('Audits', 'audits');
?>
<div class="page-head">
  <div>
    <h1>Website audits</h1>
    <p>Every audit run from <span class="mono">/tools/free-website-audit/</span>. Each one is
       a real measurement of a real page, and each one arrives with an email address attached.</p>
  </div>
  <?php if (Auth::isAdmin()): ?>
  <form method="post" style="margin:0">
    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
    <input type="hidden" name="action" value="run">
    <button class="btn btn--ghost btn--sm" type="submit">Run the queue now</button>
  </form>
  <?php endif; ?>
</div>

<div class="kpis">
  <div class="kpi"><div class="kpi__k">Audits · 90 days</div><div class="kpi__v"><?= (int)($stat['n'] ?? 0) ?></div>
    <div class="kpi__d"><?= (int)($stat['pending'] ?? 0) ?> waiting, <?= (int)($stat['failed'] ?? 0) ?> failed</div></div>
  <div class="kpi"><div class="kpi__k">Average score</div><div class="kpi__v"><?= (int)($stat['avg_score'] ?? 0) ?: '—' ?></div>
    <div class="kpi__d">out of 100, across every site checked</div></div>
</div>

<?php if (Auth::isAdmin()): ?>
<div class="card">
  <h2>Try it against any site</h2>
  <p class="muted small">Runs immediately and shows the result here. Nothing is stored and nobody is emailed.</p>
  <form method="post" class="row" style="gap:.5rem;align-items:flex-end;flex-wrap:wrap">
    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
    <input type="hidden" name="action" value="test">
    <div class="field" style="flex:1 1 260px"><label for="url">Address</label>
      <input id="url" name="url" placeholder="somesite.in" required></div>
    <button class="btn btn--sm" type="submit">Run it</button>
  </form>
  <?php if (is_array($test)): ?>
    <div style="margin-top:.9rem;padding-top:.7rem;border-top:1px solid var(--rule)">
      <b><?= e((string)$test['host']) ?></b> — <b><?= (int)$test['score'] ?>/100</b>
      <div class="muted small"><?= e((string)$test['headline']) ?></div>
      <div class="tablewrap" style="margin-top:.5rem"><table>
        <tbody>
        <?php foreach ($test['checks'] as $c): ?>
          <tr><td style="width:1%"><span class="pill pill--<?= ['ok'=>'ok','warn'=>'warn','bad'=>'fail','info'=>'info'][$c['state']] ?>"><?= e($c['state']) ?></span></td>
              <td><b><?= e($c['label']) ?></b><div class="small muted"><?= e($c['finding']) ?></div></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!$rows): ?>
  <div class="card"><?php empty_state('No audits yet',
    'When someone runs the free audit their site, score and email land here, and the report goes out by email.'); ?></div>
<?php else: ?>
<div class="card card--pad0"><div class="tablewrap" style="border:0"><table>
  <thead><tr><th>When</th><th>Site</th><th>Who</th><th>Score</th><th>Status</th><th>Lead</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="n small" style="white-space:nowrap"><?= e(local_time((string)$r['ts'], 'd M, H:i')) ?></td>
      <td class="small"><a href="<?= e((string)$r['url']) ?>" rel="noopener nofollow" style="text-decoration:underline"><?= e((string)$r['host']) ?></a></td>
      <td class="small"><?= e((string)$r['name']) ?><div class="muted mono small"><?= e((string)$r['email']) ?></div></td>
      <td class="n"><?= $r['score'] === null ? '—' : (int)$r['score'] ?></td>
      <td class="small">
        <span class="pill pill--<?= ['done'=>'ok','failed'=>'fail','queued'=>'info','running'=>'warn'][(string)$r['status']] ?>"><?= e((string)$r['status']) ?></span>
        <?php if ((string)$r['error'] !== ''): ?><div class="small muted"><?= e(cut((string)$r['error'], 60)) ?></div><?php endif; ?>
        <?php if ($r['emailed_at'] === null && (string)$r['status'] === 'done'): ?>
          <div class="small muted">not emailed</div><?php endif; ?>
      </td>
      <td class="small"><?php if ((int)($r['lead_id'] ?? 0) > 0): ?>
        <a href="/admin/?p=lead&amp;id=<?= (int)$r['lead_id'] ?>" style="text-decoration:underline">open</a>
      <?php else: ?><span class="muted">—</span><?php endif; ?></td>
      <td class="small">
        <?php if (Auth::isAdmin()): ?>
          <?php if ((string)$r['status'] === 'done'): ?>
            <form method="post" style="display:inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
              <input type="hidden" name="action" value="resend"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="linkbtn" type="submit">Resend</button></form>
          <?php elseif (in_array((string)$r['status'], ['failed', 'running'], true)): ?>
            <form method="post" style="display:inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
              <input type="hidden" name="action" value="requeue"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="linkbtn" type="submit">Try again</button></form>
          <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<?php endif; ?>
<?php layout_bottom();
