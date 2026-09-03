/* PILLAR 3 — Social Media Marketing */
import { PILLARS } from '../data.mjs';
import { ctaBand } from '../partials/chrome.mjs';
import { faqBlock, objectionsBlock, crossSell, shead } from '../partials/blocks.mjs';
import { phero, includedBlock, relatedBlock, cardRow } from '../partials/pagekit.mjs';
import { weeksPhrase } from '../proof.mjs';
import * as S from '../schema.mjs';

const P = PILLARS.find(p => p.key === 'social');

const INCLUDED = [
  {
    id: 'organic', icon: 'organic', name: 'Organic social media management',
    body: `A calendar, written and shot to a plan, posted consistently, with someone actually
      answering the DMs. That last part is where most Indian businesses lose the sale: the
      content works, an interested person messages on Instagram or WhatsApp, and nobody replies
      until Monday. We treat the inbox as part of the pipeline, not as admin.`,
    points: [
      'A monthly calendar you approve, built around what you actually sell.',
      'Posting, captions and community management on Instagram, Facebook and WhatsApp Business.',
      'Enquiries from DMs logged into the CRM like any other lead, so nothing depends on memory.',
      'A monthly note on what performed and what we are stopping.',
    ],
  },
  {
    id: 'paid', icon: 'paid', name: 'Paid social — Meta & Instagram ads',
    body: `Paid social is the fastest way to find out whether your offer works. It is also the
      fastest way to spend money on nothing, so we start small, test the message before the
      budget, and keep the account in your name so the learning stays with you when we stop.`,
    points: [
      'Ad accounts, pixel and conversions API set up in your Business Manager, owned by you.',
      'A small structured test before any real budget — offer, audience, creative, in that order.',
      'Retargeting that uses the traffic your SEO and organic work already earned.',
      'Reporting on cost per enquiry, not cost per click.',
    ],
  },
  {
    id: 'social-seo', icon: 'socialseo', name: 'Social SEO — Instagram & YouTube search',
    body: `Instagram and YouTube are search engines now, and they index words. Captions, on-screen
      text, alt text and geo tags decide whether your reel is findable next month or dead in
      forty-eight hours. Almost nobody in your category is doing this deliberately, which is
      precisely why it works.`,
    points: [
      'Keyword-led captions and on-screen text, written for search as well as for people.',
      'Alt text and location tagging applied consistently, not occasionally.',
      'Content built to be found later — answers to real questions, not only trends.',
      'YouTube titles, descriptions and chapters treated like page SEO, because they are.',
    ],
  },
  {
    id: 'creative', icon: 'creative', name: 'Creative & short-form video',
    body: `Short video is the format that carries everything else now, and it does not need to
      be expensive — it needs to be clear in the first two seconds and worth finishing. We plan,
      shoot and cut in batches so the calendar stays fed without a shoot every week.`,
    points: [
      'Batch shoots: a day of filming that covers several weeks of posting.',
      'Reels and shorts cut for retention, with captions burned in for silent viewing.',
      'Photography and graphics that match the brand rather than a stock library.',
      'Every asset delivered to you in full resolution, in your storage.',
    ],
  },
];

const FAQS = [
  { q: 'Do we need to be on every platform?',
    a: `<p>Almost certainly not. Two done properly beats five done occasionally, and for most Indian
        SMBs the honest answer is Instagram plus WhatsApp Business, with YouTube if you have
        something worth demonstrating. We'll tell you which two, and we'd rather you spent nothing
        on the others.</p>` },
  { q: 'What does social have to do with SEO?',
    a: `<p>More than it looks. Social is where people check whether you're real before they call, and
        that checking shows up as branded searches on Google — the easiest searches you will ever
        win. Social profiles also rank for your name, and the platforms themselves are search
        engines people use directly. It's the same customer, looking in a different box.</p>` },
  { q: 'Who owns the accounts and the content?',
    a: `<p>You do, all of it. The ad account sits in your Business Manager, the pages are yours, and
        every photo, video and edit is delivered to you at full resolution. If we part ways you keep
        the audience and the archive — we don't hold anything hostage.</p>` },
  { q: 'How much should we spend on ads?',
    a: `<p>Start smaller than you think and prove the message first. A modest test budget over two or
        three weeks will tell you whether the offer lands; scaling a message that doesn't work just
        buys you the same disappointment faster. We'll recommend a starting figure based on your
        margins and what a customer is worth to you, and we'll say so if we think paid isn't your
        problem.</p>` },
  { q: 'Can you just do the content and we’ll post it?',
    a: `<p>Yes — plenty of clients want the shoot, the edits and the captions and want to keep the
        posting and replying in-house because they know their customers. That's a perfectly good
        split, and it's cheaper. We'll set up the calendar and hand it over.</p>` },
];

const title = 'Social Media Marketing Agency in Delhi, India | Wwwebtech';
const desc  = 'Organic social, Meta and Instagram ads, social SEO and short-form video for Indian businesses. Your accounts, your content, cost per enquiry — not likes.';

export const page = {
  path: P.href, nav: 'services', og: 'social', title, desc,
  schema: S.graph([
    S.organization(),
    S.webpage({ url: P.href, title, desc }),
    S.service({ name: P.name, description: P.one, url: P.href, subs: P.subs }),
    S.faqPage(FAQS),
    S.breadcrumbs([{ label: 'Home', href: '/' }, { label: 'Services', href: '/services/' }, { label: P.name }]),
  ]),
  render: () => [
    phero({
      trail: [{ label: 'Home', href: '/' }, { label: 'Services', href: '/services/' }, { label: 'Social media marketing' }],
      eyebrow: '03 · Social Media Marketing',
      h1: 'Social media marketing in Delhi that feeds search, not just the feed',
      sub: `Followers are not the product. Enquiries are. We run social so that it does three
            jobs at once: reaches people who don’t know you, reassures the ones who are checking
            you out, and sends the rest into a pipeline you can measure.`,
      secondary: { href: '#included', label: 'What’s included' },
    }),

    `<section class="section section--ruled">
      <div class="shell">
        <div class="railed railed--wide">
          <div class="rail">${shead({
            label: 'The argument',
            title: 'Vanity metrics are easy to buy. Enquiries aren’t.',
          })}</div>
          <div class="prose">
            <p>It is genuinely simple to make a social account look successful. Followers can be
              bought, reach can be rented, and a reel can do a hundred thousand views for an audience
              that will never buy from you. None of that appears on your bank statement, which is
              why we report on cost per enquiry and let the follower count be a footnote.</p>
            <p>The version of social that actually earns is less glamorous. It answers the questions
              people ask before they buy. It shows the work, so a stranger can tell you are real. It
              is written so that it can still be found in three months rather than dying with the
              trend. And critically, it hands the interested person somewhere obvious to go next —
              a DM someone answers, a WhatsApp message that reaches a human, a site that loads.</p>
            <p>That last handover is where a lot of social spend quietly leaks. It is also the part
              we can fix properly, because we built the site and the CRM it hands over to.</p>
          </div>
        </div>
      </div>
    </section>`,

    includedBlock({ title: 'Four jobs, one calendar.', items: INCLUDED }),

    cardRow({
      label: 'How we measure',
      title: 'The three numbers we report on.',
      cols: 3,
      items: [
        { icon: 'organic', t: 'Enquiries', b: 'DMs, WhatsApp messages and form fills that came from social, logged as leads — not as engagement.' },
        { icon: 'paid', t: 'Cost per enquiry', b: 'What a real conversation costs you. This is the number that decides whether to spend more or stop.' },
        { icon: 'socialseo', t: 'Findable content', b: 'How much of what we published is still being discovered through search a month later.' },
      ],
    }),

    crossSell('social'),
    objectionsBlock(weeksPhrase, [
      'One team for dev AND marketing — really?',
      'What will it cost?',
      'Who owns everything after?',
    ]),
    faqBlock(FAQS, 'Social media questions'),
    relatedBlock({ pillarKey: 'seo', siblings: ['local', 'crm'], post: 'instagram-social-seo-india',
                   note: 'Read next, or jump straight to the detail.' }),
    ctaBand({
      h2: 'Posting a lot, hearing nothing?',
      sub: 'Send us your handle and what you actually sell. We’ll tell you where the gap is between the content and the enquiry. Reply within 1 business day.',
    }),
  ].join('\n'),
};
