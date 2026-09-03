# Checks to run after going live

Fifteen checks, about ten minutes. Do them in order — a failure early on
explains most of the failures after it.

Tick each one. If something fails, the fix is under the check.

---

## The website still works

**1. The homepage loads.**
Open https://wwwebtech.in in a private window. It should look exactly as it did
before. Nothing about the automation layer is visible to visitors.

**2. A page that does not exist gives a 404.**
Open https://wwwebtech.in/not-a-real-page — you should see the site's own 404
page. *If you get a blank page or a server error, the `.htaccess` file did not
upload.*

**3. The subdomain is untouched.**
Open https://flow.wwwebtech.in — it should load as before. *If it does not, a
deploy has deleted it; restore from the backup on the server.*

---

## Enquiries reach you

**4. The form sends.**
Go to https://wwwebtech.in/contact/, fill it in with your own details, and
send it. You should see "Got it. A real person replies within 1 business day."

**5. The email arrives.**
Check the mailbox you set as *Send enquiries to*. It should be there within a
minute, with a **Reply** button that goes to whoever sent it. *If it does not
arrive: panel → Settings → Mailbox → Send me a test email. The error message
there says exactly what the mail server refused.*

**6. The sender got an acknowledgement.**
Check the address you used in step 4. There should be a short confirmation
saying when you will reply.

**7. It is in the panel.**
Panel → **Leads**. Your test enquiry is at the top. Open it, change the status
to *Contacted*, add a note, and save.

**8. The form works with JavaScript off.**
In your browser's settings, disable JavaScript for wwwebtech.in, send another
enquiry, and confirm you get a plain "Message sent" page. Turn JavaScript back
on. *This matters more than it sounds: it is the path used by people on
locked-down corporate networks.*

**9. Delete your test enquiries.**
Panel → Leads → open each test one → **Delete this lead**. Do this now so your
real numbers start clean.

---

## Measurement is running

**10. Your own visit was counted.**
Panel → **Analytics** → click **Rebuild totals now**. Your visits from the
steps above should appear under Pages. *If everything is zero: check that
Settings → Collect analytics is ticked, and that
https://wwwebtech.in/assets/js/wa.js loads.*

**11. The beacon is small and silent.**
Open https://wwwebtech.in/assets/js/wa.js — it should be about two kilobytes of
readable JavaScript. In your browser's developer tools, on any page of the
site, check the **Cookies** list for wwwebtech.in: it should be empty.

---

## The scheduled jobs are firing

**12. Wait for the top of the hour, then check the heartbeat.**
Panel → **Dashboard** → *Automation heartbeat*. `analytics_hourly` should show
**ok** with a recent time. *If it is missing entirely, the cron job in
DEPLOY.md step 7 was not created. If it shows **fail**, the message next to it
says why.*

**13. The SEO checks ran.**
Panel → **SEO health** → click **Run the daily checks**. It should finish in
under a minute and list its findings. Expect one or two warnings — that is the
point of it. *An SEO panel reporting that everything is perfect is a broken SEO
panel.*

---

## The panel is locked down

**14. You cannot reach it signed out.**
Sign out of the panel. Then open each of these in a private window and confirm
every one sends you to the login screen:

- https://wwwebtech.in/admin/
- https://wwwebtech.in/admin/?p=leads
- https://wwwebtech.in/admin/?p=settings

**15. Nothing private is exposed.**
Open each of these. Every one must give **404** or **403** — never a page of
text, and never a page starting `<?php`:

- https://wwwebtech.in/_wwt.php
- https://wwwebtech.in/serve.php
- https://wwwebtech.in/admin/_boot.php
- https://wwwebtech.in/admin/pages/settings.php
- https://wwwebtech.in/api/

*If any of these shows content, stop and get it fixed before telling anyone the
panel exists.*

---

## Strongly recommended, once everything above passes

**Turn on two-factor authentication.** Panel → Settings → Two-factor
authentication. It takes two minutes and it is the single biggest improvement
you can make to this setup's security.

**Change the credentials that were shared during setup.** Any password that has
been pasted into a chat, an email or a ticket should be rotated: your SSH/FTP
password, and any API token created for the deploy.
