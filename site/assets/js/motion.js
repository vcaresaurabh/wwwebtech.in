/* ============================================================
   motion.js — the enhancement layer. Loaded by main.js on idle,
   and only on a desktop-sized viewport with a fine pointer and no
   reduced-motion preference.

   Rules this file obeys (§8 T12):
     · it only ever ADDS motion to content that is already visible;
     · it animates transform and opacity, nothing else;
     · every effect is wrapped so a failure leaves the page usable;
     · if any of it throws, the page is exactly as it was.

   Deliberately NOT here:
     · horizontal scroll (T4)  — fights Core Web Vitals and mobile;
     · WebGL distortion  (T8)  — wrong story for a "practical partner",
                                 and the single biggest performance tax;
     · scroll-scrubbed video (T9) — there is no real video to scrub, and
                                 a placeholder-free site beats stock footage.
   These three are decisions, not omissions. Please don't add them back
   without re-reading the performance budget in the brief.
   ============================================================ */

const VENDOR = '/assets/js/vendor/';

const loadScript = (src) => new Promise((res, rej) => {
  const s = document.createElement('script');
  s.src = src; s.async = false;
  s.onload = res; s.onerror = () => rej(new Error('failed ' + src));
  document.head.appendChild(s);
});

async function boot() {
  await loadScript(VENDOR + 'gsap.min.js');
  await Promise.all([
    loadScript(VENDOR + 'ScrollTrigger.min.js'),
    loadScript(VENDOR + 'SplitText.min.js'),
    loadScript(VENDOR + 'lenis.min.js'),
  ]);
  const { gsap, ScrollTrigger, SplitText, Lenis } = window;
  if (!gsap || !ScrollTrigger) return;
  gsap.registerPlugin(ScrollTrigger, ...(SplitText ? [SplitText] : []));

  const safe = (name, fn) => { try { fn(); } catch (e) { console.warn('motion:' + name, e); } };

  /* ============================================================
     T1 · Lenis smooth scroll, driven by GSAP's ticker so scroll
     position and ScrollTrigger never disagree by a frame.
     ============================================================ */
  safe('lenis', () => {
    if (!Lenis) return;
    const lenis = new Lenis({ lerp: 0.1, smoothWheel: true, autoRaf: false });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((t) => lenis.raf(t * 1000));
    gsap.ticker.lagSmoothing(0);

    /* Anything that is NOT Lenis — find-in-page, Tab-to-offscreen, PgDn,
       Home/End, dragging the scrollbar — moves the real scroll position.
       When that happens we adopt it instead of animating back to where
       Lenis thought we were, which is the usual way smooth-scroll breaks
       keyboard and find-in-page. (§11) */
    addEventListener('scroll', () => {
      if (lenis.isScrolling) return;
      if (Math.abs(window.scrollY - lenis.targetScroll) > 4) {
        lenis.scrollTo(window.scrollY, { immediate: true, force: true });
      }
    }, { passive: true });

    /* In-page anchors, including the skip link. */
    document.querySelectorAll('a[href^="#"]:not([href="#"])').forEach(a => {
      a.addEventListener('click', (e) => {
        const target = document.querySelector(a.getAttribute('href'));
        if (!target) return;
        e.preventDefault();
        lenis.scrollTo(target, { offset: -96 });
        target.setAttribute('tabindex', '-1');
        target.focus({ preventScroll: true });
      });
    });

    window.__lenis = lenis;
  });

  /* ============================================================
     T3 · Line-mask reveal. Runs after fonts settle, otherwise the
     lines are measured against the fallback face and re-break.
     ============================================================ */
  safe('split', async () => {
    if (!SplitText) return;
    await (document.fonts ? document.fonts.ready : Promise.resolve());
    document.querySelectorAll('[data-split]').forEach((el) => {
      const split = SplitText.create
        ? SplitText.create(el, { type: 'lines', mask: 'lines', linesClass: 'split-line' })
        : new SplitText(el, { type: 'lines', linesClass: 'split-line' });
      gsap.fromTo(split.lines,
        { yPercent: 110 },
        {
          yPercent: 0, duration: 0.95, ease: 'expo.out', stagger: 0.06,
          // Put the DOM back the way it was, so text stays selectable
          // and nothing is left mid-transform if the tab is backgrounded.
          onComplete: () => gsap.set(split.lines, { clearProps: 'transform' }),
        });
    });
  });

  /* ============================================================
     T2 · Scrub. The *pinning* on the pillars and the section rails is
     done with position:sticky in CSS — it costs no main-thread work
     and cannot shift layout. ScrollTrigger is used only where a value
     genuinely has to track scroll position.
     ============================================================ */
  safe('diagram', () => {
    document.querySelectorAll('[data-machine] [data-flow] path').forEach((p) => {
      const len = p.getTotalLength();
      gsap.fromTo(p,
        { strokeDasharray: len, strokeDashoffset: len },
        {
          strokeDashoffset: 0, ease: 'none',
          scrollTrigger: { trigger: p.closest('[data-machine]'), start: 'top 78%', end: 'bottom 62%', scrub: 0.8 },
          onComplete: () => gsap.set(p, { clearProps: 'strokeDasharray,strokeDashoffset' }),
        });
    });
  });

  /* Hero grid drifts a little as you leave the hero. 8px, once. */
  safe('herogrid', () => {
    const g = document.querySelector('[data-herogrid]');
    if (!g) return;
    gsap.to(g, {
      yPercent: 2.5, ease: 'none',
      scrollTrigger: { trigger: g.closest('.hero'), start: 'top top', end: 'bottom top', scrub: 1 },
    });
  });

  /* ============================================================
     T7 · Magnetic hover. Small, and desktop-only by construction —
     this file never loads on a coarse pointer.
     ============================================================ */
  safe('magnetic', () => {
    document.querySelectorAll('[data-magnetic]').forEach((el) => {
      const MAX = 10;
      const to = gsap.quickTo(el, 'x', { duration: 0.4, ease: 'power3' });
      const ty = gsap.quickTo(el, 'y', { duration: 0.4, ease: 'power3' });
      el.addEventListener('pointermove', (e) => {
        const r = el.getBoundingClientRect();
        to(gsap.utils.clamp(-MAX, MAX, (e.clientX - (r.left + r.width / 2)) * 0.35));
        ty(gsap.utils.clamp(-MAX, MAX, (e.clientY - (r.top + r.height / 2)) * 0.35));
      });
      el.addEventListener('pointerleave', () => { to(0); ty(0); });
    });
  });

  /* Recalculate once webfonts have changed the metrics. */
  if (document.fonts) document.fonts.ready.then(() => ScrollTrigger.refresh());
}

boot().catch((e) => console.warn('motion disabled:', e.message));
