<?php
/* ============================================================
   Landing pages — what each page is actually producing.

   Visits come from the server-side record in wwt_hits, not from a
   client-side beacon, because a good share of paid traffic blocks the
   beacon and a page that looks like it converts at 40% is worse than
   no number at all.

   The A/B comparison deliberately refuses to name a winner until both
   arms have enough leads to mean anything. Calling a variant at n=3 is
   how you switch to the worse page and never find out.
   ============================================================ */
declare(strict_types=1);
if (!defined('WWT_ADMIN')) { http_response_code(404); exit; }

$action = field($_POST, 'action', 40);
if ($action !== '') {
    Auth::requireAdmin();
    try {
        if ($action === 'split') {
            $v = max(0, min(100, (int)($_POST['split'] ?? 0)));
            Settings::set('lp_ab_split', (string)$v);
            audit('lp_ab_split', (string)$v, Auth::email());
            redirect('/admin/?p=landing', 'ok', $v === 0
                ? 'The test is off. Everyone sees the original page.'
                : $v . '% of visitors now see variant B.');
        }
        if ($action === 'clear_kw') {
            Settings::set('lp_unmatched_kw', '{}');
            redirect('/admin/?p=landing', 'ok', 'Cleared.');
        }
    } catch (Throwable $t) {
        redirect('/admin/?p=landing', 'bad', $t->getMessage());
    }
}

$days = (int)gets('days', '30', ['7', '30', '90', '365']);

/* Which pages exist, from the data files rather than from traffic — a
   page with no visits yet should still be listed, and listed as such. */
$dir   = WWT_PRIVATE . '/lp/data';
$slugs = [];
foreach (glob($dir . '/*.php') ?: [] as $f) $slugs[] = basename($f, '.php');
sort($slugs);

$visits = [];
foreach (DB::all(
    "SELECT path, COUNT(*) c, COUNT(DISTINCT session_hash) s FROM wwt_hits
     WHERE is_bot = 0 AND event = 'pageview' AND path LIKE '/lp/%'
       AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY) GROUP BY path", [$days]) as $r) {
    $slug = trim((string)$r['path'], '/');
    $slug = substr($slug, 3);                       // drop the leading "lp/"
    $visits[trim($slug, '/')] = ['hits' => (int)$r['c'], 'people' => (int)$r['s']];
}

$leadRows = DB::all(
    "SELECT landing_page, variant, COUNT(*) n,
            SUM(band = 'hot') hot, SUM(band = 'warm') warm,
            SUM(status IN ('qualified','won')) qualified, SUM(status = 'won') won,
            ROUND(AVG(score)) avg_score
     FROM wwt_leads
     WHERE is_test = 0 AND is_partial = 0 AND landing_page <> ''
       AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
     GROUP BY landing_page, variant", [$days]);

$byPage = [];
foreach ($leadRows as $r) {
    $p = (string)$r['landing_page'];
    $v = (string)$r['variant'] ?: 'a';
    $byPage[$p]['total'] = ($byPage[$p]['total'] ?? 0) + (int)$r['n'];
    $byPage[$p]['hot']   = ($byPage[$p]['hot']   ?? 0) + (int)$r['hot'];
    $byPage[$p]['won']   = ($byPage[$p]['won']   ?? 0) + (int)$r['won'];
    $byPage[$p]['score'] = (int)$r['avg_score'];
    $byPage[$p]['v'][$v] = ['n' => (int)$r['n'], 'hot' => (int)$r['hot'], 'won' => (int)$r['won']];
}

$partials = [];
foreach (DB::all("SELECT landing_page, COUNT(*) n FROM wwt_leads
                  WHERE is_partial = 1 AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
                  GROUP BY landing_page", [$days]) as $r) {
    $partials[(string)$r['landing_page']] = (int)$r['n'];
}

$split = Settings::int('lp_ab_split', 0);
$kw    = Settings::json('lp_unmatched_kw', []);
uasort($kw, static fn($a, $b) => (int)($b['n'] ?? 0) <=> (int)($a['n'] ?? 0));

/* Enough leads in each arm to be worth reading. Below this the page says
   "not yet", which is the honest answer nearly every week. */
$MIN_ARM = 30;

layout_top('Landing pages', 'landing');
?>
<div class="page-head">
  <div>
    <h1>Landing pages</h1>
    <p>Visits, enquiries and what became of them, per page. Visits are counted on the server,
       so ad blockers do not flatter the numbers.</p>
  </div>
  <form class="filters" method="get" style="margin:0">
    <input type="hidden" name="p" value="landing">
    <select name="days" onchange="this.form.submit()" aria-label="Period">
      <?php foreach (['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last year'] as $k => $v): ?>
        <option value="<?= e($k) ?>"<?= $days === (int)$k ? ' selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="btn btn--sm" type="submit">Apply</button></noscript>
  </form>
</div>

<div class="card card--pad0"><div class="tablewrap" style="border:0"><table>
  <thead><tr>
    <th>Page</th><th>Visitors</th><th>Enquiries</th><th>Rate</th>
    <th>Hot</th><th>Won</th><th>Avg score</th><th>Abandoned</th>
  </tr></thead>
  <tbody>
  <?php foreach ($slugs as $slug):
    $v = $visits[$slug] ?? ['hits' => 0, 'people' => 0];
    $d = $byPage[$slug] ?? [];
    $n = (int)($d['total'] ?? 0);
    $rate = $v['people'] > 0 ? (100 * $n / $v['people']) : null; ?>
    <tr>
      <td><a href="/lp/<?= e($slug) ?>/" rel="noopener" style="text-decoration:underline;font-weight:600"><?= e($slug) ?></a></td>
      <td class="n"><?= (int)$v['people'] ?: '—' ?></td>
      <td class="n"><?= $n ?: '—' ?></td>
      <td class="n"><?= $rate === null ? '—' : number_format($rate, 1) . '%' ?></td>
      <td class="n"><?= (int)($d['hot'] ?? 0) ?: '—' ?></td>
      <td class="n"><?= (int)($d['won'] ?? 0) ?: '—' ?></td>
      <td class="n"><?= (int)($d['score'] ?? 0) ?: '—' ?></td>
      <td class="n muted"><?= (int)($partials[$slug] ?? 0) ?: '—' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<p class="muted small">"Abandoned" is someone who filled in enough of the form to be reachable and then
   stopped. They are stored, never contacted automatically, and never counted as an enquiry.</p>

<h2 style="margin-top:1.6rem">A/B test</h2>
<div class="card">
  <?php if (Auth::isAdmin()): ?>
  <form method="post" class="row" style="gap:.6rem;align-items:flex-end;flex-wrap:wrap">
    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
    <input type="hidden" name="action" value="split">
    <div class="field" style="flex:0 0 auto">
      <label for="split">Share of visitors seeing variant B</label>
      <select id="split" name="split">
        <?php foreach ([0, 10, 25, 50] as $o): ?>
          <option value="<?= $o ?>"<?= $split === $o ? ' selected' : '' ?>><?= $o === 0 ? 'Off' : $o . '%' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn--sm" type="submit">Save</button>
    <p class="muted small" style="margin:0;max-width:32rem">Only pages that define a variant B are affected.
      Each visitor keeps the version they first saw for thirty days.</p>
  </form>
  <?php endif; ?>

  <?php
  $anyArm = false;
  foreach ($slugs as $slug):
    $arms = $byPage[$slug]['v'] ?? [];
    if (!isset($arms['b'])) continue;
    $anyArm = true;
    $a = $arms['a'] ?? ['n' => 0, 'hot' => 0, 'won' => 0];
    $b = $arms['b'];
  ?>
    <div style="margin-top:1rem;padding-top:.8rem;border-top:1px solid var(--rule)">
      <b><?= e($slug) ?></b>
      <div class="row" style="gap:2rem;margin-top:.4rem;flex-wrap:wrap">
        <div><span class="muted small">A</span><div><b><?= (int)$a['n'] ?></b> enquiries · <?= (int)$a['hot'] ?> hot</div></div>
        <div><span class="muted small">B</span><div><b><?= (int)$b['n'] ?></b> enquiries · <?= (int)$b['hot'] ?> hot</div></div>
      </div>
      <?php if ((int)$a['n'] < $MIN_ARM || (int)$b['n'] < $MIN_ARM): ?>
        <p class="small muted" style="margin:.4rem 0 0">Not enough yet to tell them apart —
          both need about <?= $MIN_ARM ?> enquiries before a difference means anything.
          Picking a winner now would be a coin toss with extra steps.</p>
      <?php else:
        $ra = (int)$a['n'] > 0 ? (int)$a['hot'] / (int)$a['n'] : 0.0;
        $rb = (int)$b['n'] > 0 ? (int)$b['hot'] / (int)$b['n'] : 0.0;
        $diff = $rb - $ra; ?>
        <p class="small" style="margin:.4rem 0 0">
          B produces <?= number_format(abs($diff) * 100, 1) ?> percentage points
          <?= $diff >= 0 ? 'more' : 'fewer' ?> hot leads per enquiry than A.
          <span class="muted">Both arms are past <?= $MIN_ARM ?>, so this is worth acting on.</span>
        </p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$anyArm): ?>
    <p class="muted small" style="margin-top:.8rem">No page has a variant B with traffic yet.
      A variant is defined in the page's data file, so writing one is a reviewable edit.</p>
  <?php endif; ?>
</div>

<h2 style="margin-top:1.6rem">Keywords with no tailored line</h2>
<div class="card">
  <p class="muted small">Someone arrived on a paid keyword the page has no matching headline for,
     so they saw the default. Each of these is a line worth writing.</p>
  <?php if (!$kw): ?>
    <?php empty_state('Nothing unmatched', 'Every keyword that has sent traffic has a line written for it.'); ?>
  <?php else: ?>
    <div class="tablewrap"><table>
      <thead><tr><th>Keyword</th><th>Page</th><th>Times</th><th>Last seen</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($kw, 0, 40, true) as $term => $meta): ?>
        <tr><td class="mono small"><?= e((string)$term) ?></td>
            <td class="small"><?= e((string)($meta['lp'] ?? '')) ?></td>
            <td class="n small"><?= (int)($meta['n'] ?? 0) ?></td>
            <td class="small muted"><?= e((string)($meta['last'] ?? '')) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php if (Auth::isAdmin()): ?>
    <form method="post" style="margin-top:.6rem">
      <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
      <input type="hidden" name="action" value="clear_kw">
      <button class="btn btn--ghost btn--sm" type="submit">Clear the list</button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php layout_bottom();
