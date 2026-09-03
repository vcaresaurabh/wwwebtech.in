/* /legal/privacy/ and /legal/terms/

   [V:12] The previous site had no privacy or terms pages to port, so these are
   written from scratch to describe what this site actually does. They are honest
   and specific rather than boilerplate — but they are not legal advice, and the
   owner should have them reviewed before relying on them. Every place that needs
   a decision is marked in a comment.                                            */
import { SITE } from '../data.mjs';
import { phero } from '../partials/pagekit.mjs';
import * as S from '../schema.mjs';

const UPDATED = '2026-09-03';   // rewritten for the follow-up funnel, the audit tool and offline conversions
/* Derived, not typed twice. The machine-readable date and the one people
   read had already drifted apart once. */
const fmt = new Date(UPDATED + 'T00:00:00Z').toLocaleDateString('en-GB',
  { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });

const shell = ({ path, title, desc, h1, sub, html }) => ({
  path, nav: null, og: 'home', title, desc,
  schema: S.graph([
    S.organization(),
    S.webpage({ url: path, title, desc }),
    S.breadcrumbs([{ label: 'Home', href: '/' }, { label: h1 }]),
  ]),
  render: () => `
${phero({
  trail: [{ label: 'Home', href: '/' }, { label: h1 }],
  eyebrow: 'Legal', h1, sub, ctas: false,
})}
<section class="section">
  <div class="shell">
    <div class="prose">
      <p class="u-mono">Last updated: <time datetime="${UPDATED}">${fmt}</time></p>
      ${html}
      <hr>
      <p class="u-small u-ink3">Questions about this page? Email
        <a class="u-link" href="mailto:${SITE.email}">${SITE.email}</a> and a person will answer.</p>
    </div>
  </div>
</section>`,
});

/* ============================================================ */
const privacy = shell({
  path: '/legal/privacy/',
  title: 'Privacy policy | Wwwebtech',
  desc: 'What Wwwebtech collects when you use this website, why, how long we keep it, and how to have it deleted. Short, specific and in plain English.',
  h1: 'Privacy policy',
  sub: 'What this website collects, why, and how to make us delete it. Written to be read rather than to be survived.',
  html: `
    <h2>The short version</h2>
    <p>This site collects what you type into its forms, and a record of which pages were visited.
      We do not sell data, we do not share it with advertisers, and we do not run cross-site
      tracking. If you tick the box on a form, you may get a small number of follow-up messages
      about that enquiry; one word back stops them for good.</p>

    <h2>What we collect</h2>
    <ul>
      <li><strong>What you send us.</strong> If you use a form, WhatsApp or email, we receive your
        name, email address, phone number if you give one, the budget range and service options you
        select, your message, and — on the campaign pages — how soon you need the work and whether
        you already have a website.</li>
      <li><strong>The free website audit.</strong> If you use it, we fetch the public page at the
        address you give us, measure it, and email you the result. We store the address, your name
        and email, and the findings. We do not crawl the rest of the site and we do not keep a copy
        of your pages.</li>
      <li><strong>Where you arrived from.</strong> The page you landed on, the site that referred
        you, and any campaign tags in the link — including the click identifier an ad platform adds
        when you arrive from an advertisement.</li>
      <li><strong>How the visit went.</strong> Which pages were seen and roughly how long you spent
        before submitting. This is recorded on our own server, not in your browser.</li>
      <li><strong>Server logs.</strong> Our hosting provider records standard web-server
        information — IP address, browser type, pages requested, timestamps. This is generated
        automatically by the server, is used for security and troubleshooting, and is retained
        according to our host's own policy.</li>
    </ul>
    <p>Your IP address is truncated before we store it — the last part is discarded, so the record
      identifies a network rather than a device.</p>

    <h2>Analytics and cookies</h2>
    <!-- If the owner enables a third-party tag from the panel's Integrations page,
         this section must name it and a cookie notice becomes necessary. The panel
         says so at the point of the decision. -->
    <p>This website loads no third-party analytics or advertising scripts, so nothing about you is
      sent to another company as you browse. Visits are counted on our own server instead.</p>
    <p>The campaign pages under <code>/lp/</code> set one cookie, named <code>wwtv_</code> followed
      by the page name. It stores a single letter and exists so that if we are testing two versions
      of a page you keep seeing the one you saw first. It expires after thirty days, holds nothing
      that identifies you, and is not read by anyone else.</p>

    <h2>Follow-up messages</h2>
    <p>If you tick the consent box on a form, we may send you a short series of follow-up messages
      about that enquiry — by email, and by WhatsApp if you gave a number. Some of them are drafted
      with the help of an AI model and reviewed by a person before they are sent.</p>
    <p>They stop automatically and immediately if you reply, if you book a call, or if you ask us to
      stop. Every email carries a one-click unsubscribe link, and replying with the word "stop" does
      the same thing. Nothing is sent between 9pm and 9am, and never more than two messages a day.</p>
    <p>We do not add you to a newsletter, and there is nothing to opt out of separately — this is
      about your enquiry and it ends when your enquiry does.</p>

    <h2>Why we hold what you send</h2>
    <p>To reply to you and, if we work together, to deliver and support that work. That is the whole
      purpose. We do not add enquiries to a marketing list and we do not use them to advertise to
      you elsewhere.</p>

    <h2>Who else sees it</h2>
    <p>Enquiries are handled by the Wwwebtech team. Your information is processed by the services we
      use to run the business, each only to the extent needed:</p>
    <ul>
      <li><strong>Hostinger</strong> — web and email hosting. Everything above is stored here, in a
        database we control.</li>
      <li><strong>Anthropic</strong> — the AI model that drafts follow-up messages. It receives your
        name, what you asked about, and what has already been said to you. It does not receive your
        email address, phone number or IP address.</li>
      <li><strong>Meta</strong> — only if you gave a phone number and we message you on WhatsApp.</li>
      <li><strong>Google and Microsoft advertising</strong> — if you arrived from one of their ads
        and later became a customer, we tell them that the click became a sale, so that their system
        stops spending our money on the wrong clicks. Where a click identifier exists, that is all we
        send. Where it does not, we send a one-way cryptographic hash of your email address and phone
        number — never the address or number itself, and nothing that can be turned back into them.</li>
    </ul>
    <p>We do not sell your information, and we do not pass it to anyone for their own marketing.</p>

    <h2>How long we keep it</h2>
    <p>Enquiries that do not become projects are deleted within twelve months. Individual visit
      records are deleted after ninety days by default; only the daily totals are kept beyond that.
      Records relating to actual client work are kept for as long as we work together and afterwards
      for the period our accountant and Indian tax law require.</p>

    <h2>Your choices</h2>
    <p>You can ask us what we hold about you, ask for it to be corrected, or ask us to delete it.
      Email <a class="u-link" href="mailto:${SITE.email}">${SITE.email}</a> and we will action it
      within thirty days. You do not need to give a reason.</p>
    <p>To stop follow-up messages without contacting us at all: use the unsubscribe link at the
      bottom of any of them, or reply with the word "stop". Both take effect immediately and cover
      every channel, not just the one you used.</p>

    <h2>Clients: your data is yours</h2>
    <p>If we build a website, CRM or automation for you, the data inside it belongs to you, sits in
      accounts registered in your name, and is exportable at any time. We hold access in order to do
      the work and for no other purpose. If we stop working together, we hand over and remove our
      access.</p>

    <h2>Security</h2>
    <p>The site is served over HTTPS. Access to enquiry data is limited to the people who need it.
      No system is perfectly secure, and we will not pretend otherwise — but we will tell you
      promptly if something happens that affects your information.</p>

    <h2>Changes</h2>
    <p>If this policy changes we will update the date at the top. Material changes will be
      described rather than quietly folded in.</p>

    <h2>Contact</h2>
    <p>Wwwebtech, ${SITE.locality}, ${SITE.region}, India.
      <a class="u-link" href="mailto:${SITE.email}">${SITE.email}</a> ·
      <a class="u-link" href="tel:${SITE.phoneE164}">${SITE.phoneNbsp}</a></p>`,
});

/* ============================================================ */
const terms = shell({
  path: '/legal/terms/',
  title: 'Terms of service | Wwwebtech',
  desc: 'The terms covering this website and Wwwebtech engagements: scope, fixed quotes, ownership of code and accounts, support response times and cancellation.',
  h1: 'Terms of service',
  sub: 'The rules for using this site, and the principles behind every engagement. The specifics of your project live in your written scope, which wins over this page.',
  html: `
    <h2>About these terms</h2>
    <p>These terms cover your use of wwwebtech.in. Client work is governed by the written scope and
      quote we agree with you. Where the two differ, <strong>your signed scope takes
      precedence</strong> over anything on this page.</p>

    <h2>Using this website</h2>
    <p>You are welcome to read, quote and link to anything here. Please do not copy substantial
      parts of the writing and publish it as your own; a link back is all we ask. The Wwwebtech name
      and logo are ours.</p>
    <p>Do not attempt to disrupt the site, probe it for vulnerabilities without asking, or use the
      contact form to send bulk or automated messages. If you have found a genuine security issue,
      please tell us at <a class="u-link" href="mailto:${SITE.email}">${SITE.email}</a> — we will
      thank you properly.</p>

    <h2>What is on this site is not a quote</h2>
    <p>Descriptions of our services are exactly that. Nothing here forms a contract or a binding
      price. A quote is binding only when it is in writing, addressed to you, and describes a
      specific scope.</p>

    <h2>How we work — the commitments behind every engagement</h2>
    <ul>
      <li><strong>Written scope, fixed quote.</strong> You get both before you pay anything. If the
        scope changes, we quote the change and wait for your agreement before doing it.</li>
      <li><strong>You own everything.</strong> Domain, code, content, hosting, ad accounts and CRM
        data are registered in your name from the start and stay that way. We do not hold assets or
        logins as leverage.</li>
      <li><strong>A reply within one business day.</strong> On active projects and on support plans.
        This is the commitment we would most like to be judged on.</li>
      <li><strong>No guarantees of search rankings.</strong> Nobody controls Google's index. We
        commit to the work and to honest reporting, not to a position.</li>
    </ul>

    <h2>Payment and cancellation</h2>
    <p>Project payment schedules are set out in your quote — typically staged against milestones.
      Support and retainer plans are billed monthly and can be cancelled with reasonable notice as
      set out in your agreement. On cancellation you keep everything: accounts, code, content and
      backups.</p>
    <!-- [V:12] Confirm the actual notice period and payment stages with the owner,
         then state them explicitly here rather than referring out to the quote. -->

    <h2>Third-party services</h2>
    <p>Projects often involve services we do not control — hosting, payment gateways, Google, Meta,
      WhatsApp. We will advise on choices and configure things properly, but we cannot be
      responsible for their outages, pricing changes or policy decisions.</p>

    <h2>Liability</h2>
    <p>We will do the work with reasonable skill and care, and fix our own mistakes. We are not
      liable for indirect or consequential losses such as lost profits, and our total liability for
      any engagement is limited to the fees you paid us for it. Nothing here limits liability that
      cannot lawfully be limited.</p>

    <h2>Governing law</h2>
    <p>These terms are governed by the laws of India, and the courts at Delhi have jurisdiction.</p>

    <h2>Contact</h2>
    <p>Wwwebtech, ${SITE.locality}, ${SITE.region}, India.
      <a class="u-link" href="mailto:${SITE.email}">${SITE.email}</a></p>`,
});

export const pages = [privacy, terms];
