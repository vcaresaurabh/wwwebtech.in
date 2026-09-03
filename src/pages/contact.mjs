/* /contact/ */
import { SITE } from '../data.mjs';
import { contactForm } from '../partials/chrome.mjs';
import { shead, faqBlock } from '../partials/blocks.mjs';
import { phero } from '../partials/pagekit.mjs';
import { icon } from '../partials/icons.mjs';
import * as S from '../schema.mjs';

const FAQS = [
  { q: 'How fast will you reply?',
    a: `<p>Within one business day, every time — that's the whole commitment and we'd rather be
        judged on it than on anything else on this site. If you send something on a Sunday you'll
        hear back Monday.</p>` },
  { q: 'What should we include?',
    a: `<p>Two lines is genuinely enough: what you sell, and what isn't working. A link to your
        current site helps, and so does a rough budget range — not because we'll spend it all, but
        because it tells us immediately whether we should be proposing a rebuild or a repair.</p>` },
  { q: 'Do you charge for the first conversation?',
    a: `<p>No. The first call, the questions, and the written scope and quote that follow are free.
        You only pay once you've agreed a scope and a fixed price in writing.</p>` },
  { q: 'What if we’re not sure what we need?',
    a: `<p>That's most enquiries, and it's a fine place to start. Tick "Not sure" and describe the
        symptom rather than the cure. Working out which of the three services you actually need — and
        which you don't — is the first thing we do anyway.</p>` },
];

const title = 'Contact Wwwebtech — reply within 1 business day';
const desc  = 'Tell us what is not working. Web, CRM, SEO or social — a real person in East Delhi replies within 1 business day. No newsletter, no sales sequence.';

export const page = {
  path: '/contact/', nav: 'contact', og: 'contact', title, desc,
  schema: S.graph([
    S.organization(),
    S.webpage({ url: '/contact/', title, desc }),
    { '@type': 'ContactPage', '@id': SITE.origin + '/contact/#contactpage', url: SITE.origin + '/contact/' },
    S.faqPage(FAQS),
    S.breadcrumbs([{ label: 'Home', href: '/' }, { label: 'Contact' }]),
  ]),
  render: () => [
    phero({
      trail: [{ label: 'Home', href: '/' }, { label: 'Contact' }],
      eyebrow: 'Contact',
      h1: 'Tell us what’s not working',
      sub: `Site, leads, rankings, or all three — describe it in two lines. A real person replies
            within 1 business day. No newsletter, no five-email sales sequence, no call centre.`,
      ctas: false,
    }),

    `<section class="section">
      <div class="shell">
        <div class="ctaband__grid">
          <div>
            ${shead({ label: 'The details', title: 'Three ways to reach us.' })}
            <div class="stack" style="--flow:var(--s5);margin-top:var(--s7)">
              <p><a class="btn btn--lg" href="${SITE.whatsapp}" rel="noopener" data-magnetic>
                ${icon('whatsapp','ico--sm')} WhatsApp us</a></p>
              <p class="u-small">Fastest during working hours, Monday to Saturday.</p>
            </div>

            <div style="margin-top:var(--s7);padding-top:var(--s6);border-top:1px solid var(--rule)">
              <p class="u-mono">Prefer email?</p>
              <p style="margin-top:var(--s3)">
                <a class="u-link" href="mailto:${SITE.email}">${SITE.email}</a> — we reply within
                1 business day.
              </p>
              <p style="margin-top:var(--s4)">
                <a class="u-link" href="tel:${SITE.phoneE164}">${SITE.phoneNbsp}</a>
              </p>
            </div>

            <div style="margin-top:var(--s7);padding-top:var(--s6);border-top:1px solid var(--rule)">
              <p class="u-mono">Where we are</p>
              <address style="margin-top:var(--s3);font-style:normal">
                Wwwebtech<br>${SITE.locality}, ${SITE.region}<br>India
              </address>
              <p class="u-small u-ink3" style="margin-top:var(--s4)">
                We work with businesses across India. Visits by appointment.
              </p>
              <div class="cluster" style="margin-top:var(--s5)">
                <a class="chip" href="${SITE.linkedin}" rel="noopener">${icon('linkedin','ico--sm')} LinkedIn</a>
                <a class="chip" href="${SITE.instagram}" rel="noopener">${icon('instagram','ico--sm')} Instagram</a>
              </div>
            </div>
          </div>

          <div>
            <h2 class="visually-hidden">Enquiry form</h2>
            ${contactForm('c')}
          </div>
        </div>
      </div>
    </section>`,

    faqBlock(FAQS, 'Before you write'),
  ].join('\n'),
};
