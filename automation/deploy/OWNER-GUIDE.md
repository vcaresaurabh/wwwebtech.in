# The panel, in plain English

**https://wwwebtech.in/admin/**

This is written for the person who owns the business, not the person who built
the site. There is nothing in here you can break by clicking.

---

## The one thing to know first

Every number in this panel is something the site actually recorded. Nothing is
estimated, sampled, or filled in to look busy. If a number is zero, it is zero.
If something cannot be known, the panel says so instead of guessing.

That is why some panels say "not available" rather than showing a figure. It is
deliberate.

---

## Dashboard

The first screen. Four numbers, and two lists.

- **Leads · 24h** — enquiries in the last day.
- **Unactioned** — enquiries you have not moved out of *New*. This is the one
  to keep at zero.
- **Visitors · 7d** — people, not page loads.
- **Posts live** — published blog posts, and how many topics are queued.

**Automation heartbeat** shows each scheduled job and when it last ran. If one
says **fail**, the reason is on the same line. If one has not run for a day,
the scheduled job has stopped — that is worth an email to whoever maintains
the site.

---

## Leads

Every contact-form enquiry, newest first.

**The enquiry is saved before the email is sent.** If your mailbox breaks, you
lose the notification, not the enquiry. A lead that could not be emailed is
marked **not emailed** and there is a button to try again.

### The pipeline

Move each enquiry along as you deal with it:

**New** → **Contacted** → **Qualified** → **Won** or **Lost**

You can change status from the list, or open a lead to add notes. Notes are
private and are never sent to anyone.

### Getting the data out

**Export CSV** downloads exactly what you are looking at — the same filters,
the same rows. It opens in Excel and Google Sheets with the rupee signs intact.

---

## Analytics

First-party and cookieless. No cookie is set on anyone's device, no IP address
is stored, and nothing is shared with Google or anyone else. That is why the
site needs no cookie banner.

- **Visitors** counts people; **Pageviews** counts pages opened.
- **Read, not bounced** is the share of visits where someone actually read —
  they scrolled, or stayed fifteen seconds. It is a better number than bounce
  rate and harder to flatter.
- **AI crawlers** is the one most agencies cannot show you: how often ChatGPT,
  Claude, Perplexity and Google's AI have fetched your pages. They do not run
  JavaScript, so ordinary analytics never sees them.
- **Geography** is empty on purpose. This server provides no country
  information and we will not guess it from someone's browser language.

Totals are rebuilt every hour. **Rebuild totals now** does it immediately.

---

## Blog

Writes and publishes posts to a fixed brief.

**The switch at the top is the important control.** *Stop publishing* takes
effect immediately and nothing is written or published until you turn it back
on. Existing posts are untouched.

### What stops a bad post

Every draft has to pass a list of mechanical checks before it can go live:

- at least 800 words, one heading structure, a real FAQ;
- every link points at a page that exists;
- no invented statistic, no "studies show", no made-up client or result;
- no promise or guarantee;
- not a rewrite of something already published.

A post that fails any of these is **never published**. It is listed with the
reason it was rejected, so you can see what the checks caught.

### Cost

Each post costs one to three cents. The **monthly cap** is a hard stop: once
reached, nothing further is sent until the next month. Set a spend limit in
your Anthropic account as well.

### Before you turn it on

Use **Write a post** with *publish straight away* unticked, and read the draft.
Do that two or three times. Turn on automatic publishing only when you would
have been happy to put your name to what it produced.

---

## SEO health

Checks run against the live website over the internet, so they see what a
visitor and a search engine actually get.

Findings are ordered by how much they matter: failures, then warnings, then
facts, then what is fine. Each one says what is wrong in a sentence.

There is no score out of 100, on purpose. A single number hides the one line
that tells you what to fix.

The **full crawl** button checks every internal link and takes a few minutes.
It runs by itself once a week.

---

## Conversations

Every message with a lead — yours and theirs — on one thread.

Replying here does the same thing as replying from your mailbox, plus two
things it cannot: it records what was said, and it **stops whatever automated
sequence that person was in**. That happens the moment you press Send.

- **Ask Claude for a draft** writes a starting point. It is a draft. It is put
  in the box for you to edit and it is never sent by pressing that button.
- **Snippets** paste at your cursor. They are openings and closings, not whole
  messages.
- **Save as a private note** records something for yourself. It is not sent
  anywhere.

Threads where the last message came from *them* are sorted to the top, because
those are the only urgent thing on this page.

---

## Funnel

What the automation is about to say, to whom, and when.

The **Stop everything** button is at the top for a reason. It halts every
sequence and cancels every message that is written but not yet sent. If
something reads wrong, press it — you can resume, and there is no cost to
being early.

**Waiting for your approval** is the normal state, not an error. Every
AI-written message waits there until you read it. You can edit the words before
approving.

**In a sequence right now** lists who is being followed up and when the next
message is due. *Stop* takes one person out.

**Sequences** at the bottom is where you can let one sequence send without
review. That is per sequence, deliberately — see FUNNEL-SETUP.md. The safe
answer is to leave it as it is.

### What it refuses to do

The rules are in code, not in the instructions given to the AI, so an AI in a
persuasive mood cannot talk its way past them:

- Never contacts anyone who did not tick the consent box.
- Never sends between 9pm and 9am, or on a Sunday, or on a public holiday.
- Never more than two messages a day to one person.
- Never names a client, result or testimonial. There are none in the system to
  name, and a message that invents one is refused before it reaches you.
- Never says a ranking is guaranteed.
- Stops completely the moment someone replies, books a call, or is moved out of
  *New* or *Contacted*.

---

## Landing pages

Visits, enquiries and what became of them, for each `/lp/` page.

Visits are counted **on the server**, not by a script in the browser, because a
good share of paid traffic blocks the script. The numbers here are lower than a
Google Analytics figure for the same page, and they are the more honest ones.

**Abandoned** is someone who filled in enough of the form to be reachable and
then stopped. They are stored and never contacted automatically. They are not
counted as enquiries.

The A/B section refuses to name a winner until both versions have around thirty
enquiries each. Calling it earlier is a coin toss with extra steps, and the
usual result is switching to the worse page and never finding out.

**Keywords with no tailored line** is a to-do list: each row is a paid keyword
that sent someone to a page with no headline written for it.

---

## Audits

Every run of the free website audit at `/tools/free-website-audit/`.

Each audit is a real measurement of a real page — seventeen checks against the
live HTML plus Google's own speed data. Nothing is invented to look alarming,
and a site in good shape is told so. That matters: the first call after a
scaremongering report goes badly.

Each one also arrives with an email address attached, so every row here is also
a lead. **Try it against any site** runs one immediately without storing
anything or emailing anyone — useful before a sales call.

---

## Integrations

Where you add Google Analytics, Meta Pixel, Search Console verification and
similar, by pasting an ID.

Two things this page tells you that most do not:

1. **What each one costs your visitors.** Every tag is JavaScript from someone
   else's server, loaded on every page. The size is next to each one.
2. **Whether it sets cookies.** Most do. The moment one is on, the claim on
   your privacy page that the site sets no cookies stops being true, and you
   may need a consent banner. The page says so at the point you decide.

Saving stores the ID. Nothing reaches the site until you press **Apply to the
site**. **Remove everything** puts every page back exactly as it was.

---

## Settings

- **Your password** and **two-factor authentication**. Turn 2FA on.
- **Who can sign in.** *Read-only* accounts can see leads, analytics and SEO
  and change nothing — the right role for someone who only watches.
- **Mailbox.** Where enquiries are sent from and to, and the reply promise
  used in the acknowledgement. Keep that wording identical to what the website
  says.
- **Follow-up and scoring.** Who the follow-up messages appear to come from,
  where you get alerted, and the score thresholds that decide hot, warm and
  cold. The panel shows how your last ninety days actually split — if nearly
  everything is one band, move the threshold. See FUNNEL-SETUP.md.
- **Telegram alerts.** Free, internal, and the fastest way to hear about a
  lead. Message your bot once before saving, or Telegram refuses.
- **WhatsApp.** Off unless you set it up. The monthly cap is a hard stop, not a
  warning. See WHATSAPP-SETUP.md.
- **Reading replies.** The mailbox the panel checks for replies. This is what
  stops a sequence when someone answers, so it is worth the five minutes.
- **Data retention.** How long individual visit records are kept. Daily totals
  are kept forever regardless.

---

## When something looks wrong

| What you see | What it usually means |
|---|---|
| A job shows **fail** on the Dashboard | The message beside it says why. Most often a password changed. |
| A lead says **not emailed** | The mailbox password changed. Settings → Mailbox → Send me a test email. |
| Analytics stopped at a date | Settings → *Collect analytics* got unticked, or the site was redeployed without `wa.js`. |
| The blog stopped | The monthly cap was reached, the API key expired, or the topic queue is empty. The Blog page says which. |
| SEO health reports a failure | Read the line. It is describing something real on the live site. |
| Messages pile up in **Waiting for your approval** | Working as intended. Nothing sends until you read it. |
| Someone replied but the sequence carried on | Settings → Reading replies is not configured, or the mailbox password changed. Mark the lead *Contacted* to stop it by hand. |
| An audit says **failed** | The site did not answer — a typo, a site that is down, or a firewall that blocks anything that is not a person. |
| WhatsApp stopped sending | The monthly cap was reached. The Funnel page shows the figure. It falls back to email on its own. |

If you are unsure, nothing in this panel is urgent except an enquiry sitting in
**New**. Everything else can wait for whoever maintains the site.
