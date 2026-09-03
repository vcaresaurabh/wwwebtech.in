/* ============================================================
   PROOF FLAGS  —  the honesty switch.
   ============================================================

   The site ships with zero fabricated proof. Sections that need real
   client evidence are built and waiting behind these flags; until a
   flag is true, an honest fallback renders in its place.

   HOW TO SWITCH ONE ON
   --------------------
     1. Fill in the real data below.
     2. Set its flag to true.
     3. Run `node build.mjs` and re-upload the site/ folder.

   These flags are read at BUILD time, not in the browser, so the
   sections they control are real HTML that works with JavaScript
   turned off and is visible to Google. Nothing here is ever fetched
   by the live page.

   Never put a number, name, quote or logo in this file that the
   client has not actually given you, in writing, with permission.
   ============================================================ */

export const PROOF = {
  clients: false,   // [V:3]  homepage section 04 — the client logo wall
  work:    false,   // [V:6]  homepage section 08 + /work/ — real case studies
  quotes:  false,   // [V:7]  homepage section 11 — named testimonials
};

/* --- [V:1] Projects shipped. A whole number, e.g. 40.
       Used in the hero proof line and the stat row. null hides both. */
export const PROJECTS = null;

/* --- [V:2] The year Wwwebtech started, e.g. 2019.
       Used in the hero proof line, /about/, and schema foundingDate.
       (Also set foundingYear in src/data.mjs to the same value.) */
export const FOUNDED = null;

/* --- [V:4] Honest typical launch window, in weeks. e.g. '4-6'.
       Used in the stat row and the "how long" objection card.
       A range is fine and reads more honestly than a single number. */
export const LAUNCH_WEEKS = null;

/* --- [V:5] Percentage of clients still with you after a year, e.g. 85.
       Only fill this in if you can actually count it. null shows the
       "100% client-owned code & accounts" promise-stat instead. */
export const RETENTION = null;

/* --- [V:3] Client logo wall. Needs written permission per logo.
       file: an SVG dropped into site/assets/img/clients/
       Set PROOF.clients = true once there are at least six. */
export const CLIENTS = [
  // { name: 'Acme Traders', file: 'acme.svg', url: 'https://…' },
];

/* --- [V:6] Case studies. ONE number each, and it must be measured.
       result: the headline outcome. Write it as a real sentence.
       Set PROOF.work = true once there are at least three. */
export const CASES = [
  // {
  //   slug:     'acme-traders',
  //   client:   'Acme Traders',
  //   result:   '3× more enquiries in 90 days',
  //   service:  'Website + Local SEO',
  //   timeframe:'90 days',
  //   challenge:'What was broken when they came to us.',
  //   built:    'What we actually built.',
  //   stack:    ['Static site', 'Custom CRM', 'GBP'],
  // },
];

/* --- [V:7] Testimonials. Name AND role AND company, or leave it out.
       An unattributed quote is not proof; it is decoration.
       Set PROOF.quotes = true once there are at least two. */
export const QUOTES = [
  // { text: '…', name: 'Full Name', role: 'Founder', company: 'Acme Traders' },
];

/* --- [V:8] Credentials. Delete any line that is not literally true today.
       These render as plain mono text chips, never as badge artwork,
       because badge artwork implies a certification we may not hold. */
export const CREDENTIALS = [
  'Google Business Profile',      // verify the profile is claimed + verified
  'GSTIN-registered',             // verify the business is GST registered
  // 'Meta Business Partner-track',
  // 'Clutch profile',
];
