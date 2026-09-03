/* /blog/ */
import { SITE, POSTS } from '../data.mjs';
import { ctaBand } from '../partials/chrome.mjs';
import { postCard, shead } from '../partials/blocks.mjs';
import { phero } from '../partials/pagekit.mjs';
import * as S from '../schema.mjs';

const title = 'Blog — practical notes on web, search and social';
const desc  = 'Plain-English writing on website speed in India, AI search visibility, and social SEO. Written for the person paying the invoice, not for other agencies.';

export const page = {
  path: '/blog/', nav: 'blog', og: 'blog', title, desc,
  schema: S.graph([
    S.organization(),
    S.webpage({ url: '/blog/', title, desc }),
    {
      '@type': 'Blog', '@id': SITE.origin + '/blog/#blog',
      name: 'The Wwwebtech blog', url: SITE.origin + '/blog/',
      publisher: { '@id': SITE.origin + '/#organization' },
      blogPost: POSTS.map(p => ({
        '@type': 'BlogPosting', headline: p.title, url: SITE.origin + '/blog/' + p.slug + '/',
        datePublished: p.date, description: p.dek,
      })),
    },
    S.breadcrumbs([{ label: 'Home', href: '/' }, { label: 'Blog' }]),
  ]),
  render: () => [
    phero({
      trail: [{ label: 'Home', href: '/' }, { label: 'Blog' }],
      eyebrow: 'Writing',
      h1: 'We sell SEO, so we publish',
      sub: `An agency that sells search and has nothing for you to read is asking you to take its
            word for it. These are written for the person paying the invoice — no jargon you have
            to keep, and nothing you could not check yourself.`,
      ctas: false,
    }),

    `<section class="section" aria-labelledby="posts-h">
      <div class="shell">
        <h2 class="visually-hidden" id="posts-h">Latest posts</h2>
        <div class="grid" style="--cols-sm:2;--cols-md:3">
          <!--BLOG_TEASERS_START-->
          ${POSTS.map(postCard).join('')}
          <!--BLOG_TEASERS_END-->
        </div>
      </div>
    </section>`,

    `<section class="section section--ruled">
      <div class="shell">
        <div class="railed railed--wide">
          <div class="rail">${shead({ label: 'How we write', title: 'Three rules for this blog.' })}</div>
          <div class="prose">
            <ul>
              <li><strong>Nothing you can’t check.</strong> If we make a claim about how something
                works, you should be able to verify it yourself in ten minutes. Where something is
                genuinely uncertain — and a lot of AI search currently is — we say so.</li>
              <li><strong>Written for owners, not for other agencies.</strong> No acronym goes past
                without a plain-English translation, and we’d rather explain one thing properly than
                list ten.</li>
              <li><strong>Including what not to buy.</strong> Every post has a section on the things
                that get sold in this category and don’t work. Some of them are things we could
                happily charge you for.</li>
            </ul>
          </div>
        </div>
      </div>
    </section>`,

    ctaBand({
      h2: 'Got a question we haven’t answered?',
      sub: 'Ask it. If it’s a good one we’ll answer properly, and it’ll probably become the next post.',
    }),
  ].join('\n'),
};
