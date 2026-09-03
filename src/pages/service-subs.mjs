/* ============================================================
   The four sub-service pages that earn their own URL:
     /services/crm-systems/
     /services/business-automation/
     /services/ai-visibility-geo/
     /services/technical-support/
   One factory, four content blocks — so they stay structurally
   identical and only the words differ.
   ============================================================ */
import { PILLARS } from '../data.mjs';
import { ctaBand } from '../partials/chrome.mjs';
import { faqBlock, shead } from '../partials/blocks.mjs';
import { phero, relatedBlock, cardRow } from '../partials/pagekit.mjs';
import * as S from '../schema.mjs';

const subPage = (d) => {
  const pillar = PILLARS.find(p => p.key === d.pillarKey);
  const trail = [
    { label: 'Home', href: '/' },
    { label: 'Services', href: '/services/' },
    { label: pillar.short, href: pillar.href },
    { label: d.crumb },
  ];
  return {
    path: d.path, nav: 'services', og: d.og, title: d.title, desc: d.desc,
    schema: S.graph([
      S.organization(),
      S.webpage({ url: d.path, title: d.title, desc: d.desc }),
      S.service({ name: d.serviceName, description: d.desc, url: d.path }),
      S.faqPage(d.faqs),
      S.breadcrumbs(trail),
    ]),
    render: () => [
      phero({
        trail, eyebrow: d.eyebrow, h1: d.h1, sub: d.sub,
        secondary: { href: pillar.href, label: `All of ${pillar.short}` },
      }),
      `<section class="section section--ruled">
        <div class="shell">
          <div class="railed railed--wide">
            <div class="rail">${shead({ label: 'The short version', title: d.argueTitle })}</div>
            <div class="prose">${d.argue}</div>
          </div>
        </div>
      </section>`,
      cardRow({ label: 'What you get', title: d.getTitle, cols: 2, items: d.get, id: 'included' }),
      cardRow({ label: 'How it runs', title: d.howTitle, cols: d.how.length, items: d.how }),
      faqBlock(d.faqs, d.faqTitle),
      relatedBlock({ pillarKey: d.relatedPillar, siblings: d.siblings, post: d.post }),
      ctaBand({ h2: d.ctaH2, sub: d.ctaSub }),
    ].join('\n'),
  };
};

/* ============================================================ */
export const pages = [

  subPage({
    path: '/services/crm-systems/', og: 'crm', pillarKey: 'web', crumb: 'CRM systems',
    title: 'Custom CRM Systems for Small Business in India | Wwwebtech',
    desc: 'Custom CRM built around how your team actually sells. Every enquiry from site, WhatsApp and calls in one place. Your data, exportable any time.',
    serviceName: 'Custom CRM systems for small business',
    eyebrow: 'Web Development & Systems',
    h1: 'Custom CRM systems for small businesses in India',
    sub: `Off-the-shelf CRMs make you work the way the software works. We build the other way
          round — a system shaped like your pipeline, holding only the fields you use, that your
          team will actually open on a Tuesday morning.`,
    argueTitle: 'Most leads are lost after they arrive, not before.',
    argue: `<p>Businesses spend heavily on getting the phone to ring and almost nothing on what
      happens next. The enquiry lands in a shared inbox. Someone means to call back. A WhatsApp
      message arrives on a personal phone and stays there. Two weeks later nobody can say whether
      that lead was ever answered, and the honest answer is usually no.</p>
      <p>A CRM fixes that, but only if people use it — and people don't use software that asks
      them twenty questions to log a phone call. So we build small: the fields you actually need,
      the stages you actually have, on one screen. Then we add automation so the system does the
      remembering instead of your sales person.</p>
      <p>The other reason to build rather than buy is what happens as you grow. Per-seat pricing
      on a product CRM turns your success into someone else's revenue, and the data lives in
      their account. A system you own has a fixed cost and an export button.</p>`,
    getTitle: 'What a Wwwebtech CRM includes.',
    get: [
      { icon: 'crm', t: 'One record per customer', b: 'Website enquiries, WhatsApp messages, calls and emails attached to a single contact, so anyone picking it up has the full history.' },
      { icon: 'automation', t: 'Your pipeline, your stage names', b: 'Stages named after what your business actually does — “site visit booked”, “quote sent”, “awaiting PO” — not a generic sales funnel.' },
      { icon: 'clock', t: 'Follow-ups that don’t rely on memory', b: 'Ownership, reminders and escalation, so a lead cannot sit unclaimed for a week without somebody being told.' },
      { icon: 'content', t: 'Reporting you can read', b: 'Where leads came from, how fast they were answered, and which sources produced money rather than noise.' },
      { icon: 'shield', t: 'Your data, exportable', b: 'Hosted where you choose, in your account, with a working export. No per-seat pricing that grows every time you hire.' },
      { icon: 'websites', t: 'Wired into the site from day one', b: 'Because we build the website too, the form on it writes straight into the CRM — no zap in the middle to break silently.' },
    ],
    howTitle: 'Three steps, and you’re running it yourself.',
    how: [
      { k: 'Step 01', t: 'We watch how you sell now', b: 'Half a day with the people doing the selling, mapping the real process — including the parts that only live in someone’s head.' },
      { k: 'Step 02', t: 'We build the smallest thing that works', b: 'A first version in weeks, not months, with your real data in it. You use it, you tell us what’s wrong, we change it.' },
      { k: 'Step 03', t: 'We train, then get out of the way', b: 'Your team is trained on it and you own the logins. We stay on support if you want us, and you can fire us without losing anything.' },
    ],
    faqTitle: 'CRM questions',
    faqs: [
      { q: 'Why not just use a ready-made CRM?',
        a: `<p>Sometimes you should, and we'll say so. If your process is genuinely standard and you're
            happy inside someone else's structure, a subscription product is cheaper and faster. Custom
            is worth it when your process is the thing that makes you competitive, when per-seat pricing
            is about to become painful, or when the CRM needs to talk to systems a product won't.</p>` },
      { q: 'Where does our data live?',
        a: `<p>Wherever you want it to — your hosting, your cloud account, in your name. We build it,
            you own it. There's a working export from day one, and we'll show you how to use it before
            you need it.</p>` },
      { q: 'Will our team actually use it?',
        a: `<p>That's the real risk, and it's a design problem, not a training problem. The usual cause of
            an abandoned CRM is that logging a call takes longer than making one. We build the smallest
            system that answers your questions, watch your team use the first version, and cut whatever
            they route around.</p>` },
      { q: 'Can it handle WhatsApp?',
        a: `<p>Yes — for most Indian businesses that's the main channel, so it can't be an afterthought.
            We connect WhatsApp Business so conversations are logged against the contact record rather
            than living on one person's phone.</p>` },
    ],
    relatedPillar: 'web', siblings: ['automation', 'websites'], post: 'website-speed-india',
    ctaH2: 'Losing leads you already paid for?',
    ctaSub: 'Tell us how an enquiry reaches you today and where it usually stalls. We’ll tell you whether you need a CRM or just a better inbox.',
  }),

  subPage({
    path: '/services/business-automation/', og: 'automation', pillarKey: 'web', crumb: 'Business automation',
    title: 'Business Automation & Integrations, India | Wwwebtech',
    desc: 'Connect the tools you already pay for so your team stops copying data between them. Practical automation for Indian SMBs — WhatsApp, CRM, invoicing.',
    serviceName: 'Business automation & integrations',
    eyebrow: 'Web Development & Systems',
    h1: 'Business automation and integrations for growing Indian companies',
    sub: `The quiet cost in most small businesses is re-typing. The same customer detail entered
          into a form, a sheet, an invoice and a WhatsApp message. We connect what you already
          have so it moves itself.`,
    argueTitle: 'The most expensive software you own is the copy-paste.',
    argue: `<p>Ask anyone in your office what takes the longest and you'll rarely hear "the actual
      work". You'll hear about the sheet that has to be updated, the invoice that has to be raised
      from the order, the follow-up list someone rebuilds every Monday. It is invisible on the
      P&amp;L because it doesn't have a line item — it just eats hours.</p>
      <p>Automation gets sold as a transformation project. It's usually much smaller than that. In
      most businesses there are three or four specific handovers that account for the bulk of the
      wasted time, and connecting those is a matter of weeks, not a change programme.</p>
      <p>We look for those first, we do them, and we stop. Automating something nobody does often
      is a hobby, not a service.</p>`,
    getTitle: 'The connections worth making first.',
    get: [
      { icon: 'automation', t: 'Website to CRM to inbox', b: 'An enquiry writes itself into the CRM, notifies the right person, and starts the follow-up clock. No manual re-entry, no lead lost in a shared mailbox.' },
      { icon: 'organic', t: 'WhatsApp in the pipeline', b: 'Business messages logged against the customer record and answerable by whoever is on duty, rather than trapped on one person’s phone.' },
      { icon: 'ecommerce', t: 'Orders to invoices to books', b: 'Order details flowing into GST-correct invoicing and into whatever your accountant uses, so month-end stops being a reconstruction exercise.' },
      { icon: 'content', t: 'The dashboard you keep asking for', b: 'One screen that answers the question you currently ask someone to go and check — updated automatically, not weekly by hand.' },
    ],
    howTitle: 'How we scope it.',
    how: [
      { k: 'Step 01', t: 'Find the three handovers', b: 'We map where information changes hands and time the ones people complain about. The list is usually shorter and more boring than expected.' },
      { k: 'Step 02', t: 'Automate one, prove it', b: 'We build the highest-value one first and let you live with it for a fortnight. If it doesn’t save the time we said, we don’t build the rest.' },
      { k: 'Step 03', t: 'Document and hand over', b: 'Written down, in your accounts, with a note on what to do if something breaks. Automation nobody understands is a liability.' },
    ],
    faqTitle: 'Automation questions',
    faqs: [
      { q: 'Do we have to replace our current tools?',
        a: `<p>Usually not. Most of this work is connecting things you already pay for. We'd rather make
            your existing stack talk to itself than sell you a migration you didn't ask for — replacing
            a tool is a separate decision with its own cost, and we'll flag it as one.</p>` },
      { q: 'What if the automation breaks?',
        a: `<p>Some of them will, eventually — an API changes, a password expires. So we build in alerts
            that tell us before they tell you, document what each one does, and keep a manual path
            available. Anything that can only work automatically is a single point of failure.</p>` },
      { q: 'Is this worth it for a small team?',
        a: `<p>Often more so, because a small team has no slack. The test is simple: if a task happens
            weekly and takes an hour, it costs you a working week a year. If it happens daily, it's
            more than a month. We'll do that arithmetic with you before quoting.</p>` },
    ],
    relatedPillar: 'web', siblings: ['crm', 'websites'], post: 'website-speed-india',
    ctaH2: 'What is your team re-typing?',
    ctaSub: 'Describe the one task everyone grumbles about. We’ll tell you whether it can be automated and roughly what it would cost.',
  }),

  subPage({
    path: '/services/ai-visibility-geo/', og: 'geo', pillarKey: 'seo', crumb: 'AI visibility (GEO/AEO)',
    title: 'GEO & AEO: ChatGPT, Gemini & AI Overviews SEO | Wwwebtech',
    desc: 'Generative Engine Optimization for Indian businesses. Get named in ChatGPT, Gemini and AI Overviews — entity clarity, schema, quotable answers.',
    serviceName: 'GEO and AEO — generative and answer engine optimization',
    eyebrow: 'SEO & AI Visibility',
    h1: 'GEO and AEO: getting your business named in AI answers',
    sub: `When someone asks ChatGPT or Gemini for a supplier in Delhi, it names a few businesses
          and stops. There is no page two. This is the work of being one of the few — and right
          now it is unusually winnable.`,
    argueTitle: 'There is no page two in an AI answer.',
    argue: `<p>Generative Engine Optimization (GEO) and Answer Engine Optimization (AEO) are new
      labels for a genuinely new situation. In classic search, being eleventh still got you seen
      occasionally. In an AI answer, being eleventh means you do not exist — the model names three
      or four options and moves on.</p>
      <p>The good news is that these systems are not ranking pages so much as assembling an
      understanding. They want to know what your business is, where it operates, what it does,
      and whether that story is consistent everywhere they can check. A small business with
      accurate, consistent, clearly-written information can beat a much larger competitor whose
      website contradicts its own listings.</p>
      <p>The other good news: almost none of your competitors are doing this deliberately yet. The
      cost of getting organised now is far lower than the cost of catching up in two years, and
      most of the work pays off in ordinary Google results at the same time.</p>`,
    getTitle: 'What the work actually consists of.',
    get: [
      { icon: 'geo', t: 'Making your entity unambiguous', b: 'One consistent answer to “who is this business, where, and what do they do” across your site, your schema, your listings and your profiles.' },
      { icon: 'content', t: 'Answers worth quoting', b: 'Content restructured so a specific claim can be lifted cleanly — a direct answer near the top, in plain language, with the detail underneath.' },
      { icon: 'techseo', t: 'Structured data that matches reality', b: 'Organization, LocalBusiness, Service, FAQ and Article markup that describes what is genuinely on the page. Never invented ratings — that is a penalty, not a shortcut.' },
      { icon: 'local', t: 'The sources they lean on', b: 'Google Business Profile, directories and mentions tidied and made consistent, because answer engines cross-check what you say against what others say.' },
    ],
    howTitle: 'The order we do it in.',
    how: [
      { k: 'Step 01', t: 'Find out what they say now', b: 'We ask the major assistants about your category and your name, and record what comes back — including who they name instead of you.' },
      { k: 'Step 02', t: 'Fix the contradictions', b: 'Different addresses, old phone numbers, a service you stopped offering. Inconsistency is the cheapest thing to fix and the most damaging to leave.' },
      { k: 'Step 03', t: 'Publish quotable answers', b: 'Pages that answer the questions people actually ask an assistant, written so the answer survives being extracted from the page.' },
      { k: 'Step 04', t: 'Re-check, monthly', b: 'The same prompts, run again, so you can see movement. This is a young field — we report what we observe, not what we hope.' },
    ],
    faqTitle: 'AI visibility questions',
    faqs: [
      { q: 'Is GEO just SEO with a new name?',
        a: `<p>It overlaps heavily, and anyone telling you it's a completely separate discipline is
            selling something. The foundations are the same: be fast, be crawlable, be clear, be
            consistent. What's genuinely different is the emphasis on entity clarity, on being quotable
            rather than merely rankable, and on third-party sources — because the model is assembling
            an answer, not ordering a list.</p>` },
      { q: 'Can you guarantee ChatGPT will recommend us?',
        a: `<p>No. Nobody can, and these systems change their behaviour without notice. What we can do
            is make you the easiest business in your category to describe accurately, remove the
            contradictions that make a model hedge, and measure what the assistants actually say about
            you month to month.</p>` },
      { q: 'How do you measure something like this?',
        a: `<p>We keep a fixed set of prompts — the questions a real customer would ask — and run them
            on a schedule, recording whether you're named, how you're described, and who's named
            instead. It's a sample rather than a ranking report, and we're upfront about that. It is
            still far better than guessing.</p>` },
      { q: 'Should we block AI crawlers to protect our content?',
        a: `<p>For most businesses selling a service, no — being cited is the point. Blocking them is a
            reasonable choice for a publisher whose product is the words themselves. You are not that;
            your words are an advert for the work. Our robots.txt allows them, deliberately.</p>` },
    ],
    relatedPillar: 'seo', siblings: ['techseo', 'content'], post: 'chatgpt-ai-search-visibility',
    ctaH2: 'Ask ChatGPT who does what you do in Delhi.',
    ctaSub: 'If it doesn’t say you, send us the question you tried. We’ll tell you what’s missing and what it takes to be in the answer.',
  }),

  subPage({
    path: '/services/technical-support/', og: 'support', pillarKey: 'web', crumb: 'Technical support',
    title: 'Website Support & Maintenance Plans, India | Wwwebtech',
    desc: 'Website support with a real response time: within 1 business day. Monitoring, backups, updates and monthly improvements. Cancel any time.',
    serviceName: 'Ongoing technical support and maintenance',
    eyebrow: 'Web Development & Systems',
    h1: 'Website support and maintenance, with a response time in writing',
    sub: `A site is not finished at launch — it is just started. Support plans cover the part
          nobody enjoys quoting for: monitoring, backups, updates, and the small monthly
          improvements that stop a good site quietly becoming an old one.`,
    argueTitle: '“It was fine when you launched it” is not a maintenance plan.',
    argue: `<p>Websites decay. Certificates expire, dependencies go out of date, a plugin stops
      being maintained, an image someone uploaded at 4MB drags the whole page down, and a form
      quietly stops sending email for three weeks before anyone notices. None of this is dramatic
      and all of it costs you enquiries.</p>
      <p>The other half of support is the thing agencies don't put in the contract: being
      reachable. Our commitment is a reply within one business day, every time. Not a ticket
      number and silence — an answer from someone who knows your site.</p>
      <p>And because we don't hold anything hostage, a support plan is a service you keep because
      it's useful, not because leaving is painful. Everything is in your name; you can cancel and
      take it all with you.</p>`,
    getTitle: 'What’s in a support plan.',
    get: [
      { icon: 'shield', t: 'Monitoring and backups', b: 'Uptime and form-delivery checks so we find out the contact form broke before you do, plus backups you can actually restore — tested, not assumed.' },
      { icon: 'techseo', t: 'Performance kept honest', b: 'Core Web Vitals re-checked regularly, because a site that launched fast rarely stays fast once content starts being added.' },
      { icon: 'clock', t: 'A reply within 1 business day', b: 'Every request, every time. If something is genuinely broken and losing you money, it jumps the queue.' },
      { icon: 'content', t: 'Small monthly improvements', b: 'An hours allowance for the changes that never justify their own quote: new pages, copy edits, a fix to something that has been annoying you.' },
    ],
    howTitle: 'How support works.',
    how: [
      { k: 'Step 01', t: 'You email or WhatsApp', b: 'No portal to learn and no ticket ritual. Describe the problem the way you would to a colleague.' },
      { k: 'Step 02', t: 'We reply within a business day', b: 'With either a fix, or a straight answer about what it will take and when. You always know where it stands.' },
      { k: 'Step 03', t: 'You get a monthly summary', b: 'What was done, what we noticed, and what we think is worth doing next. One page, no jargon.' },
    ],
    faqTitle: 'Support questions',
    faqs: [
      { q: 'Do you support sites you didn’t build?',
        a: `<p>Often, yes. We'll do a short audit first so we know what we're taking on and can be honest
            about it — if a site is built on something abandoned or actively unsafe, we'd rather tell you
            that up front than take a monthly fee to keep it upright.</p>` },
      { q: 'What counts as an emergency?',
        a: `<p>The site is down, checkout is broken, the contact form isn't delivering, or there's a
            security problem. Those jump the queue. A new page or a copy change is normal work and goes
            into the monthly allowance.</p>` },
      { q: 'Can we cancel?',
        a: `<p>Yes, and you keep everything — the site, the code, the domain, the accounts, the backups.
            They were always in your name. We'd rather you stayed because the service is worth paying
            for than because leaving is difficult.</p>` },
    ],
    relatedPillar: 'web', siblings: ['websites', 'crm'], post: 'website-speed-india',
    ctaH2: 'Something on your site broken?',
    ctaSub: 'Tell us what it is doing and what it should be doing. We’ll tell you what’s wrong, whether or not you end up hiring us.',
  }),
];
