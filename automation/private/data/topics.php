<?php
/* ============================================================
   topics.php — the seed topic bank. 90 titles, 30 per cluster.

   Each entry is a title seed and an ANGLE. The angle is the important
   half: it tells the writer what the piece has to argue, which is what
   stops ninety posts turning into ninety variations of "what is SEO".

   Rules these were written to, and that any addition should follow:
     · A question a real Indian small-business owner would actually ask.
     · Answerable from reasoning and publicly known mechanics — never
       from statistics we would have to invent.
     · Specific enough that two topics cannot produce the same article.

   Seeded into wwt_topics on first run. Editing this file does not change
   topics already in the database; add new ones in the panel.
   ============================================================ */

declare(strict_types=1);

return [

/* ── web ────────────────────────────────────────────────────
   Websites, ecommerce, CRM, automation. The buyer is deciding
   what to build and what it should cost. */
'web' => [
  ['How much should a business website cost in India in 2026?', 'Break the price into what is actually being bought — design, build, content, and the ongoing part nobody quotes for. Explain why the same brief returns quotes an order of magnitude apart, and what a low quote is usually leaving out.'],
  ['WordPress or a custom-built site: how to decide, honestly', 'Neither is the right answer for everyone. Give the decision rule: who maintains it, how often it changes, what it has to integrate with. Be clear where WordPress genuinely wins.'],
  ['What a website redesign actually involves, week by week', 'A realistic timeline for a small business, including the parts that always slip — content, approvals, and the client-side work nobody budgets time for.'],
  ['Why your website is slow on a mid-range Android and fast on your laptop', 'Devices, not connections, are usually the culprit. Explain processor-bound JavaScript in plain terms and what to cut.'],
  ['The five pages every small-business website needs before anything else', 'Argue from what a buyer needs to decide, not from a template. Explain what each page has to prove.'],
  ['Should your business build an app, or a better mobile site?', 'The honest answer is usually the site. Give the conditions under which an app earns its cost.'],
  ['Who owns your website? The question to ask before you sign', 'Domain, hosting, code, content and analytics can each be held by someone else. What to ask for in writing.'],
  ['What "responsive design" is supposed to mean, and how it goes wrong', 'Shrinking is not designing. Show the difference with the decisions that actually change on a small screen.'],
  ['Shared hosting, VPS or managed: what an Indian small business actually needs', 'Match hosting to real traffic and real risk. Explain what you are paying for at each step.'],
  ['Why your contact form is losing enquiries without telling you', 'Silent failure modes: spam filters, missing SPF, JavaScript errors, forms that never send. How to test yours properly.'],
  ['eCommerce in India: what it costs to run, beyond the build', 'Payment gateway fees, returns, logistics, GST, catalogue work. The build is the cheap part.'],
  ['Shopify, WooCommerce or custom: choosing by what you sell', 'Decide by catalogue size, variant complexity and how much the checkout has to bend.'],
  ['How to write product pages that answer the question stopping the sale', 'Objection-led product copy, with the questions Indian buyers actually ask.'],
  ['What a CRM is for, if you have never used one', 'Start from the problem — enquiries lost in WhatsApp and inboxes — not from the software.'],
  ['Spreadsheet, off-the-shelf CRM, or something built for you?', 'The decision rule is how unusual your process is. Be honest that most businesses should start with the spreadsheet.'],
  ['The follow-up problem: why most small-business leads go cold', 'Speed and consistency beat everything else. Show how a simple pipeline fixes it.'],
  ['What to automate first in a small business, and what to leave alone', 'Automate the repetitive and rule-based. Leave judgement alone. Give a test for telling them apart.'],
  ['Connecting your website to WhatsApp Business, properly', 'The mechanics, the limits of the free tier, and where the API becomes necessary.'],
  ['Why your invoices, enquiries and stock do not talk to each other', 'Integration explained without jargon: what an API is, in terms of a business process.'],
  ['Payment gateways in India: what the percentages actually mean', 'Compare on total cost of a transaction including settlement time, not headline rate.'],
  ['How to brief a web developer so you get what you pictured', 'What to bring: examples, real content, the decision you want the site to cause.'],
  ['The maintenance nobody quotes for, and what happens when you skip it', 'Updates, backups, certificate renewals, broken forms. Frame as risk, not upsell.'],
  ['Website security for businesses with nothing worth stealing', 'You are not the target; automation is. Explain commodity attacks and the basics that stop them.'],
  ['What to do when your website goes down', 'A calm checklist, in order, with how to tell whose problem it is.'],
  ['Multilingual websites in India: when they are worth it', 'Decide by audience and by whether you can maintain the second language honestly.'],
  ['Booking and appointment systems for small businesses', 'What to look for, and why the calendar integration is the part that breaks.'],
  ['Why "we will add content later" is how projects die', 'Content is the long pole. Show the sequencing that avoids it.'],
  ['Migrating a website without losing your search rankings', 'Redirect mapping, what actually carries over, and the checks to run after.'],
  ['Accessibility on a small-business website: the parts that matter most', 'Contrast, labels, keyboard, alt text. Frame as reach and as basic decency.'],
  ['How to tell whether your website is actually working', 'Define success before measuring. Enquiries per visitor beats traffic every time.'],
],

/* ── seo ────────────────────────────────────────────────────
   Technical SEO, content, local, and AI/answer-engine visibility. */
'seo' => [
  ['How long does SEO actually take to work?', 'Explain the compounding curve and what changes at each stage. Refuse to give a single number; give the conditions that move it.'],
  ['Why your business does not appear on Google, and how to diagnose it', 'A diagnostic order: indexed, relevant, competitive. Each with a way to check.'],
  ['Core Web Vitals for people who do not write code', 'What LCP, INP and CLS measure, why field data differs from lab, and what to do about each.'],
  ['What a technical SEO audit should actually find', 'The real list, and how to tell an audit that found problems from one that ran a tool.'],
  ['Google Search Console: the four reports worth your time', 'Which to read, how often, and the decision each one supports.'],
  ['Keyword research without the tools, for a business that knows its customers', 'Start from sales conversations. Show how to turn real questions into pages.'],
  ['Why ranking for your own brand name is not an SEO strategy', 'The distinction between demand capture and demand creation.'],
  ['How to write a page that ranks and still sounds like a person', 'Structure serves the reader first. Show how headings and specificity do both jobs.'],
  ['Local SEO in Delhi NCR: what moves the needle', 'Proximity, prominence, relevance. What a business can actually influence.'],
  ['Google Business Profile: the fields most businesses leave empty', 'Field by field, what each one affects.'],
  ['How to get reviews without buying them, and why buying them backfires', 'The mechanics of review filtering and the practical ask that works.'],
  ['Duplicate content: what it really is, and what it is not', 'Kill the myth of a penalty. Explain consolidation and canonicalisation.'],
  ['Internal linking: the cheapest SEO work most sites never do', 'Anchor text, hierarchy, and the pages that deserve the links.'],
  ['What backlinks are worth in 2026, and how to earn them honestly', 'Relevance over volume. Concrete, non-spammy ways a small business earns links.'],
  ['Why your traffic went up and your enquiries did not', 'Intent mismatch. How to check which queries actually convert.'],
  ['Structured data for small businesses: what to add and what to skip', 'The handful of schema types that change how a listing looks.'],
  ['How Google decides what to show for "near me" searches', 'Explain the local pack mechanics without pretending to know the algorithm.'],
  ['Site speed and rankings: what the connection actually is', 'Separate the ranking factor from the conversion effect. The second is bigger.'],
  ['The SEO promises that should make you walk away', 'Guaranteed rankings, secret contacts, thousands of links. Explain why each is a red flag.'],
  ['Choosing between one big website and several small ones', 'Consolidation almost always wins. Explain why, and the exceptions.'],
  ['How to audit your own website in an afternoon', 'A short, honest checklist a non-specialist can genuinely complete.'],
  ['Content that ranks for years, and content with a shelf life', 'How to tell them apart before you commission either.'],
  ['Why nobody reads your blog, and what to change first', 'Distribution and topic selection, not writing quality.'],
  ['Search intent in four kinds, and the page each one needs', 'Informational, navigational, commercial, transactional — with real examples.'],
  ['Is your business visible inside ChatGPT and Perplexity?', 'How answer engines source claims, and what makes a page quotable.'],
  ['What AI crawlers fetch, and how to check whether they reach you', 'GPTBot, ClaudeBot, PerplexityBot: robots.txt, server logs, and what blocking costs.'],
  ['Writing for answer engines without writing for robots', 'Clear claims, attributable facts, structure. The same things that help readers.'],
  ['llms.txt and the emerging conventions for AI access', 'What exists today, what is speculative, and a defensible position to take now.'],
  ['Should you block AI crawlers from your website?', 'The genuine trade-off between training use and answer visibility. Give the decision, not a slogan.'],
  ['How to measure SEO when rankings move every day', 'Measure at the level of enquiries and impressions, not positions.'],
],

/* ── social ─────────────────────────────────────────────────
   Organic, paid, social search, and creative. */
'social' => [
  ['Which social platforms an Indian small business should actually be on', 'Decide by where your buyers already ask questions, not by follower counts.'],
  ['Instagram is a search engine now: what that changes', 'Captions, alt text and on-screen words are indexed. What to do differently.'],
  ['Why follower count is the least useful number on your dashboard', 'Reach, saves and shares predict business outcomes; followers do not.'],
  ['What to post when you have nothing to announce', 'A repeatable set of formats drawn from work you already do.'],
  ['How often to post, and why consistency beats frequency', 'The real constraint is what you can sustain for a year.'],
  ['Reels for businesses that are not entertaining', 'Demonstration, explanation and proof formats that suit unglamorous businesses.'],
  ['Writing captions that get read past the first line', 'The first line does one job. Show what it is.'],
  ['Hashtags in 2026: what they still do', 'Diminished but not useless. A sane, small approach.'],
  ['Should you boost a post, or run a proper campaign?', 'The difference in targeting and measurement, and when boosting is fine.'],
  ['Meta ads for small budgets: what is realistic', 'What a modest daily budget can and cannot learn. Set expectations honestly.'],
  ['Google Ads or Meta Ads: which fits your business', 'Demand capture versus demand creation, with a decision rule.'],
  ['How to read an ad report without being fooled by it', 'Which metrics are vanity, which are diagnostic, which are decisions.'],
  ['Why your ads work for a week and then stop', 'Creative fatigue and audience saturation, explained.'],
  ['Landing pages for ad traffic: why your homepage is the wrong place', 'Message match, single action, and what to remove.'],
  ['Retargeting without being creepy', 'Frequency caps, exclusions, and the timing that respects people.'],
  ['WhatsApp as a sales channel: what works and what annoys', 'Consent, timing and templates. Where broadcast crosses the line.'],
  ['LinkedIn for Indian B2B: what actually generates conversations', 'Posting from a person rather than a page, and what to write about.'],
  ['The content calendar that survives a busy month', 'Batch, template, and reserve. A system that assumes you get busy.'],
  ['Photography on a phone that does not look like it', 'Light, background, and framing. Three rules that carry most of it.'],
  ['Video without a studio: the setup that is good enough', 'Sound first, light second, camera last. Explain why sound matters most.'],
  ['Working with a designer: how to give feedback that helps', 'Describe the problem, not the fix. With examples of both.'],
  ['Brand consistency when several people post', 'A one-page guide that a team will actually follow.'],
  ['Responding to a bad review in public', 'Structure and tone, and the sentence that does the most work.'],
  ['User-generated content: how to ask, and how to use it legally', 'Permission, credit and the rights you actually get.'],
  ['Influencer marketing for small budgets in India', 'Micro-creators, deliverables, and how to judge fit before reach.'],
  ['What to measure on social, by what you are trying to achieve', 'Awareness, consideration and conversion each have different signals.'],
  ['Why your social traffic does not convert, and whose fault it is', 'Usually the landing page, sometimes the promise. How to tell.'],
  ['Scheduling tools: what they help with and what they cost you', 'Native reach myths, and the real trade-off in responsiveness.'],
  ['Building a social presence from zero', 'The first ninety days, honestly, with what to expect and what not to.'],
  ['When to stop posting on a platform', 'Sunk cost is not a strategy. A rule for deciding to leave.'],
],

];
