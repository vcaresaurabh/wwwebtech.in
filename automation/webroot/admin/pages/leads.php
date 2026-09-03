<?php
/* ============================================================
   Leads — the list, the pipeline and the export.

   The table is the mini-CRM: every enquiry, newest first, with a
   status you can move without leaving the row. Read-only accounts
   see everything and can change nothing.
   ============================================================ */
declare(strict_types=1);

/* Reached only through admin/index.php, which has already checked the session,
   the CSRF token and the role. A direct request must not run any of this. */
if (!defined('WWT_ADMIN')) { http_response_code(404); exit; }


/* ── Mutations ─────────────────────────────────────────────
   index.php lets viewers reach this page (they should see leads) so
   the write guard has to live here rather than in the router. */
$action = field($_POST, 'action', 40);
if ($action !== '') {
    Auth::requireAdmin();
    try {
        switch ($action) {
            case 'status': {
                $id = (int)($_POST['id'] ?? 0);
                $to = field($_POST, 'status', 20);
                if (!isset(Leads::STATUSES[$to])) throw new InvalidArgumentException('Unknown status.');
                DB::run('UPDATE wwt_leads SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?', [$to, $id]);
                audit('lead_status', "id=$id → $to", Auth::email());
                redirect(field($_POST, 'back', 400) ?: '/admin/?p=leads', 'ok',
                         'Moved to ' . Leads::STATUSES[$to] . '.');
            }
            case 'bulk_status': {
                $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
                $to  = field($_POST, 'status', 20);
                if (!$ids) throw new InvalidArgumentException('Nothing was ticked.');
                if (!isset(Leads::STATUSES[$to])) throw new InvalidArgumentException('Unknown status.');
                [$ph, $vals] = DB::inClause($ids);
                // SAFE-SQL: $ph is a placeholder list built by DB::inClause() — it is
                // only ever "(?,?,?)" or "(NULL)". Every id is bound in $vals.
                DB::run("UPDATE wwt_leads SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id IN $ph",
                        array_merge([$to], $vals));
                audit('lead_status_bulk', count($ids) . " → $to", Auth::email());
                redirect(field($_POST, 'back', 400) ?: '/admin/?p=leads', 'ok',
                         count($ids) . ' moved to ' . Leads::STATUSES[$to] . '.');
            }
            case 'purge_tests': {
                $n = DB::run('DELETE FROM wwt_leads WHERE is_test = 1')->rowCount();
                audit('lead_purge_tests', (string)$n, Auth::email());
                redirect('/admin/?p=leads', 'ok', $n . ' test ' . ($n === 1 ? 'lead' : 'leads') . ' removed.');
            }
        }
    } catch (Throwable $t) {
        redirect('/admin/?p=leads', 'bad', $t->getMessage());
    }
}

/* ── Filters ───────────────────────────────────────────────
   Every filter contributes a bound parameter. Nothing here is ever
   concatenated into the SQL. */
$status = gets('status', '', array_merge([''], array_keys(Leads::STATUSES)));
$days   = gets('days', '90', ['7', '30', '90', '365', 'all']);
$show   = gets('show', 'real', ['real', 'test', 'all']);
$q      = cut(trim((string)($_GET['q'] ?? '')), 80);

$where = ['1=1']; $args = [];
if ($status !== '')  { $where[] = 'status = ?';                                   $args[] = $status; }
if ($days !== 'all') { $where[] = 'ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)'; $args[] = (int)$days; }
if ($show === 'real'){ $where[] = 'is_test = 0'; }
if ($show === 'test'){ $where[] = 'is_test = 1'; }
if ($q !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR message LIKE ? OR utm_campaign LIKE ?)';
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
    array_push($args, $like, $like, $like, $like, $like);
}
/* $sqlWhere is assembled ONLY from the literal clause strings above; every
   value the visitor supplied is in $args and reaches SQL through a
   placeholder. Nothing from $_GET is ever concatenated into it. */
$sqlWhere = implode(' AND ', $where);

/* ── CSV export ────────────────────────────────────────────
   Exports exactly the filtered set, so "what I am looking at" and
   "what I get" cannot disagree. */
if (gets('export') === 'csv') {
    // SAFE-SQL: $sqlWhere is placeholders and literals only — see where it is built.
    $rows = DB::all("SELECT * FROM wwt_leads WHERE $sqlWhere ORDER BY ts DESC LIMIT 5000", $args);
    $name = 'wwwebtech-leads-' . local_now()->format('Y-m-d') . '.csv';

    header_remove('Content-Security-Policy');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    /* Excel on Windows assumes the system codepage unless the file starts
       with a UTF-8 BOM — without it the rupee sign and curly quotes arrive
       as mojibake. */
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_values(Leads::CSV_COLUMNS), ',', '"', '\\');
    foreach ($rows as $r) fputcsv($out, Leads::csvRow($r), ',', '"', '\\');
    fclose($out);
    audit('lead_export', count($rows) . ' rows', Auth::email());
    exit;
}

/* ── Page data ─────────────────────────────────────────────*/
$per   = 50;
$page  = max(1, (int)gets('pg', '1'));
// SAFE-SQL: as above.
$total = (int)DB::val("SELECT COUNT(*) FROM wwt_leads WHERE $sqlWhere", $args, 0);
$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);
// SAFE-SQL: $sqlWhere as above; $per and the offset are integers computed here.
$rows  = DB::all("SELECT * FROM wwt_leads WHERE $sqlWhere ORDER BY ts DESC LIMIT $per OFFSET " . (($page - 1) * $per), $args);

$counts = [];
foreach (DB::all('SELECT status, COUNT(*) c FROM wwt_leads WHERE is_test = 0 GROUP BY status') as $r) {
    $counts[$r['status']] = (int)$r['c'];
}
$testCount   = (int)DB::val('SELECT COUNT(*) FROM wwt_leads WHERE is_test = 1', [], 0);
$mailFailed  = (int)DB::val("SELECT COUNT(*) FROM wwt_leads WHERE mail_status IN ('failed','skipped') AND is_test = 0", [], 0);
$back        = '/admin/' . qs();

layout_top('Leads', 'leads');
?>
<div class="page-head">
  <div>
    <h1>Leads</h1>
    <p>Every contact-form submission, newest first. Saved the moment it arrives —
       the email is a copy, not the record.</p>
  </div>
  <div class="row" style="gap:.4rem">
    <a class="btn btn--ghost btn--sm" href="<?= e(qs(['export' => 'csv'])) ?>">Export CSV</a>
  </div>
</div>

<?php if ($mailFailed > 0): ?>
  <div class="alert alert--warn">
    <b><?= $mailFailed ?></b> <?= $mailFailed === 1 ? 'lead was' : 'leads were' ?> saved but the
    notification email did not go out. The enquiries are safe and listed below — check
    <a href="/admin/?p=settings" style="text-decoration:underline">the mailbox settings</a>.
  </div>
<?php endif; ?>

<div class="chipbar" style="margin-bottom:.7rem">
  <a href="<?= e(qs(['status' => ''], ['pg'])) ?>"<?= $status === '' ? ' aria-current="true"' : '' ?>>
    All <span class="muted">· <?= array_sum($counts) ?></span></a>
  <?php foreach (Leads::STATUSES as $k => $label): ?>
    <a href="<?= e(qs(['status' => $k], ['pg'])) ?>"<?= $status === $k ? ' aria-current="true"' : '' ?>>
      <?= e($label) ?> <span class="muted">· <?= (int)($counts[$k] ?? 0) ?></span></a>
  <?php endforeach; ?>
</div>

<form class="filters" method="get">
  <input type="hidden" name="p" value="leads">
  <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <div class="field" style="flex:1 1 220px">
    <label for="q">Search</label>
    <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Name, email, phone, message…">
  </div>
  <div class="field">
    <label for="days">Period</label>
    <select id="days" name="days">
      <?php /* (string)$k, not $k: PHP turns the numeric keys into integers,
               so `$days === $k` never matched and the control always showed
               the first option regardless of the filter actually applied. */ ?>
      <?php foreach (['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days',
                      '365' => 'Last year', 'all' => 'All time'] as $k => $v): ?>
        <option value="<?= e($k) ?>"<?= $days === (string)$k ? ' selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="show">Include</label>
    <select id="show" name="show">
      <option value="real"<?= $show === 'real' ? ' selected' : '' ?>>Real enquiries</option>
      <option value="test"<?= $show === 'test' ? ' selected' : '' ?>>Test only<?= $testCount ? " ($testCount)" : '' ?></option>
      <option value="all"<?= $show === 'all' ? ' selected' : '' ?>>Everything</option>
    </select>
  </div>
  <button class="btn btn--sm" type="submit">Apply</button>
  <?php if ($q !== '' || $days !== '90' || $show !== 'real'): ?>
    <a class="btn btn--ghost btn--sm" href="/admin/?p=leads">Reset</a>
  <?php endif; ?>
</form>

<?php if (!$rows): ?>
  <div class="card">
  <?php if ($total === 0 && $q === '' && $status === ''): ?>
    <?php empty_state('No leads yet',
      'When someone sends the contact form they appear here within seconds, and a copy is emailed to you.'); ?>
  <?php else: ?>
    <?php empty_state('Nothing matches those filters', 'Try a wider period, or clear the search.'); ?>
  <?php endif; ?>
  </div>
<?php else: ?>

<?php /* The bulk form is declared AFTER the table, and the row checkboxes
         join it with form="bulk". Wrapping the table in it instead would
         nest the per-row status forms inside it — invalid HTML that
         browsers resolve by silently dropping the inner form. */ ?>
  <div class="card card--pad0">
    <div class="tablewrap" style="border:0">
      <table>
        <thead><tr>
          <?php if (Auth::isAdmin()): ?><th style="width:1%"><span class="vh">Select</span></th><?php endif; ?>
          <th>Received</th><th>Name</th><th>Score</th><th>Contact</th><th>Needs</th>
          <th>Budget</th><th>Source</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $l):
          $src = $l['utm_source'] !== '' ? $l['utm_source'] : (ref_domain((string)$l['referrer']) ?: 'direct'); ?>
          <tr>
            <?php if (Auth::isAdmin()): ?>
            <td><input type="checkbox" form="bulk" name="ids[]" value="<?= (int)$l['id'] ?>"
                       aria-label="Select lead <?= (int)$l['id'] ?>"></td>
            <?php endif; ?>
            <td class="n small" style="white-space:nowrap"><?= e(local_time($l['ts'], 'd M, H:i')) ?></td>
            <td>
              <a href="/admin/?p=lead&amp;id=<?= (int)$l['id'] ?>" style="text-decoration:underline;font-weight:600"><?= e($l['name']) ?></a>
              <?php if ((int)$l['is_test'] === 1): ?> <span class="pill pill--info">test</span><?php endif; ?>
              <?php if (in_array($l['mail_status'], ['failed', 'skipped'], true)): ?>
                <span class="pill pill--fail" title="<?= e($l['mail_error'] ?: 'Not emailed') ?>">not emailed</span>
              <?php endif; ?>
              <div class="small muted"><?= e(cut((string)$l['message'], 64)) ?><?= mb_strlen((string)$l['message']) > 64 ? '…' : '' ?></div>
            </td>
            <td class="n"><span class="pill pill--<?= e((string)$l['band']) ?>"><?= e((string)$l['band']) ?></span>
              <div class="small muted"><?= (int)$l['score'] ?></div></td>
            <td class="small">
              <a href="mailto:<?= e($l['email']) ?>" style="text-decoration:underline"><?= e($l['email']) ?></a>
              <?php if ($l['phone'] !== ''): ?><div class="muted"><?= e($l['phone']) ?></div><?php endif; ?>
            </td>
            <td class="small"><?= e($l['service'] ?: '—') ?></td>
            <td class="small"><?= e($l['budget'] ?: '—') ?></td>
            <td class="small"><?= e($src) ?></td>
            <td><span class="pill pill--<?= e($l['status']) ?>"><?= e(Leads::STATUSES[$l['status']] ?? $l['status']) ?></span></td>
            <td class="r">
              <?php if (Auth::isAdmin()): ?>
              <form method="post" style="display:inline">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                <input type="hidden" name="back" value="<?= e($back) ?>">
                <select name="status" onchange="this.form.submit()" aria-label="Change status">
                  <?php foreach (Leads::STATUSES as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= $l['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <noscript><button class="btn btn--sm" type="submit">Set</button></noscript>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="pager">
    <span><?= number_format($total) ?> <?= $total === 1 ? 'lead' : 'leads' ?><?php
      if ($pages > 1) echo ' · page ' . $page . ' of ' . $pages; ?></span>
    <?php if ($pages > 1): ?>
    <span class="row" style="gap:.35rem">
      <?php if ($page > 1): ?><a href="<?= e(qs(['pg' => $page - 1])) ?>">Newer</a><?php endif; ?>
      <span class="cur"><?= $page ?></span>
      <?php if ($page < $pages): ?><a href="<?= e(qs(['pg' => $page + 1])) ?>">Older</a><?php endif; ?>
    </span>
    <?php endif; ?>
  </div>

  <?php if (Auth::isAdmin()): ?>
  <div class="card" style="margin-top:.9rem">
    <div class="row" style="align-items:flex-end;gap:.5rem">
      <form method="post" id="bulk" class="row" style="align-items:flex-end;gap:.5rem;margin:0">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="bulk_status">
        <input type="hidden" name="back" value="<?= e($back) ?>">
        <div class="field" style="margin:0;min-width:180px">
          <label for="bs">With the ticked leads</label>
          <select id="bs" name="status">
            <?php foreach (Leads::STATUSES as $k => $label): ?>
              <option value="<?= e($k) ?>">Mark as <?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn--sm" type="submit">Apply</button>
      </form>
      <span class="spacer"></span>
      <?php if ($testCount > 0): ?>
      <form method="post" style="margin:0"
            onsubmit="return confirm('Delete all <?= $testCount ?> test leads? Real enquiries are untouched.')">
        <?= Csrf::field() ?><input type="hidden" name="action" value="purge_tests">
        <button class="btn btn--danger btn--sm" type="submit">Delete <?= $testCount ?> test <?= $testCount === 1 ? 'lead' : 'leads' ?></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
<?php endif; ?>
<?php layout_bottom();
