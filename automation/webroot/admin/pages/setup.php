<?php
/* First-run: create the first admin account. Reachable only while
   wwt_admin_users is empty — index.php enforces that. */
declare(strict_types=1);

/* Reached only through admin/index.php, which has already checked the session,
   the CSRF token and the role. A direct request must not run any of this. */
if (!defined('WWT_ADMIN')) { http_response_code(404); exit; }


$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // No session exists yet on a truly fresh install, so the CSRF token is
    // minted on the GET that rendered this form and checked here.
    if (!Csrf::valid($_POST['_csrf'] ?? null)) {
        $err = 'Session expired — reload and try again.';
    } else {
        $email = field($_POST, 'email', 150);
        $p1    = (string)($_POST['password'] ?? '');
        $p2    = (string)($_POST['password2'] ?? '');
        try {
            if ($p1 !== $p2) throw new InvalidArgumentException('The two passwords do not match.');
            if ((int)DB::val('SELECT COUNT(*) FROM wwt_admin_users', [], 0) > 0) {
                throw new RuntimeException('An account already exists.');
            }
            Auth::createUser($email, $p1, 'admin');
            audit('setup_complete', 'first admin created', $email);
            redirect('/admin/?p=login', 'ok', 'Account created. Sign in below.');
        } catch (Throwable $t) {
            $err = $t->getMessage();
        }
    }
}
$token = Csrf::token();
?><!DOCTYPE html>
<html lang="en-IN"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>First-time setup · Wwwebtech admin</title>
<link rel="stylesheet" href="/admin/assets/admin.css"></head>
<body><div class="login"><div class="login__box">
  <div class="login__brand" style="filter:invert(1)"><?= wwt_logo_svg('#131614', '#686D69') ?></div>
  <h1>Create your account</h1>
  <p class="sub">This screen appears once. After the first account exists it is switched off.</p>
  <div class="card">
    <?php if ($err): ?><div class="alert alert--bad"><?= e($err) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= e($token) ?>">
      <div class="field"><label for="e">Your email</label>
        <input id="e" name="email" type="email" required autofocus value="<?= e((string)($_POST['email'] ?? 'contact@wwwebtech.in')) ?>"></div>
      <div class="field"><label for="p">Password</label>
        <input id="p" name="password" type="password" required minlength="12">
        <p class="hint">At least 12 characters. Use a password manager.</p></div>
      <div class="field"><label for="p2">Password again</label>
        <input id="p2" name="password2" type="password" required minlength="12"></div>
      <button class="btn" type="submit">Create admin account</button>
    </form>
  </div>
</div></div></body></html>
