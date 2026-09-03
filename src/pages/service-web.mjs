/* PILLAR 1 — Web Development & Systems */
import { PILLARS, SUBS } from '../data.mjs';
import { ctaBand } from '../partials/chrome.mjs';
import { machineDiagram, faqBlock, objectionsBlock, crossSell, shead } from '../partials/blocks.mjs';
import { phero, includedBlock, relatedBlock, cardRow } from '../partials/pagekit.mjs';
import { weeksPhrase } from '../proof.mjs';
import * as S from '../schema.mjs';

const P = PILLARS.find(p => p.key === 'web');

const INCLUDED = [
  {
    id: 'websites', icon: 'websites', name: 'Custom websites & web platforms',
    body: `We build sites in code, not in a page builder. That means the HTML your customer
      downloads is the HTML we wrote — no forty plugins, no theme you can't update, no
      mystery scripts slowing down the first paint. The result is a site that loads fast on a
      patchy 4G connection in a lift, which is where a lot of Indian browsing actually happens.`,
    points: [
      'Hand-built pages, so page weight stays under control instead of creeping up with every plugin.',
      'A content setup that matches how your team actually edits — not a CMS nobody logs into.',
      'Accessibility and keyboard support built in, because they are also what search engines read.',
      'Everything handed over in your name: domain, repository, hosting, analytics.',
    ],
  },
  {
    id: 'ecommerce', icon: 'ecommerce', name: 'eCommerce development',
    body: `Selling online in India means UPI, COD, GST invoices, pin-code serviceability and
      returns — none of which a template from another market handles well. We build stores
      around those realities, and we make the catalogue structure something Google can read,
      so your product pages have a chance of ranking rather than living behind a search box.`,
    points: [
      'Payments that suit Indian buyers: UPI, cards, netbanking, and cash on delivery where it makes sense.',
      'GST-correct invoicing and tax handling agreed with your accountant before launch.',
      'Product schema and clean category URLs, so listings can appear in search and in shopping surfaces.',
      'A checkout we load-test before launch, not after your first campaign.',
    ],
  },
  {
    id: 'crm-link', icon: 'crm', name: 'Custom CRM systems',
    href: SUBS.crm.href, hrefLabel: 'CRM systems in detail',
    body: `Most small businesses lose leads in the gap between the website and the follow-up —
      an enquiry lands in a shared inbox, someone means to call back, and a week passes. A CRM
      that matches how your team actually sells closes that gap. We build them to fit your
      pipeline rather than making you fit a product's.`,
    points: [
      'Every website enquiry, WhatsApp message and call logged against one contact record.',
      'Stages named after your sales process, not a generic template.',
      'Reminders and ownership, so no lead sits unclaimed.',
      'Reporting you can actually read: where leads come from, and which ones turn into money.',
    ],
  },
  {
    id: 'automation-link', icon: 'automation', name: 'Business automation & integrations',
    href: SUBS.automation.href, hrefLabel: 'Automation in detail',
    body: `The work that quietly eats your team's week is usually copying information from one
      place to another. We connect the tools you already pay for, so the copying stops. This is
      the least glamorous thing we do and often the fastest to pay for itself.`,
    points: [
      'Website, CRM, WhatsApp, email and accounting talking to each other instead of to a spreadsheet.',
      'Internal dashboards that answer the question you keep asking someone to check.',
      'Automated follow-ups that still sound like a person wrote them.',
    ],
  },
];

const FAQS = [
  { q: 'Do we own the website when it’s finished?',
    a: `<p>Yes, completely. The domain, the code repository, the hosting account, the content and
        the analytics are all registered in your name from day one — not ours. If you ever want to
        move to another agency, you send them a login, not a request.</p>` },
  { q: 'Can you work with the website we already have?',
    a: `<p>Usually, yes. We audit first and tell you honestly whether it’s worth fixing or replacing.
        A site with good content and bad performance is often worth keeping; a site built on an
        abandoned theme with thirty plugins usually isn’t. Either way you get the reasoning, in
        writing, before you spend anything.</p>` },
  { q: 'How long does a business website take?',
    a: `<p>A typical business site runs ${weeksPhrase} from kickoff to launch, and the schedule is
        agreed in writing before you pay anything. What moves the date is almost always content —
        photography, product details, approvals — so we plan that in from week one rather than
        discovering it in week four.</p>` },
  { q: 'What does it cost?',
    a: `<p>Fixed quote against a written scope. You’ll know the number and what’s in it before we
        start, and if the scope changes we quote the change before doing it. We don’t send surprise
        invoices — transparent pricing has been the policy since day one.</p>` },
  { q: 'Do we need the CRM as well, or can we just have the site?',
    a: `<p>You can absolutely just have the site. We’ll tell you if we think the CRM would pay for
        itself, but the argument for it is only worth making once you have more enquiries than one
        person can comfortably remember. Plenty of clients start with the website and add the CRM
        a year later.</p>` },
];

const title = 'Web Development & CRM Systems in Delhi | Wwwebtech';
const desc  = 'Custom websites, eCommerce, CRM and automation for Indian businesses. Built in code, not page builders. You own the domain, code and accounts from day one.';

export const page = {
  path: P.href, nav: 'services', og: 'web', title, desc,
  schema: S.graph([
    S.organization(),
    S.webpage({ url: P.href, title, desc }),
    S.service({ name: P.name, description: P.one, url: P.href, subs: P.subs }),
    S.faqPage(FAQS),
    S.breadcrumbs([{ label: 'Home', href: '/' }, { label: 'Services', href: '/services/' }, { label: P.name }]),
  ]),
  render: () => [
    phero({
      trail: [{ label: 'Home', href: '/' }, { label: 'Services', href: '/services/' }, { label: 'Web development' }],
      eyebrow: '01 · Web Development & Systems',
      h1: 'Web development &amp; business systems in Delhi, for all of India',
      sub: `A fast website is the easy half. The hard half is the system behind it — where the
            enquiry goes, who follows it up, and whether anyone can tell you next month which
            channel actually paid. We build both, and hand both over in your name.`,
      secondary: { href: '#included', label: 'What’s included' },
    }),

    `<section class="section section--ruled">
      <div class="shell">
        <div class="railed railed--wide">
          <div class="rail">${shead({
            label: 'The argument',
            title: 'A website is a machine part, not a brochure.',
          })}</div>
          <div class="prose">
            <p>Most business websites in India are judged on how they look on the day they launch.
              That’s the wrong test. The right test is what happens over the following year: whether
              the site still loads in under two seconds on a mid-range Android phone, whether Google
              can read it, whether the enquiries land somewhere a human will see them, and whether
              you can change a price without calling a developer.</p>
            <p>We build for that year, not for the launch screenshot. In practice that means writing
              the code ourselves so we know what’s in it, keeping the page weight small enough to
              survive a weak signal, and wiring the site into the system that catches what it
              produces. It’s less exciting than a homepage animation. It’s also the difference
              between a site that earns and a site that sits there.</p>
          </div>
        </div>
        <div style="margin-top:var(--s9);overflow-x:auto">
          <div style="min-width:640px">${machineDiagram({ id: 'machine-web' })}</div>
        </div>
      </div>
    </section>`,

    includedBlock({ title: 'Four things, and how they fit together.', items: INCLUDED }),

    cardRow({
      label: 'How we build',
      title: 'What’s different about the way we do it.',
      cols: 3,
      items: [
        { icon: 'techseo', t: 'Performance is a requirement, not a phase',
          b: 'Core Web Vitals are part of the acceptance criteria on every build. If a page misses the budget, it doesn’t ship — the same standard we sell as a service.' },
        { icon: 'shield', t: 'Boring, maintainable technology',
          b: 'We pick the least clever option that solves the problem. Clever code is fun to write and expensive to inherit, and someone will inherit it.' },
        { icon: 'clock', t: 'Weekly, on the real thing',
          b: 'You review the actual site every week on a real URL, not a PDF of a design. Surprises get found in week two, when they’re cheap.' },
      ],
    }),

    crossSell('web'),
    objectionsBlock(weeksPhrase, [
      'What will it cost?',
      'How long will it take?',
      'Who owns everything after?',
    ]),
    faqBlock(FAQS, 'Web development questions'),
    relatedBlock({ pillarKey: 'seo', siblings: ['crm', 'automation'], post: 'website-speed-india',
                   note: 'Read next, or jump straight to the detail.' }),
    ctaBand({
      h2: 'Tell us what’s not working.',
      sub: 'Slow site, leads going missing, a store that won’t convert — describe it in two lines. A real person replies within 1 business day.',
    }),
  ].join('\n'),
};
