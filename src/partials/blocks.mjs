/* ============================================================
   Reusable section blocks, shared by the homepage and inner pages.
   ============================================================ */
import { SITE, PILLARS, SUBS, POSTS } from '../data.mjs';
import { icon, arw } from './icons.mjs';

export const esc = (s = '') => String(s)
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

const fmtDate = (iso) => new Date(iso + 'T00:00:00Z').toLocaleDateString('en-GB',
  { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC' });

/* --- Section heading: mono label on a rail, title + optional sub --- */
export const shead = ({ label, title, sub, tag = 'h2' }) => `
  <p class="u-mono">${label}</p>
  <${tag} class="shead__title">${title}</${tag}>
  ${sub ? `<p class="shead__sub u-lead">${sub}</p>` : ''}`;

/* ============================================================
   THE MACHINE DIAGRAM  (§9.3)
   Website -> Traffic -> CRM -> Customers, with the loop back.
   Drawn, not stock. Themed by CSS vars so it works on paper and on
   the dark bands. Labels are generic by construction — there is no
   client name or invented number anywhere in it.
   ============================================================ */
export const machineDiagram = ({ id = 'machine', draw = true } = {}) => `
<svg class="diagram${draw ? ' diagram--draw' : ''}" viewBox="0 0 760 336" role="img"
     aria-labelledby="${id}-t ${id}-d" data-machine>
  <title id="${id}-t">How the three services work as one machine</title>
  <desc id="${id}-d">Google and Maps, AI answers, and social each send people to your
    website. The website sends every enquiry into your CRM. The CRM turns enquiries into
    customers, and their reviews and referrals feed search and social again.</desc>
  <defs>
    <marker id="${id}-head" viewBox="0 0 10 10" refX="8" refY="5"
            markerWidth="6" markerHeight="6" orient="auto-start-reverse">
      <path d="M0 1.5 8.5 5 0 8.5" class="dg-flow" stroke-linejoin="round"/>
    </marker>
  </defs>

  <!-- sources -->
  <g class="dg-mut" font-family="var(--font-mono)" font-size="11" letter-spacing="1"
     text-anchor="end" style="text-transform:uppercase">
    <text x="150" y="66">Google &amp; Maps</text>
    <text x="150" y="164">AI answers</text>
    <text x="150" y="262">Instagram &amp; YouTube</text>
  </g>
  <g class="dg-mut" font-family="var(--font-text)" font-size="11" text-anchor="end" opacity=".75">
    <text x="150" y="82">local search, reviews</text>
    <text x="150" y="180">ChatGPT, Gemini, Overviews</text>
    <text x="150" y="278">reels, shorts, social search</text>
  </g>

  <!-- flow in -->
  <g class="dg-flow" marker-end="url(#${id}-head)" data-flow>
    <path d="M162 62 C196 62 196 158 214 158"/>
    <path d="M162 160 L214 160"/>
    <path d="M162 258 C196 258 196 162 214 162"/>
  </g>

  <!-- boxes -->
  <g>
    <rect class="dg-box" x="222" y="112" width="152" height="96" rx="4"/>
    <text class="dg-ink" x="242" y="150" font-family="var(--font-display)" font-size="19" font-weight="500">Your website</text>
    <text class="dg-mut" x="242" y="172" font-family="var(--font-text)" font-size="12">Fast, indexed,</text>
    <text class="dg-mut" x="242" y="188" font-family="var(--font-text)" font-size="12">built to convert</text>

    <rect class="dg-box" x="432" y="112" width="152" height="96" rx="4"/>
    <text class="dg-ink" x="452" y="150" font-family="var(--font-display)" font-size="19" font-weight="500">Your CRM</text>
    <text class="dg-mut" x="452" y="172" font-family="var(--font-text)" font-size="12">Every enquiry caught,</text>
    <text class="dg-mut" x="452" y="188" font-family="var(--font-text)" font-size="12">followed up, measured</text>

    <rect class="dg-wash" x="642" y="112" width="106" height="96" rx="4"/>
    <text class="dg-ink" x="662" y="150" font-family="var(--font-display)" font-size="19" font-weight="500">Customers</text>
    <text class="dg-mut" x="662" y="172" font-family="var(--font-text)" font-size="12">and repeat</text>
    <text class="dg-mut" x="662" y="188" font-family="var(--font-text)" font-size="12">business</text>
  </g>

  <!-- flow across -->
  <g class="dg-flow" marker-end="url(#${id}-head)" data-flow>
    <path d="M382 160 L424 160"/>
    <path d="M592 160 L634 160"/>
  </g>

  <!-- the loop back: reviews and referrals feed search and social again -->
  <g class="dg-flow" marker-end="url(#${id}-head)" stroke-dasharray="4 5" opacity=".8" data-flow>
    <path d="M695 216 C695 300 660 312 420 312 C240 312 120 312 120 292 L120 274"/>
  </g>
  <text class="dg-mut" x="420" y="330" font-family="var(--font-mono)" font-size="10"
        letter-spacing="1" text-anchor="middle">REVIEWS &amp; REFERRALS FEED IT AGAIN</text>
</svg>`;

/* ============================================================
   07 · Three pillars
   ============================================================ */
export const pillarsBlock = ({ pinned = true } = {}) => `
<section class="section section--ruled" id="services">
  <div class="shell">
    <div class="railed railed--wide">
      <div class="rail${pinned ? ' rail--sticky' : ''}">
        ${shead({
          label: 'What we do',
          title: 'Three services. One team that argues internally so you don’t have to.',
        })}
      </div>
      <div class="pillars" data-pillars>
        ${PILLARS.map(p => `
        <article class="pillar">
          <div>
            <p class="u-mono pillar__index">${p.index}</p>
            <h3 class="pillar__name">${p.name}</h3>
            <p class="pillar__one">${p.one}</p>
            <p class="pillar__cta"><a class="lnk" href="${p.href}">Explore ${p.short} ${arw()}</a></p>
          </div>
          <div class="pillar__subs">
            ${p.subs.map(k => `
            <a class="pillar__sub" href="${SUBS[k].href}">
              ${icon(k === 'crm' ? 'crm' : k, 'ico--sm')}
              <span class="pillar__sub-name">${SUBS[k].phrase}</span>
              ${arw()}
            </a>`).join('')}
          </div>
        </article>`).join('')}
      </div>
    </div>
  </div>
</section>`;

/* ============================================================
   09 · Process
   ============================================================ */
export const PROCESS = [
  { n: '01', name: 'Listen', body: 'We map how your business actually gets customers today — not how a template says it should. Then we tell you which of the three services you actually need, and which you don’t.' },
  { n: '02', name: 'Build',  body: 'Site, CRM and tracking, reviewed with you weekly. Technical SEO is in the first commit, not bolted on at the end. You approve the design before anyone writes code.' },
  { n: '03', name: 'Launch', body: 'SEO, Google Business Profile and socials go live on day one, not in “phase 2”. Search Console, analytics and the CRM are wired up before we call it done.' },
  { n: '04', name: 'Grow',   body: 'Monthly reporting written in plain English you can actually read, and a roadmap we own together. If a number goes down, we tell you before you ask.' },
];

export const processBlock = () => `
<section class="section section--ruled process" id="process" data-process>
  <div class="shell">
    <div class="railed railed--wide">
      <div class="rail rail--sticky">
        ${shead({
          label: 'How we work',
          title: 'Four steps. You always know which one you’re in.',
        })}
      </div>
      <div class="process__rail">
        <div class="process__track" aria-hidden="true"><span class="process__fill" data-fill></span></div>
        <ol class="process__steps">
          ${PROCESS.map(s => `
          <li class="pstep" data-step>
            <span class="pstep__num" aria-hidden="true">${s.n}</span>
            <h3 class="pstep__name"><span class="visually-hidden">Step ${s.n}: </span>${s.name}</h3>
            <p class="pstep__body">${s.body}</p>
          </li>`).join('')}
        </ol>
      </div>
    </div>
  </div>
</section>`;

/* ============================================================
   10 · Objection cards
   ============================================================ */
export const OBJECTIONS = [
  { q: 'One team for dev AND marketing — really?',
    a: 'That’s the point. Your developer and your SEO argue inside our office, not across two vendors’ contracts.' },
  { q: 'What will it cost?',
    a: 'Fixed quotes, written scope, no surprise invoices. Transparent pricing has been our policy since day one.' },
  { q: 'How long will it take?',
    a: (w) => `A typical business site: ${w} from kickoff to launch. You’ll have the schedule before you pay anything.` },
  { q: 'Who owns everything after?',
    a: 'You do. Domain, code, content, ad accounts, CRM data — all in your name from the start.' },
  { q: 'What happens after launch?',
    a: 'A support plan with real response times — within 1 business day — plus monitoring, backups and monthly improvements.' },
  { q: 'Can you work with what we already have?',
    a: 'Yes. We audit before we propose; we rebuild only what’s actually broken.' },
];

export const objectionsBlock = (weeks, pick = null) => {
  const list = pick ? OBJECTIONS.filter(o => pick.includes(o.q)) : OBJECTIONS;
  return `
<section class="section section--ruled" id="questions">
  <div class="shell">
    ${shead({
      label: 'Straight answers',
      title: 'The six things people ask us on the first call.',
    })}
    <div class="grid" style="--cols-sm:2;--cols-md:3;margin-top:var(--s8)">
      ${list.map(o => `
      <article class="card">
        <p class="objection__q">“${o.q}”</p>
        <p class="objection__a">${typeof o.a === 'function' ? o.a(weeks) : o.a}</p>
      </article>`).join('')}
    </div>
  </div>
</section>`;
};

/* ============================================================
   12 · Blog teasers
   ============================================================ */
export const postCard = (p) => `
<article class="card card--link post-card">
  <p class="u-mono post-card__date">
    <time datetime="${p.date}">${fmtDate(p.date)}</time> · ${p.read} min read
  </p>
  <h3 class="post-card__title">
    <a class="card__stretch" href="/blog/${p.slug}/">${p.title}</a>
  </h3>
  <p class="post-card__dek">${p.dek}</p>
</article>`;

export const blogBlock = (limit = 3) => `
<section class="section section--ruled" id="writing">
  <div class="shell">
    <div class="railed railed--wide">
      <div class="rail">
        ${shead({
          label: 'Writing',
          title: 'We sell SEO, so we publish.',
          sub: 'An agency that sells search with nothing to read is asking you to take its word for it.',
        })}
        <p style="margin-top:var(--s6)"><a class="lnk" href="/blog/">All posts ${arw()}</a></p>
      </div>
      <div class="grid" style="--cols-sm:2;--cols-md:3">
        <!--BLOG_TEASERS_START-->
        ${POSTS.slice(0, limit).map(postCard).join('')}
        <!--BLOG_TEASERS_END-->
      </div>
    </div>
  </div>
</section>`;

/* ============================================================
   FAQ  (schema-backed; <details> so it works with JS off)
   ============================================================ */
export const faqBlock = (faqs, title = 'Questions we get asked') => `
<section class="section section--ruled" id="faq">
  <div class="shell">
    <div class="railed railed--wide">
      <div class="rail">${shead({ label: 'FAQ', title })}</div>
      <div class="faq">
        ${faqs.map(f => `
        <details class="faq__item">
          <summary>${f.q}<span class="faq__sign" aria-hidden="true"></span></summary>
          <div class="faq__body">${f.a}</div>
        </details>`).join('')}
      </div>
    </div>
  </div>
</section>`;

/* --- Breadcrumbs ---------------------------------------------------- */
export const crumbs = (trail) => `
<nav aria-label="Breadcrumb">
  <ol class="crumbs u-mono">
    ${trail.map(t => `<li>${t.href ? `<a href="${t.href}">${t.label}</a>`
                                   : `<span aria-current="page">${t.label}</span>`}</li>`).join('')}
  </ol>
</nav>`;

/* --- Cross-sell: how this pillar feeds the other two ----------------- */
export const crossSell = (currentKey) => {
  const others = PILLARS.filter(p => p.key !== currentKey);
  const why = {
    web:    'A site that loads fast and is structured properly is the thing SEO and social are pointing people at. Without it, both are paying to send traffic somewhere that leaks.',
    seo:    'Search is the cheapest lead you will ever get, because the person is already looking for you. It compounds — and it needs a fast site to land on.',
    social: 'Social is where people check whether you are real before they call. It also feeds brand searches on Google, which are the easiest searches to win.',
  };
  return `
<section class="section section--ruled">
  <div class="shell">
    ${shead({ label: 'The cross-sell, honestly',
              title: 'What this connects to — and why we’d mention it.' })}
    <div class="grid" style="--cols-sm:2;margin-top:var(--s8)">
      ${others.map(p => `
      <article class="card card--link">
        <p class="u-mono card__eyebrow">${p.index} · ${p.short}</p>
        <h3 class="card__title"><a class="card__stretch" href="${p.href}">${p.name}</a></h3>
        <p class="card__body">${why[p.key]}</p>
        <p class="card__foot"><span class="lnk">Explore ${p.short} ${arw()}</span></p>
      </article>`).join('')}
    </div>
  </div>
</section>`;
};

export { fmtDate };
