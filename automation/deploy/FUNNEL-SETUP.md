# Turning the follow-up on

The landing pages, the scoring and the notifications work the moment the code
is deployed. The **automated follow-up** does not: it stays off until you turn
it on, deliberately, in this order. Nothing below costs money except where it
says so.

Do it in order. Each step is safe to stop at — the system works fine with only
the first two done.

---

## 1 · Decide who the messages come from

**Settings → Follow-up and scoring** (the names) and **Connections → Email — sending** (the mailbox)

Two fields matter:

- **Messages are signed** — your name. Not "The Wwwebtech Team".
- **and sent from** — a mailbox you can actually read.

Automated mail signed by a real person gets replies. Mail from `noreply@` gets
filed. More importantly, replies to that address are how the system learns
someone has answered — and a reply is what stops the sequence.

On **Connections → Alert recipients**, add whatever address you check on your phone. Every new
lead sends a copy there as well as to the company address, because the company
inbox is not where you notice things at 9pm.

> If you leave the sender blank, follow-up messages still send, but from the
> ordinary company mailbox and without the reply tracking. It works; it is just
> less good.

---

## 2 · Let it read replies

**Connections → Email — reading replies**

Pick the provider preset and fill in the mailbox from step 1:

| Field | Hostinger value |
|---|---|
| IMAP host | `imap.hostinger.com` |
| Port | `993` |
| Mailbox | the same address as the sender |
| Password | that mailbox's password |

Press **Check the mailbox**. It shows the unread count and the three most recent subjects. It should say how many messages it read.

If it says PHP has no IMAP extension, that is the host's build and not
something you can fix from the panel. Everything else still works — you will
just have to mark a lead as *Contacted* yourself when you reply from your own
mail app, and that stops the sequence too.

**Why this matters more than it looks:** this is the mechanism that keeps the
automation polite. Without it, someone can reply "yes please call me" and still
get the next scheduled follow-up two days later.

---

## 3 · Check the scoring is telling you something

**Settings → Follow-up and scoring**, at the bottom of that section, the panel
shows how the last ninety days of leads split across hot, warm and cold.

If nearly everything is one band, the thresholds are wrong. A score that calls
90% of leads hot is not ranking anything — it is just a label. Move **Hot from**
up until the hot list is the list you would actually work through first.

There is no correct number. The default is 60 and 35 because that is what the
sample data suggested, not because it is a law.

---

## 4 · Telegram alerts (optional, free)

**Connections → Telegram** — the card walks you through this; the short version:

1. On your phone, message **@BotFather** on Telegram, send `/newbot`, and follow
   it. It gives you a token.
2. Paste the token in.
3. Message your new bot once, saying anything. **This step is not optional** —
   Telegram does not let a bot start a conversation, so an unmessaged bot fails
   with "chat not found".
4. Press **Detect my chat** — the panel finds you. Click yourself.
5. Press **Send a test**.

This is internal only. No customer ever sees Telegram. It exists because email
notifications get missed and a phone buzz does not.

---

## 5 · Turn the sequences on

**Funnel → How it sends → Turn on**

New leads now get enrolled as they arrive. Nothing sends yet, because every
AI-written message waits for you.

Watch **Funnel → Waiting for your approval** for a week. Read each message
before approving it. You are checking two things:

- Does it sound like you?
- Is anything in it not true?

The system refuses to send a message that names a client you have not had, or
promises a ranking, or contains two different calls to action. It cannot refuse
a message that is merely bland, and that is what you are reading for.

---

## 6 · Only then, consider letting one send unattended

**Funnel → Sequences → Let it send**

This is per sequence, on purpose. Turn it on for the gentlest one first — the
cold nurture — and leave the hot sequence under review, because those are the
messages that go to people worth the most.

Turn it on only when you have approved at least twenty messages from that
sequence unchanged. If you are still editing them, the answer is no.

---

## The switch to stop everything

**Funnel → Stop everything**, at the top of the page.

It halts every sequence and cancels every message that is written but not yet
sent. Use it the moment something reads wrong. There is no cost to being early
and no penalty for using it — resuming picks up where things were.

---

## What it will not do, ever

- Message anyone who did not tick the consent box.
- Message anyone between 9pm and 9am, or on a Sunday.
- Send more than two messages a day to one person, or two on the same channel.
- Keep going after someone replies, books a call, or is marked anything other
  than New or Contacted.
- Invent a client, a result, a testimonial or a case study.
- Say the word "guaranteed" about a ranking.

Those are enforced in code, not in the prompt. An AI that decides to be
persuasive still cannot get past them.
