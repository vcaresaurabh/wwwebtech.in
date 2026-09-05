<?php
/* ============================================================
   Blog — the generator, its kill switch, and everything it wrote.

   The kill switch is the first control on the page for a reason: if
   something goes wrong with automated publishing, the owner needs to
   stop it in one click without reading anything else.
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

            case 'toggle': {
                $on = isset($_POST['blog_enabled']);
                Settings::set('blog_enabled', $on ? '1' : '0');
                audit('blog_toggle', $on ? 'ON' : 'OFF', Auth::email());
                redirect('/admin/?p=blog', $on ? 'ok' : 'warn',
                    $on ? 'Automatic publishing is on.'
                        : 'Automatic publishing is off. Nothing new will be written or published.');
            }

            case 'prefs': {
                $m = field($_POST, 'blog_model', 60);
                if (!isset(Claude::MODELS[$m])) throw new InvalidArgumentException('Unknown model.');
                Settings::set('blog_model', $m);
                Settings::set('blog_per_day', (string)max(0, min(4, (int)($_POST['blog_per_day'] ?? 2))));
                Settings::set('blog_effort', in_array(field($_POST, 'blog_effort', 10),
                    ['low', 'medium', 'high'], true) ? field($_POST, 'blog_effort', 10) : 'medium');
                $cap = (float)($_POST['blog_monthly_cap_usd'] ?? 15);
                if ($cap < 0 || $cap > 500) throw new InvalidArgumentException('Set a cap between 0 and 500.');
                Settings::set('blog_monthly_cap_usd', (string)$cap);

                audit('blog_settings', 'model=' . $m, Auth::email());
                redirect('/admin/?p=blog', 'ok', 'Saved.');
            }


            case 'generate': {
                $publish = isset($_POST['publish_now']);
                /* Writing a post takes over a minute against the API, which is
                   longer than a default web timeout. */
                @set_time_limit(Jobs::WEB_TIME_LIMIT);
                @ignore_user_abort(true);
                $out = Task::run('blog_manual', static fn(): string =>
                    json_encode(Blog::generateOne($publish)) ?: '{}');
                $r = json_decode((string)$out, true) ?: [];
                if (!empty($r['ok'])) {
                    redirect('/admin/?p=blog', 'ok', sprintf(
                        '“%s” written — %d words, $%.4f. %s',
                        (string)$r['title'], (int)$r['words'], (float)$r['cost'],
                        $publish ? 'It is live.' : 'Saved as a draft; review it before publishing.'));
                }
                redirect('/admin/?p=blog', 'bad', 'Not published: ' . (string)($r['error'] ?? 'unknown error'));
            }

            case 'publish': {
                $id = (int)($_POST['id'] ?? 0);
                $p  = DB::one('SELECT * FROM wwt_posts WHERE id=?', [$id]);
                if (!$p) throw new RuntimeException('No such post.');
                $src = Blog::loadSource((string)$p['slug']);
                if (!$src) throw new RuntimeException('The article text for that post is missing from the server.');
                Publisher::publish($id, [
                    'slug' => $p['slug'], 'title' => $p['title'], 'dek' => $p['dek'],
                    'date' => gmdate('Y-m-d'),
                    'read' => (int)($src['read'] ?? max(1, (int)round((int)$p['word_count'] / 220))),
                    'body' => (string)$src['body'],
                ], (array)($src['faq'] ?? []));
                redirect('/admin/?p=blog', 'ok', 'Published.');
            }

            case 'unpublish': {
                Publisher::unpublish((int)($_POST['id'] ?? 0));
                redirect('/admin/?p=blog', 'ok', 'Taken off the site. The draft is kept.');
            }

            case 'delete': {
                $id = (int)($_POST['id'] ?? 0);
                $p  = DB::one('SELECT slug, status FROM wwt_posts WHERE id=?', [$id]);
                if ($p && $p['status'] === 'published') Publisher::unpublish($id);
                if ($p) @unlink(Blog::sourcePath((string)$p['slug']));
                DB::run('DELETE FROM wwt_posts WHERE id=?', [$id]);
                audit('post_delete', 'id=' . $id, Auth::email());
                redirect('/admin/?p=blog', 'ok', 'Deleted.');
            }

            case 'seed_topics': {
                $n = Blog::seedTopics();
                redirect('/admin/?p=blog', 'ok', $n . ' topics added.');
            }

            case 'add_topic': {
                $t = field($_POST, 'title_seed', 200);
                $a = field($_POST, 'angle', 400);
                $c = field($_POST, 'cluster', 40);
                if ($t === '' || $a === '') throw new InvalidArgumentException('A topic needs both a subject and an angle.');
                if (!isset(Blog::CLUSTERS[$c])) throw new InvalidArgumentException('Pick a category.');
                DB::run('INSERT INTO wwt_topics (cluster, title_seed, angle, sort_order) VALUES (?,?,?,999)',
                        [$c, $t, $a]);
                audit('topic_add', $t, Auth::email());
                redirect('/admin/?p=blog', 'ok', 'Topic added to the queue.');
            }
        }
    } catch (Throwable $t) {
        redirect('/admin/?p=blog', 'bad', $t->getMessage());
    }
}

$on        = Blog::enabled();
$keySet    = Claude::configured();
$model     = Claude::model();
$spent     = Claude::spentThisMonth();
$cap       = Claude::monthlyCap();
$left      = Blog::topicsLeft();
$posts     = DB::all("SELECT * FROM wwt_posts ORDER BY id DESC LIMIT 60");
$counts    = [];
foreach (DB::all('SELECT status, COUNT(*) c FROM wwt_posts GROUP BY status') as $r) {
    $counts[$r['status']] = (int)$r['c'];
}
$byCluster = DB::all("SELECT cluster, COUNT(*) total, SUM(used_at IS NULL) unused
                      FROM wwt_topics GROUP BY cluster ORDER BY cluster");
$lastRun   = DB::one("SELECT * FROM wwt_task_runs WHERE task='blog_daily'");

layout_top('Blog', 'blog');
?>
<div class="page-head">
  <div>
    <h1>Blog</h1>
    <p>Posts are written to a fixed brief and checked mechanically before anything
       goes live. A post that fails a check is never published — it is listed below
       with the reason.</p>
  </div>
</div>

<?php /* ── The switch, first, big ─────────────────────────── */ ?>
<section class="card" style="margin-bottom:1rem;border-left:3px solid var(--<?= $on ? 'ok' : 'ink-3' ?>)">
  <form method="post" class="row" style="justify-content:space-between;align-items:center">
    <?= Csrf::field() ?><input type="hidden" name="action" value="toggle">
    <div>
      <h2 style="margin-bottom:.2rem">Automatic publishing is
        <?= $on ? '<span style="color:var(--ok)">on</span>' : '<span class="muted">off</span>' ?></h2>
      <p class="small muted">
        <?php if ($on): ?>
          Up to <?= Blog::perDay() ?> post<?= Blog::perDay() === 1 ? '' : 's' ?> a day, written by
          <?= e(Claude::MODELS[$model]['label'] ?? $model) ?>, published automatically when they pass every check.
        <?php else: ?>
          <b>Nothing will be written or published on a schedule.</b> Existing posts stay exactly
          as they are. Use “Write a post” below to try one by hand first — that works whether
          this is on or off.
        <?php endif; ?>
      </p>
    </div>
    <?php if ($on): ?>
      <button class="btn btn--danger" type="submit">Stop publishing</button>
    <?php else: ?>
      <input type="hidden" name="blog_enabled" value="1">
      <button class="btn" type="submit"<?= $keySet ? '' : ' disabled title="Add an API key first"' ?>>
        Start publishing</button>
    <?php endif; ?>
  </form>
</section>

<?php if (!$keySet): ?>
  <div class="alert alert--warn">
    No Anthropic API key is set, so nothing can be written. Add one below —
    get it from <b>console.anthropic.com → API keys</b>, and set a spend limit there as well
    as the monthly cap here.
  </div>
<?php endif; ?>

<div class="kpis">
  <div class="kpi"><div class="kpi__k">Published</div>
    <div class="kpi__v"><?= (int)($counts['published'] ?? 0) ?></div>
    <div class="kpi__d flat"><?= (int)($counts['queued'] ?? 0) ?> draft<?= (int)($counts['queued'] ?? 0) === 1 ? '' : 's' ?> waiting</div></div>
  <div class="kpi"><div class="kpi__k">Rejected</div>
    <div class="kpi__v"><?= (int)($counts['failed'] ?? 0) ?></div>
    <div class="kpi__d flat">never reached the site</div></div>
  <div class="kpi"><div class="kpi__k">Spent this month</div>
    <div class="kpi__v">$<?= number_format($spent, 2) ?></div>
    <div class="kpi__d <?= $spent >= $cap ? 'down' : 'flat' ?>">of $<?= number_format($cap, 2) ?> cap</div></div>
  <div class="kpi"><div class="kpi__k">Topics left</div>
    <div class="kpi__v"><?= $left ?></div>
    <div class="kpi__d <?= $left < 14 ? 'down' : 'flat' ?>">
      <?= $left < 14 ? 'running low — add more' : 'about ' . (int)floor($left / max(1, Blog::perDay())) . ' days' ?></div></div>
</div>

<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr));margin-bottom:1rem">
  <section class="card">
    <h2>How it is set up</h2>
    <form method="post" style="margin-top:.7rem">
      <?= Csrf::field() ?><input type="hidden" name="action" value="prefs">
      <div class="field">
        <label for="bm">Written by</label>
        <select id="bm" name="blog_model">
          <?php foreach (Claude::MODELS as $id => $m): ?>
            <option value="<?= e($id) ?>"<?= $model === $id ? ' selected' : '' ?>>
              <?= e($m['label']) ?> — $<?= number_format($m['in'], 2) ?>/$<?= number_format($m['out'], 2) ?> per million tokens</option>
          <?php endforeach; ?>
        </select>
        <?php
        /* Quote the MEASURED average once there is one. The first version of
           this line guessed "1-3 cents"; the first real post cost 12. A cost
           estimate that is wrong by a factor of ten is worse than none. */
        $avg = (float)DB::val("SELECT AVG(cost_usd) FROM wwt_posts WHERE cost_usd > 0
                               AND model = ?", [$model], 0.0);
        $n   = (int)DB::val("SELECT COUNT(*) FROM wwt_posts WHERE cost_usd > 0 AND model = ?", [$model], 0);
        ?>
        <p class="hint"><?php if ($n > 0): ?>
          Measured: <b>$<?= number_format($avg, 3) ?></b> per post on this model, averaged over
          <?= $n ?> <?= $n === 1 ? 'attempt' : 'attempts' ?> (rejected drafts cost the same as
          published ones). At <?= Blog::perDay() ?> a day that is about
          <b>$<?= number_format($avg * Blog::perDay() * 30, 2) ?></b> a month.
        <?php else: ?>
          Cost is measured from the first post onwards and shown here. Until then, expect
          roughly ten to fifteen cents a post on Opus and appreciably less on the others —
          write one and this line will tell you the real figure.
        <?php endif; ?></p>
      </div>
      <div class="row" style="align-items:flex-start">
        <div class="field" style="flex:0 0 120px"><label for="bpd">Posts a day</label>
          <select id="bpd" name="blog_per_day">
            <?php foreach ([0, 1, 2, 3, 4] as $n): ?>
              <option value="<?= $n ?>"<?= Blog::perDay() === $n ? ' selected' : '' ?>><?= $n ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field" style="flex:0 0 140px"><label for="bef">Care taken</label>
          <select id="bef" name="blog_effort">
            <?php foreach (['low' => 'Low (cheapest)', 'medium' => 'Medium', 'high' => 'High (best)'] as $k => $v): ?>
              <option value="<?= e($k) ?>"<?= Settings::get('blog_effort', 'medium') === (string)$k ? ' selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field" style="flex:1 1 130px"><label for="bcap">Monthly cap (USD)</label>
          <input id="bcap" name="blog_monthly_cap_usd" type="number" min="0" max="500" step="1"
                 value="<?= e((string)$cap) ?>">
          <p class="hint">Nothing is sent once this is reached.</p></div>
      </div>
      <p class="small muted">The Anthropic key lives on <a href="/admin/?p=connections#c-claude" style="text-decoration:underline">Connections</a>, with a test button and a spend meter.</p>
      <button class="btn" type="submit">Save</button>
    </form>
  </section>

  <section class="card">
    <h2>Write one now</h2>
    <p class="small muted" style="margin:.4rem 0 .8rem">
      Runs the whole pipeline once, on the next topic in the queue. Use “save as a draft”
      the first few times so you can read it before anyone else does.</p>
    <form method="post">
      <?= Csrf::field() ?><input type="hidden" name="action" value="generate">
      <div class="field field--inline">
        <input type="checkbox" id="pn" name="publish_now" value="1" style="width:auto">
        <label for="pn" style="text-transform:none;font-family:var(--font);font-size:13px;letter-spacing:0">
          Publish straight away instead of saving a draft</label>
      </div>
      <button class="btn" type="submit"<?= $keySet ? '' : ' disabled' ?>>Write a post</button>
      <span class="small muted" style="margin-left:.4rem">Takes up to a minute.</span>
    </form>

    <h2 style="margin-top:1.3rem">The daily job</h2>
    <?php $sched = Blog::scheduleStatus(); ?>

    <?php /* Four different things can stop a post appearing — the switch, the
             key, the schedule not having come round, and cron not firing at
             all. Say which, rather than leave the owner to guess. */ ?>
    <?php if (!$sched['cron_ok']): ?>
      <p class="small" style="margin-top:.4rem;color:var(--bad)">
        <b>Scheduled jobs are not running.</b> The hourly job has not reported in over
        three hours, so nothing on a schedule will happen — including this one.
        Check hPanel → Advanced → Cron Jobs against DEPLOY.md step 7.</p>
    <?php elseif ($sched['blocked'] !== ''): ?>
      <p class="small" style="margin-top:.4rem">
        <span class="pill pill--warn">nothing scheduled</span>
        <?= e($sched['blocked']) ?> The job runs every day at
        <?= e(Blog::dailyCronAt()) ?> server time regardless, and will publish
        as soon as that is resolved.</p>
    <?php else: ?>
      <p class="small" style="margin-top:.4rem">
        <span class="pill pill--ok">scheduled</span>
        Next run <b><?= e($sched['due']) ?></b> — up to
        <?= Blog::perDay() ?> post<?= Blog::perDay() === 1 ? '' : 's' ?>.</p>
    <?php endif; ?>

    <?php if ($lastRun && $lastRun['last_end']): ?>
      <p class="small muted" style="margin-top:.5rem">
        Last ran <?= e(local_time($lastRun['last_end'])) ?> —
        <?= e(cut((string)($lastRun['last_error'] ?: $lastRun['last_status']), 200)) ?>
      </p>
    <?php else: ?>
      <p class="small muted" style="margin-top:.5rem">It has never run.</p>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Topic queue</h2>
    <p class="small muted" style="margin:.4rem 0 .7rem">
      Posts are taken from here, rotating between the three categories so the blog does not
      become thirty pieces about one of them.</p>
    <div class="tablewrap"><table>
      <thead><tr><th>Category</th><th class="r">Left</th><th class="r">Total</th></tr></thead><tbody>
      <?php foreach ($byCluster as $c): ?>
        <tr><td><?= e(Blog::CLUSTERS[$c['cluster']] ?? $c['cluster']) ?></td>
            <td class="r"><?= (int)$c['unused'] ?></td>
            <td class="r muted"><?= (int)$c['total'] ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>

    <form method="post" style="margin-top:.9rem;padding-top:.9rem;border-top:1px solid var(--rule)">
      <?= Csrf::field() ?><input type="hidden" name="action" value="add_topic">
      <div class="field"><label for="ts">Add a topic</label>
        <input id="ts" name="title_seed" type="text" maxlength="200" placeholder="The question a customer actually asks"></div>
      <div class="field"><label for="ta">What the piece must argue</label>
        <textarea id="ta" name="angle" rows="2" maxlength="400"
                  placeholder="The angle. Without this, you get a generic article."></textarea></div>
      <div class="row">
        <div class="field" style="margin:0;flex:1 1 160px"><label for="tc">Category</label>
          <select id="tc" name="cluster">
            <?php foreach (Blog::CLUSTERS as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
          </select></div>
        <button class="btn btn--sm" type="submit" style="align-self:flex-end">Add</button>
      </div>
    </form>
    <?php if ($left === 0): ?>
      <form method="post" style="margin-top:.6rem">
        <?= Csrf::field() ?><input type="hidden" name="action" value="seed_topics">
        <button class="btn btn--ghost btn--sm" type="submit">Reload the starter topics</button>
      </form>
    <?php endif; ?>
  </section>
</div>

<section class="card card--pad0">
  <div style="padding:.85rem 1.1rem;border-bottom:1px solid var(--rule)">
    <h2>Everything written</h2>
    <p class="small muted">Newest first. Rejected pieces are kept so you can see what the
       checks caught.</p>
  </div>
  <?php if (!$posts): ?>
    <?php empty_state('Nothing written yet',
      'Use “Write a post” above to try one, or turn on automatic publishing.'); ?>
  <?php else: ?>
  <div class="tablewrap" style="border:0"><table>
    <thead><tr><th>Title</th><th>Category</th><th class="r">Words</th><th class="r">Cost</th>
      <th>Status</th><th>When</th><th></th></tr></thead><tbody>
    <?php foreach ($posts as $p): ?>
      <tr>
        <td>
          <?php if ($p['status'] === 'published'): ?>
            <a href="<?= e(rtrim((string)cfg('site.url',''), '/') . '/blog/' . $p['slug'] . '/') ?>"
               target="_blank" rel="noopener" style="text-decoration:underline;font-weight:600"><?= e($p['title']) ?></a>
          <?php else: ?>
            <span style="font-weight:600"><?= e($p['title']) ?></span>
          <?php endif; ?>
          <?php if ($p['reject_reason']): ?>
            <div class="small" style="color:var(--bad);margin-top:.2rem"><?= e($p['reject_reason']) ?></div>
          <?php endif; ?>
          <div class="small muted"><?= e((string)$p['model']) ?></div>
        </td>
        <td class="small"><?= e($p['cluster']) ?></td>
        <td class="r"><?= number_format((int)$p['word_count']) ?></td>
        <td class="r small">$<?= number_format((float)$p['cost_usd'], 4) ?></td>
        <?php $pill = ['published' => 'won', 'failed' => 'fail', 'queued' => 'new'][$p['status']] ?? 'lost'; ?>
        <td><span class="pill pill--<?= e($pill) ?>">
              <?= e($p['status'] === 'queued' ? 'draft' : (string)$p['status']) ?></span></td>
        <td class="n small"><?= e(local_time($p['published_at'] ?: $p['created_at'], 'd M, H:i')) ?></td>
        <td class="r">
          <?php if (Auth::isAdmin()): ?>
          <div class="row" style="gap:.25rem;justify-content:flex-end;flex-wrap:nowrap">
            <?php if ($p['status'] === 'published'): ?>
              <form method="post" style="margin:0"><?= Csrf::field() ?>
                <input type="hidden" name="action" value="unpublish">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn--ghost btn--sm" type="submit">Take down</button></form>
            <?php elseif ($p['status'] !== 'failed'): ?>
              <form method="post" style="margin:0"><?= Csrf::field() ?>
                <input type="hidden" name="action" value="publish">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn--sm" type="submit">Publish</button></form>
            <?php endif; ?>
            <form method="post" style="margin:0"
                  onsubmit="return confirm('Delete this permanently?')"><?= Csrf::field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="btn btn--danger btn--sm" type="submit">Delete</button></form>
          </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
<?php layout_bottom();
