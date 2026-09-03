<?php
/* ============================================================
   Conversations — every thread with a lead, in one place.

   The point of this page is that replying here is the same act as
   replying from your own mailbox, except that it is recorded and it
   stops the sequence. If it were slower or clumsier than the mailbox
   nobody would use it, and the record would rot — so the reply box is
   the first thing in a thread, already focused, with the draft button
   next to it rather than in a dialog.

   Sending here is never automated: a human pressed the button, which
   is precisely the event that ends the automation for that lead.
   ============================================================ */
declare(strict_types=1);
if (!defined('WWT_ADMIN')) { http_response_code(404); exit; }

$leadId = (int)gets('id', '0');
$action = field($_POST, 'action', 40);

if ($action !== '') {
    Auth::requireAdmin();
    $id = (int)($_POST['id'] ?? 0);
    try {
        $l = DB::one('SELECT * FROM wwt_leads WHERE id = ?', [$id]);
        if (!$l) throw new RuntimeException('No such lead.');

        switch ($action) {
            case 'reply': {
                $channel = field($_POST, 'channel', 12);
                $body    = trim((string)($_POST['body'] ?? ''));
                $subject = field($_POST, 'subject', 190);
                if ($body === '') throw new RuntimeException('There is nothing to send.');
                if ((int)$l['do_not_contact'] === 1) {
                    throw new RuntimeException('This person has asked not to be contacted. '
                        . 'If they have since asked you to get back in touch, clear that on their lead first.');
                }

                if ($channel === 'whatsapp') {
                    $r = WhatsApp::sendSession($l, $body);
                    if (empty($r['ok'])) throw new RuntimeException((string)($r['error'] ?? 'WhatsApp refused it.'));
                    $status = 'sent'; $cost = (int)($r['cost_paise'] ?? 0); $pid = (string)($r['id'] ?? '');
                } else {
                    if ($subject === '') $subject = 'Re: your enquiry';
                    $r = Mailer::sendAs([
                        'to' => (string)$l['email'], 'to_name' => (string)$l['name'],
                        'subject' => $subject, 'text' => $body,
                        'html' => nl2br(e($body), false),
                        'lead_id' => $id,
                    ]);
                    if (empty($r['ok'])) throw new RuntimeException((string)($r['error'] ?? 'The mail server refused it.'));
                    $status = 'sent'; $cost = 0; $pid = '';
                }

                DB::insert("INSERT INTO wwt_messages (lead_id, ts, direction, channel, subject, body,
                            status, cost_paise, provider_id, approved_by, is_ai)
                            VALUES (?, UTC_TIMESTAMP(), 'out', ?, ?, ?, ?, ?, ?, ?, 0)",
                    [$id, $channel === 'whatsapp' ? 'whatsapp' : 'email',
                     cut($subject, 255), $body, $status, $cost, cut($pid, 190), cut(Auth::email(), 150)]);

                Timeline::add($id, 'reply_sent', $channel, cut($subject, 200), Auth::email());

                /* A human just engaged. That is the stop condition. */
                Funnel::stop($id, 'you replied');
                if ((string)$l['status'] === 'new') {
                    DB::run("UPDATE wwt_leads SET status = 'contacted', updated_at = UTC_TIMESTAMP() WHERE id = ?", [$id]);
                }
                audit('conv_reply', 'lead ' . $id . ' via ' . $channel, Auth::email());
                redirect('/admin/?p=conversations&id=' . $id, 'ok', 'Sent, and the sequence is stopped.');
            }
            case 'note': {
                $body = trim((string)($_POST['body'] ?? ''));
                if ($body === '') throw new RuntimeException('Nothing to save.');
                DB::insert("INSERT INTO wwt_messages (lead_id, ts, direction, channel, subject, body,
                            status, approved_by) VALUES (?, UTC_TIMESTAMP(), 'out', 'note', 'Note', ?, 'sent', ?)",
                    [$id, $body, cut(Auth::email(), 150)]);
                redirect('/admin/?p=conversations&id=' . $id, 'ok', 'Note saved. It is not sent anywhere.');
            }
            case 'draft': {
                /* A draft is written, shown, and nothing more. It is never
                   queued from here — the owner still has to press Send. */
                $step = ['step_no' => 0, 'channel' => 'email', 'purpose' => field($_POST, 'purpose', 80) ?: 'reply to their last message',
                         'template_key' => '', 'offset_minutes' => 0];
                $out = FunnelWriter::compose($l, $step, 'email');
                if (empty($out['ok'])) throw new RuntimeException((string)($out['error'] ?? 'Could not write a draft.'));
                $_SESSION['conv_draft'] = ['id' => $id, 'subject' => $out['subject'], 'body' => $out['body']];
                redirect('/admin/?p=conversations&id=' . $id, 'ok', 'Draft ready — read it before you send it.');
            }
        }
    } catch (Throwable $t) {
        redirect('/admin/?p=conversations' . ($id ? '&id=' . $id : ''), 'bad', $t->getMessage());
    }
}

/* ── Thread list ───────────────────────────────────────────
   Ordered by last activity, with anything unanswered first: a reply
   sitting unanswered is the only thing on this page that is urgent. */
$threads = DB::all(
    "SELECT l.id, l.name, l.email, l.phone, l.band, l.status, l.do_not_contact,
            t.last_ts, t.msgs, t.last_dir,
            (SELECT m2.body FROM wwt_messages m2 WHERE m2.lead_id = l.id
              ORDER BY m2.ts DESC, m2.id DESC LIMIT 1) AS last_body
     FROM (SELECT lead_id, MAX(ts) AS last_ts, COUNT(*) AS msgs,
                  SUBSTRING_INDEX(GROUP_CONCAT(direction ORDER BY ts DESC, id DESC), ',', 1) AS last_dir
           FROM wwt_messages GROUP BY lead_id) t
     JOIN wwt_leads l ON l.id = t.lead_id
     ORDER BY (t.last_dir = 'in') DESC, t.last_ts DESC
     LIMIT 60");

$lead = $leadId > 0 ? DB::one('SELECT * FROM wwt_leads WHERE id = ?', [$leadId]) : null;
$thread = $lead ? DB::all('SELECT * FROM wwt_messages WHERE lead_id = ? ORDER BY ts ASC, id ASC', [$leadId]) : [];
$draft  = ($_SESSION['conv_draft']['id'] ?? 0) === $leadId ? $_SESSION['conv_draft'] : null;
unset($_SESSION['conv_draft']);

$snippets = Settings::json('conv_snippets', [
    'Ask for a call'    => "Would a ten-minute call be easier than email? I am free most afternoons this week — tell me a time that suits and I will ring you.",
    'Send the quote'    => "Here is what the work would involve and what it would cost.\n\n[  ]\n\nHappy to talk any of it through before you decide.",
    'Nothing needed'    => "Having looked properly, I do not think you need to spend money on this yet. If that changes, you know where I am.",
    'Chasing politely'  => "Just checking this did not get buried. No rush at all — if the timing is wrong, say so and I will leave you be.",
]);

layout_top('Conversations', 'conversations');
?>
<div class="page-head">
  <div>
    <h1>Conversations</h1>
    <p>Every message with a lead, in and out, on one thread. Replying here stops whatever
       sequence they were in — which is the point.</p>
  </div>
</div>

<div class="grid grid--split">
  <div>
    <?php if (!$threads): ?>
      <div class="card"><?php empty_state('No conversations yet',
        'A thread starts the first time a message is sent to a lead, or the first time one replies.'); ?></div>
    <?php else: ?>
    <div class="card card--pad0">
      <div class="tablewrap" style="border:0"><table>
        <thead><tr><th>Lead</th><th>Last message</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($threads as $t): ?>
          <tr<?= (int)$t['id'] === $leadId ? ' aria-current="true"' : '' ?>>
            <td>
              <a href="/admin/?p=conversations&amp;id=<?= (int)$t['id'] ?>" style="text-decoration:underline;font-weight:600"><?= e((string)$t['name']) ?></a>
              <?php if ((string)$t['last_dir'] === 'in'): ?> <span class="pill pill--warn">replied</span><?php endif; ?>
              <?php if ((int)$t['do_not_contact'] === 1): ?> <span class="pill pill--fail">do not contact</span><?php endif; ?>
              <div class="small muted mono"><?= e((string)$t['email']) ?></div>
            </td>
            <td class="small muted"><?= e(cut(trim(strip_tags((string)$t['last_body'])), 70)) ?></td>
            <td class="small n" style="white-space:nowrap"><?= e(local_time((string)$t['last_ts'], 'd M, H:i')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>
    <?php endif; ?>
  </div>

  <div>
  <?php if (!$lead): ?>
    <div class="card"><?php empty_state('Pick a conversation',
      'Choose someone on the left to read the thread and reply.'); ?></div>
  <?php else: ?>
    <div class="card">
      <div class="row" style="justify-content:space-between;gap:1rem;flex-wrap:wrap">
        <div>
          <h2 style="margin:0"><a href="/admin/?p=lead&amp;id=<?= (int)$lead['id'] ?>" style="text-decoration:underline"><?= e((string)$lead['name']) ?></a></h2>
          <p class="small muted" style="margin:.2rem 0 0">
            <span class="mono"><?= e((string)$lead['email']) ?></span>
            <?php if ((string)$lead['phone'] !== ''): ?> · <span class="mono"><?= e((string)$lead['phone']) ?></span><?php endif; ?>
            · <span class="pill pill--<?= e((string)$lead['band']) ?>"><?= e((string)$lead['band']) ?> <?= (int)$lead['score'] ?></span>
          </p>
        </div>
        <?php if ((string)$lead['phone'] !== ''): ?>
        <a class="btn btn--ghost btn--sm" href="https://wa.me/<?= e(WhatsApp::e164((string)$lead['phone'])) ?>" rel="noopener">Open in WhatsApp</a>
        <?php endif; ?>
      </div>

      <?php if ((int)$lead['do_not_contact'] === 1): ?>
        <div class="alert alert--bad">They have opted out. Nothing may be sent to them, by hand or otherwise.</div>
      <?php endif; ?>

      <?php if (Auth::isAdmin() && (int)$lead['do_not_contact'] !== 1): ?>
      <form method="post" class="stack" style="margin-top:.8rem">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$lead['id'] ?>">
        <div class="row" style="gap:.5rem;flex-wrap:wrap;align-items:flex-end">
          <div class="field" style="flex:0 0 auto">
            <label for="channel">Send by</label>
            <select id="channel" name="channel">
              <option value="email">Email</option>
              <option value="whatsapp"<?= WhatsApp::sendable() ? '' : ' disabled' ?>>WhatsApp<?= WhatsApp::sendable() ? '' : ' (not available)' ?></option>
            </select>
          </div>
          <div class="field" style="flex:1 1 260px">
            <label for="subject">Subject</label>
            <input id="subject" name="subject" value="<?= e($draft['subject'] ?? ('Re: ' . cut((string)$lead['service'] ?: 'your enquiry', 60))) ?>">
          </div>
        </div>
        <div class="field">
          <label for="body">Message</label>
          <textarea id="body" name="body" rows="7" autofocus><?= e($draft['body'] ?? '') ?></textarea>
        </div>
        <div class="row" style="gap:.4rem;flex-wrap:wrap">
          <button class="btn btn--sm" type="submit" name="action" value="reply">Send</button>
          <button class="btn btn--ghost btn--sm" type="submit" name="action" value="draft">Ask Claude for a draft</button>
          <button class="btn btn--ghost btn--sm" type="submit" name="action" value="note">Save as a private note</button>
        </div>
        <?php if ($snippets): ?>
        <div class="row" style="gap:.35rem;flex-wrap:wrap;margin-top:.2rem">
          <span class="small muted">Snippets:</span>
          <?php foreach ($snippets as $label => $text): ?>
            <button class="linkbtn small" type="button"
                    data-snippet="<?= e((string)$text) ?>"><?= e((string)$label) ?></button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <p class="small muted" style="margin:0">Sending stops every sequence for this person, immediately.</p>
      </form>
      <?php endif; ?>
    </div>

    <?php if (!$thread): ?>
      <div class="card"><?php empty_state('Nothing sent yet', 'Your first message will appear here.'); ?></div>
    <?php else: ?>
      <?php foreach (array_reverse($thread) as $m):
        $in = (string)$m['direction'] === 'in';
        $note = (string)$m['channel'] === 'note'; ?>
        <div class="card" style="border-left:3px solid <?= $in ? 'var(--marigold,#E07000)' : ($note ? 'var(--rule)' : 'var(--ink-3,#686D69)') ?>">
          <div class="row small muted" style="justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <span><b><?= $in ? e((string)$lead['name']) : ($note ? 'Private note' : 'You') ?></b>
              · <?= e((string)$m['channel']) ?>
              <?php if ((int)$m['is_ai'] === 1): ?> · <span class="pill pill--info">AI written</span><?php endif; ?>
              <?php if ((string)$m['status'] !== 'sent' && (string)$m['status'] !== 'received'): ?>
                · <span class="pill pill--<?= (string)$m['status'] === 'failed' ? 'fail' : 'warn' ?>"><?= e((string)$m['status']) ?></span>
              <?php endif; ?>
            </span>
            <span><?= e(local_time((string)$m['ts'], 'd M Y, H:i')) ?></span>
          </div>
          <?php if ((string)$m['subject'] !== '' && !$note): ?>
            <div style="font-weight:600;margin-top:.35rem"><?= e((string)$m['subject']) ?></div>
          <?php endif; ?>
          <div class="lead-msg" style="white-space:pre-wrap;margin-top:.35rem"><?= e(trim((string)$m['body'])) ?></div>
          <?php if ((string)$m['error'] !== ''): ?>
            <p class="small" style="color:var(--bad,#B3261E);margin:.4rem 0 0"><?= e((string)$m['error']) ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>
  </div>
</div>

<script>
/* Snippets paste at the cursor rather than replacing what is there —
   they are openings and closings, not whole messages. */
document.addEventListener('click', function (ev) {
  var b = ev.target.closest('[data-snippet]');
  if (!b) return;
  var ta = document.getElementById('body');
  if (!ta) return;
  var at = ta.selectionStart || ta.value.length;
  var text = b.getAttribute('data-snippet');
  ta.value = ta.value.slice(0, at) + text + ta.value.slice(ta.selectionEnd || at);
  ta.focus();
  ta.selectionStart = ta.selectionEnd = at + text.length;
});
</script>
<?php layout_bottom();
