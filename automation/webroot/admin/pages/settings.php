<?php
/* Settings — account, 2FA, staff accounts, retention. Admin only
   (index.php already enforced that). */
declare(strict_types=1);

/* Reached only through admin/index.php, which has already checked the session,
   the CSRF token and the role. A direct request must not run any of this. */
if (!defined('WWT_ADMIN')) { http_response_code(404); exit; }


$action = field($_POST, 'action', 40);

if ($action !== '') {
    try {
        switch ($action) {

            case 'password': {
                $cur = (string)($_POST['current'] ?? '');
                $new = (string)($_POST['new'] ?? '');
                if ($new !== (string)($_POST['new2'] ?? '')) throw new InvalidArgumentException('The two new passwords do not match.');
                $row = DB::one('SELECT pass_hash FROM wwt_admin_users WHERE id=?', [Auth::id()]);
                if (!$row || !password_verify($cur, (string)$row['pass_hash'])) {
                    throw new InvalidArgumentException('Your current password is not correct.');
                }
                Auth::setPassword(Auth::id(), $new);
                audit('password_change', 'self', Auth::email());
                redirect('/admin/?p=settings', 'ok', 'Password changed.');
            }

            case 'totp_enable': {
                $secret = field($_POST, 'secret', 64);
                if (!Totp::verify($secret, field($_POST, 'code', 10))) {
                    throw new InvalidArgumentException('That code did not match. Check your app clock and try again.');
                }
                DB::run('UPDATE wwt_admin_users SET totp_secret=? WHERE id=?', [$secret, Auth::id()]);
                audit('2fa_enabled', '', Auth::email());
                redirect('/admin/?p=settings', 'ok', 'Two-factor authentication is on.');
            }

            case 'totp_disable': {
                DB::run('UPDATE wwt_admin_users SET totp_secret=NULL WHERE id=?', [Auth::id()]);
                audit('2fa_disabled', '', Auth::email());
                redirect('/admin/?p=settings', 'warn', 'Two-factor authentication is off.');
            }

            case 'user_add': {
                $id = Auth::createUser(field($_POST, 'email', 150), (string)($_POST['password'] ?? ''),
                                       field($_POST, 'role', 10) === 'admin' ? 'admin' : 'viewer');
                audit('user_add', 'id=' . $id . ' role=' . field($_POST, 'role', 10), Auth::email());
                redirect('/admin/?p=settings', 'ok', 'Account created.');
            }

            case 'user_del': {
                $uid = (int)($_POST['uid'] ?? 0);
                if ($uid === Auth::id()) throw new InvalidArgumentException('You cannot delete the account you are signed in with.');
                if ((int)DB::val('SELECT COUNT(*) FROM wwt_admin_users WHERE role=\'admin\'', [], 0) <= 1
                    && DB::val('SELECT role FROM wwt_admin_users WHERE id=?', [$uid]) === 'admin') {
                    throw new InvalidArgumentException('That is the only admin account — the panel would lock everyone out.');
                }
                DB::run('DELETE FROM wwt_admin_users WHERE id=?', [$uid]);
                audit('user_delete', 'id=' . $uid, Auth::email());
                redirect('/admin/?p=settings', 'ok', 'Account removed.');
            }

            case 'funnel_prefs': {
                Settings::set('funnel_sender_name',  field($_POST, 'sender_name', 80));
                $se = strtolower(field($_POST, 'sender_email', 150));
                if ($se !== '' && !filter_var($se, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('That sender address is not a valid email.');
                }
                Settings::set('funnel_sender_email', $se);
                Settings::set('funnel_review_ping',  isset($_POST['review_ping']) ? '1' : '0');
                /* The automatic acknowledgement to the person who wrote in.
                   An unticked box sends nothing, so absence means off. */
                Settings::set('lead_ack_enabled', isset($_POST['lead_ack_enabled']) ? '1' : '0');
                Settings::set('reply_promise', field($_POST, 'reply_promise', 60) ?: '1 business day');
                $wording = trim((string)($_POST['consent_wording'] ?? ''));
                if ($wording !== '') Settings::set('consent_wording', cut($wording, 400));

                /* Bands. Warm above hot would silently classify nothing as
                   warm, so it is refused rather than saved. */
                $hot  = max(1, min(200, (int)($_POST['hot_at'] ?? 60)));
                $warm = max(0, min(199, (int)($_POST['warm_at'] ?? 35)));
                if ($warm >= $hot) throw new RuntimeException('The warm threshold has to be below the hot one.');
                Settings::set('score_hot_at',  (string)$hot);
                Settings::set('score_warm_at', (string)$warm);
                audit('settings_change', 'funnel', Auth::email());
                redirect('/admin/?p=settings', 'ok', 'Saved.');
            }

            case 'prefs': {
                foreach (['hits_retention_days', 'analytics_enabled', 'blog_enabled'] as $k) {
                    if (array_key_exists($k, $_POST)) Settings::set($k, field($_POST, $k, 80));
                }
                Settings::set('analytics_enabled', isset($_POST['analytics_enabled']) ? '1' : '0');
                audit('settings_change', 'preferences', Auth::email());
                redirect('/admin/?p=settings', 'ok', 'Preferences saved.');
            }
        }
    } catch (Throwable $t) {
        redirect('/admin/?p=settings', 'bad', $t->getMessage());
    }
}

$me      = DB::one('SELECT * FROM wwt_admin_users WHERE id=?', [Auth::id()]) ?? [];
$users   = DB::all('SELECT id,email,role,last_login,locked_until FROM wwt_admin_users ORDER BY role, email');
$hasTotp = !empty($me['totp_secret']);
$newSecret = $hasTotp ? '' : Totp::secret();
if ($newSecret !== '') $_SESSION['pending_totp'] = $newSecret;

layout_top('Settings', 'settings');
?>
<div class="page-head"><div><h1>Settings</h1>
  <p>Your account, who else can sign in, and how long raw analytics are kept.</p></div></div>

<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(330px,1fr))">

  <section class="card">
    <h2>Change your password</h2>
    <form method="post" style="margin-top:.75rem" autocomplete="off">
      <?= Csrf::field() ?><input type="hidden" name="action" value="password">
      <div class="field"><label for="c">Current password</label>
        <input id="c" name="current" type="password" required autocomplete="current-password"></div>
      <div class="field"><label for="n1">New password</label>
        <input id="n1" name="new" type="password" required minlength="12" autocomplete="new-password">
        <p class="hint">Minimum 12 characters.</p></div>
      <div class="field"><label for="n2">New password again</label>
        <input id="n2" name="new2" type="password" required minlength="12" autocomplete="new-password"></div>
      <button class="btn" type="submit">Change password</button>
    </form>
  </section>

  <section class="card">
    <h2>Two-factor authentication</h2>
    <?php if ($hasTotp): ?>
      <p class="small" style="margin-top:.5rem"><span class="pill pill--ok">on</span>
        Your account asks for a 6-digit code at sign-in.</p>
      <form method="post" style="margin-top:.9rem"
            onsubmit="return confirm('Turn off two-factor authentication?')">
        <?= Csrf::field() ?><input type="hidden" name="action" value="totp_disable">
        <button class="btn btn--danger btn--sm" type="submit">Turn off</button>
      </form>
    <?php else: ?>
      <p class="small muted" style="margin-top:.5rem">
        Add a second step at sign-in using any authenticator app
        (Google Authenticator, 1Password, Authy).</p>
      <ol class="small" style="margin:.75rem 0 .75rem 1.1rem;padding:0;color:var(--ink-2)">
        <li style="margin-bottom:.3rem">In your app choose “add account → enter key manually”.</li>
        <li style="margin-bottom:.3rem">Type this key:
          <code class="mono" style="font-size:12px;text-transform:none;letter-spacing:.04em;
            background:var(--wash);padding:.15rem .35rem;display:inline-block;margin-top:.2rem;
            word-break:break-all"><?= e($newSecret) ?></code></li>
        <li>Enter the 6-digit code it shows.</li>
      </ol>
      <form method="post">
        <?= Csrf::field() ?><input type="hidden" name="action" value="totp_enable">
        <input type="hidden" name="secret" value="<?= e($newSecret) ?>">
        <div class="field"><label for="tc">Code from your app</label>
          <input id="tc" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required></div>
        <button class="btn" type="submit">Turn on</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="card card--pad0" style="grid-column:1/-1">
    <div style="padding:.85rem 1.1rem;border-bottom:1px solid var(--rule)">
      <h2>Who can sign in</h2>
      <p class="small muted"><b>Admin</b> can change everything. <b>Read-only</b> sees leads,
        analytics and SEO but cannot edit, publish or change settings — the right role for
        someone who only watches the site.</p>
    </div>
    <div class="tablewrap" style="border:0">
      <table><thead><tr><th>Email</th><th>Role</th><th>Last signed in</th><th></th></tr></thead><tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= e($u['email']) ?><?= (int)$u['id'] === Auth::id() ? ' <span class="pill pill--info">you</span>' : '' ?></td>
          <td><span class="pill pill--<?= $u['role'] === 'admin' ? 'qualified' : 'info' ?>"><?= e($u['role']) ?></span></td>
          <td class="n small"><?= e($u['last_login'] ? local_time($u['last_login']) : 'never') ?></td>
          <td class="r">
            <?php if ((int)$u['id'] !== Auth::id()): ?>
            <form method="post" onsubmit="return confirm('Remove <?= e($u['email']) ?>?')" style="display:inline">
              <?= Csrf::field() ?><input type="hidden" name="action" value="user_del">
              <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
              <button class="btn btn--danger btn--sm" type="submit">Remove</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
    <div style="padding:.9rem 1.1rem;border-top:1px solid var(--rule)">
      <form method="post" class="row" style="align-items:flex-end">
        <?= Csrf::field() ?><input type="hidden" name="action" value="user_add">
        <div class="field" style="margin:0;flex:1 1 200px"><label for="ue">Email</label>
          <input id="ue" name="email" type="email" required></div>
        <div class="field" style="margin:0;flex:1 1 180px"><label for="up">Password</label>
          <input id="up" name="password" type="password" required minlength="12" autocomplete="new-password"></div>
        <div class="field" style="margin:0;flex:0 0 140px"><label for="ur">Role</label>
          <select id="ur" name="role"><option value="viewer">Read-only</option><option value="admin">Admin</option></select></div>
        <button class="btn" type="submit">Add account</button>
      </form>
    </div>
  </section>

  <section class="card" style="grid-column:1/-1">
    <h2>Connections</h2>
    <p class="muted" style="margin:.3rem 0 0">The mailbox, the reply reader, Telegram, WhatsApp, the AI key, PageSpeed and the
      feed keys are all entered, tested and rotated on one page now —
      <a href="/admin/?p=connections" style="text-decoration:underline">Connections</a>. Each one has its own
      step-by-step guide.</p>
  </section>

  <section class="card" style="grid-column:1/-1">
    <h2>Follow-up and scoring</h2>
    <p class="muted small" style="margin:.2rem 0 0;max-width:46rem">These decide what a customer
      receives and who it appears to come from. Automated mail signed by a real person gets read;
      mail from "noreply" gets filed. The address below therefore has to be one you can actually
      read, because people reply to it.</p>
    <form method="post" style="margin-top:.75rem" class="stack">
      <?= Csrf::field() ?><input type="hidden" name="action" value="funnel_prefs">
      <div class="row" style="gap:.7rem;flex-wrap:wrap">
        <div class="field" style="flex:1 1 200px"><label for="fsn">Messages are signed</label>
          <input id="fsn" name="sender_name" value="<?= e((string)Settings::get('funnel_sender_name', '')) ?>" placeholder="Saurabh"></div>
        <div class="field" style="flex:1 1 240px"><label for="fse">and sent from</label>
          <input id="fse" name="sender_email" type="email" value="<?= e((string)Settings::get('funnel_sender_email', '')) ?>" placeholder="saurabh@wwwebtech.in">
          <p class="hint">Replies come back to this mailbox with a +lead tag, which is how a reply
            gets attached to the right person and stops their sequence.</p></div>
      </div>
      <div class="row" style="gap:.7rem;flex-wrap:wrap;align-items:flex-end">
        <div class="field" style="max-width:150px"><label for="hot">Hot from</label>
          <input id="hot" name="hot_at" type="number" min="1" max="200" value="<?= Score::hotAt() ?>"></div>
        <div class="field" style="max-width:150px"><label for="warm">Warm from</label>
          <input id="warm" name="warm_at" type="number" min="0" max="199" value="<?= Score::warmAt() ?>"></div>
        <div class="field field--inline" style="margin:0 0 .6rem">
          <input type="checkbox" id="rp" name="review_ping" value="1" style="width:auto"
                 <?= Settings::bool('funnel_review_ping', true) ? 'checked' : '' ?>>
          <label for="rp" style="text-transform:none;font-family:var(--font);font-size:13px;letter-spacing:0">
            Telegram me when a message needs approval</label>
        </div>
      </div>
      <?php $dist = Score::distribution();
            /* Below a handful of leads the split says nothing about the
               thresholds, and printing it invites a change based on noise. */
            if ((int)$dist['total'] >= 5): ?>
        <p class="small muted" style="margin:0">With these thresholds, the last
          <?= (int)$dist['total'] ?> leads split
          <b><?= (int)($dist['hot'] ?? 0) ?></b> hot ·
          <b><?= (int)($dist['warm'] ?? 0) ?></b> warm ·
          <b><?= (int)($dist['cold'] ?? 0) ?></b> cold.
          If nearly everything is hot, the threshold is too low to be useful for deciding what to do first.</p>
      <?php elseif ((int)$dist['total'] > 0): ?>
        <p class="small muted" style="margin:0">Only <?= (int)$dist['total'] ?>
          <?= (int)$dist['total'] === 1 ? 'lead' : 'leads' ?> in the last 90 days — too few to tell you
          anything useful about where these thresholds should sit. Leave them until there are a few more.</p>
      <?php endif; ?>
      <div class="row" style="gap:.7rem;flex-wrap:wrap;align-items:flex-end">
        <div class="field field--inline" style="margin:0 0 .6rem">
          <input type="checkbox" id="ack" name="lead_ack_enabled" value="1" style="width:auto"
                 <?= Settings::bool('lead_ack_enabled', true) ? 'checked' : '' ?>>
          <label for="ack" style="text-transform:none;font-family:var(--font);font-size:13px;letter-spacing:0">
            Send an automatic acknowledgement to each enquiry</label>
        </div>
        <div class="field" style="max-width:220px"><label for="rp2">Reply promise in it</label>
          <input id="rp2" name="reply_promise" maxlength="60" value="<?= e((string)Settings::get('reply_promise', '1 business day')) ?>">
          <p class="hint">Keep it identical to what the website says.</p></div>
      </div>
      <div class="field"><label for="cw">Consent wording on the forms</label>
        <textarea id="cw" name="consent_wording" rows="2"><?= e(Leads::consentWording()) ?></textarea>
        <p class="hint">Stored with each lead exactly as it read when they agreed, so changing it here
          never rewrites what someone actually consented to.</p></div>
      <div><button class="btn" type="submit">Save</button></div>
    </form>
  </section>

  <section class="card" style="grid-column:1/-1">
    <h2>Data retention</h2>
    <form method="post" style="margin-top:.75rem" class="row" >
      <?= Csrf::field() ?><input type="hidden" name="action" value="prefs">
      <div class="field" style="margin:0;max-width:220px">
        <label for="hr">Keep raw analytics for</label>
        <select id="hr" name="hits_retention_days">
          <?php foreach ([30, 60, 90, 180, 365] as $d): ?>
            <option value="<?= $d ?>"<?= Settings::int('hits_retention_days', 90) === $d ? ' selected' : '' ?>><?= $d ?> days</option>
          <?php endforeach; ?>
        </select>
        <p class="hint">Daily totals are kept forever regardless — only the row-per-visit detail is pruned.</p>
      </div>
      <div class="field field--inline" style="margin:0;align-self:center">
        <input type="checkbox" id="ae" name="analytics_enabled" value="1" style="width:auto"
               <?= Settings::bool('analytics_enabled', true) ? 'checked' : '' ?>>
        <label for="ae" style="text-transform:none;font-family:var(--font);font-size:13px;letter-spacing:0">
          Collect analytics</label>
      </div>
      <button class="btn" type="submit">Save</button>
    </form>
  </section>
</div>
<?php layout_bottom();
