# Prompt for Claude in Chrome — funnel providers

Paste the block below into Claude in Chrome. Have your **Hostinger hPanel**
open and signed in, and be ready to sign into Telegram, Google Ads and Google
Cloud when it asks.

It collects credentials and hands them back to you. It never puts a secret
into a file — you paste them into the admin panel afterwards.

---

You are setting up third-party accounts for my business, wwwebtech.in. Work
through the tasks in order. Stop and tell me if any screen does not match what
I describe rather than improvising.

## Scope — read this first

- **The only Hostinger account you may touch is the one hosting
  wwwebtech.in.** I have a second account for an unrelated business. Do not
  open it. If a screen lists more than one hosting plan, confirm the domain
  reads `wwwebtech.in` before doing anything.
- Do not delete anything, do not change DNS, billing or domain settings, and do
  not touch the `flow.wwwebtech.in` subdomain.
- Do not spend money. Everything below is free. If any screen asks for a card
  or offers a paid upgrade, stop and tell me.
- Do not change any existing Google Ads campaign, budget or bid. You are only
  creating conversion actions and reading IDs.
- Report every credential to me in your final reply and nowhere else.

## Task 1 — A Telegram bot for instant lead alerts

This is how I get told about a new enquiry in seconds rather than when I next
check email. It is free and unlimited.

1. Open Telegram (web.telegram.org is fine) and search for **@BotFather**.
2. Send `/newbot`.
3. Name: `Wwwebtech Leads`. Username: something ending in `bot`, e.g.
   `wwwebtech_leads_bot` — BotFather will tell you if it is taken.
4. It replies with an **HTTP API token** that looks like
   `1234567890:AAF...`. **Copy it.**
5. Now find your own chat ID: search for **@userinfobot**, start it, and it
   replies with a numeric **Id**. Copy that.
6. Finally, open a chat with the bot you just created and send it any message
   (e.g. "hello"). A bot cannot message you until you have messaged it first —
   skip this and the alerts will silently never arrive.

Report: the bot token, the numeric chat ID, and confirmation you sent it a
message.

## Task 2 — A booking link

1. Go to **cal.com** and sign up with my Google account (free tier).
2. Create an event type: **"30-minute intro call"**, 30 minutes.
3. Set availability to Monday–Saturday, 10:00–19:00, timezone **Asia/Kolkata**.
4. Copy the public booking link (looks like `cal.com/yourname/30min`).

If cal.com asks for payment at any point, stop — the free tier covers this.

Report: the booking link.

## Task 3 — A PageSpeed API key check

I may already have one. Go to **console.cloud.google.com** → APIs & Services →
Credentials, and tell me whether an API key already exists there. Do **not**
create a new one or change any existing key. I only need to know.

Report: whether a key exists, and its display name if so.

## Task 4 — Google Ads conversion actions

Only do this if I have a Google Ads account. If I do not, say so and skip.

1. Go to **ads.google.com** → Goals → Conversions → Summary.
2. Click **+ New conversion action** → **Import** → **Other data sources or
   CRM** → **Track conversions from clicks**.
3. Create **three** actions, exactly these names (the spelling matters — my
   system uploads rows matching these names):
   - `Lead form submit` — category *Submit lead form*, value: don't use a value
   - `Qualified lead` — category *Qualified lead*, value: don't use a value
   - `Won deal` — category *Purchase*, value: **use different values for each
     conversion**, currency **INR**
4. For each, set **Count** to *One* and the click-through window to **90 days**.
5. Do not set any of them as a primary/bidding goal yet. I will do that once
   real data is flowing.

Report: confirmation the three exist, and the **Conversion ID** and
**Conversion label** for each (Ads shows these under the action's tag setup).

## Task 5 — Read back the IDs already installed

Go to **https://wwwebtech.in/admin/** and sign in (I will give you the
password separately, or do this step yourself if I have not). Open
**Integrations** and tell me which IDs are already filled in — GA4, GTM, Google
Ads, Meta Pixel, Clarity, Search Console, Bing.

If you cannot sign in, instead open **https://wwwebtech.in/** and view source,
and tell me which tracking tags you can see in the HTML.

Report: the list of what is already configured.

## When you are done, report back

1. The Telegram bot token and numeric chat ID, and that you messaged the bot.
2. The cal.com booking link.
3. Whether a PageSpeed key already exists.
4. The three Google Ads conversion actions, with IDs and labels — or that I
   have no Ads account.
5. What is already configured under Integrations.
6. Anything you could not do, or that looked different from my description.

Treat the bot token as a password: put it in your reply to me and nowhere else.
