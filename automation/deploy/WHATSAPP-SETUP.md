# WhatsApp, and whether it is worth it

WhatsApp is where your customers already are, and a WhatsApp message gets read
where an email gets ignored. That is the case for it, and it is a strong one.

Here is the case against doing it *automatically*, so you can decide with the
real numbers rather than the obvious ones.

---

## What it actually costs

Meta charges per conversation, by category. In India, at the time of writing:

| Category | Roughly | What it is for |
|---|---|---|
| Utility | ₹0.35 | Following up on something the customer started |
| Marketing | ₹0.78 | Anything promotional |
| Service | free | Your reply, inside 24 hours of theirs |

Only **utility** and **service** are used here. Marketing templates are refused
in code — not discouraged, refused — because a marketing template sent to
someone who filled in a contact form is the fastest way to get a number
blocked, and a blocked number is not recoverable by paying more.

At ₹0.35 a message and a monthly cap of ₹200, that is about 570 messages a
month. The cap is a hard stop: at the cap the funnel silently falls back to
email rather than spending more. You will not get a surprise bill.

---

## Why the internal alerts use Telegram instead

Because they are for **you**, not for customers, and:

- Telegram bot messages are free and unlimited. WhatsApp charges per
  conversation even to reach yourself.
- A Telegram bot takes five minutes and no approval. WhatsApp Business API
  needs a Meta Business account, a verified business, a dedicated phone number
  that can no longer be used in the normal WhatsApp app, and template approval
  for each message shape.
- Alerts are noisy by design. Putting them in the same app your customers
  message you in means the alert about a lead buries the lead's actual message.

Customers get WhatsApp. You get Telegram. They are different problems.

---

## Before you enable it

You need, from Meta:

1. A **Meta Business account** with the business verified.
2. A **phone number** that is not currently on WhatsApp — or one you are
   willing to delete from the app first. This is irreversible for that number.
3. A **permanent access token** (a system user token, not the 24-hour one the
   dashboard hands you first).
4. The **phone number ID** from the WhatsApp → API setup screen.
5. At least one **approved utility template**. Approval takes hours to days.

That is a real afternoon of work, and none of it can be done from this panel.

---

## Turning it on

**Connections → WhatsApp**

The card walks you through all six values from Meta, shows the webhook
URL and verify token to paste into Meta, syncs the template list, and
unlocks the switch only after one test has passed and the business is
recorded as verified.

Start with the cap at ₹200. It is enough for a month of ordinary volume and
cheap enough that a mistake is not expensive.

---

## The rules it enforces

- **Utility category only.** A marketing template is refused with an error, not
  downgraded or sent anyway.
- **The 24-hour window.** Inside 24 hours of a customer's own message, replies
  are free-form and free. Outside it, only an approved template can reopen the
  conversation, and it costs.
- **The monthly cap.** Checked before every send, not after. At the cap the
  step falls back to email.
- **Quiet hours and consent** apply exactly as they do to email. A WhatsApp
  message at 10pm is worse than an email at 10pm, not better.

---

## If you skip this entirely

Everything works. The sequences are written so that a WhatsApp step falls back
to email when WhatsApp is unavailable, and "unavailable" includes "never set
up". You lose the open rate; you lose nothing else.

That is a perfectly reasonable place to stop.
