/* ============================================================
   HOMEPAGE — fourteen sections.
   Sections 01 (nav), 13 (CTA band) and 14 (footer) come from
   src/partials/chrome.mjs so every page shares them exactly.
   ============================================================ */
import { SITE, PILLARS, POSTS } from '../data.mjs';
import { icon, arw } from '../partials/icons.mjs';
import { ctaBand } from '../partials/chrome.mjs';
import {
  shead, machineDiagram, pillarsBlock, processBlock,
  objectionsBlock, blogBlock,
} from '../partials/blocks.mjs';
import {
  PROOF, PROJECTS, FOUNDED, LAUNCH_WEEKS, RETENTION,
  CLIENTS, CASES, QUOTES, CREDENTIALS, weeksPhrase,
} from '../proof.mjs';
import * as S from '../schema.mjs';

/* --- 02 · HERO ----------------------------------------------------- */
const hero = () => {
  /* [V:1] + [V:2] fill this line. Until then it makes only promises we keep. */
  const proof = [
    'Response within 1 business day',
    PROJECTS && FOUNDED ? `${PROJECTS} projects shipped since ${FOUNDED}`
      : PROJECTS ? `${PROJECTS} projects shipped`
      : 'You own the code, the content and the accounts',
  ];
  return `
<section class="hero" id="top">
  <svg class="hero__grid" aria-hidden="true" focusable="false" data-herogrid>
    <defs><pattern id="grid" width="72" height="72" patternUnits="userSpaceOnUse">
      <path d="M72 0H0V72" fill="none" stroke="currentColor" stroke-width="1"/>
    </pattern></defs>
    <rect width="100%" height="100%" fill="url(#grid)"/>
  </svg>

  <div class="shell hero__inner">
    <p class="u-mono">Web · SEO · Social — Delhi, India</p>

    <h1 data-split>We build fast websites, get them found on Google and AI search,
      and run the social that feeds both.</h1>

    <p class="hero__sub u-lead">Wwwebtech is a technology partner for growing Indian
      businesses — one accountable team for your website, your CRM, and your customer
      pipeline. Based in East Delhi, serving all of India.</p>

    <div class="hero__cta">
      <a class="btn btn--lg" href="/contact/" data-magnetic>Get a proposal ${arw()}</a>
      <a class="btn btn--ghost btn--lg" href="#process">See how we work ${arw('arrow-down')}</a>
    </div>

    <p class="hero__proof u-mono">
      ${proof.map(t => `<span><span class="dot" aria-hidden="true"></span>${t}</span>`).join('')}
    </p>
  </div>
</section>`;
};

/* --- 03 · PLATFORM STRIP ------------------------------------------- */
const PLATFORMS = ['Google', 'Google Maps', 'ChatGPT', 'Gemini', 'AI Overviews',
  'Instagram', 'YouTube', 'Facebook', 'WhatsApp Business', 'Amazon', 'Flipkart', 'JustDial'];

const strip = () => {
  const track = (hidden) =>
    `<div class="marquee__track"${hidden ? ' aria-hidden="true"' : ''}>` +
    PLATFORMS.map(p => `<span class="marquee__item">${p}</span>`).join('') + '</div>';
  return `
<section class="strip" aria-labelledby="strip-h">
  <p class="strip__label u-mono" id="strip-h">We get you found everywhere people actually search</p>
  <div class="marquee">${track(false)}${track(true)}</div>
</section>`;
};

/* --- 04 · CLIENT WALL  (data-proof="clients") ----------------------- */
const clientWall = () => PROOF.clients && CLIENTS.length ? `
<section class="section section--tight" data-proof="clients" aria-labelledby="clients-h">
  <div class="shell">
    <p class="u-mono" id="clients-h" style="text-align:center">The businesses behind our work</p>
    <div class="logowall" style="margin-top:var(--s7)">
      ${CLIENTS.map(c => `<a href="${c.url || '#'}" rel="noopener">
        <img src="/assets/img/clients/${c.file}" alt="${c.name}" height="36" loading="lazy" decoding="async">
      </a>`).join('')}
    </div>
  </div>
</section>` : `
<!-- [V:3] No client logos yet, and we will not invent any. This band runs in
     their place; flip PROOF.clients in site/assets/js/proof-config.js. -->
<section class="pullband" aria-label="Our position on ownership">
  <div class="shell">
    <blockquote>Every client owns their code, their content, and their accounts.
      We just make them work harder.</blockquote>
  </div>
</section>`;

/* --- 05 · STAT ROW ------------------------------------------------- */
const stat = ({ num, word, label }) => `
  <div class="stat">
    ${num
      ? `<span class="stat__num" data-count="${num.value}">${num.prefix || ''}${num.value}${num.suffix || ''}</span>`
      : `<span class="stat__num stat__num--word">${word}</span>`}
    <p class="stat__label">${label}</p>
  </div>`;

const stats = () => `
<section class="band section--ruled" aria-labelledby="stats-h">
  <h2 class="visually-hidden" id="stats-h">Wwwebtech in numbers</h2>
  <div class="shell">
    <div class="stats">
      ${/* [V:1] */ PROJECTS
        ? stat({ num: { value: PROJECTS, suffix: '+' }, label: 'projects shipped for Indian businesses' })
        : stat({ word: 'One accountable team', label: 'Developer, SEO and social — in one office, on one contract' })}

      ${/* [V:4] */ LAUNCH_WEEKS
        ? stat({ word: `${LAUNCH_WEEKS} weeks`, label: 'typical business site, kickoff to launch' })
        : stat({ word: 'Weekly', label: 'how often you see the build, not a status email' })}

      ${/* [V:5] */ RETENTION
        ? stat({ num: { value: RETENTION, suffix: '%' }, label: 'of clients are still with us after a year' })
        : stat({ num: { value: 100, suffix: '%' }, label: 'client-owned code, content, domains and ad accounts' })}

      ${stat({ num: { value: 1 }, label: 'business day — our response time, guaranteed' })}
    </div>
  </div>
</section>`;

/* --- 06 · POSITIONING LINE + THE MACHINE --------------------------- */
const positioning = () => `
<section class="section section--ruled" aria-labelledby="pos-h">
  <div class="shell">
    <h2 class="position__line" id="pos-h">Most agencies build the shop <em class="u-italic">or</em>
      bring the footfall. We build the machine end to end — the website, the traffic,
      and the CRM that catches every lead.</h2>
    <p class="position__sub u-mono">That’s the difference between a vendor and a technology partner.</p>
    <div style="margin-top:var(--s9);overflow-x:auto">
      <div style="min-width:640px">${machineDiagram({ id: 'machine-home' })}</div>
    </div>
  </div>
</section>`;

/* --- 08 · WORK  (data-proof="work") -------------------------------- */
const ANATOMY = [
  { k: 'Week 1–2', t: 'Strategy, sitemap, and a design you approve before code',
    b: 'We map how you actually get customers, agree the pages that matter, and show you the design. Nothing gets built until you have said yes to it.' },
  { k: 'Week 3–5', t: 'Build, content, technical SEO baked in from the first commit',
    b: 'Speed, structured data and clean URLs are part of the build, not an upsell afterwards. You see progress weekly, on the real thing.' },
  { k: 'Launch + 90 days', t: 'Search Console, GBP, socials live, CRM catching leads',
    b: 'Everything is wired up and handed over in your name. Then we watch the numbers with you for a quarter and fix what the real world finds.' },
];

const work = () => {
  const cards = PROOF.work && CASES.length ? CASES.map(c => `
    <li>
      <article class="card card--link workcard">
        <div>
          <p class="u-mono card__eyebrow">${c.client}</p>
          <h3 class="workcard__result"><a class="card__stretch" href="/work/#${c.slug}">${c.result}</a></h3>
          <p class="workcard__meta u-mono">${c.service} · ${c.timeframe}</p>
        </div>
        <div class="workcard__art"><span class="workcard__arrow" data-magnetic>${icon('arrow-up-right','ico--sm')}</span></div>
      </article>
    </li>`).join('')
    : ANATOMY.map(a => `
    <li>
      <article class="card workcard">
        <div>
          <p class="u-mono card__eyebrow">${a.k}</p>
          <h3 class="workcard__result">${a.t}</h3>
          <p class="workcard__meta card__body">${a.b}</p>
        </div>
        <div class="workcard__art" aria-hidden="true">
          <svg class="diagram" viewBox="0 0 220 120" role="presentation">
            <rect class="dg-box" x="8" y="14" width="204" height="92" rx="4"/>
            <path class="dg-rule" d="M8 38h204" fill="none"/>
            <circle class="dg-fill" cx="24" cy="26" r="3"/>
            <circle class="dg-rule" cx="36" cy="26" r="3" fill="none"/>
            <circle class="dg-rule" cx="48" cy="26" r="3" fill="none"/>
            <rect class="dg-wash" x="24" y="52" width="88" height="8" rx="2"/>
            <rect class="dg-wash" x="24" y="68" width="132" height="6" rx="2" opacity=".7"/>
            <rect class="dg-wash" x="24" y="82" width="104" height="6" rx="2" opacity=".5"/>
            <rect class="dg-fill" x="168" y="78" width="28" height="12" rx="2"/>
          </svg>
        </div>
      </article>
    </li>`).join('');

  return `
<section class="section section--ruled" id="work" ${PROOF.work ? 'data-proof="work"' : ''}>
  <div class="shell">
    <div class="railed railed--wide">
      <div class="rail rail--sticky">
        ${PROOF.work
          ? shead({ label: 'Work', title: 'Results, not screenshots.' })
          : shead({
              label: 'Work',
              title: 'What a launch with us looks like.',
              sub: 'We’re a young brand. Rather than dress up someone else’s numbers as ours, here is exactly what the first three months run like — and we’ll walk you through real, recent work on a call.',
            })}
      </div>
      <div>
        <ul class="stack-cards" data-stack>${cards}</ul>
        <p style="margin-top:var(--s7)">
          <a class="lnk" href="/contact/">Ask us to walk you through recent work ${arw()}</a>
        </p>
      </div>
    </div>
  </div>
</section>`;
};

/* --- 11 · TRUST BAND ----------------------------------------------- */
const trust = () => `
<section class="section section--ruled" aria-labelledby="trust-h">
  <div class="shell">
    <div class="railed railed--wide">
      <div class="rail">
        ${shead({ label: 'Trust', title: 'What we can actually show you.', tag: 'h2' })}
      </div>
      <div>
        ${PROOF.quotes && QUOTES.length ? `
        <div class="grid" style="--cols-sm:2" data-proof="quotes">
          ${QUOTES.slice(0, 2).map(q => `
          <figure class="card">
            <blockquote class="quote__text">“${q.text}”</blockquote>
            <figcaption class="quote__who">
              <span class="quote__name">${q.name}</span>
              <span class="u-small u-ink3">${q.role}, ${q.company}</span>
            </figcaption>
          </figure>`).join('')}
        </div>` : `
        <!-- [V:7] No testimonials yet. An unattributed quote is decoration, not
             proof, so nothing stands in for one. Flip PROOF.quotes when real,
             named, permissioned quotes exist. -->
        <div class="card">
          <p class="quote__text">We don’t have a wall of testimonials yet, and we’re not
            going to write ourselves one. What we can do is put you on a call with the
            people doing the work, and show you what we’ve shipped.</p>
          <p class="card__foot"><a class="lnk" href="/contact/">Book that call ${arw()}</a></p>
        </div>`}

        <div style="margin-top:var(--s7);padding-top:var(--s6);border-top:1px solid var(--rule)">
          <p class="u-mono" id="trust-h">On the record</p>
          <!-- [V:8] Only credentials that genuinely exist today. Plain text
               chips, never badge artwork — artwork implies certification. -->
          <div class="cluster" style="margin-top:var(--s4)">
            ${CREDENTIALS.map(c => `<span class="chip">${c}</span>`).join('')}
            <span class="chip">Registered in ${SITE.locality}, ${SITE.region}</span>
          </div>
          <p class="u-small u-ink3" style="margin-top:var(--s4);max-width:52ch">
            No review scores, no partner badges we haven’t earned. If it isn’t on this
            list, we don’t claim it.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>`;

/* ============================================================ */
const title = 'Web Development, SEO & Social Agency in Delhi | Wwwebtech';
const desc  = 'Websites, custom CRM, SEO and social media for growing Indian businesses. One accountable team in East Delhi. You own the code and the accounts.';

export const page = {
  path: '/',
  nav: 'home',
  og: 'home',
  title, desc,
  schema: S.graph([
    S.organization(),
    S.website(),
    S.webpage({ url: '/', title, desc }),
    ...PILLARS.map(p => S.service({ name: p.name, description: p.one, url: p.href, subs: p.subs })),
  ]),
  render: () => [
    hero(),
    strip(),
    clientWall(),
    stats(),
    positioning(),
    pillarsBlock(),
    work(),
    processBlock(),
    objectionsBlock(weeksPhrase),
    trust(),
    blogBlock(),
    ctaBand(),
  ].join('\n'),
};
