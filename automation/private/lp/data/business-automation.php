<?php
/* /lp/business-automation/ — T2-5. Hours saved per week, not
   "digital transformation". The buyer has staff doing data entry. */
declare(strict_types=1);

return [
  'slug' => 'business-automation', 'variant' => 'a',
  'title' => 'Business process automation in India | Wwwebtech',
  'desc'  => 'Connect the tools you already use and stop retyping the same data. Measured in hours saved per week, quoted against what those hours cost.',

  'h1'  => 'Someone in your office retypes the same data three times a day.',
  'sub' => 'Not because they are inefficient — because two systems that should talk to each other do not. That gap has a weekly cost, and it is usually a person.',
  'chips' => ['Measured in hours saved', 'Works with what you already use', 'No new subscriptions to learn'],
  'cta' => 'Get a scoped proposal',
  'cta_sub' => 'Two questions to start. No obligation, no sales sequence.',

  'match' => [
    'business-process-automation' => 'The manual steps between your systems, removed one at a time.',
    'workflow-automation'         => 'Start with the task that eats the most hours, not the one that demos best.',
    'data-entry-automation'       => 'If a person is retyping what another system already knows, that is the job.',
    'api-integration'             => 'Making the tools you already pay for talk to each other.',
    'tally-integration'           => 'Orders, invoices and stock moving into Tally without anyone retyping them.',
    'whatsapp-automation'         => 'Enquiries from WhatsApp into your system, and confirmations back out.',
  ],

  'trust' => [
    'GSTIN-registered business in Delhi',
    'You own the automation and the credentials',
    'Fixed written scope before any work starts',
    'We quote against hours saved, and say when it is not worth it',
  ],

  'pains' => [
    ['“We hired someone just to move data between systems.”',
     'That salary is the real price of the integration you did not build. It recurs every month and it grows.'],
    ['“Every month-end is two days of reconciliation.”',
     'Two days, twelve times a year, at whatever those people cost. Most reconciliation exists because two systems disagree by design.'],
    ['“By the time the report is ready it is out of date.”',
     'A number you get three weeks late is history, not management information.'],
  ],

  'gets' => [
    'A written map of where your data actually goes today',
    'The two or three steps costing the most hours, identified before any building',
    'Your existing tools connected — no new subscription to learn',
    'Orders and invoices flowing into your accounting system automatically',
    'Enquiries from every channel landing in one place',
    'Reports that build themselves and arrive on a schedule',
    'Alerts when something fails, rather than silence',
    'Documentation your team can follow when we are not here',
    'An honest note of which manual steps are not worth automating',
  ],

  'proof_mode' => 'fallback',
  'proof_head' => 'We are a young firm. Here is what we can show you instead.',
  'proof_sub'  => 'No invented efficiency percentages. What follows is checkable.',

  'steps' => [
    ['Map what actually happens', 'Not the process on paper — the one your team really follows, including the workarounds. Usually the workarounds are the finding.', 'Week 1'],
    ['Count the hours',           'Each manual step, how often, how long. This is what tells us whether automating it is worth your money.', 'Week 1'],
    ['Automate the biggest one first', 'One workflow, working, in production. Proof before scale.', 'Weeks 2–4'],
    ['Then the next',             'Each one quoted separately against the hours it saves. You stop whenever the arithmetic stops working.', 'Ongoing'],
  ],

  'objections' => [
    ['Will this replace our staff?',
     'Usually it removes the part of their job they like least. The businesses that get most from this redeploy people onto work that needs judgement, which is the work that was being squeezed out by data entry.'],
    ['Our processes are messy. Do we need to fix them first?',
     'No — and trying to is how these projects stall for a year. We automate the process you actually have, and the mapping usually reveals which mess is worth cleaning up anyway.'],
    ['We use very old software. Can it connect?',
     'Often, even without an API — a scheduled export and import is unglamorous and works. We find out during scoping rather than promising in advance.'],
    ['What if it breaks?',
     'It alerts you rather than failing silently, which is the difference between an inconvenience and a month-end disaster. Manual fallback is part of the design.'],
    ['How do you price it?',
     'Against the hours saved. If a workflow costs more to automate than it saves in two years, we will tell you not to do it.'],
    ['Do we need to buy new software?',
     'Usually not. Most of this work is connecting things you already pay for.'],
  ],

  'price_from' => null, 'price_note' => null,
  'price_moves' => [
    ['How many systems are involved', 'Two talking to each other is straightforward. Five is a different project.'],
    ['Whether they have an API',      'Modern tools connect cleanly. Older ones need a scheduled file exchange, which is slower to build.'],
    ['How much the data needs cleaning', 'Inconsistent records are usually the real work, not the connection itself.'],
    ['How many exceptions there are', 'The rule is easy. The fourteen special cases your team knows by heart are the cost.'],
  ],

  'faq' => [
    ['What is business process automation?',
     'Removing the manual steps between systems that should already be talking. In practice it usually means a person stops retyping data that another system already holds.'],
    ['How do we know it is worth the money?',
     'Count the hours the manual step takes, multiply by what those hours cost, compare to the build. We do that arithmetic with you before quoting, and we say when it does not work out.'],
    ['Can you connect to Tally?',
     'Usually yes. Tally has an integration path and most order and invoice flows can be made to work. We verify against your specific version during scoping.'],
    ['What about WhatsApp enquiries?',
     'They can be captured into your CRM automatically, and confirmations sent back out. WhatsApp charges per message for some categories, and we set that up with the cost visible rather than hidden.'],
    ['How long does a first automation take?',
     'Two to four weeks for a single workflow, including the mapping. We do one, prove it, then decide together whether to do the next.'],
    ['What happens if you disappear?',
     'You hold the credentials and the code, and the documentation is written for your team. Any competent developer can pick it up.'],
  ],

  'final_h2'  => 'Tell us which task eats the most time.',
  'final_sub' => 'Two questions to start. If automating it would not pay for itself, we will say so.',
];
