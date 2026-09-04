You are working in my Chrome browser to finish setting up the automation
behind my website, wwwebtech.in. The code is already built, deployed and
working — nothing here involves writing code. Every task below is filling in
a setting, creating a free account, or rotating a password.

Work through the tasks in order. After each one, verify it the way the task
says, and tell me the result before moving on. If a screen does not look like
the description, stop and tell me what you see instead of guessing.

────────────────────────────────────────────────────────────
RULES — these are not suggestions
────────────────────────────────────────────────────────────

- **Use <your-google-account>** for anything that needs a Google or other
  third-party account. If a login prompt appears for a different account,
  stop and tell me.
- **Hostinger (hPanel) is the host.** Anything about the server, email
  mailboxes, cron jobs or SSH is done there.
- **Only touch the Hostinger account that hosts wwwebtech.in.** I have a
  second Hostinger account for an unrelated business. Do not open it, and do
  not change anything in it. If a screen lists more than one hosting plan,
  confirm the domain reads `wwwebtech.in` before doing anything.
- **Do not do anything involving Google Ads.** Skip it entirely, even if a
  screen offers it. If you think a task needs Ads, stop and tell me.
- **Never delete anything.** Not a file, not a mailbox, not a database, not a
  cron job. The only exception is replacing an API key with a new one, and
  only where a task below says to.
- **Never touch** DNS, domains, billing, subscriptions, the database itself,
  or the `flow.wwwebtech.in` subdomain.
- **Never spend money.** Everything below is free. If a screen asks for card
  details or shows a price, stop.
- **Do not edit any file** in File Manager or anywhere else. If a task seems
  to need a file edited, stop and tell me.
- If you need a password I have not given you, ask me. Do not guess, and do
  not try to reset a password to get around a login.

────────────────────────────────────────────────────────────
WHAT IS ALREADY DONE — do not redo any of this
────────────────────────────────────────────────────────────

- The website, admin panel, landing pages and the free audit tool are live.
- The database is set up and migrated. Sequences and templates are seeded.
- Three cron jobs already exist: hourly, daily, weekly. They are running.
- The Anthropic API key and PageSpeed key are already saved and working.
- The follow-up funnel is deliberately switched OFF. Leave it off. I will
  turn it on myself after I have read what it wants to send.

────────────────────────────────────────────────────────────
TASK 1 — Add the 5-minute cron job (hPanel)
────────────────────────────────────────────────────────────

Why: this is what checks for replies and sends approved messages. Without it,
someone can reply to a follow-up and still get the next one an hour later.

1. Go to hPanel → **Advanced → Cron Jobs**.
2. Look at the existing jobs. One of them ends in the word `hourly`. Copy that
   whole command exactly — it looks like:
   `/usr/bin/php /home/uXXXXXXXXX/wwt_private/cron/run.php hourly`
3. Create a new cron job:
   - Type: **Custom**
   - Command: the command you just copied, with the last word `hourly`
     changed to **`frequent`**. Change nothing else — the path must match the
     existing job character for character.
   - Schedule: **every 5 minutes**. If hPanel offers a dropdown, choose the
     5-minute option. If it asks for a cron expression, use `*/5 * * * *`.
4. Save.

**Verify:** the Cron Jobs list now shows four jobs, and the new one ends in
`frequent`. Read the new row back to me.

────────────────────────────────────────────────────────────
TASK 2 — Sender identity and alerts (admin panel)
────────────────────────────────────────────────────────────

Go to **https://wwwebtech.in/admin/** and sign in. (Ask me if you need the
password.) Then open **Settings**.

First, scroll to the **Mailbox** section and read the address in the field
labelled **"Mailbox address"**. You will need it in a moment. Tell me what it
is. Do not change anything in that section yet.

Now find the section headed **Follow-up and scoring** and fill in:

- **Messages are signed:** `Saurabh`
- **and sent from:** the exact mailbox address you just read from the Mailbox
  section above. Do not invent an address, and do not use a `noreply` one.
- **Also alert this address:** `<your-google-account>`
- **Hot from** and **Warm from:** leave whatever numbers are already there.
- Leave the consent wording exactly as it is. Do not reword it — it is a
  legal record of what people agreed to.

Click **Save**.

**Verify:** a green "Saved." appears, and reloading the page shows your three
values still filled in. Also read back to me the line just below the
thresholds about the last 90 days of leads — it will either give a hot/warm/
cold split, or say there are too few leads to judge. Tell me which.

────────────────────────────────────────────────────────────
TASK 3 — Let the panel read replies (admin panel)
────────────────────────────────────────────────────────────

Why: this is what stops the automation when a customer answers. It matters
more than anything else on this list.

Still in **Settings**, find the section headed **Reading replies**:

- **IMAP host:** `imap.hostinger.com`
- **Port:** `993`
- **Mailbox:** the same address you used in Task 2
- **Password:** the password for that mailbox. Ask me for it.

Click **Save**, then click **Check for replies now**.

**Verify:** the message at the top should read something like
"Checked the mailbox: 0 new · 0 matched to a lead…". Any sentence starting
"could not connect" is a failure — tell me the exact wording and stop.

Note: the page may show a blue notice about IMAP before you save. That is
expected and it goes away once the mailbox is configured.

────────────────────────────────────────────────────────────
TASK 4 — Telegram alerts (free, optional)
────────────────────────────────────────────────────────────

Only attempt this if I am already signed in to **web.telegram.org** in this
browser. If I am not, skip the whole task and tell me — do not try to create a
Telegram account or enter a phone number.

1. In Telegram, search for **@BotFather** and open the chat.
2. Send `/newbot`. Give the name `Wwwebtech alerts`. When it asks for a
   username, use something ending in `bot`, e.g. `wwwebtech_alerts_bot`. If
   that name is taken, add digits until one is accepted.
3. BotFather replies with a token that looks like `1234567890:AAxxxxx…`.
   Keep it — you will paste it in a moment. Do not post it anywhere else.
4. **Open a chat with your new bot and send it any message, e.g. "hello".**
   This step is not optional. Telegram does not let a bot start a
   conversation, so an unmessaged bot fails later with "chat not found".
5. In a new tab open:
   `https://api.telegram.org/bot<TOKEN>/getUpdates`
   replacing `<TOKEN>` with the token from step 3. Find `"chat":{"id":…}` and
   note that number. It may be negative — keep the minus sign.
6. Back in the admin panel → **Settings → Telegram alerts**: paste the token
   into **Bot token** and the number into **Chat ID**. Click **Save**.
7. Click **Send a test message**.

**Verify:** a message from the bot arrives in Telegram saying alerts are
working. If the panel says "chat not found", step 4 was missed — go back and
message the bot, then try again.

────────────────────────────────────────────────────────────
TASK 5 — Google Search Console: submit the updated sitemap
────────────────────────────────────────────────────────────

Why: the site gained several new pages today, including a free-audit tool.
Search Console needs telling.

1. Go to **https://search.google.com/search-console** signed in as
   **<your-google-account>**.
2. Select the **wwwebtech.in** property. If there is no property for it, stop
   and tell me — do not create one, and do not attempt any DNS verification.
3. Open **Sitemaps** in the left menu.
4. If `sitemap.xml` is already listed, click it and use **Resubmit** if that
   is offered. Otherwise enter `sitemap.xml` and click **Submit**.
5. Open **URL Inspection** and inspect:
   `https://wwwebtech.in/tools/free-website-audit/`
   Then click **Request indexing**.

**Verify:** the sitemap row shows "Success" (it may say "Couldn't fetch" for a
few minutes right after submitting — that is normal, tell me if it persists),
and read back how many URLs it discovered.

Do not click into anything about Ads, Merchant Center or monetisation.

────────────────────────────────────────────────────────────
TASK 6 — Rotate the mailbox password
────────────────────────────────────────────────────────────

Why: this password was shared in a chat transcript and should be replaced.

**Do the two halves back to back.** If you change it in hPanel and stop, my
lead notification emails break immediately.

1. hPanel → **Emails** → select the mailbox you used in Tasks 2 and 3 →
   **Change password**.
2. Generate a strong password (20+ characters, mixed case, digits, symbols).
   **Show it to me in your reply** so I can store it. Save it in hPanel.
3. Immediately go to the admin panel → **Settings → Mailbox**, paste the new
   password into the field labelled **"Mailbox password"**, and click
   **Save mailbox settings**.
4. Click **Send me a test email** and confirm it reports success.
5. Then go to **Settings → Reading replies**, paste the same new password,
   click **Save**, and click **Check for replies now**.

**Verify:** both the test email and the reply check succeed. If either fails,
tell me the exact error before doing anything else — do not try a different
password.

────────────────────────────────────────────────────────────
TASK 7 — Rotate the SSH password
────────────────────────────────────────────────────────────

Nothing on the live site depends on this, so it is safe to change on its own.

1. hPanel → **Advanced → SSH Access**.
2. Change the SSH password. Generate a strong one and **show it to me** so I
   can store it.

**Verify:** hPanel confirms the change. Nothing else needs updating.

────────────────────────────────────────────────────────────
TASK 8 — Rotate the Anthropic API key
────────────────────────────────────────────────────────────

1. Go to **https://console.anthropic.com** signed in as
   **<your-google-account>**. If that account has no access to the
   organisation holding the key, stop and tell me.
2. **API keys** → create a new key, name it `wwwebtech-panel`. Copy it.
3. In the admin panel go to **Blog** → the **How it is set up** section →
   paste the new key into the field labelled **"Anthropic API key"** → click
   **Save**.
4. Click **Check the key works**. It must report success.
5. Only after that succeeds, go back to the Anthropic console and **revoke
   the old key** — the one that is not named `wwwebtech-panel`.

**Verify:** "Check the key works" succeeds *before* you revoke anything. If it
fails, leave the old key alone and tell me.

────────────────────────────────────────────────────────────
TASK 9 — Rotate the PageSpeed API key
────────────────────────────────────────────────────────────

1. Go to **https://console.cloud.google.com/apis/credentials** signed in as
   **<your-google-account>**.
2. Create a new API key. Restrict it to the **PageSpeed Insights API** only.
   Copy it.
3. In the admin panel go to **SEO health**, paste the new key into the field
   labelled **"PageSpeed API key"**, and click **Save**.
4. Click **Run the daily checks**. It should complete and report the checks.
5. Only after that succeeds, delete the old PageSpeed key in the Google
   console.

**Verify:** the daily checks run and report "8 checks — 8 ok, 0 warnings,
0 failures", or tell me what it actually says.

Stay out of Ads, billing and any paid API while you are in the Google console.

────────────────────────────────────────────────────────────
FINAL CHECK
────────────────────────────────────────────────────────────

Open each of these and confirm it loads without an error:

- https://wwwebtech.in/
- https://wwwebtech.in/tools/free-website-audit/
- https://wwwebtech.in/lp/custom-crm/
- https://wwwebtech.in/admin/  → the **Dashboard**

On the Dashboard, look at the automation heartbeat and tell me whether every
job shows "ok" and when each last ran.

────────────────────────────────────────────────────────────
DO NOT DO THESE
────────────────────────────────────────────────────────────

- **Google Ads / offline conversions.** Excluded entirely. I will do it.
- **The database password.** Do not change it. Changing it needs a file edited
  on the server at the same moment, which is not safe from a browser.
- **WhatsApp Business API.** Not now. It needs business verification and can
  cost money.
- **Turning the funnel on.** Leave "Sequences" off and the kill switch clear.
  I will turn it on after I have read what it wants to send.
- **Editing any file** in File Manager.

────────────────────────────────────────────────────────────
WHEN YOU FINISH
────────────────────────────────────────────────────────────

Give me one summary containing:

1. Each task: done, skipped, or failed — and for anything not done, why.
2. Every new password and API key you generated, so I can store them.
3. The lead split figure from Task 2 and the sitemap URL count from Task 5.
4. Anything you saw that looked wrong but was not part of a task.
