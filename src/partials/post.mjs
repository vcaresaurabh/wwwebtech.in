/* ============================================================
   Blog post shell. One layout, so every post carries the same
   header, schema, diagram slot and outbound service links.
   ============================================================ */
import { POSTS, SUBS } from '../data.mjs';
import { ctaBand } from './chrome.mjs';
import { crumbs, fmtDate, shead } from './blocks.mjs';
import { arw } from './icons.mjs';
import { DIAGRAMS } from './diagrams.mjs';
import * as S from '../schema.mjs';

/* `tocHtml` exists so build.mjs can emit a placeholder version of this page
   for the automation layer to fill in on the server. Left out, the contents
   list is derived from the body exactly as before. */
export const postPage = ({ slug, body, links, caption, tocHtml }) => {
  const p = POSTS.find(x => x.slug === slug);
  const url = '/blog/' + slug + '/';
  const trail = [{ label: 'Home', href: '/' }, { label: 'Blog', href: '/blog/' }, { label: p.title }];
  const diagram = DIAGRAMS[slug];

  /* The diagram belongs at the point in the argument it illustrates, so a post
     places it with {{DIAGRAM}}. Without the marker it falls to the end. */
  const figure = diagram ? `<figure>
    <div style="overflow-x:auto"><div style="min-width:600px">${diagram(slug)}</div></div>
    <figcaption>${caption}</figcaption>
  </figure>` : '';
  const withDiagram = body.includes('{{DIAGRAM}}')
    ? body.replace('{{DIAGRAM}}', figure)
    : body + figure;

  return {
    path: url, nav: 'blog', og: 'blog-' + slug, ogType: 'article',
    lastmod: p.date,
    title: p.title.length > 58 ? p.title.slice(0, 57) + '…' : p.title,
    desc: p.dek.length > 155 ? p.dek.slice(0, 152) + '…' : p.dek,
    schema: S.graph([
      S.organization(),
      S.article(p),
      S.breadcrumbs(trail),
    ]),
    render: () => `
<article>
  <header class="phero">
    <div class="shell">
      ${crumbs(trail)}
      <p class="u-mono" style="margin-top:var(--s5)">
        <time datetime="${p.date}">${fmtDate(p.date)}</time> · ${p.read} min read · Wwwebtech Team
      </p>
      <h1 data-split>${p.title}</h1>
      <p class="phero__sub u-lead">${p.dek}</p>
    </div>
  </header>

  <div class="section">
    <div class="shell">
      <div class="railed railed--wide">
        <div class="rail rail--sticky">
          <p class="u-mono">In this piece</p>
          <nav aria-label="On this page" style="margin-top:var(--s4)">
            <ul class="stack" style="--flow:var(--s3)">
              ${tocHtml ?? [...body.matchAll(/<h2 id="([^"]+)">(.*?)<\/h2>/g)]
                .map(m => `<li><a class="u-small" href="#${m[1]}">${m[2].replace(/<[^>]+>/g, '')}</a></li>`).join('')}
            </ul>
          </nav>
        </div>
        <div class="prose">
          ${withDiagram}
        </div>
      </div>
    </div>
  </div>

  <section class="section section--ruled">
    <div class="shell">
      ${shead({ label: 'If this is your problem', title: 'What we’d actually do about it.' })}
      <div class="grid" style="--cols-sm:2;--cols-md:${links.length};margin-top:var(--s8)">
        ${links.map(k => `
        <article class="card card--link">
          <p class="u-mono card__eyebrow">Service</p>
          <h3 class="card__title" style="font-size:var(--h4)">
            <a class="card__stretch" href="${SUBS[k] ? SUBS[k].href : k.href}">${SUBS[k] ? SUBS[k].phrase : k.label}</a>
          </h3>
        </article>`).join('')}
      </div>
      <p style="margin-top:var(--s7)"><a class="lnk" href="/blog/">All posts ${arw()}</a></p>
    </div>
  </section>
</article>
${ctaBand({
  h2: 'Want us to look at yours?',
  sub: 'Send the URL and what you think is wrong. We’ll tell you what we see, whether or not you hire us. Reply within 1 business day.',
})}`,
  };
};
