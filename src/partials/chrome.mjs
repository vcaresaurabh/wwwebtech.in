/* ============================================================
   Page chrome: nav, mobile menu, footer, CTA band.
   Everything here is derived from src/data.mjs, so a nav or footer
   link can never point at a page the build didn't produce.
   ============================================================ */
import { SITE, PILLARS, SUBS, FOOTER_COMPANY } from '../data.mjs';
import { icon, arw } from './icons.mjs';

export const logo = (cls = '') => `<span class="logo${cls ? ' ' + cls : ''}">
<svg viewBox="0 28 568 78" role="img" aria-label="Wwwebtech"><use href="#logo-word"/></svg></span>`;

/* The wordmark outlines, defined once per page and referenced by <use>. */
export const logoDefs = (word, tail) =>
  `<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs><g id="logo-word">` +
  `<path class="logo__word" d="${word}"/><path class="logo__tail" d="${tail}"/>` +
  `<circle class="logo__dot" cx="554" cy="87" r="10"/></g></defs></svg>`;

const subLinks = (p) => p.subs.map(k =>
  `<a href="${SUBS[k].href}">${SUBS[k].phrase}</a>`).join('');

export const nav = () => `
<a class="skip-link" href="#main">Skip to content</a>
<header class="nav" id="nav" data-nav-root>
  <div class="shell nav__inner">
    <a href="/" aria-label="Wwwebtech — home" data-nav="home">${logo()}</a>

    <nav class="nav__menu" aria-label="Primary">
      <details class="nav__drop" data-drop>
        <summary data-nav="services">Services ${arw('chevron')}</summary>
        <div class="nav__panel">
          ${PILLARS.map(p => `<div class="nav__col">
            <a class="nav__pillar" href="${p.href}">${p.name}</a>
            ${subLinks(p)}
          </div>`).join('')}
        </div>
      </details>
      <a class="nav__link" href="/work/"    data-nav="work">Work</a>
      <a class="nav__link" href="/about/"   data-nav="about">About</a>
      <a class="nav__link" href="/blog/"    data-nav="blog">Blog</a>
      <a class="nav__link" href="/contact/" data-nav="contact">Contact</a>
    </nav>

    <div class="nav__actions">
      <a class="btn u-lg-only" href="/contact/">Get a proposal</a>
      <button class="nav__burger" id="burger" type="button"
              aria-expanded="false" aria-controls="mobmenu" aria-label="Open menu">
        <span class="bar"></span><span class="bar"></span>
      </button>
    </div>
  </div>
</header>

<div class="mobmenu" id="mobmenu" hidden>
  ${PILLARS.map(p => `<div class="mobmenu__group">
    <a class="mobmenu__lead" href="${p.href}">${p.name} ${arw()}</a>
    <div class="mobmenu__sub">${subLinks(p)}</div>
  </div>`).join('')}
  <div class="mobmenu__group">
    <div class="mobmenu__sub">
      <a href="/work/">Work</a><a href="/about/">About</a>
      <a href="/blog/">Blog</a><a href="/contact/">Contact</a>
    </div>
  </div>
  <div class="mobmenu__foot">
    <a class="btn btn--lg" href="/contact/">Get a proposal</a>
    <a class="btn btn--ghost btn--lg" href="${SITE.whatsapp}" rel="noopener">WhatsApp us ${arw('arrow-up-right')}</a>
  </div>
</div>`;

/* --- 13 · CTA + form band. Shared by home and every service page. --- */
export const ctaBand = ({ h2, sub, id = 'talk' } = {}) => `
<section class="band ctaband section" id="${id}">
  <div class="shell ctaband__grid">
    <div>
      <p class="u-mono">Start here</p>
      <h2 class="shead__title">${h2 || 'Tell us what’s not working.'}</h2>
      <p class="ctaband__sub u-lead">${sub || 'Site, leads, rankings, or all three — describe it in two lines. A real person replies within 1 business day.'}</p>
      <div class="ctaband__alt">
        <p class="u-mono">Rather not fill a form?</p>
        <a class="btn btn--ghost" href="${SITE.whatsapp}" rel="noopener">${icon('whatsapp','ico--sm')} WhatsApp us instead</a>
        <a class="u-link u-small" href="mailto:${SITE.email}">${SITE.email}</a>
      </div>
    </div>
    <div>${contactForm()}</div>
  </div>
</section>`;

/* --- The one form. Static-host safe: fetch → FORM_ENDPOINT, with a
       mailto: fallback and a honeypot. No CAPTCHA. (§6-13) ---------- */
export const contactForm = (idp = 'f') => `
<form class="form" id="${idp}-form" data-form novalidate
      action="${SITE.leadEndpoint}" method="post">
  <div class="form__row">
    <div class="field">
      <label for="${idp}-name">Name <span class="req" aria-hidden="true">*</span></label>
      <input class="input" id="${idp}-name" name="name" type="text" required autocomplete="name"
             maxlength="100" placeholder="Your name">
      <p class="field__error" id="${idp}-name-err" hidden></p>
    </div>
    <div class="field">
      <label for="${idp}-email">Email <span class="req" aria-hidden="true">*</span></label>
      <input class="input" id="${idp}-email" name="email" type="email" required autocomplete="email"
             maxlength="150" placeholder="you@company.com">
      <p class="field__error" id="${idp}-email-err" hidden></p>
    </div>
  </div>

  <div class="field">
    <label for="${idp}-phone">Phone / WhatsApp</label>
    <input class="input" id="${idp}-phone" name="phone" type="tel" autocomplete="tel"
           maxlength="30" placeholder="+91 …">
  </div>

  <fieldset class="field" style="border:0;padding:0;min-width:0">
    <!-- name="need[]", not name="need". PHP keeps only the LAST value of a
         repeated plain field, so with "need" a visitor who ticked three
         boxes had two of them silently dropped before anyone saw them. -->
    <legend class="u-mono" style="padding:0;margin-bottom:.45rem">What do you need?</legend>
    <div class="chips">
      ${['Website','CRM','SEO','Social','Not sure'].map((v,i) => `
      <input type="checkbox" id="${idp}-n${i}" name="need[]" value="${v}">
      <label for="${idp}-n${i}">${v}</label>`).join('')}
    </div>
  </fieldset>

  <div class="field">
    <label for="${idp}-budget">Budget</label>
    <select class="select" id="${idp}-budget" name="budget">
      <option value="">Select a range</option>
      <option>Under ₹50k</option>
      <option>₹50k – ₹1.5L</option>
      <option>₹1.5L – ₹5L</option>
      <option>₹5L+</option>
      <option>Ongoing retainer</option>
    </select>
  </div>

  <div class="field">
    <label for="${idp}-message">What’s not working? <span class="req" aria-hidden="true">*</span></label>
    <textarea class="textarea" id="${idp}-message" name="message" required maxlength="2000"
              placeholder="Two lines is plenty."></textarea>
    <p class="field__error" id="${idp}-message-err" hidden></p>
  </div>

  <!-- Honeypot. Real people never see or fill this. -->
  <div class="hp" aria-hidden="true">
    <label for="${idp}-company">Company (leave blank)</label>
    <input id="${idp}-company" name="company" type="text" tabindex="-1" autocomplete="off">
  </div>

  <div class="cluster" style="gap:var(--s5)">
    <button class="btn btn--lg" type="submit">Send it ${arw()}</button>
    <p class="form__status" role="status" aria-live="polite" data-status></p>
  </div>
  <p class="form__note">We reply within 1 business day. No newsletter, no sales sequence.</p>
</form>`;

/* --- 14 · Fat footer. Every link resolves — that's the point. ------ */
export const footer = () => `
<footer class="footer">
  <div class="shell footer__grid">
    <div>
      ${logo()}
      <p class="footer__blurb">${SITE.tagline}</p>
      <address class="footer__nap">
        <span>${SITE.locality}, ${SITE.region}, India</span>
        <a href="mailto:${SITE.email}">${SITE.email}</a>
        <a href="tel:${SITE.phoneE164}">${SITE.phoneNbsp}</a>
      </address>
    </div>

    <div class="footer__col">
      <h2 class="u-mono">Services</h2>
      <ul>${PILLARS.flatMap(p => p.subs).map(k =>
        `<li><a href="${SUBS[k].href}">${SUBS[k].phrase}</a></li>`).join('')}
        <li><a href="/services/technical-support/">Ongoing technical support</a></li>
      </ul>
    </div>

    <div class="footer__col">
      <h2 class="u-mono">Company</h2>
      <ul>${FOOTER_COMPANY.map(l => `<li><a href="${l.href}">${l.label}</a></li>`).join('')}</ul>
    </div>

    <div class="footer__col">
      <h2 class="u-mono">Find us</h2>
      <ul>
        <li><a href="${SITE.linkedin}" rel="noopener">LinkedIn</a></li>
        <li><a href="${SITE.instagram}" rel="noopener">Instagram</a></li>
        <li><a href="${SITE.whatsapp}" rel="noopener">WhatsApp</a></li>
        ${SITE.gbp ? `<li><a href="${SITE.gbp}" rel="noopener">Google Business Profile</a></li>`
                   : `<!-- [V:10] Google Business Profile URL → SITE.gbp in src/data.mjs. Adding it
                            links this row and adds the profile to LocalBusiness sameAs. -->`}
      </ul>
    </div>
  </div>
  <div class="shell">
    <div class="footer__bar">
      <span>© ${new Date().getFullYear()} Wwwebtech</span>
      <span>Made with <span class="heart" aria-hidden="true">❤</span><span class="visually-hidden">love</span> in India</span>
    </div>
  </div>
</footer>`;
