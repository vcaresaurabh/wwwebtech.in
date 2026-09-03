/* ============================================================
   Landing page enhancement.

   The form in the HTML is a plain three-fieldset form that POSTs to
   /api/lead.php and works completely without this file. Everything here
   is added on top: step navigation, validation, capture of the click IDs
   and UTMs from the URL, and a fetch submit with an inline success state.

   Nothing starts hidden in the markup. `.js-on` is set by this script,
   and only then does CSS hide the inactive steps — so if the script
   fails to load or throws, the visitor gets one long working form
   rather than a blank panel.
   ============================================================ */
(function () {
  'use strict';

  var form = document.getElementById('lp-form');
  if (!form) return;

  var doc = document.documentElement;
  var $  = function (s, r) { return (r || form).querySelector(s); };
  var $$ = function (s, r) { return [].slice.call((r || form).querySelectorAll(s)); };

  var steps  = $$('.step');
  var bar    = document.querySelector('.steps-bar');
  var status = $('[data-status]');
  var btnNext = $('[data-next]');
  var btnBack = $('[data-back]');
  var btnSend = $('[data-submit]');
  var at = 0;

  /* ── Capture (§3.2) ─────────────────────────────────────
     The click IDs are the only way an offline conversion attributes back
     to the ad that paid for it, so they are read first and stored, not
     just for this page load but for a return visit in the same session. */
  var KEYS = ['gclid', 'gbraid', 'wbraid', 'msclkid', 'fbclid',
              'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

  function params() { try { return new URLSearchParams(location.search); } catch (e) { return null; } }
  function store(k, v) { try { sessionStorage.setItem('wwt_' + k, v); } catch (e) {} }
  function recall(k) { try { return sessionStorage.getItem('wwt_' + k) || ''; } catch (e) { return ''; } }

  (function capture() {
    var q = params();
    KEYS.forEach(function (k) {
      var v = q ? (q.get(k) || '') : '';
      if (v) store(k, v.slice(0, 160));
      var field = form.elements[k];
      if (field) field.value = v || recall(k);
    });
    var kw = q ? (q.get('kw') || q.get('utm_term') || '') : '';
    if (form.elements._kw) form.elements._kw.value = kw.slice(0, 120);
    if (form.elements._ref) form.elements._ref.value = document.referrer || '';

    /* Pages seen this session, and how long they spent before submitting.
       Both feed the lead score. */
    var seen = (parseInt(recall('seen'), 10) || 0) + 1;
    store('seen', String(seen));
    if (form.elements._seen) form.elements._seen.value = String(seen);
  })();

  var landedAt = Date.now();

  /* ── Draft recovery (§3.1) ──────────────────────────────
     Someone who half-filled the form yesterday should not start again. */
  var DRAFT = 'wwt_lp_' + (form.dataset.lp || 'x');
  function saveDraft() {
    try {
      var d = {};
      $$('input, select, textarea').forEach(function (el) {
        if (!el.name || el.name.charAt(0) === '_' || el.name === 'company') return;
        if (el.type === 'checkbox' || el.type === 'radio') { if (el.checked) d[el.name] = (d[el.name] || []).concat(el.value); }
        else if (el.value) d[el.name] = el.value;
      });
      localStorage.setItem(DRAFT, JSON.stringify(d));
    } catch (e) {}
  }
  (function loadDraft() {
    try {
      var d = JSON.parse(localStorage.getItem(DRAFT) || '{}');
      Object.keys(d).forEach(function (n) {
        var v = d[n];
        $$('[name="' + n + '"]').forEach(function (el) {
          if (el.type === 'checkbox' || el.type === 'radio') {
            if (Array.isArray(v) && v.indexOf(el.value) > -1) el.checked = true;
          } else if (!el.value) el.value = v;
        });
      });
    } catch (e) {}
  })();
  form.addEventListener('input', saveDraft);
  form.addEventListener('change', saveDraft);

  /* ── The URL field appears only when it is relevant ── */
  var siteUrl = $('#site-url');
  function toggleUrl() {
    var yes = $('input[name="has_site"][value="yes"]');
    if (siteUrl) siteUrl.hidden = !(yes && yes.checked);
  }
  $$('input[name="has_site"]').forEach(function (r) { r.addEventListener('change', toggleUrl); });
  toggleUrl();

  /* ── Steps ──────────────────────────────────────────── */
  function show(i) {
    at = Math.max(0, Math.min(steps.length - 1, i));
    steps.forEach(function (s, n) { s.classList.toggle('is-active', n === at); });
    if (bar) [].slice.call(bar.children).forEach(function (b, n) { b.classList.toggle('is-done', n <= at); });
    btnBack.hidden = at === 0;
    btnNext.hidden = at === steps.length - 1;
    btnSend.hidden = at !== steps.length - 1;
    var focusable = steps[at].querySelector('input:not([type=hidden]), select, textarea');
    if (focusable && at > 0) focusable.focus({ preventScroll: true });
    partial();
  }

  function fail(el, msg) {
    var p = document.getElementById(el.id + '-err');
    el.setAttribute('aria-invalid', 'true');
    if (p) { p.textContent = msg; p.hidden = false; }
  }
  function clear(el) {
    var p = document.getElementById(el.id + '-err');
    el.removeAttribute('aria-invalid');
    if (p) { p.hidden = true; p.textContent = ''; }
  }

  function validate(stepEl) {
    var bad = null;
    $$('[required]', stepEl).forEach(function (el) {
      var v = (el.type === 'checkbox') ? el.checked : el.value.trim();
      var problem = null;
      if (!v) problem = el.type === 'checkbox' ? 'Please tick this to continue.' : 'This one’s needed.';
      else if (el.type === 'email' && !/^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/.test(el.value.trim())) problem = 'That email doesn’t look right.';
      else if (el.type === 'tel' && el.value.replace(/\D/g, '').length < 10) problem = 'Please include the full number.';
      if (problem) { fail(el, problem); bad = bad || el; } else { clear(el); }
    });
    return bad;
  }

  /* ── Partial lead (§3.1) ────────────────────────────────
     An abandoned form still records something recoverable. Sent only
     once we have a way to reach them, and flagged so it never triggers
     a notification or a sequence. */
  var partialSent = false;
  function partial() {
    if (partialSent || at < steps.length - 1) return;
    var email = (form.elements.email && form.elements.email.value || '').trim();
    var phone = (form.elements.phone && form.elements.phone.value || '').trim();
    if (!email && !phone) return;
    partialSent = true;
    var d = new FormData(form);
    d.delete('company');
    d.set('_partial', '1');
    try {
      if (navigator.sendBeacon) navigator.sendBeacon('/api/lead.php', d);
    } catch (e) {}
  }
  var lastStep = steps[steps.length - 1];
  if (lastStep) lastStep.addEventListener('focusout', partial);

  btnNext.addEventListener('click', function () {
    var bad = validate(steps[at]);
    if (bad) { bad.focus(); return; }
    show(at + 1);
  });
  btnBack.addEventListener('click', function () { show(at - 1); });

  form.addEventListener('input', function (ev) {
    if (ev.target.hasAttribute('required') && ev.target.value.trim()) clear(ev.target);
  });

  /* ── Submit ─────────────────────────────────────────── */
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();

    for (var i = 0; i < steps.length; i++) {
      var bad = validate(steps[i]);
      if (bad) { show(i); bad.focus(); say('Have a look at the highlighted field.', 'is-err'); return; }
    }
    if (form.elements.company && form.elements.company.value) { done(); return; }

    var data = new FormData(form);
    data.delete('company');
    data.set('_dwell', String(Math.round((Date.now() - landedAt) / 1000)));

    btnSend.disabled = true;
    say('Sending…', '');

    fetch(form.action, { method: 'POST', body: data, headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (!res.ok || res.j.ok === false) throw new Error(res.j.error || 'That didn’t send.');
        try { localStorage.removeItem(DRAFT); } catch (e) {}
        done(res.j.message);
      })
      .catch(function (err) {
        btnSend.disabled = false;
        say(String(err.message || 'That didn’t send. Please call or WhatsApp us instead.'), 'is-err');
      });
  });

  function say(msg, cls) { if (status) { status.textContent = msg; status.className = 'form__status ' + (cls || ''); } }

  function done(msg) {
    var wrap = form.closest('.formwrap');
    if (!wrap) return;
    wrap.innerHTML =
      '<div class="done">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>' +
      '<h2>Got it.</h2><p>' + (msg ? String(msg).replace(/[<>&]/g, '') : 'A real person replies within 1 business day.') + '</p>' +
      '<p style="margin-top:1rem"><a class="btn btn--ghost" href="https://wa.me/918595250209" rel="noopener">Message us on WhatsApp</a></p>' +
      '</div>';
    wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  /* Only now does the CSS hide anything. */
  doc.classList.add('js-on');
  show(0);
})();
