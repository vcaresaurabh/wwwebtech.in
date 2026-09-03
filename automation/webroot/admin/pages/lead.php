<?php
/* ============================================================
   One lead, in full — everything captured, plus your notes and
   the status control. Reached from the table or straight from the
   "Open in the panel" button in the notification email.
   ============================================================ */
declare(strict_types=1);

/* Reached only through admin/index.php, which has already checked the session,
   the CSRF token and the role. A direct request must not run any of this. */
if (!defined('WWT_ADMIN')) { http_response_code(404); exit; }


$id = (int)($_GET['id'] ?? 0);

$action = field($_POST, 'action', 40);
if ($action !== '') {
    Auth::requireAdmin();
    $id = (int)($_POST['id'] ?? $id);
    try {
        switch ($action) {
            case 'save': {
                $to = field($_POST, 'status', 20);
                if (!isset(Leads::STATUSES[$to])) throw new InvalidArgumentException('Unknown status.');
                DB::run('UPDATE wwt_leads SET status = ?, notes = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                        [$to, cut(trim((string)($_POST['notes'] ?? '')), 5000), $id]);
                audit('lead_update', "id=$id → $to", Auth::email());
                redirect('/admin/?p=lead&id=' . $id, 'ok', 'Saved.');
            }
            case 'stop_funnel': {
                /* The owner has decided this person should be left alone.
                   Recorded with their name, because "who stopped it" is the
                   first question when someone asks why nothing was sent. */
                $n = Funnel::stop($id, 'stopped by ' . Auth::email());
                audit('funnel_stop_lead', 'lead ' . $id, Auth::email());
                redirect('/admin/?p=lead&id=' . $id, 'ok',
                    $n ? 'Stopped. Nothing further will be sent automatically.'
                       : 'Nothing was running for them.');
            }
            case 'resend': {
                $l = DB::one('SELECT * FROM wwt_leads WHERE id = ?', [$id]);
                if (!$l) throw new RuntimeException('That lead no longer exists.');
                if (!Mailer::configured()) throw new RuntimeException('SMTP is not configured yet — see DEPLOY.md (SETUP-2).');
                $m = Leads::ownerEmail($id, $l, (int)$l['is_test'] === 1);
                $r = Mailer::send([
                    'to'            => Mailer::leadRecipient(),
                    'subject'       => $m['subject'],
                    'text'          => $m['text'],
                    'html'          => $m['html'],
                    'reply_to'      => (string)$l['email'],
                    'reply_to_name' => (string)$l['name'],
                ]);
                Leads::markMail($id, $r['ok'] ? 'sent' : 'failed', $r['error']);
                audit('lead_resend', "id=$id " . ($r['ok'] ? 'ok' : 'failed'), Auth::email());
                redirect('/admin/?p=lead&id=' . $id,
                         $r['ok'] ? 'ok' : 'bad',
                         $r['ok'] ? 'Sent again.' : 'Still failing: ' . $r['error']);
            }
            case 'delete': {
                DB::run('DELETE FROM wwt_leads WHERE id = ?', [$id]);
                audit('lead_delete', 'id=' . $id, Auth::email());
                redirect('/admin/?p=leads', 'ok', 'Lead deleted.');
            }
        }
    } catch (Throwable $t) {
        redirect('/admin/?p=lead&id=' . $id, 'bad', $t->getMessage());
    }
}

$l = DB::one('SELECT * FROM wwt_leads WHERE id = ?', [$id]);
if (!$l) {
    http_response_code(404);
    layout_top('Lead not found', 'leads');
    echo '<div class="card">';
    empty_state('That lead is not here', 'It may have been deleted. Everything else is on the leads page.');
    echo '<p style="margin-top:.9rem"><a class="btn btn--ghost btn--sm" href="/admin/?p=leads">Back to leads</a></p></div>';
    layout_bottom();
    exit;
}

$site   = rtrim((string)cfg('site.url', 'https://wwwebtech.in'), '/');
$source = $l['utm_source'] !== '' ? $l['utm_source'] : (ref_domain((string)$l['referrer']) ?: 'direct');
$camp   = trim(implode(' / ', array_filter([$l['utm_source'], $l['utm_medium'], $l['utm_campaign']])));

layout_top($l['name'] . ' · lead', 'leads');
?>
<div class="page-head">
  <div>
    <p class="mono"><a href="/admin/?p=leads" style="text-decoration:underline">Leads</a> · #<?= (int)$l['id'] ?></p>
    <h1><?= e($l['name']) ?>
      <span class="pill pill--<?= e((string)$l['band']) ?>"><?= e((string)$l['band']) ?> · <?= (int)$l['score'] ?></span>
      <?php if ((int)$l['is_test'] === 1): ?><span class="pill pill--info">test</span><?php endif; ?>
      <?php if ((int)$l['is_partial'] === 1): ?><span class="pill pill--warn">abandoned form</span><?php endif; ?>
      <?php if ((int)$l['do_not_contact'] === 1): ?><span class="pill pill--fail">do not contact</span><?php endif; ?></h1>
    <p>Received <?= e(local_time($l['ts'], 'd M Y \a\t H:i')) ?> IST<?php
       if ($l['updated_at']) echo ' · last updated ' . e(local_time($l['updated_at'], 'd M, H:i')); ?></p>
  </div>
  <div class="row" style="gap:.4rem">
    <a class="btn btn--sm" href="/admin/?p=conversations&amp;id=<?= (int)$l['id'] ?>">Open the conversation</a>
    <?php if ($l['phone'] !== ''):
      $wa = preg_replace('/\D/', '', (string)$l['phone']);
      if (strlen($wa) === 10) $wa = '91' . $wa; ?>
      <a class="btn btn--ghost btn--sm" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a>
    <?php endif; ?>
  </div>
</div>

<?php if (in_array($l['mail_status'], ['failed', 'skipped'], true)): ?>
  <div class="alert alert--warn">
    This lead was saved but not emailed<?= $l['mail_error'] ? ' — ' . e($l['mail_error']) : '' ?>.
    Nothing was lost; the enquiry is below.
    <?php if (Auth::isAdmin()): ?>
    <form method="post" style="display:inline-block;margin-left:.5rem">
      <?= Csrf::field() ?><input type="hidden" name="action" value="resend">
      <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
      <button class="btn btn--sm" type="submit">Try sending again</button>
    </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="grid grid--split">

  <section class="card">
    <h2>What they wrote</h2>
    <div class="lead-msg" style="margin-top:.6rem"><?= e($l['message']) ?></div>

    <h2 style="margin-top:1.4rem">Details</h2>
    <dl class="dl" style="margin-top:.6rem">
      <dt>Email</dt><dd><a href="mailto:<?= e($l['email']) ?>" style="text-decoration:underline"><?= e($l['email']) ?></a></dd>
      <?php if ($l['phone'] !== ''): ?><dt>Phone</dt><dd><a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string)$l['phone'])) ?>" style="text-decoration:underline"><?= e($l['phone']) ?></a></dd><?php endif; ?>
      <?php if ($l['service'] !== ''): ?><dt>Needs</dt><dd><?= e($l['service']) ?></dd><?php endif; ?>
      <?php if ($l['budget'] !== ''): ?><dt>Budget</dt><dd><?= e($l['budget']) ?></dd><?php endif; ?>
      <?php if ($l['country'] !== ''): ?><dt>Country</dt><dd><?= e($l['country']) ?></dd><?php endif; ?>
    </dl>

    <h2 style="margin-top:1.4rem">Where they came from</h2>
    <dl class="dl" style="margin-top:.6rem">
      <dt>Page</dt><dd><?php if ($l['page'] !== ''): ?>
        <a href="<?= e($site . $l['page']) ?>" target="_blank" rel="noopener" style="text-decoration:underline"><?= e($l['page']) ?></a>
      <?php else: ?><span class="muted">not recorded</span><?php endif; ?></dd>
      <dt>Source</dt><dd><?= e($source) ?></dd>
      <?php if ($camp !== ''): ?><dt>Campaign</dt><dd><?= e($camp) ?></dd><?php endif; ?>
      <?php if ($l['referrer'] !== ''): ?><dt>Referrer</dt><dd class="small" style="word-break:break-all"><?= e($l['referrer']) ?></dd><?php endif; ?>
      <dt>Network</dt><dd class="small"><?= e($l['ip_trunc'] ?: '—') ?>
        <span class="muted">· truncated, never the full address</span></dd>
      <dt>Email copy</dt><dd>
        <span class="pill pill--<?= $l['mail_status'] === 'sent' ? 'ok' : ($l['mail_status'] === 'pending' ? 'info' : 'fail') ?>"><?= e($l['mail_status']) ?></span>
        <?php if ($l['mail_error']): ?><span class="small muted"><?= e($l['mail_error']) ?></span><?php endif; ?>
      </dd>
    </dl>

    <h2 style="margin-top:1.4rem">Why this score</h2>
    <?php $why = Score::of($l); ?>
    <p class="muted small" style="margin:.3rem 0 .5rem">
      <?= (int)$why['score'] ?> points, which puts them in <b><?= e((string)$why['band']) ?></b>
      (hot from <?= Score::hotAt() ?>, warm from <?= Score::warmAt() ?>). Those thresholds are yours to
      move in Settings — if nearly everything is landing in one band, the band is wrong, not the leads.</p>
    <ul class="stack mono small" style="list-style:none;padding:0;margin:0;gap:.2rem">
      <?php foreach ($why['why'] as $line): ?>
        <li><?= e((string)$line) ?></li>
      <?php endforeach; ?>
    </ul>

    <h2 style="margin-top:1.4rem">Everything that has happened</h2>
    <?php $events = Timeline::of((int)$l['id']); ?>
    <?php if (!$events): ?>
      <p class="muted small">Nothing yet beyond the enquiry itself.</p>
    <?php else: ?>
      <ol class="stack" style="list-style:none;padding:0;margin:.6rem 0 0;gap:.55rem">
        <?php foreach ($events as $ev): ?>
          <li style="display:flex;gap:.7rem;align-items:baseline">
            <span class="mono small muted" style="flex:0 0 8.5rem"><?= e(local_time((string)$ev['ts'], 'd M, H:i')) ?></span>
            <span><b><?= e(str_replace('_', ' ', (string)$ev['kind'])) ?></b>
              <?php if ((string)$ev['channel'] !== ''): ?><span class="muted small">· <?= e((string)$ev['channel']) ?></span><?php endif; ?>
              <?php if ((string)$ev['detail'] !== ''): ?><div class="small muted"><?= e((string)$ev['detail']) ?></div><?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </section>

  <aside>
    <?php $enrol = DB::one("SELECT e.*, s.title FROM wwt_sequence_enrollments e
                            LEFT JOIN wwt_sequences s ON s.id = e.sequence_id
                            WHERE e.lead_id = ? ORDER BY e.id DESC LIMIT 1", [(int)$l['id']]); ?>
    <section class="card">
      <h2>Automation</h2>
      <?php if (!$enrol): ?>
        <p class="muted small" style="margin:.4rem 0 0">Not in a sequence.</p>
      <?php else: ?>
        <dl class="dl" style="margin-top:.5rem">
          <dt>Sequence</dt><dd><?= e((string)($enrol['title'] ?? '—')) ?></dd>
          <dt>State</dt><dd><span class="pill pill--<?= (string)$enrol['status'] === 'active' ? 'ok' : 'info' ?>"><?= e((string)$enrol['status']) ?></span>
            <?php if ((string)$enrol['stop_reason'] !== ''): ?><div class="small muted"><?= e((string)$enrol['stop_reason']) ?></div><?php endif; ?></dd>
          <dt>Step</dt><dd><?= (int)$enrol['step'] ?></dd>
          <dt>Next</dt><dd><?php if ($enrol['next_run_at'] === null): ?><span class="muted">nothing scheduled</span>
            <?php else: ?><?= e(local_time((string)$enrol['next_run_at'], 'd M, H:i')) ?><?php endif; ?></dd>
        </dl>
        <?php if (Auth::isAdmin() && (string)$enrol['status'] === 'active'): ?>
        <form method="post" style="margin-top:.5rem">
          <?= Csrf::field() ?><input type="hidden" name="action" value="stop_funnel">
          <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
          <button class="btn btn--ghost btn--sm" type="submit">Stop messaging them</button>
        </form>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2>Status &amp; notes</h2>
      <?php if (Auth::isAdmin()): ?>
      <form method="post" style="margin-top:.7rem">
        <?= Csrf::field() ?><input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
        <div class="field">
          <label for="st">Stage</label>
          <select id="st" name="status">
            <?php foreach (Leads::STATUSES as $k => $label): ?>
              <option value="<?= e($k) ?>"<?= $l['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="nt">Notes</label>
          <textarea id="nt" name="notes" rows="7" maxlength="5000"
                    placeholder="What you agreed, what you quoted, when to follow up."><?= e((string)$l['notes']) ?></textarea>
          <p class="hint">Private to the panel. Never sent to anyone.</p>
        </div>
        <button class="btn" type="submit">Save</button>
      </form>
      <?php else: ?>
        <p class="small" style="margin-top:.6rem">
          <span class="pill pill--<?= e($l['status']) ?>"><?= e(Leads::STATUSES[$l['status']] ?? $l['status']) ?></span></p>
        <?php if ($l['notes']): ?>
          <div class="small" style="white-space:pre-wrap;margin-top:.7rem;color:var(--ink-2)"><?= e((string)$l['notes']) ?></div>
        <?php else: ?>
          <p class="small muted" style="margin-top:.7rem">No notes. Your account is read-only.</p>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <?php if (Auth::isAdmin()): ?>
    <section class="card" style="margin-top:.9rem">
      <h2>Remove</h2>
      <p class="small muted" style="margin:.4rem 0 .7rem">Deleting is permanent. Export first if you might need it.</p>
      <form method="post" onsubmit="return confirm('Delete this lead permanently?')">
        <?= Csrf::field() ?><input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
        <button class="btn btn--danger btn--sm" type="submit">Delete this lead</button>
      </form>
    </section>
    <?php endif; ?>
  </aside>
</div>
<?php layout_bottom();
