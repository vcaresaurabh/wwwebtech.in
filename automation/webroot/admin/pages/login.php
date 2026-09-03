<?php
declare(strict_types=1);

/* Reached only through admin/index.php, which has already checked the session,
   the CSRF token and the role. A direct request must not run any of this. */
if (!defined('WWT_ADMIN')) { http_response_code(404); exit; }


if (Auth::check()) redirect('/admin/');

$err = ''; $needTotp = !empty($_POST['totp']) || !empty($_GET['totp']);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Csrf::valid($_POST['_csrf'] ?? null)) {
        $err = 'Session expired — please try again.';
    } else {
        $r = Auth::attempt(
            field($_POST, 'email', 150),
            (string)($_POST['password'] ?? ''),
            field($_POST, 'totp', 10)
        );
        if ($r['ok']) {
            $next = (string)($_POST['next'] ?? '/admin/');
            // Only ever redirect within this site.
            if (!preg_match('#^/admin(/|\?|$)#', $next)) $next = '/admin/';
            redirect($next);
        }
        $err      = (string)($r['error'] ?? 'Sign-in failed.');
        $needTotp = !empty($r['need_totp']);
    }
}
$flash = flash();
$token = Csrf::token();
?><!DOCTYPE html>
<html lang="en-IN"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Sign in · Wwwebtech admin</title>
<link rel="stylesheet" href="/admin/assets/admin.css"></head>
<body><div class="login"><div class="login__box">
  <div class="login__brand"><?= wwt_logo_svg('#131614', '#686D69') ?></div>
  <h1>Operations panel</h1>
  <p class="sub">Leads, analytics, blog and SEO for wwwebtech.in</p>
  <div class="card">
    <?php if ($flash): ?><div class="alert alert--<?= e($flash['type'] ?: 'info') ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert--bad"><?= e($err) ?></div><?php endif; ?>
    <?php if ($needTotp && !$err): ?><div class="alert alert--info">Enter the 6-digit code from your authenticator app.</div><?php endif; ?>
    <form method="post" autocomplete="on">
      <input type="hidden" name="_csrf" value="<?= e($token) ?>">
      <input type="hidden" name="next" value="<?= e((string)($_GET['next'] ?? '/admin/')) ?>">
      <div class="field"><label for="e">Email</label>
        <input id="e" name="email" type="email" required autocomplete="username"
               value="<?= e(field($_POST, 'email', 150)) ?>" <?= $needTotp ? '' : 'autofocus' ?>></div>
      <div class="field"><label for="p">Password</label>
        <input id="p" name="password" type="password" required autocomplete="current-password"></div>
      <?php if ($needTotp): ?>
      <div class="field"><label for="t">Authenticator code</label>
        <input id="t" name="totp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
               autocomplete="one-time-code" required autofocus></div>
      <?php endif; ?>
      <button class="btn" type="submit">Sign in</button>
    </form>
  </div>
  <p class="sub" style="margin-top:.9rem">Five wrong attempts locks the account for 15 minutes.</p>
</div></div></body></html>
