/* PILLAR 2 — SEO & AI Visibility */
import { PILLARS, SUBS } from '../data.mjs';
import { ctaBand } from '../partials/chrome.mjs';
import { faqBlock, objectionsBlock, crossSell, shead } from '../partials/blocks.mjs';
import { phero, includedBlock, relatedBlock, cardRow } from '../partials/pagekit.mjs';
import { weeksPhrase } from '../proof.mjs';
import * as S from '../schema.mjs';

const P = PILLARS.find(p => p.key === 'seo');

const INCLUDED = [
  {
    id: 'technical', icon: 'techseo', name: 'Technical SEO & Core Web Vitals',
    body: `Before content strategy, before keywords, the site has to be readable and fast. In
      India that second word matters more than most agencies admit: a large share of your
      visitors are on a mid-range Android phone on a congested network, and Google measures
      what they experience, not what your laptop does.`,
    points: [
      'A full crawl and audit: what Google can reach, what it is ignoring, and what is duplicated.',
      'Core Web Vitals worked on until the field data moves, not just the lab score.',
      'Clean URLs, correct canonicals, working redirects, an honest sitemap and robots file.',
      'Structured data that describes what the page actually is, so it can qualify for rich results.',
    ],
  },
  {
    id: 'content', icon: 'content', name: 'Content & on-page SEO',
    body: `We start from the questions your customers actually type and ask on calls, not from a
      keyword tool's idea of volume. Then we work out which of those questions deserve a page,
      which belong on a page you already have, and which are not worth the effort at all —
      that last list saves more money than the first two make.`,
    points: [
      'Keyword and intent mapping tied to your real services and service areas.',
      'Page-level briefs: what the page must answer, and what it must link to.',
      'Rewrites of the pages you already rank for but convert badly — usually the fastest win.',
      'Internal linking that gives your important pages somewhere to inherit authority from.',
    ],
  },
  {
    id: 'local', icon: 'local', name: 'Local SEO & Google Business Profile',
    body: `For most Delhi businesses the map pack is worth more than the blue links. It is also
      the part of search most often left half-finished — a profile claimed three years ago, one
      photo, no categories, no review replies. Fixing it is unglamorous and frequently the
      single highest-return thing on the list.`,
    points: [
      'Profile claimed, verified, categorised properly, and filled out completely.',
      'Consistent name, address and phone number everywhere they appear online.',
      'A review process your team can actually keep up — asking, and replying.',
      'Local landing pages for the areas you genuinely serve, not fifty spun city pages.',
    ],
  },
  {
    id: 'geo-link', icon: 'geo', name: 'GEO & AEO — visibility in AI search',
    href: SUBS.geo.href, hrefLabel: 'AI visibility in detail',
    body: `A growing share of buying research now happens inside ChatGPT, Gemini and Google's AI
      Overviews, where there is no page two and often only three or four businesses named. Being
      one of the named ones is a different job from ranking, and it is early enough that a
      well-organised small business can still win it.`,
    points: [
      'Making your entity unambiguous: who you are, where, and what you do.',
      'Content shaped so an answer engine can lift a clean, quotable claim from it.',
      'The third-party sources these systems lean on, tidied and made consistent.',
    ],
  },
];

const FAQS = [
  { q: 'How long before SEO does anything?',
    a: `<p>Technical fixes and Google Business Profile work can move things within weeks. Ranking
        for competitive commercial terms is a six-to-twelve month project, and anyone who promises
        you page one in thirty days is either talking about a term nobody searches or is about to
        do something you will have to undo later. We tell you which bucket each target falls into
        before we start.</p>` },
  { q: 'Do you guarantee rankings?',
    a: `<p>No, and you should be wary of anyone who does — nobody controls Google's index. What we
        commit to is the work: the audit, the fixes, the content, the reporting, and a monthly
        conversation about what moved and what didn't. If a number goes down, you hear it from us
        first.</p>` },
  { q: 'Is AI search actually worth worrying about yet?',
    a: `<p>It is worth an hour of your attention now rather than a panic later. The practical answer
        is that most of the work that makes you visible in AI answers — clear structure, accurate
        business information, content that answers real questions — is the same work that helps
        you in ordinary search. You are not making a bet; you are doing SEO properly and getting
        the AI surface as a side effect.</p>` },
  { q: 'Can you do SEO on a website you didn’t build?',
    a: `<p>Yes, that's most of this work. We audit what you have, tell you what can be fixed in place
        and what is structurally in the way, and give you the cost of each path. Sometimes the honest
        answer is that the platform is the problem — we'll say so, and we'll say it before you've
        paid for six months of work that can't succeed.</p>` },
  { q: 'What do the monthly reports look like?',
    a: `<p>Two pages, in plain English: what we did, what changed, what we're doing next. Rankings
        and traffic are in there, but so is the number you actually care about — enquiries. If the
        CRM is ours too, we can tie the two together and show you which search terms turned into
        real business.</p>` },
];

const title = 'SEO & AI Search Visibility Agency in India | Wwwebtech';
const desc  = 'Technical SEO, Core Web Vitals, local SEO and GEO for Indian businesses. Rank on Google and get named in AI answers. No ranking guarantees, real reporting.';

export const page = {
  path: P.href, nav: 'services', og: 'seo', title, desc,
  schema: S.graph([
    S.organization(),
    S.webpage({ url: P.href, title, desc }),
    S.service({ name: P.name, description: P.one, url: P.href, subs: P.subs }),
    S.faqPage(FAQS),
    S.breadcrumbs([{ label: 'Home', href: '/' }, { label: 'Services', href: '/services/' }, { label: P.name }]),
  ]),
  render: () => [
    phero({
      trail: [{ label: 'Home', href: '/' }, { label: 'Services', href: '/services/' }, { label: 'SEO & AI visibility' }],
      eyebrow: '02 · SEO & AI Visibility',
      h1: 'SEO and AI search visibility for Indian businesses',
      sub: `Search stopped being one box a while ago. Your customers are looking in Google, in
            Maps, and increasingly inside an AI answer that names three businesses and stops.
            We work on all of it, and we tell you honestly which parts are worth your money.`,
      secondary: { href: '#included', label: 'What’s included' },
    }),

    `<section class="section section--ruled">
      <div class="shell">
        <div class="railed railed--wide">
          <div class="rail">${shead({
            label: 'The argument',
            title: 'Page one is getting smaller. Being the answer matters more.',
          })}</div>
          <div class="prose">
            <p>For twenty years SEO meant getting ten blue links to put you near the top. That page
              is now mostly other things: a map pack, a shopping row, People Also Ask, and an
              AI-written summary that answers the question outright and names a handful of sources.
              A ranking that used to earn a click may now earn a glance.</p>
            <p>That sounds like bad news, and for businesses doing nothing it is. But the same
              change cuts the other way for anyone willing to be genuinely clear about what they
              do. Answer engines reward unambiguous, well-structured, verifiable information — which
              is exactly what a small, honest business can produce and a bloated competitor
              usually can't. The work has not become impossible. It has become less about volume
              and more about being legible.</p>
            <p>So we do the boring foundations first: make the site fast, make it crawlable, make
              your business details identical everywhere. Then we write the pages that answer real
              questions, and we make sure the answer is easy to lift. That order matters — content
              on a site Google struggles to read is money you have set on fire.</p>
          </div>
        </div>
      </div>
    </section>`,

    includedBlock({ title: 'Four jobs, in the order they pay off.', items: INCLUDED }),

    cardRow({
      label: 'What you get monthly',
      title: 'What a retainer actually looks like.',
      cols: 3,
      items: [
        { icon: 'content', t: 'A written plan, before the month starts',
          b: 'You know what we intend to do and why. No invoice arrives describing work you first heard about on the invoice.' },
        { icon: 'techseo', t: 'Work you can see in the site',
          b: 'Pages published, speed fixed, schema added, listings corrected. Every item is something you could check yourself.' },
        { icon: 'clock', t: 'Two pages of reporting, in English',
          b: 'What moved, what didn’t, and what we’re doing about it. If a number went the wrong way, it’s in there.' },
      ],
    }),

    crossSell('seo'),
    objectionsBlock(weeksPhrase, [
      'What will it cost?',
      'Can you work with what we already have?',
      'What happens after launch?',
    ]),
    faqBlock(FAQS, 'SEO questions'),
    relatedBlock({ pillarKey: 'web', siblings: ['geo', 'socialseo'], post: 'chatgpt-ai-search-visibility',
                   note: 'Read next, or jump straight to the detail.' }),
    ctaBand({
      h2: 'Not showing up where you should?',
      sub: 'Tell us the searches you think you should be winning and we’ll tell you what’s actually in the way. A real person replies within 1 business day.',
    }),
  ].join('\n'),
};
