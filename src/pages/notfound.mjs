/* 404.html — must be served with a real 404 status. See README. */
import { PILLARS } from '../data.mjs';
import { arw } from '../partials/icons.mjs';
import * as S from '../schema.mjs';

const title = 'Page not found | Wwwebtech';
const desc  = 'That page does not exist. Here are the services, the writing, and a way to reach a person.';

export const page = {
  path: '/404.html', nav: null, og: 'home', title, desc, noindex: true,
  schema: S.graph([S.organization()]),
  render: () => `
<section class="section e404">
  <div class="shell">
    <p class="u-mono">Error 404</p>
    <h1 style="margin-top:var(--s5)">Lost? That’s a routing problem — our favourite kind.</h1>
    <p class="u-lead" style="margin-top:var(--s6);max-width:52ch">
      The page you asked for isn’t here. It may have moved when we rebuilt the site, or the link
      may have been wrong to begin with. Either way, here is everything that does exist.
    </p>

    <div class="grid" style="--cols-sm:3;margin-top:var(--s8)">
      ${PILLARS.map(p => `
      <article class="card card--link">
        <p class="u-mono card__eyebrow">${p.index}</p>
        <h2 class="card__title" style="font-size:var(--h4)">
          <a class="card__stretch" href="${p.href}">${p.name}</a></h2>
        <p class="card__body">${p.one}</p>
      </article>`).join('')}
    </div>

    <div class="cluster" style="margin-top:var(--s8);gap:var(--s4)">
      <a class="btn" href="/">Back to the homepage ${arw()}</a>
      <a class="btn btn--ghost" href="/blog/">Read something instead ${arw()}</a>
      <a class="btn btn--ghost" href="/contact/">Tell a person ${arw()}</a>
    </div>

    <p class="u-small u-ink3" style="margin-top:var(--s7);max-width:52ch">
      If you followed a link from somewhere on this site, that’s our bug — please
      <a class="u-link" href="/contact/">tell us</a> and we’ll fix it today.
    </p>
  </div>
</section>`,
};
