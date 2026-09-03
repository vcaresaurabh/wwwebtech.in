# Telling Google which clicks actually made money

Google optimises for what you tell it about. If you only report form
submissions, it gets very good at finding people who fill in forms — which is
not the same as finding customers, and is often cheaper per lead and far worse
per rupee.

This closes that loop. It is the highest-value thing on this list and it costs
nothing.

---

## What the site already does

Every landing page captures the click ID (`gclid`, `gbraid`, `wbraid`,
`msclkid`) and stores it with the lead. That happens automatically; there is
nothing to switch on.

When you mark a lead **Qualified** or **Won**, a row is queued for export.

---

## What you have to do, once

### 1 · Create the conversion actions in Google Ads

**Tools → Conversions → New conversion action → Import → Manual**

Create two, named **exactly** these — the CSV is matched on the name:

- `Qualified lead`
- `Won deal`

Set "Won deal" to use a **value**, in INR. The system sends the deal value you
record with the lead.

### 2 · Get the URL from the panel

**Integrations → Offline conversions → Generate a key**

Three URLs appear. Copy them somewhere safe — they are credentials. Anyone
holding one can read your customers' hashed contact details.

### 3 · Schedule the imports

**Google Ads → Tools → Conversions → Uploads → Schedule → HTTPS**

Paste the `type=google` URL. Set it to daily.

Do the same with the `type=enhanced` URL under **Enhanced conversions for
leads**, and the `type=microsoft` URL in Microsoft Advertising if you use it.

---

## Why there are two Google URLs

Not every lead has a click ID. Someone can click your ad on their phone, think
about it for a week, then search your name on a laptop and fill in the form
with no ad click attached.

- **`type=google`** exports the leads that do have a click ID. Exact match.
- **`type=enhanced`** exports the ones that do not, as a SHA-256 hash of their
  email and phone. Google matches the hash against its own records.

The email is lowercased and trimmed before hashing, Gmail dots are stripped,
and the phone is normalised to `+91…` — because a hash of the wrong string
matches nothing at all and fails completely silently. That normalisation is
covered by the test suite for exactly that reason.

**No readable email or phone number leaves the server.** Only the hash does.

---

## The `&mark=1` on the end

With it, the rows are marked as sent and will not appear in the next fetch.
That is what you want for the scheduled import.

Leave it off if you just want to open the URL and look at the file — otherwise
looking at it consumes the queue and Google never gets those rows.

---

## Rotating the key

**Integrations → Generate a new key** invalidates the old URLs immediately.
Rotate it if a URL has been pasted anywhere it should not have been, then
update the schedules in both platforms. Until you do, the imports will fail —
which is the correct behaviour, and Google will email you about it.

---

## What good looks like after a month

In Google Ads, "Qualified lead" and "Won deal" appear as conversion columns
next to "Lead form submit". The campaigns that produce the first two are
usually not the ones that produce the most of the third, and that difference is
the entire point of doing this.
