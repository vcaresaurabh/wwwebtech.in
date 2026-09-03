<?php
/* ============================================================
   The multi-step form (§3.1).

   It is a PLAIN FORM. Three fieldsets, one submit button, a normal POST
   to /api/lead.php. The script in lp.js hides all but one fieldset and
   adds Next/Back on top of that — so with JavaScript off this is one
   long form that works, which is the requirement in §2.7, not a
   degraded fallback bolted on afterwards.

   Every hidden capture field in §3.2 is present in the markup with an
   empty value; the script fills what it can from the URL and the
   document, and the endpoint recovers the rest from the request.
   ============================================================ */
declare(strict_types=1);

$SERVICES = ['Website', 'CRM', 'SEO', 'Social', 'Automation', 'Not sure'];
$preselect = $lp['preselect'] ?? match ($lp['slug']) {
    'custom-crm'          => 'CRM',
    'website-development', 'website-development-delhi', 'ecommerce' => 'Website',
    'seo-ai-visibility'   => 'SEO',
    'business-automation' => 'Automation',
    'social-media'        => 'Social',
    default               => '',
};
?>
<div class="formwrap">
  <div class="formwrap__head">
    <h2>2 quick questions</h2>
    <p><?= e($lp['cta_sub']) ?></p>
  </div>

  <div class="steps-bar" aria-hidden="true">
    <span class="is-done"></span><span></span><span></span>
  </div>

  <form id="lp-form" method="post" action="/api/lead.php" novalidate
        data-lp="<?= e($lp['slug']) ?>" data-variant="<?= e($variant) ?>">

    <?php /* ── Step 1 — the easiest question, pre-answered ── */ ?>
    <fieldset class="step is-active" data-step="1" style="border:0;padding:0;margin:0;min-width:0">
      <legend class="step__legend">1 · What do you need?</legend>
      <div class="chips">
        <?php foreach ($SERVICES as $i => $s): ?>
          <input type="checkbox" id="svc<?= $i ?>" name="need[]" value="<?= e($s) ?>"
                 <?= $s === $preselect ? 'checked' : '' ?>>
          <label for="svc<?= $i ?>"><?= e($s) ?></label>
        <?php endforeach; ?>
      </div>
      <p class="hint">Pick as many as apply.</p>
    </fieldset>

    <?php /* ── Step 2 — context ── */ ?>
    <fieldset class="step" data-step="2" style="border:0;padding:0;margin:0;min-width:0">
      <legend class="step__legend">2 · A little context</legend>

      <div class="field">
        <label for="has-site">Do you have a website now?</label>
        <div class="radios">
          <label><input type="radio" name="has_site" value="yes" id="has-site"> Yes</label>
          <label><input type="radio" name="has_site" value="no"> Not yet</label>
        </div>
        <input class="input" id="site-url" name="site_url" type="url" inputmode="url"
               placeholder="yourbusiness.in" style="margin-top:.5rem" hidden>
      </div>

      <div class="field">
        <label for="timeline">When do you need this?</label>
        <select class="select" id="timeline" name="timeline">
          <option value="">Select</option>
          <option value="asap">As soon as possible</option>
          <option value="1-3m">In the next 1–3 months</option>
          <option value="research">Just researching for now</option>
        </select>
      </div>

      <div class="field">
        <label for="budget">Budget range</label>
        <select class="select" id="budget" name="budget">
          <option value="">Select</option>
          <option>Under ₹50k</option>
          <option>₹50k – ₹1.5L</option>
          <option>₹1.5L – ₹5L</option>
          <option>₹5L+</option>
          <option value="Not sure yet">Not sure yet</option>
        </select>
        <p class="hint">“Not sure yet” is a real answer — it will not count against you.</p>
      </div>
    </fieldset>

    <?php /* ── Step 3 — contact ── */ ?>
    <fieldset class="step" data-step="3" style="border:0;padding:0;margin:0;min-width:0">
      <legend class="step__legend">3 · Where do we reply?</legend>

      <div class="field">
        <label for="lname">Your name <span class="req" aria-hidden="true">*</span></label>
        <input class="input" id="lname" name="name" type="text" required maxlength="100"
               autocomplete="name" placeholder="Your name">
        <p class="err" id="lname-err" hidden></p>
      </div>

      <div class="field">
        <label for="lphone">Phone / WhatsApp <span class="req" aria-hidden="true">*</span></label>
        <input class="input" id="lphone" name="phone" type="tel" required maxlength="30"
               autocomplete="tel" inputmode="tel" placeholder="+91 …">
        <p class="err" id="lphone-err" hidden></p>
      </div>

      <div class="field">
        <label for="lemail">Email <span class="req" aria-hidden="true">*</span></label>
        <input class="input" id="lemail" name="email" type="email" required maxlength="150"
               autocomplete="email" placeholder="you@company.com">
        <p class="err" id="lemail-err" hidden></p>
      </div>

      <div class="field">
        <label for="lcompany">Company <span class="hint" style="display:inline;font-weight:400">(optional)</span></label>
        <input class="input" id="lcompany" name="company_name" type="text" maxlength="120"
               autocomplete="organization">
      </div>

      <div class="field">
        <label for="lmsg">Anything specific?</label>
        <textarea class="textarea" id="lmsg" name="message" maxlength="2000"
                  placeholder="Two lines is plenty."></textarea>
      </div>

      <div class="field">
        <label class="consent">
          <input type="checkbox" name="consent" value="1" required>
          <span>I agree to Wwwebtech contacting me about this enquiry by email, phone
            and WhatsApp. No newsletter, and you can ask us to stop at any time.
            <a href="/legal/privacy/">Privacy policy</a>.</span>
        </label>
        <p class="err" id="consent-err" hidden></p>
      </div>
    </fieldset>

    <?php /* Honeypot. Hidden from people, irresistible to bots. */ ?>
    <div class="hp" aria-hidden="true">
      <label for="lp-company">Company (leave blank)</label>
      <input id="lp-company" name="company" type="text" tabindex="-1" autocomplete="off">
    </div>

    <?php /* §3.2 hidden capture. The click IDs are the whole reason offline
             conversions can attribute back to the ad that paid for them. */ ?>
    <input type="hidden" name="_lp"        value="<?= e($lp['slug']) ?>">
    <input type="hidden" name="_variant"   value="<?= e($variant) ?>">
    <input type="hidden" name="_page"      value="/lp/<?= e($lp['slug']) ?>/">
    <input type="hidden" name="_ref"       value="">
    <input type="hidden" name="_started"   value="<?= time() ?>">
    <?php foreach (['gclid','gbraid','wbraid','msclkid','fbclid',
                    'utm_source','utm_medium','utm_campaign','utm_term','utm_content',
                    '_kw','_seen','_dwell'] as $h): ?>
      <input type="hidden" name="<?= e($h) ?>" value="">
    <?php endforeach; ?>

    <div class="form__nav">
      <button type="button" class="btn btn--ghost form__back" data-back hidden>Back</button>
      <button type="button" class="btn btn--wide" data-next hidden>Next</button>
      <button type="submit" class="btn btn--wide" data-submit><?= e($lp['cta']) ?></button>
    </div>
    <p class="form__status" role="status" aria-live="polite" data-status></p>
    <p class="hint" style="margin-top:.6rem">A real person replies within 1 business day.</p>
  </form>
</div>
