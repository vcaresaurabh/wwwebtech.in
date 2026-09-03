/* /about/ */
import { SITE } from '../data.mjs';
import { ctaBand } from '../partials/chrome.mjs';
import { shead, faqBlock } from '../partials/blocks.mjs';
import { phero, cardRow } from '../partials/pagekit.mjs';
import { FOUNDED, PROJECTS, CREDENTIALS } from '../proof.mjs';
import { arw } from '../partials/icons.mjs';
import * as S from '../schema.mjs';

/* The four "Approach" values, carried over verbatim from the existing site.
   They were the best thing on it and they are still true. (§7.4) */
const APPROACH = [
  { icon: 'organic', t: 'Understand the business before proposing technology',
    b: 'The first meeting is about how you make money and where that currently breaks. Nobody can scope a useful system from a feature list.' },
  { icon: 'websites', t: 'Build simple systems that scale',
    b: 'The least clever solution that solves the problem, every time. Clever is fun to write and expensive for the next person to inherit.' },
  { icon: 'shield', t: 'Prioritise clarity, security and performance',
    b: 'These are not finishing touches. They are the difference between a system that lasts three years and one that gets rebuilt in eighteen months.' },
  { icon: 'clock', t: 'Act as a long-term technology partner',
    b: 'We would rather have twenty clients for five years than a hundred for one. That shapes what we recommend, including when we recommend nothing.' },
];

const FAQS = [
  { q: 'Who will we actually be talking to?',
    a: `<p>The people doing the work. There is no account manager relaying your question to a
        developer and the answer back to you — that game of telephone is where requirements go to
        die. If you ask a technical question you get a technical answer, from whoever wrote it.</p>` },
  { q: 'How big is the team?',
    a: `<p>Small and senior, in East Delhi. That's a deliberate choice: it means the person who
        scoped your project is the person building it, and it means we can only take on so much at
        once. If we're full, we'll tell you rather than start late.</p>` },
  { q: 'Why should we trust a young brand?',
    a: `<p>You shouldn't, on the strength of a website. What you can do is check the things that are
        checkable: everything is registered in your name, the scope and price are fixed in writing
        before you pay, and there's a reply within one business day. Ask us to walk you through
        recent work on a call — that's real, and it beats a testimonial we wrote ourselves.</p>` },
];

const title = 'About Wwwebtech — a technology partner in East Delhi';
const desc  = 'A small senior team in East Delhi building websites, CRM systems and search visibility for Indian businesses. Long-term reliability, not quick delivery.';

export const page = {
  path: '/about/', nav: 'about', og: 'about', title, desc,
  schema: S.graph([
    S.organization(),
    S.webpage({ url: '/about/', title, desc }),
    S.faqPage(FAQS),
    S.breadcrumbs([{ label: 'Home', href: '/' }, { label: 'About' }]),
  ]),
  render: () => [
    phero({
      trail: [{ label: 'Home', href: '/' }, { label: 'About' }],
      eyebrow: 'About',
      h1: 'A practical technology partner, not just a vendor',
      sub: `Wwwebtech is a technology consulting and development firm focused on building reliable,
            scalable digital systems for growing businesses. We work with startups, small businesses
            and teams that need technology they can depend on — not over-engineered products or
            buzzword-driven solutions.`,
      secondary: { href: '/work/', label: 'See how we work' },
    }),

    `<section class="section section--ruled">
      <div class="shell">
        <div class="railed railed--wide">
          <div class="rail">${shead({ label: 'What we do', title: 'Systems built around real workflows.' })}</div>
          <div class="prose">
            <p>Our work spans web platforms, CRM systems, business automation and ongoing technical
              support — and, since these are now the same job, search and social visibility as well.
              Every solution we build is designed around real workflows, operational constraints and
              long-term maintainability.</p>
            <p>Based in India, we understand local business realities such as cost efficiency,
              scalability and compliance, while delivering systems that meet modern global standards.
              In practice that means we think about what a page weighs on a mid-range Android phone,
              about GST on an invoice, about whether the person answering WhatsApp on a Saturday can
              see the same customer record as the person who took the call on Thursday.</p>
            <p>We are focused on long-term reliability, not short-term delivery. It is a slower way
              to grow an agency and a better way to keep clients.</p>
          </div>
        </div>
      </div>
    </section>`,

    cardRow({ label: 'Our approach', title: 'Four rules we actually apply.', cols: 2, items: APPROACH }),

    `<section class="section section--ruled" id="team">
      <div class="shell">
        <div class="railed railed--wide">
          <div class="rail">${shead({ label: 'The team', title: 'Who you talk to.' })}</div>
          <div class="prose">
            <!-- [V:11] Replace this block with real names, roles and photos when the
                 owner supplies them. Until then it says something true rather than
                 inventing a leadership page. -->
            <p>A small senior team in East Delhi — no account-manager relay race; you talk to the
              people doing the work. The person who scopes your project is the person who builds it,
              and the person who answers when something breaks at 9pm is someone who knows why it
              was built that way.</p>
            <p>That is a constraint as much as a promise: it means we take on fewer projects at once,
              and it means we will tell you if we are full rather than start you late.</p>
            ${FOUNDED ? `<p>Wwwebtech has been doing this since ${FOUNDED}${PROJECTS ? `, across ${PROJECTS} projects` : ''}.</p>` : ''}
          </div>
        </div>
      </div>
    </section>`,

    `<section class="section section--ruled" id="where">
      <div class="shell">
        <div class="railed railed--wide">
          <div class="rail">${shead({ label: 'Where we are', title: 'East Delhi, working across India.' })}</div>
          <div>
            <div class="prose">
              <p>We are based in ${SITE.locality}, ${SITE.region}. Most of the work happens over calls
                and shared screens, so where a client is matters far less than it used to — we work
                with businesses across India. Being in Delhi does matter for one thing: when a local
                business needs someone to actually understand its service area, its competitors and
                the way its customers search, we are not guessing from another time zone.</p>
            </div>
            <div class="cluster" style="margin-top:var(--s7)">
              ${CREDENTIALS.map(c => `<span class="chip">${c}</span>`).join('')}
              <span class="chip">${SITE.locality}, ${SITE.region}</span>
              <span class="chip">Serving all of India</span>
            </div>
            <address class="prose" style="margin-top:var(--s6);font-style:normal">
              <p>
                <a class="u-link" href="mailto:${SITE.email}">${SITE.email}</a><br>
                <a class="u-link" href="tel:${SITE.phoneE164}">${SITE.phoneNbsp}</a><br>
                <a class="u-link" href="${SITE.whatsapp}" rel="noopener">WhatsApp</a> ·
                <a class="u-link" href="${SITE.linkedin}" rel="noopener">LinkedIn</a> ·
                <a class="u-link" href="${SITE.instagram}" rel="noopener">Instagram</a>
              </p>
            </address>
            <p style="margin-top:var(--s6)"><a class="lnk" href="/contact/">Start a conversation ${arw()}</a></p>
          </div>
        </div>
      </div>
    </section>`,

    faqBlock(FAQS, 'About Wwwebtech'),
    ctaBand({
      h2: 'Want to talk to the person who’d build it?',
      sub: 'That is who replies. Two lines about what’s not working is enough to start.',
    }),
  ].join('\n'),
};
