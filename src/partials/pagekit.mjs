/* ============================================================
   Building blocks shared by the service, pillar and content pages.
   ============================================================ */
import { SITE, PILLARS, SUBS, POSTS } from '../data.mjs';
import { icon, arw } from './icons.mjs';
import { crumbs, shead } from './blocks.mjs';

/* --- Inner-page hero ------------------------------------------------ */
export const phero = ({ eyebrow, h1, sub, trail, ctas = true, secondary }) => `
<section class="phero">
  <div class="shell">
    ${trail ? crumbs(trail) : ''}
    ${eyebrow ? `<p class="u-mono" style="margin-top:var(--s5)">${eyebrow}</p>` : ''}
    <h1 data-split>${h1}</h1>
    ${sub ? `<p class="phero__sub u-lead">${sub}</p>` : ''}
    ${ctas ? `<div class="phero__cta">
      <a class="btn btn--lg" href="/contact/" data-magnetic>Get a proposal ${arw()}</a>
      ${secondary ? `<a class="btn btn--ghost btn--lg" href="${secondary.href}">${secondary.label} ${arw()}</a>` : ''}
    </div>` : ''}
  </div>
</section>`;

/* --- "What's included": the four sub-services, expanded -------------- */
export const includedBlock = ({ label = 'What’s included', title, items }) => `
<section class="section section--ruled" id="included">
  <div class="shell">
    <div class="railed railed--wide">
      <div class="rail rail--sticky">${shead({ label, title })}</div>
      <div class="stack" style="--flow:0">
        ${items.map((it, i) => `
        <article id="${it.id}" class="pillar" style="${i === 0 ? 'border-top:0;padding-top:0' : ''}">
          <div>
            <p class="u-mono pillar__index">${icon(it.icon, 'ico--accent')}</p>
            <h2 class="pillar__name" style="font-size:clamp(1.5rem,2.6vw,2rem)">${it.name}</h2>
            ${it.href ? `<p class="pillar__cta"><a class="lnk" href="${it.href}">${it.hrefLabel || 'Read more'} ${arw()}</a></p>` : ''}
          </div>
          <div class="prose" style="max-width:none">
            <p>${it.body}</p>
            ${it.points ? `<ul>${it.points.map(p => `<li>${p}</li>`).join('')}</ul>` : ''}
          </div>
        </article>`).join('')}
      </div>
    </div>
  </div>
</section>`;

/* --- Related links. Every service page links its pillar, two siblings,
       one post and contact — the internal-linking rule in §10. -------- */
export const relatedBlock = ({ pillarKey, siblings = [], post, note }) => {
  const pillar = PILLARS.find(p => p.key === pillarKey);
  const p = POSTS.find(x => x.slug === post);
  return `
<section class="section section--ruled">
  <div class="shell">
    ${shead({ label: 'Where to next', title: note || 'Related reading and services.' })}
    <div class="grid" style="--cols-sm:2;--cols-md:4;margin-top:var(--s8)">
      <article class="card card--link">
        <p class="u-mono card__eyebrow">Pillar</p>
        <h3 class="card__title" style="font-size:var(--h4)">
          <a class="card__stretch" href="${pillar.href}">${pillar.name}</a></h3>
        <p class="card__body">${pillar.one}</p>
      </article>
      ${siblings.map(k => `
      <article class="card card--link">
        <p class="u-mono card__eyebrow">Service</p>
        <h3 class="card__title" style="font-size:var(--h4)">
          <a class="card__stretch" href="${SUBS[k].href}">${SUBS[k].phrase}</a></h3>
      </article>`).join('')}
      ${p ? `
      <article class="card card--link">
        <p class="u-mono card__eyebrow">Reading</p>
        <h3 class="card__title" style="font-size:var(--h4)">
          <a class="card__stretch" href="/blog/${p.slug}/">${p.title}</a></h3>
        <p class="card__body">${p.read} min read</p>
      </article>` : ''}
    </div>
  </div>
</section>`;
};

/* --- A short, generic prose section with an optional rail ------------ */
export const proseSection = ({ label, title, html, id, sticky = true }) => `
<section class="section section--ruled"${id ? ` id="${id}"` : ''}>
  <div class="shell">
    <div class="railed railed--wide">
      <div class="rail${sticky ? ' rail--sticky' : ''}">${shead({ label, title })}</div>
      <div class="prose">${html}</div>
    </div>
  </div>
</section>`;

/* --- A row of simple numbered/labelled cards ------------------------- */
export const cardRow = ({ label, title, sub, items, cols = 3, id }) => `
<section class="section section--ruled"${id ? ` id="${id}"` : ''}>
  <div class="shell">
    ${shead({ label, title, sub })}
    <div class="grid" style="--cols-sm:2;--cols-md:${cols};margin-top:var(--s8)">
      ${items.map(it => `
      <article class="card">
        ${it.icon ? `<p class="card__eyebrow">${icon(it.icon, 'ico--accent')}</p>`
                  : `<p class="u-mono card__eyebrow">${it.k || ''}</p>`}
        <h3 class="card__title" style="font-size:var(--h4)">${it.t}</h3>
        <p class="card__body">${it.b}</p>
      </article>`).join('')}
    </div>
  </div>
</section>`;
