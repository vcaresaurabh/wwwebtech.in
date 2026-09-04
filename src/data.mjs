/* ============================================================
   Single source of truth for site structure.
   Nav, footer, sitemap, breadcrumbs and internal links all derive
   from here — so a link can never point at a page that isn't built.
   ============================================================ */

export const SITE = {
  name:   'Wwwebtech',
  origin: 'https://wwwebtech.in',
  tagline:'Empowering Indian businesses with world-class IT solutions.',
  email:  'contact@wwwebtech.in',
  phone:  '+91 85952 50209',
  phoneNbsp: '+91\u00a085952\u00a050209',   // display form; keeps the number on one line
  phoneE164: '+918595250209',
  whatsapp:  'https://wa.me/918595250209',
  locality:  'East Delhi',
  region:    'Delhi',
  country:   'IN',
  // Verified from the live site's own Organization schema (not invented).
  linkedin:  'https://www.linkedin.com/company/wwwebtech/',   // [V:9] confirm this is current
  instagram: 'https://www.instagram.com/wwwebtech.in/',       // [V:9] confirm this is current
  gbp:       null,          // [V:10] Google Business Profile URL — footer + LocalBusiness sameAs
  foundingYear: null,       // [V:2] founding year — hero proof line, about, schema foundingDate
  analyticsId:  'G-3EMCNLKC8Q', // GA4 property already on the live site; injection is opt-in, see layout

  /* Where the contact form posts. This is the ONE place it is defined:
     the <form action> is built from it and assets/js/main.js reads the
     action back off the form, so the JavaScript path and the no-JavaScript
     path can never drift apart.

       '/api/lead.php'   — the automation layer: stores the enquiry in MySQL,
                           emails it over authenticated SMTP, and puts it in
                           the admin panel. Requires automation/ to be
                           deployed and the database created first.

     The standalone mailer that used to ship as /contact.php is gone. It was
     dead once the forms moved here, but it stayed live, accepting POSTs from
     anyone and sending mail from the server — an open relay with a rate
     limit. If the automation layer is ever removed, the fallback is the
     503 page api/lead.php shows, which names the address to email. */
  leadEndpoint: '/api/lead.php',
};

/* --- Twelve sub-services (§5). Each links to a page or an in-page
       anchor on its pillar. `phrase` is the exact-match anchor text
       used in the footer and in cross-links (§10). ---------------- */
export const SUBS = {
  websites:  { phrase: 'Custom websites & web platforms',      href: '/services/web-development/#websites',   pillar: 'web' },
  ecommerce: { phrase: 'eCommerce development',                href: '/services/web-development/#ecommerce',  pillar: 'web' },
  crm:       { phrase: 'CRM systems for small business',       href: '/services/crm-systems/',                pillar: 'web' },
  automation:{ phrase: 'Business automation & integrations',   href: '/services/business-automation/',        pillar: 'web' },

  techseo:   { phrase: 'Technical SEO & Core Web Vitals',      href: '/services/seo/#technical',              pillar: 'seo' },
  content:   { phrase: 'Content & on-page SEO',                href: '/services/seo/#content',                pillar: 'seo' },
  local:     { phrase: 'Local SEO & Google Business Profile',  href: '/services/seo/#local',                  pillar: 'seo' },
  geo:       { phrase: 'GEO & AEO — visibility in AI search',  href: '/services/ai-visibility-geo/',          pillar: 'seo' },

  organic:   { phrase: 'Organic social media management',      href: '/services/social-media-marketing/#organic', pillar: 'social' },
  paid:      { phrase: 'Paid social — Meta & Instagram ads',   href: '/services/social-media-marketing/#paid',    pillar: 'social' },
  socialseo: { phrase: 'Social SEO — Instagram & YouTube',     href: '/services/social-media-marketing/#social-seo', pillar: 'social' },
  creative:  { phrase: 'Creative & short-form video',          href: '/services/social-media-marketing/#creative', pillar: 'social' },
};

/* --- Three pillars ------------------------------------------- */
export const PILLARS = [
  {
    key: 'web', index: '01',
    name: 'Web Development & Systems',
    short: 'Web & Systems',
    href: '/services/web-development/',
    one: 'Fast, secure websites and the business systems behind them — built to be maintained, not rebuilt every two years.',
    subs: ['websites', 'ecommerce', 'crm', 'automation'],
    icon: 'websites',
  },
  {
    key: 'seo', index: '02',
    name: 'SEO & AI Visibility',
    short: 'SEO & AI Visibility',
    href: '/services/seo/',
    one: 'Rank where your customers look now: Google, Maps, and the AI answers in ChatGPT, Gemini and AI Overviews.',
    subs: ['techseo', 'content', 'local', 'geo'],
    icon: 'techseo',
  },
  {
    key: 'social', index: '03',
    name: 'Social Media Marketing',
    short: 'Social Media',
    href: '/services/social-media-marketing/',
    one: 'Content and campaigns on Instagram, YouTube and Meta that feed search, retargeting, and your pipeline — not vanity metrics.',
    subs: ['organic', 'paid', 'socialseo', 'creative'],
    icon: 'creative',
  },
];

/* --- Blog ----------------------------------------------------- */
export const POSTS = [
  {
    slug: 'website-speed-india',
    title: 'Why your business website feels slow in India — and the 7 fixes that actually matter',
    dek:  'Core Web Vitals, explained for the person paying the invoice. What actually moves the needle on an Indian mobile network — and what is a waste of money.',
    date: '2026-08-04', read: 9, service: '/services/seo/#technical',
  },
  {
    slug: 'chatgpt-ai-search-visibility',
    title: 'ChatGPT is answering your customers’ questions. Is your business in the answer?',
    dek:  'How AI Overviews, ChatGPT and Gemini choose which businesses to name — and the five things to fix on your site before your competitor does.',
    date: '2026-07-21', read: 10, service: '/services/ai-visibility-geo/',
  },
  {
    slug: 'instagram-social-seo-india',
    title: 'Instagram is a search engine now: Social SEO for Indian businesses',
    dek:  'Captions, alt text, geo tags and Reels retention are ranking inputs. A 30-day plan to make your social account findable instead of just pretty.',
    date: '2026-07-02', read: 8, service: '/services/social-media-marketing/#social-seo',
  },
];

/* --- Footer --------------------------------------------------- */
export const FOOTER_COMPANY = [
  { label: 'About',    href: '/about/' },
  { label: 'Work',     href: '/work/' },
  { label: 'Blog',     href: '/blog/' },
  { label: 'Free website audit', href: '/tools/free-website-audit/' },
  { label: 'Contact',  href: '/contact/' },
  { label: 'Privacy policy',    href: '/legal/privacy/' },
  { label: 'Terms of service',  href: '/legal/terms/' },
];

/* --- Old → new URL map (§3). Drives .htaccess and _redirects. -- */
/* Pages the static build does not produce but the live site serves — the
   automation layer's PHP front controllers. The build's link checker admits
   them, the sitemap lists the ones worth finding, and the QA gate agrees,
   because all three read this one list. `servedBy` is verified to exist at
   build time: an entry with no controller behind it is a 404 nothing catches. */
export const SERVER_PAGES = [
  { path: '/tools/free-website-audit/', servedBy: 'automation/webroot/tools/index.php',
    inSitemap: true,  priority: '0.8' },
  { path: '/lp/',                       servedBy: 'automation/webroot/lp/index.php',
    inSitemap: false },   // campaign pages: reached from ads, not from search
];

export const REDIRECTS = [
  ['/services/website-development', '/services/web-development/'],
  ['/services/crm-systems',         '/services/crm-systems/'],
  ['/services/business-automation', '/services/business-automation/'],
  ['/services/technical-support',   '/services/technical-support/'],
  ['/about',    '/about/'],
  ['/services', '/services/'],
  ['/contact',  '/contact/'],
];

export const sub = (k) => SUBS[k];
export const pillarOf = (k) => PILLARS.find(p => p.key === SUBS[k].pillar);
