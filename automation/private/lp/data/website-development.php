<?php
/* ============================================================
   /lp/website-development/ — T1-2.

   The buyer: an SMB whose site is old, slow, or a template everyone
   in their sector also uses. They have been told it needs "a refresh"
   and do not know what that should cost or involve.

   The hook: speed, evidenced by this site's own score. It is the one
   claim in this market that can be checked in ten seconds, which is
   exactly why it is the wedge.
   ============================================================ */
declare(strict_types=1);

return [
  'slug' => 'website-development', 'variant' => 'a',
  'title' => 'Website development company in Delhi | Wwwebtech',
  'desc'  => 'Fast, search-ready business websites built in weeks. Under two seconds on a mid-range Android — check our own score. You own the code and the accounts.',

  'h1'  => 'A website that loads before your customer gives up.',
  'sub' => 'Most business sites in India are built for a designer’s laptop, not a mid-range Android on patchy 4G. We build for the phone your customer actually holds.',
  'chips' => ['Under 2 seconds on mobile', 'Built in 4–6 weeks', 'You own the code and accounts'],
  'cta' => 'Get a scoped proposal',
  'cta_sub' => 'Two questions to start. No obligation, no sales sequence.',

  'match' => [
    'website-development-company' => 'A Delhi team, a fixed written scope, and the code handed to you at the end.',
    'website-design-company'      => 'Designed around what your customer needs to decide, not around a template.',
    'business-website'            => 'Built to bring you enquiries, not to win a design award.',
    'website-redesign'            => 'Rebuilt without losing the rankings and pages you already have.',
    'ecommerce-website'           => 'Storefront, payments and stock — built to load fast even with a full catalogue.',
    'website-cost'                => 'A fixed written price after one call, and nothing added without your say-so.',
    'wordpress-website'           => 'WordPress when it genuinely fits, and something faster when it does not.',
    'fast-website'                => 'Speed is the specification, not an optimisation pass at the end.',
  ],

  'trust' => [
    'GSTIN-registered business in Delhi',
    'You own the code, the domain and every account',
    'Fixed written scope before any work starts',
    'Core Web Vitals measured weekly and published',
  ],

  'pains' => [
    ['“People say the site is slow but it looks fine to me.”',
     'It probably is fine on your laptop. On a mid-range Android on 4G it can take four times longer, and that is most of your traffic.'],
    ['“We have a website but it brings us nothing.”',
     'A brochure that nobody finds is not a marketing asset. Being found and being persuasive are two separate jobs, and most sites do neither.'],
    ['“The last developer disappeared and we cannot edit anything.”',
     'If you do not hold the domain, the hosting and the code, you do not have a website — you have a dependency.'],
  ],

  'gets' => [
    'A site that loads in under two seconds on a mid-range Android',
    'Every page written to answer one real customer question',
    'Search-engine basics built in from the first commit, not bolted on',
    'A contact form that reaches you and cannot silently fail',
    'Content you can edit yourself, without calling us',
    'Analytics that tell you which pages bring enquiries',
    'The domain, the hosting and the code in your name',
    'Redirects from every old URL, so existing rankings survive',
    'A written handover document your team can actually follow',
  ],

  'proof_mode' => 'fallback',
  'proof_head' => 'Check our claim before you believe it.',
  'proof_sub'  => 'We sell speed, so the honest thing is to be measured on it. This page and this site are the evidence.',

  'steps' => [
    ['Work out what the site is for', 'One session on what the site must cause — an enquiry, a booking, a call. Everything after this resolves against that answer.', 'Week 1'],
    ['Content and structure first', 'The words before the design. A layout built around real content survives contact with reality; one built around placeholder text does not.', 'Week 1–2'],
    ['Design and build, reviewed weekly', 'You see it in your own browser every week, on your own phone. Mobile first, always.', 'Weeks 2–5'],
    ['Launch and hand over', 'Redirects mapped, search console connected, accounts transferred to you, and a document your team can follow.', 'Week 5–6'],
  ],

  'objections' => [
    ['Can we not just use Wix or a template?',
     'For a simple brochure site with no ambition to rank, sometimes yes, and we will say so. Templates struggle when you need speed, custom functionality, or search visibility in a competitive category — and they are hard to leave once your content lives inside them.'],
    ['How is this different from the ₹15,000 quote we were given?',
     'Usually in what is not included: content, redirects, search setup, testing on real devices, and someone answering the phone in month three. A cheap site that brings no enquiries is the most expensive kind.'],
    ['Will we lose our Google rankings?',
     'Not if the redirects are mapped properly, which is a specific piece of work we do and quote for. Ask any developer whether redirect mapping is included — the answer tells you a lot.'],
    ['Can we update it ourselves afterwards?',
     'Yes. Editing text, adding pages and swapping images are things your team does without us. If you need us for a price change, it is built wrong.'],
    ['What if we already have content?',
     'Then the project is shorter and cheaper. We will use what works, rewrite what does not, and tell you honestly which is which.'],
    ['How long does it actually take?',
     'Four to six weeks for a typical business site. The variable is almost never the build — it is how quickly content and approvals come back from your side.'],
  ],

  'price_from' => null,
  'price_note' => null,
  'price_moves' => [
    ['How many pages',        'Five pages and fifty are different projects, and most businesses need fewer than they think.'],
    ['Who writes the words',  'Content is the long pole. If you supply it, the project is shorter and cheaper.'],
    ['What it has to do',     'A brochure is straightforward. Bookings, payments or a customer login are real engineering.'],
    ['Whether photography is needed', 'Real photographs of your premises and team beat stock, and a shoot is a real line item.'],
  ],

  'faq' => [
    ['How much does a business website cost in Delhi?',
     'Quotes for the same brief routinely vary by a factor of ten, which tells you the number is describing different work rather than different value. What moves it is the number of pages, who writes the content, and whether the site has to do anything beyond inform. We give a fixed written price after one call.'],
    ['How long does it take to build a website?',
     'Four to six weeks is normal for a business site. The build is rarely what delays a project — content and approvals are. If you can commit to a weekly review slot, the timeline holds.'],
    ['Will my website work on mobile?',
     'It is designed for mobile first and tested on real mid-range Android devices, not just resized in a browser. That is where most Indian web traffic actually is.'],
    ['Do you use WordPress?',
     'Where it genuinely fits — when you publish often and want a familiar editor. Where you need speed above all, or the site barely changes, something lighter is usually the better answer. We recommend based on what you will actually do with it.'],
    ['What happens if we want changes later?',
     'Small edits you make yourself. Larger work is quoted separately, and there is no retainer you must sign to be allowed to ask.'],
    ['Who owns the website when it is finished?',
     'You do — domain, hosting, code, analytics and search console, all in your name from day one.'],
  ],

  'final_h2'  => 'Tell us what your site is not doing.',
  'final_sub' => 'Two questions to start. If your existing site is fixable rather than replaceable, we will tell you that instead.',
];
