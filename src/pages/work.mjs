/* /work/ — case studies, behind the same proof flag as homepage section 08 */
import { ctaBand } from '../partials/chrome.mjs';
import { shead, processBlock } from '../partials/blocks.mjs';
import { phero, cardRow } from '../partials/pagekit.mjs';
import { PROOF, CASES } from '../proof.mjs';
import { icon, arw } from '../partials/icons.mjs';
import * as S from '../schema.mjs';

const ANATOMY = [
  { k: 'Week 1–2', t: 'Strategy, sitemap, and a design you approve before code',
    b: 'We map how you actually get customers, agree the pages that matter, and show you the design. Nothing gets built until you have said yes to it.' },
  { k: 'Week 3–5', t: 'Build, content, technical SEO baked in from the first commit',
    b: 'Speed, structured data and clean URLs are part of the build, not an upsell afterwards. You see progress weekly, on the real thing.' },
  { k: 'Launch + 90 days', t: 'Search Console, GBP, socials live, CRM catching leads',
    b: 'Everything is wired up and handed over in your name. Then we watch the numbers with you for a quarter and fix what the real world finds.' },
];

/* Full case template. Renders only when PROOF.work is on and CASES has data. */
const caseStudy = (c) => `
<section class="section section--ruled" id="${c.slug}">
  <div class="shell">
    <div class="railed railed--wide">
      <div class="rail rail--sticky">
        <p class="u-mono">${c.client}</p>
        <h2 class="shead__title">${c.result}</h2>
        <p class="u-mono" style="margin-top:var(--s5)">${c.service} · ${c.timeframe}</p>
      </div>
      <div class="prose">
        <h3>The challenge</h3><p>${c.challenge}</p>
        <h3>What we built</h3><p>${c.built}</p>
        <h3>The result</h3><p>${c.result}</p>
        ${c.stack?.length ? `<div class="cluster" style="margin-top:var(--s6)">
          ${c.stack.map(s => `<span class="chip">${s}</span>`).join('')}</div>` : ''}
      </div>
    </div>
  </div>
</section>`;

const title = 'Our work — how a Wwwebtech project actually runs';
const desc  = 'What the first ninety days with Wwwebtech look like, step by step. We would rather walk you through real recent work than publish numbers you cannot check.';

export const page = {
  path: '/work/', nav: 'work', og: 'work', title, desc,
  schema: S.graph([
    S.organization(),
    S.webpage({ url: '/work/', title, desc }),
    S.breadcrumbs([{ label: 'Home', href: '/' }, { label: 'Work' }]),
  ]),
  render: () => [
    phero({
      trail: [{ label: 'Home', href: '/' }, { label: 'Work' }],
      eyebrow: 'Work',
      h1: PROOF.work && CASES.length
        ? 'Work, measured in outcomes rather than screenshots'
        : 'We would rather show you than tell you',
      sub: PROOF.work && CASES.length
        ? 'Every number on this page came from the client’s own analytics or CRM, and is published with their permission.'
        : `We’re a young brand with shipped work we’re happy to walk you through on a call. What we
           won’t do is decorate this page with numbers you can’t check, or a logo wall of companies
           that never hired us. Here instead is exactly how a project runs.`,
      secondary: { href: '/services/', label: 'See the services' },
    }),

    ...(PROOF.work && CASES.length
      ? [
          `<section class="section section--ruled">
            <div class="shell">${shead({ label: 'Selected work', title: 'Recent projects.' })}
              <ul class="stack-cards" data-stack style="margin-top:var(--s8)">
                ${CASES.map(c => `<li><article class="card card--link workcard">
                  <div>
                    <p class="u-mono card__eyebrow">${c.client}</p>
                    <h2 class="workcard__result"><a class="card__stretch" href="#${c.slug}">${c.result}</a></h2>
                    <p class="workcard__meta u-mono">${c.service} · ${c.timeframe}</p>
                  </div>
                  <div class="workcard__art"><span class="workcard__arrow" data-magnetic>${icon('arrow-down','ico--sm')}</span></div>
                </article></li>`).join('')}
              </ul>
            </div>
          </section>`,
          ...CASES.map(caseStudy),
        ]
      : [
          `<!-- [V:6] Case studies live behind PROOF.work in site/assets/js/proof-config.js.
                Fill in CASES (client, service, timeframe, ONE measured number each, with
                permission) and flip the flag. This honest stand-in renders until then. -->`,
          cardRow({
            label: 'The anatomy of a project',
            title: 'What a launch with us looks like.',
            sub: 'Three phases, and what you can expect to see at the end of each one.',
            cols: 3, items: ANATOMY, id: 'anatomy',
          }),
          `<section class="section section--ruled">
            <div class="shell">
              <div class="railed railed--wide">
                <div class="rail">${shead({ label: 'Why this page looks like this', title: 'Proof we can’t show you yet.' })}</div>
                <div class="prose">
                  <p>Most agency work pages open with a grid of logos and a set of round numbers.
                    Ours doesn’t, because we can’t currently show you a case study with a number in it
                    that you could verify, and publishing one anyway would be the single most
                    dishonest thing on an otherwise honest website.</p>
                  <p>What we can do is put you on a call and walk you through recent work: what the
                    client came with, what we built, what happened afterwards, and what we would do
                    differently. You’ll learn considerably more from twenty minutes of that than from
                    a tile that says “+312% traffic”.</p>
                  <p>When clients give us permission to publish specifics, this page will fill up —
                    with their names on it, and numbers that came from their analytics rather than ours.</p>
                  <p><a class="lnk" href="/contact/">Ask us to walk you through recent work ${arw()}</a></p>
                </div>
              </div>
            </div>
          </section>`,
        ]),

    processBlock(),
    ctaBand({
      h2: 'Ask the awkward questions.',
      sub: 'What went wrong on a project, what we’d do differently, what we’re not good at. We’ll answer all three. Reply within 1 business day.',
    }),
  ].join('\n'),
};
