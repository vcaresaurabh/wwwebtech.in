/* /styleguide.html — internal reference. Not linked from anywhere, noindex.
   Every component on the site appears here once, so a change can be checked
   in one place instead of by scrolling twenty pages. */
import { contactForm } from '../partials/chrome.mjs';
import { icon, arw, ICON_KEYS } from '../partials/icons.mjs';
import { machineDiagram, faqBlock } from '../partials/blocks.mjs';
import { cwvGauge, aiSources, socialLoop } from '../partials/diagrams.mjs';
import * as S from '../schema.mjs';

const swatch = (name, v, note) => `
  <div>
    <div style="height:64px;background:var(${v});border:1px solid var(--rule);border-radius:var(--radius)"></div>
    <p class="u-mono" style="margin-top:.5rem">${name}</p>
    <p class="u-small u-ink3">${note}</p>
  </div>`;

const row = (label, html) => `
  <div style="padding-block:var(--s6);border-top:1px solid var(--rule)">
    <p class="u-mono" style="margin-bottom:var(--s4)">${label}</p>
    ${html}
  </div>`;

export const page = {
  path: '/styleguide.html', nav: null, og: 'home', noindex: true,
  title: 'Styleguide (internal) | Wwwebtech',
  desc: 'Internal component reference. Not linked publicly.',
  schema: S.graph([]),
  render: () => `
<section class="section" id="top">
  <div class="shell">
    <p class="u-mono">Internal · not linked publicly · noindex</p>
    <h1 style="margin-top:var(--s5)">Styleguide</h1>
    <p class="u-lead" style="margin-top:var(--s5);max-width:56ch">
      Every component the site uses, once. If something looks wrong here it is wrong
      everywhere, which is the point.
    </p>

    ${row('Colour — paper', `<div class="grid" style="--cols:2;--cols-sm:4;--cols-md:6">
      ${swatch('--paper', '--paper', 'page ground')}
      ${swatch('--card', '--card', 'raised surfaces')}
      ${swatch('--wash', '--wash', 'alternate ground')}
      ${swatch('--rule', '--rule', 'hairlines')}
      ${swatch('--marigold-wash', '--marigold-wash', 'accent chip ground')}
      ${swatch('--ink', '--ink', 'headlines')}
    </div>`)}

    ${row('Colour — ink &amp; accent', `<div class="grid" style="--cols:2;--cols-sm:4;--cols-md:6">
      ${swatch('--ink-2', '--ink-2', 'body text')}
      ${swatch('--ink-3', '--ink-3', 'captions')}
      ${swatch('--marigold', '--marigold', 'CTAs, ≥24px only')}
      ${swatch('--marigold-deep', '--marigold-deep', 'accent text &lt;24px')}
      ${swatch('--band', '--band', 'dark band ground')}
      ${swatch('--marigold-lift', '--marigold-lift', 'accent on ink')}
    </div>`)}

    ${row('Type scale', `
      <p class="t-h1">H1 — Fraunces 500</p>
      <h2 style="margin-top:var(--s5)">H2 — the section heading</h2>
      <h3 style="margin-top:var(--s5)">H3 — the card title</h3>
      <h4 style="margin-top:var(--s5)">H4 — Archivo 600</h4>
      <p style="margin-top:var(--s5);max-width:var(--measure)">Body — Archivo 400 at 16–17px on a
        1.6 line height, capped at 68 characters. Numbers use tabular figures: 1,234,567 · 2026 · ₹1.5L.</p>
      <p class="u-lead" style="margin-top:var(--s4)">Lead — the larger intro paragraph.</p>
      <p class="u-mono" style="margin-top:var(--s4)">Mono label — 11px, .12em tracking, uppercase</p>
      <p class="u-italic" style="margin-top:var(--s4);font-size:1.5rem">Fraunces italic — used for pull quotes and emphasis</p>`)}

    ${row('Buttons', `<div class="cluster">
      <a class="btn" href="#top">Primary ${arw()}</a>
      <a class="btn btn--lg" href="#top">Primary large ${arw()}</a>
      <a class="btn btn--ghost" href="#top">Ghost ${arw()}</a>
      <a class="btn btn--quiet" href="#top">Quiet ${arw()}</a>
      <a class="lnk" href="#top">Inline link ${arw()}</a>
    </div>`)}

    ${row('Chips', `<div class="cluster">
      <span class="chip">Plain chip</span>
      <span class="chip chip--accent">Accent chip</span>
      <span class="chip">${icon('local','ico--sm')} With icon</span>
    </div>`)}

    ${row(`Icons (${ICON_KEYS.length})`, `<div class="cluster" style="gap:var(--s5)">
      ${ICON_KEYS.map(k => `<span style="text-align:center;width:5.5rem">
        ${icon(k, 'ico--accent')}<span class="u-small u-ink3" style="display:block;margin-top:.35rem;font-size:.6875rem">${k}</span>
      </span>`).join('')}
    </div>`)}

    ${row('Cards', `<div class="grid" style="--cols-sm:2;--cols-md:3">
      <article class="card"><p class="u-mono card__eyebrow">Eyebrow</p>
        <h3 class="card__title" style="font-size:var(--h4)">Plain card</h3>
        <p class="card__body">Body copy at the small size, in ink-2.</p></article>
      <article class="card card--link"><p class="u-mono card__eyebrow">Eyebrow</p>
        <h3 class="card__title" style="font-size:var(--h4)"><a class="card__stretch" href="#top">Link card</a></h3>
        <p class="card__body">The whole card is the target, without nesting interactive elements.</p>
        <p class="card__foot"><span class="lnk">Explore ${arw()}</span></p></article>
      <article class="card"><p class="objection__q">“An objection card”</p>
        <p class="objection__a">Two sentences, answering it straight.</p></article>
    </div>`)}

    ${row('Form', contactForm('sg'))}

    ${row('Diagram — the machine', `<div style="overflow-x:auto"><div style="min-width:640px">${machineDiagram({ id: 'sg-machine', draw: false })}</div></div>`)}
    ${row('Diagram — Core Web Vitals', `<div style="overflow-x:auto"><div style="min-width:600px">${cwvGauge('sg-cwv')}</div></div>`)}
    ${row('Diagram — AI sources', `<div style="overflow-x:auto"><div style="min-width:600px">${aiSources('sg-ai')}</div></div>`)}
    ${row('Diagram — social loop', `<div style="overflow-x:auto"><div style="min-width:600px">${socialLoop('sg-loop')}</div></div>`)}
  </div>
</section>

<section class="band section">
  <div class="shell">
    <p class="u-mono">On the dark band, every token flips</p>
    <h2 style="margin-top:var(--s4)">Band heading</h2>
    <p style="margin-top:var(--s4);max-width:var(--measure)">Body copy on the band. The band
      redefines ink, rule and card so components need no band-specific variants.</p>
    <div class="cluster" style="margin-top:var(--s6)">
      <a class="btn" href="#top">Primary ${arw()}</a>
      <a class="btn btn--ghost" href="#top">Ghost ${arw()}</a>
      <span class="chip">Chip on band</span>
    </div>
    <div class="stats" style="margin-top:var(--s7)">
      <div class="stat"><span class="stat__num">42</span><p class="stat__label">A counted stat</p></div>
      <div class="stat"><span class="stat__num stat__num--word">A promise stat</span><p class="stat__label">Word variant, same baseline</p></div>
    </div>
  </div>
</section>

${faqBlock([
  { q: 'A FAQ item, closed by default', a: '<p>It is a native &lt;details&gt;, so it works with JavaScript off and is keyboard-operable for free.</p>' },
  { q: 'A second one', a: '<p>The plus sign becomes a minus when open.</p>' },
], 'FAQ component')}`,
};
