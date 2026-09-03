/* /services/ — the pillar overview */
import { PILLARS } from '../data.mjs';
import { ctaBand } from '../partials/chrome.mjs';
import { pillarsBlock, processBlock, machineDiagram, objectionsBlock, faqBlock, shead } from '../partials/blocks.mjs';
import { phero } from '../partials/pagekit.mjs';
import { cardRow } from '../partials/pagekit.mjs';
import { weeksPhrase } from '../proof.mjs';
import * as S from '../schema.mjs';

const FAQS = [
  { q: 'Do we have to buy all three?',
    a: `<p>No, and most clients don't start that way. Plenty come for a website and never buy
        marketing; plenty come for SEO on a site we didn't build. The reason we offer all three is
        that when you do want them together, you get one team and one contract instead of two
        vendors blaming each other. Buy the part you need.</p>` },
  { q: 'Which one should we start with?',
    a: `<p>Usually whichever is currently leaking. If people find you and don't enquire, that's the
        site. If nobody finds you, that's search. If you're invisible to people who've never heard
        of you, that's social. Tell us what's happening and we'll tell you where to start — including
        if the answer is "nothing yet, fix your pricing page".</p>` },
  { q: 'Do you work outside Delhi?',
    a: `<p>Yes — we're based in East Delhi and work with businesses across India. Almost all of the
        work happens over calls and shared screens regardless of city. For local SEO we'll want to
        understand your actual service area properly, wherever that is.</p>` },
  { q: 'What size of business do you usually work with?',
    a: `<p>Growing businesses that have outgrown a template but don't have an in-house technical
        team — typically somewhere between a founder doing everything and a company with its own IT
        department. If you're bigger than that we're probably not the right fit, and we'll say so
        rather than stretch.</p>` },
  { q: 'How do we start?',
    a: `<p>Send us two lines about what isn't working. We reply within one business day, usually with
        questions. If it looks like a fit we'll have a proper call, then send a written scope and a
        fixed quote. You don't pay anything to get to that point.</p>` },
];

const title = 'Services: Web Development, SEO & Social Media | Wwwebtech';
const desc  = 'Web development and CRM, SEO and AI visibility, social media marketing. Three services, one accountable team in East Delhi, serving all of India.';

export const page = {
  path: '/services/', nav: 'services', og: 'services', title, desc,
  schema: S.graph([
    S.organization(),
    S.webpage({ url: '/services/', title, desc }),
    ...PILLARS.map(p => S.service({ name: p.name, description: p.one, url: p.href, subs: p.subs })),
    S.faqPage(FAQS),
    S.breadcrumbs([{ label: 'Home', href: '/' }, { label: 'Services' }]),
  ]),
  render: () => [
    phero({
      trail: [{ label: 'Home', href: '/' }, { label: 'Services' }],
      eyebrow: 'Services',
      h1: 'Three services that only really make sense together',
      sub: `You can buy any one of them on its own, and plenty of clients do. But the reason we
            offer all three is simple: the website, the traffic and the follow-up are one system,
            and splitting them across vendors is how the gaps appear.`,
      secondary: { href: '#services', label: 'See the three' },
    }),

    `<section class="section section--ruled">
      <div class="shell">
        <div class="railed railed--wide">
          <div class="rail">${shead({ label: 'The whole thing', title: 'What we mean by “the machine”.' })}</div>
          <div class="prose">
            <p>Almost every business we meet has at least one of these three working and at least one
              missing. A beautiful site nobody finds. Good rankings sending traffic to a page that
              doesn’t convert. A busy Instagram feeding enquiries into a WhatsApp nobody answers on
              a Friday. Each of those is a gap between two vendors’ scopes.</p>
            <p>Here is the whole thing on one diagram. Where you sit on it is usually obvious within
              a minute of looking at it.</p>
          </div>
        </div>
        <div style="margin-top:var(--s9);overflow-x:auto">
          <div style="min-width:640px">${machineDiagram({ id: 'machine-services' })}</div>
        </div>
      </div>
    </section>`,

    pillarsBlock(),

    cardRow({
      label: 'Also',
      title: 'And the part that comes after launch.',
      cols: 2,
      items: [
        { icon: 'shield', t: 'Ongoing technical support', b: 'Monitoring, backups, updates and a reply within 1 business day. <a class="u-link" href="/services/technical-support/">See support plans</a>.' },
        { icon: 'clock', t: 'Audits before proposals', b: 'We look at what you already have before recommending anything. If the honest answer is that you don’t need us yet, that is what you’ll hear.' },
      ],
    }),

    processBlock(),
    objectionsBlock(weeksPhrase),
    faqBlock(FAQS, 'Choosing a service'),
    ctaBand(),
  ].join('\n'),
};
