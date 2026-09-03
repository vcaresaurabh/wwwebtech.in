/* ============================================================
   JSON-LD. Only facts we can stand behind.
   No aggregateRating, no review markup, no invented counts —
   that would be both a fabrication and a manual-action risk. (§10)
   ============================================================ */
import { SITE, PILLARS, SUBS } from './data.mjs';

const ORG_ID  = SITE.origin + '/#organization';
const SITE_ID = SITE.origin + '/#website';

const sameAs = [SITE.linkedin, SITE.instagram, SITE.gbp].filter(Boolean);

export const organization = () => ({
  '@type': ['Organization', 'ProfessionalService', 'LocalBusiness'],
  '@id': ORG_ID,
  name: SITE.name,
  url: SITE.origin + '/',
  logo: { '@type': 'ImageObject', url: SITE.origin + '/assets/img/logo.svg' },
  image: SITE.origin + '/og/home.png',
  description: 'Web development, custom CRM systems, SEO, AI search visibility and social media marketing for growing Indian businesses. Based in East Delhi, serving all of India.',
  email: SITE.email,
  telephone: SITE.phone,
  address: {
    '@type': 'PostalAddress',
    addressLocality: SITE.locality,
    addressRegion: SITE.region,
    addressCountry: SITE.country,
  },
  areaServed: { '@type': 'Country', name: 'India' },
  // [V:2] foundingYear in src/data.mjs adds foundingDate here.
  ...(SITE.foundingYear ? { foundingDate: String(SITE.foundingYear) } : {}),
  ...(sameAs.length ? { sameAs } : {}),
  knowsAbout: [
    'Web development', 'Custom CRM systems', 'Business automation',
    'Technical SEO', 'Core Web Vitals', 'Local SEO',
    'Generative Engine Optimization', 'Answer Engine Optimization',
    'Social media marketing',
  ],
});

export const website = () => ({
  '@type': 'WebSite',
  '@id': SITE_ID,
  url: SITE.origin + '/',
  name: SITE.name,
  publisher: { '@id': ORG_ID },
  inLanguage: 'en-IN',
});

export const service = ({ name, description, url, subs = [] }) => ({
  '@type': 'Service',
  name,
  description,
  url: SITE.origin + url,
  serviceType: name,
  provider: { '@id': ORG_ID },
  areaServed: { '@type': 'Country', name: 'India' },
  ...(subs.length ? {
    hasOfferCatalog: {
      '@type': 'OfferCatalog',
      name,
      itemListElement: subs.map(k => ({
        '@type': 'Offer',
        itemOffered: { '@type': 'Service', name: SUBS[k].phrase, url: SITE.origin + SUBS[k].href.split('#')[0] },
      })),
    },
  } : {}),
});

export const faqPage = (faqs) => ({
  '@type': 'FAQPage',
  mainEntity: faqs.map(f => ({
    '@type': 'Question',
    name: f.q,
    acceptedAnswer: { '@type': 'Answer', text: f.aText || f.a.replace(/<[^>]+>/g, '') },
  })),
});

export const breadcrumbs = (trail) => ({
  '@type': 'BreadcrumbList',
  itemListElement: trail.map((t, i) => ({
    '@type': 'ListItem', position: i + 1, name: t.label,
    ...(t.href ? { item: SITE.origin + t.href } : {}),
  })),
});

export const article = (p) => ({
  '@type': 'Article',
  headline: p.title,
  description: p.dek,
  datePublished: p.date,
  dateModified: p.modified || p.date,
  // [V:11] Swap to the real author once the team block exists.
  author: { '@type': 'Organization', name: 'Wwwebtech Team', url: SITE.origin + '/about/' },
  publisher: { '@id': ORG_ID },
  mainEntityOfPage: { '@type': 'WebPage', '@id': SITE.origin + '/blog/' + p.slug + '/' },
  image: SITE.origin + '/og/blog-' + p.slug + '.png',
  inLanguage: 'en-IN',
  timeRequired: 'PT' + p.read + 'M',
});

export const webpage = ({ url, title, desc }) => ({
  '@type': 'WebPage',
  '@id': SITE.origin + url + '#webpage',
  url: SITE.origin + url,
  name: title,
  description: desc,
  isPartOf: { '@id': SITE_ID },
  about: { '@id': ORG_ID },
  inLanguage: 'en-IN',
});

/** Assemble one @graph for a page. */
export const graph = (nodes) =>
  JSON.stringify({ '@context': 'https://schema.org', '@graph': nodes.filter(Boolean) });
