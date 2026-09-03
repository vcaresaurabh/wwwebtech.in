<?php
/* ============================================================
   /lp/custom-crm/ — the T1-1 page.

   The buyer: an owner whose sales pipeline lives in a spreadsheet and
   three WhatsApp groups, who has started losing enquiries and knows it.

   The hook: this is the one thing a marketing agency cannot sell them,
   and this site's own admin panel is a working example of it. Self-proof
   is the only proof available and it is genuinely the most relevant kind.

   Every claim here must be true. No client names, no invented figures.
   ============================================================ */

declare(strict_types=1);

return [
  'slug'    => 'custom-crm',
  'variant' => 'a',

  'title' => 'Custom CRM development for Indian businesses | Wwwebtech',
  'desc'  => 'A CRM built around how your business actually sells, not a subscription you bend to fit. Delhi-based. You own the code and the data.',

  /* ── 2 · Hero ───────────────────────────────────────────── */
  'h1'  => 'Your sales process lives in a spreadsheet. It is costing you enquiries.',
  'sub' => 'We build CRM systems around how your business actually sells — not a monthly subscription you have to bend your process to fit.',

  'chips' => [
    'You own the code and the data',
    'Built in weeks, not quarters',
    'No per-user monthly fee',
  ],

  'cta'     => 'Get a scoped proposal',
  'cta_sub' => 'Two questions to start. No obligation, no sales sequence.',

  /* ── 2.5 · Message match ────────────────────────────────
     Whitelist only. The parameter is looked up here and never
     rendered — an unknown value falls back to the default sub-line
     above and gets logged so the owner can add a mapping. */
  'match' => [
    'crm-for-small-business'  => 'Built for teams of five to fifty, not enterprise budgets.',
    'crm-development-company' => 'Built to your process by a Delhi team, with the code handed to you at the end.',
    'crm-software-india'      => 'Built in India, for Indian businesses — GST, WhatsApp and UPI included from the start.',
    'sales-tracking-software' => 'Every enquiry tracked from first contact to invoice, without a spreadsheet in sight.',
    'lead-management-system'  => 'Stop losing enquiries between WhatsApp, email and whoever happened to pick up.',
    'excel-to-crm'            => 'Move off spreadsheets without losing the way your team already works.',
    'whatsapp-crm'            => 'Your WhatsApp enquiries, captured and followed up automatically.',
    'zoho-alternative'        => 'A one-off build you own, instead of a per-user fee that grows with your team.',
  ],

  /* ── 4 · Trust strip ────────────────────────────────────
     Only things that are literally, checkably true today. */
  'trust' => [
    'GSTIN-registered business in Delhi',
    'You own the code, the data and every account',
    'Fixed written scope before any work starts',
    'This site\'s own admin panel is a system we built',
  ],

  /* ── 5 · Problem mirror. Their words, then the consequence. */
  'pains' => [
    ['“I don’t know how many enquiries we got last month.”',
     'If it is not counted, it cannot be improved — and you are already paying for the traffic that produced it.'],
    ['“The follow-up depends on whoever remembers.”',
     'Most enquiries are lost to silence, not to a competitor. The second follow-up is the one nobody makes.'],
    ['“Our data is in three places and none of them agree.”',
     'A spreadsheet, an inbox and a WhatsApp group is three versions of the truth, and every one of them is out of date.'],
  ],

  /* ── 6 · What you actually get. Nouns, not adjectives. */
  'gets' => [
    'A CRM your team can use without training',
    'Every enquiry captured automatically from your website, WhatsApp and email',
    'A pipeline you can see at a glance, on a phone',
    'Automatic follow-up reminders that fire whether or not anyone remembers',
    'Reports on where enquiries come from and which ones become customers',
    'Role-based access, so staff see what they need and no more',
    'Your existing spreadsheet imported, cleaned and reconciled',
    'The full source code, the database and every account in your name',
    'Documentation written for your team, not for developers',
  ],

  /* ── 7 · Proof. §2.4 fallbacks until [SETUP-7] supplies real ones. */
  'proof_mode' => 'fallback',
  'proof_head' => 'We are a young firm. Here is what we can show you instead.',
  'proof_sub'  => 'No invented case studies and no logos we do not have permission to use. What follows is checkable.',

  /* ── 8 · How it works. Real weeks. */
  'steps' => [
    ['Map how you actually sell', 'A working session on your real process — who does what, where enquiries arrive, what gets lost. We write it down and you correct it.', 'Week 1'],
    ['Agree a fixed scope',      'A written specification with a fixed price. If it is not in the document it is not in the price, and nothing gets added without you agreeing to it.', 'Week 1–2'],
    ['Build, with weekly reviews', 'You see working software in a browser every week, on your own phone. Problems found in week three are cheap.', 'Weeks 2–6'],
    ['Move your data and hand over', 'Your spreadsheet imported and reconciled, your team trained, every account transferred to your name.', 'Week 6–8'],
  ],

  /* ── 9 · Objections, in their language. */
  'objections' => [
    ['Why not just use Zoho or HubSpot?',
     'Often you should, and we will say so on the call. Off-the-shelf wins when your process is ordinary and you are happy to adapt to the software. A custom build wins when your process is the thing that makes you money, or when per-user fees for a growing team overtake a one-off cost within two years.'],
    ['What if it goes wrong halfway through?',
     'The scope is fixed and written before work starts, and you see something working in a browser every week. You are never more than seven days from being able to say this is not what I meant.'],
    ['Who owns it afterwards?',
     'You do — the source code, the database, the domain and every third-party account, in your name from day one. There is no lock-in and no clause that makes leaving expensive.'],
    ['We are not technical. Can our team actually use it?',
     'That is the design constraint, not an afterthought. If your team needs training to use it, it is built wrong. We test it with the people who will actually use it before handover.'],
    ['How long before it is useful?',
     'Six to eight weeks for a typical build. We can usually get lead capture and the pipeline live earlier than the rest, so it starts earning its keep before the final handover.'],
    ['What happens after handover?',
     'Nothing you are forced to buy. Support is available monthly if you want it, and the code is yours to take to any developer if you would rather not.'],
  ],

  /* ── 10 · Price anchor. [SETUP-3] supplies the real figures. */
  'price_from'  => null,
  'price_note'  => 'The owner has not yet cleared public price ranges. Until then this block explains how a quote is built instead of showing a figure — which is more useful than a number with no reasoning behind it.',
  'price_moves' => [
    ['How many people use it', 'A five-person sales team and a fifty-person operation are different builds.'],
    ['What it has to talk to',  'Your website is straightforward. Tally, a payment gateway or a marketplace feed adds real work.'],
    ['How much history moves',  'A clean spreadsheet imports in a day. Fifteen years across four systems does not.'],
    ['Whether you want support afterwards', 'Optional, monthly, and cancellable. Never bundled in to inflate the headline.'],
  ],

  /* ── 12 · FAQ, with FAQPage schema. */
  'faq' => [
    ['How much does a custom CRM cost in India?',
     'It depends on how many people use it, what it has to integrate with, and how much existing data moves across. A small team with a clean spreadsheet and no integrations is a very different project from a fifty-person operation connected to Tally and a payment gateway. We give a fixed written price after one scoping call, and it does not change unless you change the scope.'],
    ['How long does it take to build?',
     'Six to eight weeks is typical for a first version. Lead capture and the pipeline usually go live before the rest, so the system starts being useful part-way through rather than only at the end.'],
    ['Can it connect to WhatsApp?',
     'Yes. Enquiries arriving on WhatsApp Business can be captured into the CRM, and follow-ups can be sent from it. WhatsApp charges per message for some categories, so we set that up with the costs visible rather than hidden.'],
    ['Will it work with Tally or our accounting software?',
     'Usually. Tally has an integration path, and most accounting tools have either an API or a reliable import format. We check yours specifically during scoping rather than promising it in advance.'],
    ['What if we already have a CRM we hate?',
     'Then the first question is whether it is the software or the process, because replacing the software will not fix the process. We will tell you honestly which one we think it is, even when the answer means a smaller project for us.'],
    ['Do we have to pay a monthly fee?',
     'Not for the CRM itself — it is a one-off build that you own and host. Hosting costs what your hosting costs. Optional monthly support is exactly that: optional.'],
  ],

  'final_h2'  => 'Tell us how you sell now.',
  'final_sub' => 'Two questions to start. If an off-the-shelf CRM is the better answer for you, we will say so on the call.',
];
