<?php
/* ============================================================
   admin/index.php — the whole panel's front controller.

   One entry point, ?p=<page>. Keeping it to a single file means one
   place enforces auth, CSRF and role checks; a page dropped into
   pages/ can never be reached without passing through here.
   ============================================================ */
declare(strict_types=1);

require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';

$page = gets('p', 'dashboard', [
    'dashboard', 'login', 'logout', 'leads', 'lead', 'analytics',
    'funnel', 'conversations', 'landing', 'audits',
    'blog', 'seo', 'integrations', 'settings', 'setup',
]);

/* ── First-run gate, in BOTH directions ────────────────────────
   With no account, everything funnels to setup. Once an account
   exists, setup is closed — otherwise it stays reachable forever
   and advertises the panel to anyone who guesses the URL. */
$userCount = (int)DB::val('SELECT COUNT(*) FROM wwt_admin_users', [], 0);
if ($userCount === 0 && $page !== 'setup') redirect('/admin/?p=setup');
if ($userCount > 0  && $page === 'setup')  redirect('/admin/?p=login');

/* ── Public pages ──────────────────────────────────────────── */
if ($page === 'setup')  { require __DIR__ . '/pages/setup.php';  exit; }
if ($page === 'login')  { require __DIR__ . '/pages/login.php';  exit; }
if ($page === 'logout') {
    /* POST + token. Signing someone out is a state change like any other,
       and a GET one is forgeable from any page they are reading. */
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !Csrf::valid($_POST['_csrf'] ?? null)) {
        redirect('/admin/', 'warn', 'Use the Sign out button.');
    }
    Auth::logout();
    redirect('/admin/?p=login', 'ok', 'Signed out.');
}

/* ── Everything below needs a session ──────────────────────── */
Auth::requireLogin();
Csrf::require();

/* Sections a read-only viewer must not reach at all. */
if (in_array($page, ['blog', 'integrations', 'settings'], true)) Auth::requireAdmin();

$file = __DIR__ . '/pages/' . $page . '.php';
if (!is_file($file)) {
    http_response_code(404);
    layout_top('Not found');
    echo '<h1>That page does not exist</h1><p class="muted">'
       . '<a href="/admin/" style="text-decoration:underline">Back to the dashboard</a></p>';
    layout_bottom();
    exit;
}
require $file;
