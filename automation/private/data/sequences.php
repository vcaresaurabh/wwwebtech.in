<?php
/* ============================================================
   sequences.php — the seed sequences and their steps (§6.3).

   Offsets are minutes. `business` means the offset is counted in business
   hours (Mon–Sat, 09:30–20:00 IST); wall-clock steps still respect quiet
   hours, they just do not wait for the working day.

   Step 0 is wall-clock and immediate on purpose. The measurable goal of
   this whole build is time-to-first-response in seconds, and every other
   step is negotiable in a way that one is not.
   ============================================================ */

declare(strict_types=1);

return [

  'standard' => [
    'title' => 'Standard follow-up',
    'description' => 'For warm leads. Seven touches over six weeks, closing out honestly at day 14.',
    'steps' => [
      [0, 0,      false, 'email',    'ack',            'Acknowledge their enquiry in their own words. Restate what they asked for, give three specific and genuinely useful points about their situation, offer a call, and end with ONE easy question they can reply to in a sentence.'],
      [1, 15,     false, 'internal', '',               'Nudge the owner if nobody has opened this lead yet. No customer contact.'],
      [2, 180,    true,  'email',    'useful',         'One genuinely useful thing about their specific problem. Not a chase and not a pitch — something they could act on without us. Mention the free website audit as an easy next step.'],
      [3, 1080,   true,  'whatsapp', 'wa_slots',       'A short message offering two concrete times for a call.'],
      [4, 2880,   true,  'email',    'first30',        'What the first 30 days would look like for their specific service. Concrete, week by week, no pricing.'],
      [5, 7200,   true,  'email',    'lower_commit',   'Offer a smaller way in — the free audit, or a short paid piece of work — for someone not ready for the full project.'],
      [6, 14400,  true,  'email',    'closeout',       'A polite close-out. Say plainly that this is the last email, that we are not going to keep chasing, and that they can reply any time and we will pick it straight back up. Warm, not passive-aggressive.'],
      [7, 46080,  true,  'email',    'reactivate',     'A reactivation note six weeks later. One useful thing, no pitch, no reference to them having ignored us.'],
    ],
  ],

  'hot' => [
    'title' => 'Hot lead',
    'description' => 'Faster, call-led. For leads scoring in the hot band.',
    'steps' => [
      [0, 0,      false, 'email',    'ack_hot',        'Acknowledge immediately and lead with a call. They have budget and urgency, so the job of this message is to get a time in the diary, not to educate.'],
      [1, 10,     false, 'internal', '',               'Nudge the owner if nobody has opened this lead after ten minutes.'],
      [2, 120,    true,  'whatsapp', 'wa_slots',       'A short message offering two concrete times for a call.'],
      [3, 1080,   true,  'email',    'first30',        'What the first 30 days would look like for their service.'],
      [4, 4320,   true,  'email',    'closeout',       'A polite close-out, sooner than the standard sequence because urgency has passed.'],
    ],
  ],

  'nurture-light' => [
    'title' => 'Nurture (light)',
    'description' => 'Education-first, no sales pressure. For cold leads and audit-tool signups.',
    'steps' => [
      [0, 0,      false, 'email',    'ack_cold',       'Acknowledge warmly and set no expectation of a sale. Point them at the free audit and one piece of writing relevant to what they asked about. No call ask.'],
      [1, 4320,   true,  'email',    'useful',         'One genuinely useful thing about their problem. No pitch at all.'],
      [2, 14400,  true,  'email',    'lower_commit',   'Offer the free audit again, framed as something to do themselves.'],
      [3, 43200,  true,  'email',    'closeout',       'A gentle close-out. They were only researching, so treat that as fine.'],
    ],
  ],
];
