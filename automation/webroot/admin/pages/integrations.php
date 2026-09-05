<?php
/* ============================================================
   Integrations — paste an ID, get the right snippet on every page.

   No third-party container is required and none is used: the snippets
   are the vendors' own, written into the pages by this panel.

   The page states each tag's real page-weight cost and whether it
   creates a consent obligation, at the point where the decision is
   made. That is the whole reason to build this rather than hand the
   owner a GTM login.
   ============================================================ */
declare(strict_types=1);

/* Reached only through admin/index.php, which has already checked the session,
   the CSRF token and the role. A direct request must not run any of this. */
if (!defined('WWT_ADMIN')) { http_response_code(404); exit; }


$action = field($_POST, 'action', 40);
if ($action !== '') {
    Auth::requireAdmin();
    try {
        switch ($action) {
            case 'save': {
                foreach (array_keys(Tags::DEFS) as $k) {
                    Tags::set($k, (string)($_POST['tag_' . $k] ?? ''));
                    Settings::set('tag_' . $k . '_on', isset($_POST['on_' . $k]) ? '1' : '0');
                }
                foreach (array_keys(Tags::SLOTS) as $k) {
                    Tags::set($k, (string)($_POST['tag_' . $k] ?? ''));
                    Settings::set('tag_' . $k . '_on', isset($_POST['on_' . $k]) ? '1' : '0');
                }
                audit('tags_save', '', Auth::email());
                redirect('/admin/?p=integrations', 'ok',
                    'Saved. Nothing is on the site yet — use “Apply to the site” below.');
            }
            case 'apply': {
                $r = Tags::apply();
                $msg = $r['written'] . ' pages updated'
                     . ($r['skipped'] ? ', ' . $r['skipped'] . ' already correct' : '')
                     . ($r['missing'] ? '. ' . count($r['missing']) . ' pages had no markers and were skipped '
                                      . '— rebuild the site and redeploy.' : '.');
                redirect('/admin/?p=integrations', $r['missing'] ? 'warn' : 'ok', $msg);
            }
            case 'clear': {
                foreach (array_merge(array_keys(Tags::DEFS), array_keys(Tags::SLOTS)) as $k) {
                    Settings::set('tag_' . $k . '_on', '0');
                }
                $r = Tags::apply();
                audit('tags_clear', $r['written'] . ' pages', Auth::email());
                redirect('/admin/?p=integrations', 'ok',
                    'Everything switched off and removed from ' . $r['written'] . ' pages. The IDs are kept.');
            }
            case 'verify': {
                $v = Tags::verify();
                redirect('/admin/?p=integrations', $v['ok'] ? 'ok' : 'warn', $v['detail']);
            }
        }
    } catch (Throwable $t) {
        redirect('/admin/?p=integrations', 'bad', $t->getMessage());
    }
}

$cookieSetters = Tags::cookieSetters();
$pageCount     = count(Tags::pages());
$previewHead   = trim(Tags::headHtml());
$previewBody   = trim(Tags::bodyHtml());

layout_top('Integrations', 'integrations');
?>
<div class="page-head">
  <div>
    <h1>Integrations</h1>
    <p>Paste an ID and this writes the official snippet into all
       <?= $pageCount ?> pages of the site. No container, no extra service in the middle.</p>
  </div>
</div>

<?php if ($cookieSetters): ?>
  <div class="alert alert--warn">
    <b>These set cookies:</b> <?= e(implode(', ', $cookieSetters)) ?>.
    The site currently tells visitors it sets none, which is why it has no cookie banner.
    With any of these on, that stops being true — update
    <a href="<?= e(rtrim((string)cfg('site.url',''), '/')) ?>/legal/privacy/" target="_blank"
       rel="noopener" style="text-decoration:underline">the privacy policy</a>, and consider
    whether you now need consent before they load.
  </div>
<?php endif; ?>

<form method="post">
  <?= Csrf::field() ?><input type="hidden" name="action" value="save">

  <div class="stack">
  <?php foreach (Tags::DEFS as $key => $d):
        $val = Tags::id($key);
        $on  = Settings::bool('tag_' . $key . '_on', true); ?>
    <section class="card">
      <div class="row" style="justify-content:space-between;align-items:flex-start">
        <div style="flex:1 1 340px">
          <h2><?= e($d['label']) ?></h2>
          <p class="small muted" style="margin-top:.2rem"><?= e($d['hint']) ?></p>
        </div>
        <div class="row" style="gap:.6rem;align-items:center">
          <span class="pill pill--<?= $d['cookies'] ? 'warn' : 'ok' ?>">
            <?= $d['cookies'] ? 'sets cookies' : 'no cookies' ?></span>
          <span class="small muted"><?= e($d['weight']) ?></span>
        </div>
      </div>

      <div class="row" style="align-items:flex-end;margin-top:.7rem">
        <div class="field" style="margin:0;flex:1 1 260px">
          <label for="t-<?= e($key) ?>">ID</label>
          <input id="t-<?= e($key) ?>" name="tag_<?= e($key) ?>" type="text"
                 value="<?= e($val) ?>" placeholder="<?= e($d['example']) ?>" autocomplete="off">
        </div>
        <div class="field field--inline" style="margin:0 0 .5rem">
          <input type="checkbox" id="on-<?= e($key) ?>" name="on_<?= e($key) ?>" value="1"
                 style="width:auto"<?= $on ? ' checked' : '' ?>>
          <label for="on-<?= e($key) ?>" style="text-transform:none;font-family:var(--font);font-size:13px;letter-spacing:0">
            Switched on</label>
        </div>
      </div>
      <p class="small muted" style="margin-top:.5rem"><?= e($d['note']) ?></p>
    </section>
  <?php endforeach; ?>

  <?php foreach (Tags::SLOTS as $key => $label):
        $val = Tags::id($key);
        $on  = Settings::bool('tag_' . $key . '_on', true); ?>
    <section class="card">
      <h2><?= e($label) ?></h2>
      <p class="small muted" style="margin-top:.2rem">
        For anything not listed above. Pasted exactly as written, on every page —
        so paste only code you understand and trust.</p>
      <div class="field" style="margin-top:.6rem">
        <label for="t-<?= e($key) ?>">HTML</label>
        <textarea id="t-<?= e($key) ?>" name="tag_<?= e($key) ?>" rows="4"
                  maxlength="<?= Tags::MAX_SLOT ?>" spellcheck="false"
                  style="font-family:var(--mono);font-size:12px;text-transform:none;letter-spacing:0"
                  placeholder="&lt;script&gt;…&lt;/script&gt;"><?= e($val) ?></textarea>
      </div>
      <div class="field field--inline" style="margin:0">
        <input type="checkbox" id="on-<?= e($key) ?>" name="on_<?= e($key) ?>" value="1"
               style="width:auto"<?= $on ? ' checked' : '' ?>>
        <label for="on-<?= e($key) ?>" style="text-transform:none;font-family:var(--font);font-size:13px;letter-spacing:0">
          Switched on</label>
      </div>
    </section>
  <?php endforeach; ?>
  </div>

  <div class="card" style="margin-top:1rem">
    <button class="btn" type="submit">Save</button>
    <span class="small muted" style="margin-left:.5rem">
      Saving stores the IDs. Nothing reaches the site until you apply them.</span>
  </div>
</form>

<section class="card" style="margin-top:1rem">
  <h2>What will be written</h2>
  <p class="small muted" style="margin:.3rem 0 .7rem">
    Exactly this, into the marked places in every page. Nothing else is touched.</p>

  <p class="mono" style="margin-bottom:.3rem">In &lt;head&gt;</p>
  <?php if ($previewHead === ''): ?>
    <p class="small muted">Nothing — no tag is switched on.</p>
  <?php else: ?>
    <pre class="small" style="white-space:pre-wrap;word-break:break-all;background:var(--wash);
         padding:.7rem;border-radius:var(--radius);overflow-x:auto"><?= e($previewHead) ?></pre>
  <?php endif; ?>

  <?php if ($previewBody !== ''): ?>
    <p class="mono" style="margin:.8rem 0 .3rem">After &lt;body&gt;</p>
    <pre class="small" style="white-space:pre-wrap;word-break:break-all;background:var(--wash);
         padding:.7rem;border-radius:var(--radius);overflow-x:auto"><?= e($previewBody) ?></pre>
  <?php endif; ?>

  <div class="row" style="margin-top:1rem">
    <form method="post" style="margin:0"><?= Csrf::field() ?>
      <input type="hidden" name="action" value="apply">
      <button class="btn" type="submit">Apply to the site</button></form>
    <form method="post" style="margin:0"><?= Csrf::field() ?>
      <input type="hidden" name="action" value="verify">
      <button class="btn btn--ghost btn--sm" type="submit">Check they are live</button></form>
    <span class="spacer"></span>
    <form method="post" style="margin:0"
          onsubmit="return confirm('Switch everything off and strip the tags from every page?')">
      <?= Csrf::field() ?><input type="hidden" name="action" value="clear">
      <button class="btn btn--danger btn--sm" type="submit">Remove everything</button></form>
  </div>
  <p class="small muted" style="margin-top:.7rem">
    “Apply” rewrites the pages on this server. If your site is deployed from the repository,
    remember that the next deploy overwrites them — apply again afterwards, or keep the tags
    in the source.</p>
</section>

<?php
/* ── Offline conversions (§9) ───────────────────────────────
   The loop that tells Google which clicks became money, rather than
   which clicks became form submissions. Without it the bidding
   optimises for enquiries, and the cheapest enquiries are the worst
   ones. */
$convKey = Secrets::get('conversions_key', '');
$site    = rtrim((string)cfg('site.url', 'https://wwwebtech.in'), '/');
$adsSum  = Ads::summary();
?>
<section class="card" style="margin-top:1rem">
  <h2>Offline conversions</h2>
  <p class="small muted" style="margin:.3rem 0 .7rem;max-width:48rem">
    When a lead becomes a customer, that fact has to get back to the ad platform or the bidding
    keeps optimising for cheap enquiries instead of paying ones. These are plain CSV files the
    platforms fetch on a schedule — nothing to keep authorised, nothing to expire.</p>

  <?php if (!$adsSum): ?>
    <p class="small muted">Nothing queued yet. A row appears here when a lead with a click ID is
      marked qualified or won.</p>
  <?php else: ?>
    <div class="tablewrap"><table>
      <thead><tr><th>Platform</th><th>Queued</th><th>Sent</th><th>No click ID</th></tr></thead>
      <tbody>
      <?php foreach ($adsSum as $row): ?>
        <tr><td><?= e((string)($row['platform'] ?? '')) ?></td>
            <td class="n"><?= (int)($row['queued'] ?? 0) ?></td>
            <td class="n"><?= (int)($row['fetched'] ?? 0) ?></td>
            <td class="n muted"><?= (int)($row['noclick'] ?? 0) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <p class="small muted" style="margin-top:.4rem">Rows with no click ID cannot be matched to an
      ad. They are still exported for Enhanced Conversions, which matches on a hashed email
      instead.</p>
  <?php endif; ?>

  <?php if ($convKey === ''): ?>
    <p class="small" style="margin-top:.8rem">No key yet. Generate one on <a href="/admin/?p=connections#c-keys" style="text-decoration:underline">Connections</a> to get the URLs.</p>
  <?php else: ?>
    <p class="mono small" style="margin:.8rem 0 .3rem">Give these to the platform's scheduled import</p>
    <?php foreach ([
        'google'    => 'Google Ads · offline conversion import',
        'enhanced'  => 'Google Ads · enhanced conversions for leads',
        'microsoft' => 'Microsoft Advertising · offline conversions',
    ] as $t => $label): ?>
      <p class="small" style="margin:.5rem 0 .15rem"><?= e($label) ?></p>
      <pre class="small mono" style="white-space:pre-wrap;word-break:break-all;background:var(--wash);
           padding:.6rem;border-radius:var(--radius);margin:0"><?= e($site . '/api/conversions.php?type=' . $t
             . '&amp;key=' . $convKey . '&amp;mark=1') ?></pre>
    <?php endforeach; ?>
    <p class="small muted" style="margin-top:.6rem">
      Treat these URLs like passwords — anyone holding one can read your customers' hashed contact
      details. Drop <span class="mono">&amp;mark=1</span> if you just want to look at the file
      yourself; with it, the rows are marked as sent and will not appear in the next fetch.</p>
  <?php endif; ?>

  <p class="small muted" style="margin-top:.7rem">The key is generated and rotated on <a href="/admin/?p=connections#c-keys" style="text-decoration:underline">Connections → Feed &amp; test keys</a>.</p>
</section>
<?php layout_bottom();
