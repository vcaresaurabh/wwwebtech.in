<?php
/* ============================================================
   Funnel — what the automation is doing, and the switch to stop it.

   The most important control on this page is the kill switch, and it
   is the first thing on it. Everything else is there to answer one
   question honestly: who is being messaged, what will they receive,
   and what has it cost.

   Review-before-send is the default (§5.2). The approval queue below
   is therefore the normal path, not an exception state.
   ============================================================ */
declare(strict_types=1);
if (!defined('WWT_ADMIN')) { http_response_code(404); exit; }

$action = field($_POST, 'action', 40);
if ($action !== '') {
    Auth::requireAdmin();
    try {
        switch ($action) {
            case 'kill': {
                $on = !empty($_POST['on']);
                Settings::set('funnel_kill_switch', $on ? '1' : '0');
                if ($on) {
                    /* Stopping means stopping. Anything already written and
                       waiting goes with it — a queued message that sends
                       after the owner hit the switch is the exact failure
                       this control exists to prevent. */
                    $n = DB::run("UPDATE wwt_messages SET status = 'skipped', error = 'funnel stopped by owner'
                                  WHERE status IN ('queued','pending_review')")->rowCount();
                    DB::run("UPDATE wwt_sequence_enrollments SET status = 'paused' WHERE status = 'active'");
                    audit('funnel_kill', 'on, ' . $n . ' pending message(s) cancelled', Auth::email());
                    redirect('/admin/?p=funnel', 'warn',
                        'Everything is stopped. ' . $n . ' pending ' . ($n === 1 ? 'message was' : 'messages were') . ' cancelled.');
                }
                DB::run("UPDATE wwt_sequence_enrollments SET status = 'active' WHERE status = 'paused'");
                audit('funnel_kill', 'off', Auth::email());
                redirect('/admin/?p=funnel', 'ok', 'Sequences resumed. Nothing that was cancelled comes back.');
            }
            case 'enabled': {
                Settings::set('funnel_enabled', !empty($_POST['on']) ? '1' : '0');
                audit('funnel_enabled', !empty($_POST['on']) ? 'on' : 'off', Auth::email());
                redirect('/admin/?p=funnel', 'ok', 'Saved.');
            }
            case 'auto_send': {
                /* Opt-in PER SEQUENCE (§0.4). A single global switch would
                   let one decision about the gentlest nurture sequence turn
                   off review for the hot one too. */
                $sid = (int)($_POST['sid'] ?? 0);
                $on  = !empty($_POST['on']);
                $seq = DB::one('SELECT * FROM wwt_sequences WHERE id = ?', [$sid]);
                if (!$seq) throw new RuntimeException('No such sequence.');
                DB::run('UPDATE wwt_sequences SET auto_send = ? WHERE id = ?', [$on ? 1 : 0, $sid]);
                audit('funnel_auto_send', $seq['key_name'] . ($on ? ' → ON, sends without review' : ' → off'), Auth::email());
                redirect('/admin/?p=funnel', $on ? 'warn' : 'ok',
                    $on ? '"' . $seq['title'] . '" now sends without you seeing each message first.'
                        : '"' . $seq['title'] . '" is back to review-before-send.');
            }
            case 'approve': {
                $id = (int)($_POST['id'] ?? 0);
                $m  = DB::one("SELECT * FROM wwt_messages WHERE id = ? AND status = 'pending_review'", [$id]);
                if (!$m) throw new RuntimeException('That message is no longer waiting for review.');
                $body = (string)($_POST['body'] ?? '');
                $subj = field($_POST, 'subject', 190);
                DB::run("UPDATE wwt_messages SET body = ?, subject = ?, status = 'queued',
                         approved_by = ?, send_after = UTC_TIMESTAMP() WHERE id = ?",
                        [$body, $subj, cut(Auth::email(), 150), $id]);
                audit('funnel_approve', 'message ' . $id, Auth::email());
                redirect('/admin/?p=funnel', 'ok', 'Approved. It goes out on the next run.');
            }
            case 'reject': {
                $id = (int)($_POST['id'] ?? 0);
                DB::run("UPDATE wwt_messages SET status = 'skipped', error = 'rejected in review'
                         WHERE id = ? AND status = 'pending_review'", [$id]);
                audit('funnel_reject', 'message ' . $id, Auth::email());
                redirect('/admin/?p=funnel', 'ok', 'Discarded. The sequence carries on to the next step.');
            }
            case 'stop_lead': {
                $id = (int)($_POST['id'] ?? 0);
                $n  = Funnel::stop($id, 'stopped by ' . Auth::email());
                audit('funnel_stop_lead', 'lead ' . $id, Auth::email());
                redirect('/admin/?p=funnel', 'ok', $n ? 'Stopped.' : 'Nothing was running for that lead.');
            }
            case 'run': {
                $out = Jobs::run('funnel_tick');
                redirect('/admin/?p=funnel', 'ok', 'Ran now: ' . cut($out, 180));
            }
        }
    } catch (Throwable $t) {
        redirect('/admin/?p=funnel', 'bad', $t->getMessage());
    }
}

$killed  = Funnel::killed();
$enabled = Funnel::enabled();

$pending = DB::all(
    "SELECT m.*, l.name, l.email AS lead_email, l.band, l.id AS lid
     FROM wwt_messages m JOIN wwt_leads l ON l.id = m.lead_id
     WHERE m.status = 'pending_review' ORDER BY m.id ASC LIMIT 25");

$active = DB::all(
    "SELECT e.*, l.name, l.email, l.band, s.title AS seq_name,
            (SELECT COUNT(*) FROM wwt_messages m WHERE m.lead_id = l.id AND m.direction = 'out'
                                                   AND m.status = 'sent') AS sent
     FROM wwt_sequence_enrollments e
     JOIN wwt_leads l ON l.id = e.lead_id
     LEFT JOIN wwt_sequences s ON s.id = e.sequence_id
     WHERE e.status IN ('active','paused')
     ORDER BY e.next_run_at IS NULL, e.next_run_at ASC LIMIT 40");

$stat = DB::one(
    "SELECT
       SUM(status = 'sent')           AS sent,
       SUM(status = 'failed')         AS failed,
       SUM(status = 'skipped')        AS skipped,
       SUM(status = 'pending_review') AS waiting
     FROM wwt_messages WHERE direction = 'out'
       AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)") ?: [];

$replies = (int)DB::val("SELECT COUNT(DISTINCT lead_id) FROM wwt_messages
                         WHERE direction = 'in' AND ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)", [], 0);
$stopped = (int)DB::val("SELECT COUNT(*) FROM wwt_sequence_enrollments
                         WHERE status = 'stopped' AND ended_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)", [], 0);

$wa   = WhatsApp::spentPaiseThisMonth();
$cap  = WhatsApp::capPaise();
$seqs = DB::all("SELECT s.*, (SELECT COUNT(*) FROM wwt_sequence_steps st WHERE st.sequence_id = s.id) AS steps,
                        (SELECT COUNT(*) FROM wwt_sequence_enrollments e WHERE e.sequence_id = s.id) AS enrolled
                 FROM wwt_sequences s ORDER BY s.id");
$autoAny = (int)DB::val('SELECT COUNT(*) FROM wwt_sequences WHERE auto_send = 1 AND is_active = 1', [], 0);

layout_top('Funnel', 'funnel');
?>
<div class="page-head">
  <div>
    <h1>Funnel</h1>
    <p>What the automation is about to say, to whom, and when. Nothing here sends
       without passing the same checks twice — once when it is written, once when you approve it.</p>
  </div>
  <?php if (Auth::isAdmin()): ?>
  <form method="post" class="row" style="gap:.4rem">
    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
    <input type="hidden" name="action" value="run">
    <button class="btn btn--ghost btn--sm" type="submit">Run a tick now</button>
  </form>
  <?php endif; ?>
</div>

<?php /* The kill switch first, because in the minute someone needs it they
         should not have to look for it. */ ?>
<div class="card" style="border-color:<?= $killed ? 'var(--bad,#B3261E)' : 'var(--rule)' ?>">
  <div class="row" style="justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
    <div>
      <h2 style="margin:0 0 .3rem"><?= $killed ? 'Everything is stopped' : 'Emergency stop' ?></h2>
      <p class="muted" style="margin:0;max-width:44rem">
        <?php if ($killed): ?>
          No sequence is running and nothing is queued. Enrolments are paused, not deleted —
          resuming picks them up where they were, but the messages that were cancelled stay cancelled.
        <?php else: ?>
          Halts every sequence and cancels every message that is written but not yet sent.
          Use it the moment something reads wrong; there is no cost to being early.
        <?php endif; ?>
      </p>
    </div>
    <?php if (Auth::isAdmin()): ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
      <input type="hidden" name="action" value="kill">
      <input type="hidden" name="on" value="<?= $killed ? '0' : '1' ?>">
      <button class="btn <?= $killed ? '' : 'btn--danger' ?>" type="submit">
        <?= $killed ? 'Resume sequences' : 'Stop everything' ?></button>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="kpis">
  <div class="kpi"><div class="kpi__k">Sent · 30 days</div><div class="kpi__v"><?= (int)($stat['sent'] ?? 0) ?></div>
    <div class="kpi__d"><?= (int)($stat['failed'] ?? 0) ?> failed, <?= (int)($stat['skipped'] ?? 0) ?> cancelled</div></div>
  <div class="kpi"><div class="kpi__k">Waiting for you</div><div class="kpi__v"><?= (int)($stat['waiting'] ?? 0) ?></div>
    <div class="kpi__d"><?= $autoAny > 0
        ? $autoAny . ' ' . ($autoAny === 1 ? 'sequence sends' : 'sequences send') . ' unreviewed'
        : 'review before send' ?></div></div>
  <div class="kpi"><div class="kpi__k">People who replied</div><div class="kpi__v"><?= $replies ?></div>
    <div class="kpi__d"><?= $stopped ?> sequences stopped as a result</div></div>
  <div class="kpi"><div class="kpi__k">WhatsApp this month</div>
    <div class="kpi__v">₹<?= number_format($wa / 100, 2) ?></div>
    <div class="kpi__d">cap ₹<?= number_format($cap / 100, 0) ?><?= $wa >= $cap ? ' — reached' : '' ?></div></div>
</div>

<?php if (Auth::isAdmin()): ?>
<div class="card">
  <h2>How it sends</h2>
  <div class="row" style="gap:1.5rem;flex-wrap:wrap;align-items:flex-start">
    <form method="post" class="stack" style="gap:.4rem">
      <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
      <input type="hidden" name="action" value="enabled">
      <input type="hidden" name="on" value="<?= $enabled ? '0' : '1' ?>">
      <b>Sequences <?= $enabled ? 'are running' : 'are off' ?></b>
      <p class="muted small" style="margin:0;max-width:24rem">New leads
        <?= $enabled ? 'are enrolled automatically as they arrive.' : 'are not enrolled in anything.' ?></p>
      <button class="btn btn--ghost btn--sm" type="submit"><?= $enabled ? 'Turn off' : 'Turn on' ?></button>
    </form>
    <div class="stack" style="gap:.4rem">
      <b>Review before send</b>
      <p class="muted small" style="margin:0;max-width:26rem">Every AI-written message waits for you.
        It can be turned off for one sequence at a time, in the table at the bottom of this page —
        never for all of them at once.</p>
    </div>
  </div>
</div>
<?php endif; ?>

<h2 style="margin-top:1.6rem">Waiting for your approval</h2>
<?php if (!$pending): ?>
  <div class="card"><?php empty_state('Nothing to review',
    'When a sequence writes a message it appears here with the lead it is for. You can edit the words before approving.'); ?></div>
<?php else: ?>
  <?php foreach ($pending as $m): ?>
  <div class="card">
    <div class="row" style="justify-content:space-between;gap:1rem;flex-wrap:wrap">
      <div>
        <b><a href="/admin/?p=lead&amp;id=<?= (int)$m['lid'] ?>" style="text-decoration:underline"><?= e($m['name']) ?></a></b>
        <span class="pill pill--<?= e((string)$m['band']) ?>"><?= e((string)$m['band']) ?></span>
        <span class="muted small">· <?= e((string)$m['channel']) ?> · written <?= e(local_time((string)$m['ts'], 'd M, H:i')) ?></span>
      </div>
      <span class="muted small mono"><?= e((string)$m['lead_email']) ?></span>
    </div>
    <?php if (Auth::isAdmin()): ?>
    <form method="post" class="stack" style="margin-top:.7rem">
      <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
      <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
      <?php if ((string)$m['channel'] === 'email'): ?>
      <div class="field"><label for="s<?= (int)$m['id'] ?>">Subject</label>
        <input id="s<?= (int)$m['id'] ?>" name="subject" value="<?= e((string)$m['subject']) ?>"></div>
      <?php endif; ?>
      <div class="field"><label for="b<?= (int)$m['id'] ?>">Message</label>
        <textarea id="b<?= (int)$m['id'] ?>" name="body" rows="8"><?= e((string)$m['body']) ?></textarea></div>
      <div class="row" style="gap:.4rem">
        <button class="btn btn--sm" type="submit" name="action" value="approve">Approve and send</button>
        <button class="btn btn--ghost btn--sm" type="submit" name="action" value="reject">Discard</button>
      </div>
    </form>
    <?php else: ?>
      <pre class="lead-msg" style="white-space:pre-wrap"><?= e((string)$m['body']) ?></pre>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<h2 style="margin-top:1.6rem">In a sequence right now</h2>
<?php if (!$active): ?>
  <div class="card"><?php empty_state('Nobody is enrolled',
    'Leads join a sequence when they arrive, unless they are a test, a partial, or they have already replied.'); ?></div>
<?php else: ?>
<div class="card card--pad0"><div class="tablewrap" style="border:0"><table>
  <thead><tr><th>Lead</th><th>Sequence</th><th>Step</th><th>Sent</th><th>Next message</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($active as $e): ?>
    <tr>
      <td><a href="/admin/?p=lead&amp;id=<?= (int)$e['lead_id'] ?>" style="text-decoration:underline;font-weight:600"><?= e((string)$e['name']) ?></a>
        <span class="pill pill--<?= e((string)$e['band']) ?>"><?= e((string)$e['band']) ?></span>
        <div class="small muted mono"><?= e((string)$e['email']) ?></div></td>
      <td class="small"><?= e((string)($e['seq_name'] ?? '—')) ?></td>
      <td class="n small"><?= (int)$e['step'] ?></td>
      <td class="n small"><?= (int)$e['sent'] ?></td>
      <td class="small">
        <?php if ((string)$e['status'] === 'paused'): ?><span class="pill pill--warn">paused</span>
        <?php elseif ($e['next_run_at'] === null): ?><span class="muted">finished</span>
        <?php else: ?><?= e(local_time((string)$e['next_run_at'], 'd M, H:i')) ?><?php endif; ?>
      </td>
      <td>
        <?php if (Auth::isAdmin()): ?>
        <form method="post"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
          <input type="hidden" name="action" value="stop_lead">
          <input type="hidden" name="id" value="<?= (int)$e['lead_id'] ?>">
          <button class="linkbtn" type="submit">Stop</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<?php endif; ?>

<h2 style="margin-top:1.6rem">Sequences</h2>
<div class="card card--pad0"><div class="tablewrap" style="border:0"><table>
  <thead><tr><th>Sequence</th><th>Steps</th><th>Ever enrolled</th><th>Status</th><th>Approval</th></tr></thead>
  <tbody>
  <?php foreach ($seqs as $sq): ?>
    <tr>
      <td><b><?= e((string)$sq['title']) ?></b>
        <div class="small muted"><?= e((string)$sq['description']) ?></div>
        <div class="small muted mono"><?= e((string)$sq['key_name']) ?></div></td>
      <td class="n small"><?= (int)$sq['steps'] ?></td>
      <td class="n small"><?= (int)$sq['enrolled'] ?></td>
      <td class="small"><span class="pill pill--<?= (int)$sq['is_active'] === 1 ? 'ok' : 'info' ?>">
        <?= (int)$sq['is_active'] === 1 ? 'live' : 'off' ?></span></td>
      <td class="small">
        <?php if (Auth::isAdmin()): ?>
        <form method="post"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
          <input type="hidden" name="action" value="auto_send">
          <input type="hidden" name="sid" value="<?= (int)$sq['id'] ?>">
          <input type="hidden" name="on" value="<?= (int)$sq['auto_send'] === 1 ? '0' : '1' ?>">
          <?php if ((int)$sq['auto_send'] === 1): ?>
            <span class="pill pill--warn">sends unreviewed</span>
            <button class="linkbtn" type="submit">Require review</button>
          <?php else: ?>
            <span class="muted">you approve each one</span>
            <button class="linkbtn" type="submit">Let it send</button>
          <?php endif; ?>
        </form>
        <?php else: ?>
          <?= (int)$sq['auto_send'] === 1 ? 'sends unreviewed' : 'you approve each one' ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<p class="muted small">Steps live in <span class="mono">private/data/sequences.php</span> and are seeded with the
   <span class="mono">funnel_seed</span> job, so a sequence change is a reviewable edit rather than a click nobody remembers making.</p>

<?php layout_bottom();
