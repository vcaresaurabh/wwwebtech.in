/* ============================================================
   main.js — the part of the site that must work immediately.
   Nav, mobile menu, dropdown, counters, process rail, the form.

   Everything here is progressive: with this file blocked the page
   is still fully readable, navigable and submittable. Nothing below
   reveals content — it only adds behaviour. (§8 T12)
   ============================================================ */
(() => {
  'use strict';

  /* ------------------------------------------------------------------
     FORM ENDPOINT
     ------------------------------------------------------------------ */
  /* The endpoint is whatever the <form action> says, so there is exactly one
     source of truth and the no-JS post and the fetch() can never disagree.
     Change it in src/data.mjs (SITE.leadEndpoint) and rebuild.

     FORM_ENDPOINT below is only the fallback for a form with no action at
     all: '' makes the form compose the message in the visitor's own mail
     app, which is the last resort that never silently drops an enquiry. */
  const FORM_ENDPOINT = '';
  const FALLBACK_EMAIL = 'contact@wwwebtech.in';

  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ============================================================
     NAV — shrink when stuck, hide going down, return going up.
     Transform + class only; never touches layout. (§8 T12)
     ============================================================ */
  function nav() {
    const el = $('#nav');
    if (!el) return;
    let last = window.scrollY, ticking = false;

    const update = () => {
      const y = Math.max(0, window.scrollY);
      el.classList.toggle('is-stuck', y > 8);
      // Never hide while the mobile menu is open or focus is inside the nav.
      const locked = document.body.classList.contains('is-locked') || el.contains(document.activeElement);
      if (!locked && y > 240) el.classList.toggle('is-hidden', y > last + 4);
      else el.classList.remove('is-hidden');
      last = y;
      ticking = false;
    };
    addEventListener('scroll', () => {
      if (!ticking) { ticking = true; requestAnimationFrame(update); }
    }, { passive: true });
    update();
  }

  /* ============================================================
     MOBILE MENU — full-screen overlay, focus trapped, Esc closes.
     ============================================================ */
  function mobileMenu() {
    const btn = $('#burger'), menu = $('#mobmenu');
    if (!btn || !menu) return;
    let lastFocus = null;

    const focusables = () => $$('a[href], button:not([disabled])', menu)
      .filter(e => e.offsetParent !== null);

    const open = () => {
      lastFocus = document.activeElement;
      menu.hidden = false;
      btn.setAttribute('aria-expanded', 'true');
      btn.setAttribute('aria-label', 'Close menu');
      document.body.classList.add('is-locked');
      document.body.style.overflow = 'hidden';
      focusables()[0]?.focus();
    };
    const close = () => {
      menu.hidden = true;
      btn.setAttribute('aria-expanded', 'false');
      btn.setAttribute('aria-label', 'Open menu');
      document.body.classList.remove('is-locked');
      document.body.style.overflow = '';
      (lastFocus || btn).focus();
    };

    btn.addEventListener('click', () => menu.hidden ? open() : close());
    menu.addEventListener('click', (e) => { if (e.target.closest('a')) close(); });

    addEventListener('keydown', (e) => {
      if (menu.hidden) return;
      if (e.key === 'Escape') { e.preventDefault(); close(); return; }
      if (e.key !== 'Tab') return;
      const f = focusables();
      if (!f.length) return;
      const first = f[0], lastEl = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); lastEl.focus(); }
      else if (!e.shiftKey && document.activeElement === lastEl) { e.preventDefault(); first.focus(); }
    });

    // A resize past the desktop breakpoint must not leave the body locked.
    matchMedia('(min-width: 62rem)').addEventListener('change', (m) => { if (m.matches && !menu.hidden) close(); });
  }

  /* ============================================================
     SERVICES DROPDOWN — <details> is already keyboard-native;
     this only adds "click away / Esc / tab away closes".
     ============================================================ */
  function dropdown() {
    const d = $('[data-drop]');
    if (!d) return;
    const close = () => d.open = false;
    document.addEventListener('click', (e) => { if (d.open && !d.contains(e.target)) close(); });
    document.addEventListener('focusin', (e) => { if (d.open && !d.contains(e.target)) close(); });
    d.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && d.open) { close(); d.querySelector('summary').focus(); }
    });
  }

  /* ============================================================
     COUNTERS (§8 T10) — the final value is already in the HTML, so
     this only ever animates *up to* what a crawler or a no-JS
     visitor already sees. Runs once.
     ============================================================ */
  function counters() {
    const els = $$('[data-count]');
    if (!els.length || reduced()) return;
    const io = new IntersectionObserver((entries) => {
      for (const e of entries) {
        if (!e.isIntersecting) continue;
        io.unobserve(e.target);
        const el = e.target;
        const target = parseFloat(el.dataset.count);
        const template = el.textContent;
        const t0 = performance.now(), dur = 1200;
        const tick = (now) => {
          const p = Math.min(1, (now - t0) / dur);
          const eased = 1 - Math.pow(1 - p, 3);            // ease-out cubic
          const v = Math.round(target * eased);
          el.textContent = template.replace(String(target), String(v));
          if (p < 1) requestAnimationFrame(tick);
          else el.textContent = template;                   // exact value, always
        };
        el.textContent = template.replace(String(target), '0');
        requestAnimationFrame(tick);
      }
    }, { threshold: 0.6 });
    els.forEach(el => io.observe(el));
  }

  /* ============================================================
     PROCESS RAIL — fills as you scroll the steps. Works on every
     viewport and without GSAP; motion.js does not touch it.
     With no JS the rail is simply full, which reads as finished.
     ============================================================ */
  function processRail() {
    const root = $('[data-process]');
    if (!root) return;
    const fill = $('[data-fill]', root), steps = $$('[data-step]', root);
    if (!fill) return;
    if (reduced()) return;                     // leave the rail at its CSS default
    fill.style.setProperty('--p', '0');

    let ticking = false;
    const update = () => {
      const r = root.getBoundingClientRect();
      const vh = innerHeight;
      const span = r.height - vh * 0.4;
      const p = Math.min(1, Math.max(0, (vh * 0.6 - r.top) / (span || 1)));
      fill.style.setProperty('--p', p.toFixed(3));
      const mid = vh * 0.5;
      steps.forEach(s => {
        const b = s.getBoundingClientRect();
        s.classList.toggle('is-active', b.top <= mid && b.bottom >= mid * 0.4);
      });
      ticking = false;
    };
    addEventListener('scroll', () => {
      if (!ticking) { ticking = true; requestAnimationFrame(update); }
    }, { passive: true });
    addEventListener('resize', update, { passive: true });
    update();
  }

  /* ============================================================
     CONTACT FORM
     ============================================================ */
  function forms() {
    $$('[data-form]').forEach(setup);

    function setup(form) {
      const status = $('[data-status]', form);
      const say = (msg, state) => {
        if (!status) return;
        status.textContent = msg;
        status.dataset.state = state || '';
      };

      const showError = (field, msg) => {
        const err = $('#' + field.id + '-err', form);
        field.setAttribute('aria-invalid', 'true');
        if (err) { err.textContent = msg; err.hidden = false; field.setAttribute('aria-describedby', err.id); }
      };
      const clearError = (field) => {
        const err = $('#' + field.id + '-err', form);
        field.removeAttribute('aria-invalid');
        if (err) { err.hidden = true; err.textContent = ''; }
      };

      form.addEventListener('input', (e) => {
        if (e.target.matches('[required]') && e.target.value.trim()) clearError(e.target);
      });

      form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Honeypot: a bot filled the hidden field. Pretend everything is fine.
        if (form.elements.company && form.elements.company.value) { say('Thanks — we’ll be in touch.', 'ok'); form.reset(); return; }

        let firstBad = null;
        for (const field of $$('[required]', form)) {
          const v = field.value.trim();
          const bad = !v
            ? 'This one’s needed.'
            : (field.type === 'email' && !/^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/.test(v))
              ? 'That email doesn’t look right.' : null;
          if (bad) { showError(field, bad); firstBad = firstBad || field; }
          else clearError(field);
        }
        if (firstBad) { say('Have a look at the highlighted fields.', 'err'); firstBad.focus(); return; }

        const btn = $('button[type="submit"]', form);
        const data = new FormData(form);
        data.delete('company');

        /* Where they were and how they got here. With JavaScript off the
           endpoint recovers the same values from the Referer header, which
           a same-origin post still carries in full. */
        data.append('_page', location.pathname);
        data.append('_ref', document.referrer || '');
        const params = new URLSearchParams(location.search);
        for (const k of ['utm_source', 'utm_medium', 'utm_campaign']) {
          const v = params.get(k);
          if (v) data.append(k, v.slice(0, 120));
        }

        const endpoint = form.getAttribute('action') || FORM_ENDPOINT;

        if (!endpoint) {
          // No endpoint configured yet — hand off to the visitor's mail app so
          // the enquiry still reaches a human. See FORM_ENDPOINT above.
          const lines = [];
          for (const [k, v] of data.entries()) if (v && k[0] !== '_' && !k.startsWith('utm_')) lines.push(k + ': ' + v);
          location.href = 'mailto:' + FALLBACK_EMAIL
            + '?subject=' + encodeURIComponent('Website enquiry — ' + (data.get('name') || ''))
            + '&body=' + encodeURIComponent(lines.join('\n'));
          say('Opening your email app…', 'ok');
          return;
        }

        btn && (btn.disabled = true);
        say('Sending…');
        try {
          const res = await fetch(endpoint, {
            method: 'POST', body: data, headers: { Accept: 'application/json' },
          });
          /* Deliberately NOT called `data`: that name is the FormData above,
             and re-declaring it in this block put `body: data` in the temporal
             dead zone, so every submission threw before fetch() ever ran. */
          const reply = await res.json().catch(() => ({}));
          if (!res.ok || reply.ok === false) throw new Error(reply.error || res.status);
          form.reset();
          say(reply.message || 'Got it. A real person replies within 1 business day.', 'ok');
        } catch (err) {
          const detail = String(err && err.message || '');
          say(/@/.test(detail) ? detail
            : 'That didn’t send. Email ' + FALLBACK_EMAIL + ' and we’ll pick it up.', 'err');
        } finally {
          btn && (btn.disabled = false);
        }
      });
    }
  }

  /* ============================================================
     MOTION — loaded last, only when it can actually be used.
     Never a dependency of anything above.
     ============================================================ */
  function loadMotion() {
    if (reduced()) return;
    if (!matchMedia('(min-width: 48rem)').matches) return;   // half the budget on mobile (§0.6)
    if (!matchMedia('(pointer: fine)').matches) return;
    const go = () => import('/assets/js/motion.js').catch(() => {});
    'requestIdleCallback' in window ? requestIdleCallback(go, { timeout: 2500 }) : setTimeout(go, 1200);
  }

  /* ------------------------------------------------------------------ */
  document.documentElement.classList.add('js-on');
  nav(); mobileMenu(); dropdown(); counters(); processRail(); forms(); loadMotion();
})();
