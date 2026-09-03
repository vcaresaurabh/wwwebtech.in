/* ============================================================
   One drawn SVG per blog post. Same visual language as the machine
   diagram: hairlines, ink, one marigold accent. Themed by CSS vars,
   captioned, and described for screen readers.
   ============================================================ */

const wrap = (id, title, desc, inner, vb = '0 0 720 300') => `
<svg class="diagram" viewBox="${vb}" role="img" aria-labelledby="${id}-t ${id}-d">
  <title id="${id}-t">${title}</title>
  <desc id="${id}-d">${desc}</desc>
  ${inner}
</svg>`;

/* --- Post 1: the three Core Web Vitals, and where the lines are ----- */
export const cwvGauge = (id = 'cwv') => {
  const bars = [
    { label: 'LCP', sub: 'Largest Contentful Paint', unit: 'seconds',
      stops: ['0', '2.5', '4.0', '6+'], good: 0.42, need: 0.25 },
    { label: 'INP', sub: 'Interaction to Next Paint', unit: 'milliseconds',
      stops: ['0', '200', '500', '800+'], good: 0.42, need: 0.25 },
    { label: 'CLS', sub: 'Cumulative Layout Shift', unit: 'score',
      stops: ['0', '0.1', '0.25', '0.5+'], good: 0.42, need: 0.25 },
  ];
  const W = 640, X = 56;
  const inner = bars.map((b, i) => {
    const y = 46 + i * 82;
    const gw = W * b.good, nw = W * b.need, pw = W - gw - nw;
    return `
    <text class="dg-ink" x="0" y="${y - 8}" font-family="var(--font-mono)" font-size="12" font-weight="500" letter-spacing="1">${b.label}</text>
    <text class="dg-mut" x="34" y="${y - 8}" font-family="var(--font-text)" font-size="11">${b.sub}</text>
    <rect class="dg-fill" x="${X}" y="${y}" width="${gw}" height="10" rx="2"/>
    <rect class="dg-fill" x="${X + gw + 3}" y="${y}" width="${nw}" height="10" rx="2" opacity=".45"/>
    <rect class="dg-box" x="${X + gw + nw + 6}" y="${y}" width="${pw - 6}" height="10" rx="2"/>
    <text class="dg-mut" x="${X}" y="${y + 26}" font-family="var(--font-mono)" font-size="10">${b.stops[0]}</text>
    <text class="dg-ink" x="${X + gw}" y="${y + 26}" font-family="var(--font-mono)" font-size="10" text-anchor="middle" font-weight="500">${b.stops[1]}</text>
    <text class="dg-mut" x="${X + gw + nw}" y="${y + 26}" font-family="var(--font-mono)" font-size="10" text-anchor="middle">${b.stops[2]}</text>
    <text class="dg-mut" x="${X + W}" y="${y + 26}" font-family="var(--font-mono)" font-size="10" text-anchor="end">${b.stops[3]}</text>`;
  }).join('');

  const key = `
    <g transform="translate(56 272)">
      <rect class="dg-fill" x="0" y="0" width="10" height="10" rx="2"/>
      <text class="dg-mut" x="16" y="9" font-family="var(--font-text)" font-size="11">Good</text>
      <rect class="dg-fill" x="66" y="0" width="10" height="10" rx="2" opacity=".45"/>
      <text class="dg-mut" x="82" y="9" font-family="var(--font-text)" font-size="11">Needs work</text>
      <rect class="dg-box" x="176" y="0" width="10" height="10" rx="2"/>
      <text class="dg-mut" x="192" y="9" font-family="var(--font-text)" font-size="11">Poor — this is where you lose people</text>
    </g>`;

  return wrap(id, 'The three Core Web Vitals and their thresholds',
    'LCP should be under 2.5 seconds, INP under 200 milliseconds, and CLS under 0.1. Beyond roughly 4 seconds, 500 milliseconds and 0.25 respectively, Google classes the experience as poor.',
    inner + key, '0 0 720 300');
};

/* --- Post 2: how an answer engine decides who to name -------------- */
export const aiSources = (id = 'ai') => {
  const src = [
    { t: 'Your website', s: 'clear, structured, current' },
    { t: 'Your Google profile', s: 'claimed, categorised, reviewed' },
    { t: 'Third-party mentions', s: 'directories, press, listings' },
  ];
  const inner = `
    ${src.map((s, i) => `
      <rect class="dg-box" x="0" y="${18 + i * 82}" width="196" height="60" rx="4"/>
      <text class="dg-ink" x="16" y="${44 + i * 82}" font-family="var(--font-text)" font-size="13" font-weight="600">${s.t}</text>
      <text class="dg-mut" x="16" y="${62 + i * 82}" font-family="var(--font-text)" font-size="11">${s.s}</text>
      <path class="dg-flow" marker-end="url(#${id}-h)" d="M204 ${48 + i * 82} C248 ${48 + i * 82} 250 150 286 150"/>`).join('')}

    <rect class="dg-box" x="294" y="106" width="150" height="88" rx="4"/>
    <text class="dg-ink" x="310" y="140" font-family="var(--font-display)" font-size="17" font-weight="500">The model</text>
    <text class="dg-mut" x="310" y="160" font-family="var(--font-text)" font-size="11">cross-checks the three</text>
    <text class="dg-mut" x="310" y="176" font-family="var(--font-text)" font-size="11">for agreement</text>

    <path class="dg-flow" marker-end="url(#${id}-h)" d="M452 150 L494 150"/>

    <rect class="dg-wash" x="502" y="86" width="196" height="128" rx="4"/>
    <text class="dg-ink" x="518" y="118" font-family="var(--font-display)" font-size="17" font-weight="500">The answer</text>
    <text class="dg-mut" x="518" y="144" font-family="var(--font-text)" font-size="12">names three or four</text>
    <text class="dg-mut" x="518" y="162" font-family="var(--font-text)" font-size="12">businesses, then stops</text>
    <text class="dg-ink" x="518" y="192" font-family="var(--font-mono)" font-size="11" letter-spacing="1">NO PAGE TWO</text>

    <text class="dg-mut" x="360" y="248" font-family="var(--font-text)" font-size="12" text-anchor="middle">
      Where the three disagree, the model hedges — and hedging means naming someone else.</text>
    <defs><marker id="${id}-h" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
      <path d="M0 1.5 8.5 5 0 8.5" class="dg-flow" stroke-linejoin="round"/></marker></defs>`;

  return wrap(id, 'How an AI answer engine decides which businesses to name',
    'Your website, your Google Business Profile and third-party mentions are cross-checked against each other. Where they agree, the model is confident enough to name you. Where they disagree, it names a competitor instead.',
    inner, '0 0 720 268');
};

/* --- Post 3: the loop between social and search -------------------- */
export const socialLoop = (id = 'loop') => {
  const nodes = [
    { x: 360, y: 40,  t: 'Someone sees a reel',      s: 'discovery' },
    { x: 610, y: 128, t: 'They search your name',    s: 'branded search on Google' },
    { x: 512, y: 232, t: 'They land on your site',   s: 'and it loads fast' },
    { x: 208, y: 232, t: 'They enquire',             s: 'DM, WhatsApp or form' },
    { x: 110, y: 128, t: 'They become a customer',   s: 'and leave a review' },
  ];
  const inner = `
    ${nodes.map((n, i) => `
      <g transform="translate(${n.x - 84} ${n.y - 22})">
        <rect class="dg-box" width="168" height="46" rx="4"/>
        <text class="dg-ink" x="12" y="20" font-family="var(--font-text)" font-size="12" font-weight="600">${n.t}</text>
        <text class="dg-mut" x="12" y="35" font-family="var(--font-text)" font-size="10">${n.s}</text>
      </g>`).join('')}
    <g class="dg-flow" marker-end="url(#${id}-h)">
      <path d="M448 52 C520 62 566 88 588 108"/>
      <path d="M604 156 C588 190 566 208 552 216"/>
      <path d="M420 244 L302 244"/>
      <path d="M132 214 C112 194 100 176 98 156"/>
      <path d="M116 100 C140 70 200 48 268 42"/>
    </g>
    <text class="dg-mut" x="360" y="146" font-family="var(--font-mono)" font-size="11" letter-spacing="1"
          text-anchor="middle">ONE CUSTOMER,</text>
    <text class="dg-mut" x="360" y="164" font-family="var(--font-mono)" font-size="11" letter-spacing="1"
          text-anchor="middle">LOOKING IN DIFFERENT PLACES</text>
    <defs><marker id="${id}-h" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
      <path d="M0 1.5 8.5 5 0 8.5" class="dg-flow" stroke-linejoin="round"/></marker></defs>`;

  return wrap(id, 'The loop between social media and search',
    'Someone sees a reel, searches your business name on Google, lands on your site, enquires, becomes a customer, and leaves a review that feeds social again. Social and search are the same customer looking in different places.',
    inner, '0 0 720 290');
};

export const DIAGRAMS = {
  'website-speed-india': cwvGauge,
  'chatgpt-ai-search-visibility': aiSources,
  'instagram-social-seo-india': socialLoop,
};
