# Putting the automation layer live

This adds an admin panel, a database and three small API endpoints to
wwwebtech.in. The website itself does not change.

**Time: about 30 minutes.** You will not need to edit a file, run any SQL, or
open a terminal. Everything is done in hPanel and in the panel's own screens.

Read the whole of step 1 before starting: it is the only part where a wrong
click is annoying to undo.

> **`uXXXXXXXXX` in this guide means your Hostinger account name.** It is the
> `u`-and-digits string shown in hPanel under **Advanced → SSH Access**, and it
> also prefixes every database and file path Hostinger creates for you.
> Wherever you see `uXXXXXXXXX` below, substitute yours. It is written as a
> placeholder because this repository is public, and an account name plus a
> server address is most of what someone needs to start guessing at a password.

---

## What you need before you start

| # | Thing | Where it comes from |
|---|---|---|
| SETUP-1 | An Anthropic API key | console.anthropic.com → API keys. Only needed for the blog. |
| SETUP-2 | An email mailbox on your domain | hPanel → Emails. Created in step 4 below. |
| SETUP-3 | Your hPanel login | You already have it. |
| SETUP-4 | *(optional)* A PageSpeed API key | Free, from the Google Cloud console. Only needed for speed measurement. |

The panel works without SETUP-1 and SETUP-4. It will tell you plainly which
features are switched off because something is missing, rather than failing
quietly.

---

## Step 1 — Create the database

1. hPanel → **Databases** → **Management**.
2. Under *Create a New MySQL Database And Database User*, fill in:
   - **Database name:** `wwt`  → the real name becomes `uXXXXXXXXX_wwt`
   - **Database username:** `wwt` → the real name becomes `uXXXXXXXXX_wwt`
   - **Password:** click the generator. **Copy it somewhere now** — this screen
     will not show it again.
3. Click **Create**.
4. Write down the three values exactly as hPanel shows them:

   ```
   Database name: uXXXXXXXXX_wwt
   Username:      uXXXXXXXXX_wwt
   Password:      (the one you just copied)
   ```

You do **not** need to import anything. The panel creates its own tables the
first time you open it.

---

## Step 2 — Upload the files

Two folders go to two different places. This matters: `wwt_private` holds your
database password and must sit **outside** the public web folder, where nothing
on the internet can reach it.

### 2a. The private folder

1. hPanel → **Files** → **File Manager**.
2. You should be in `/home/uXXXXXXXXX`. If you can see a folder called
   `domains`, you are in the right place.
3. Click **New Folder**, name it `wwt_private`, and open it.
4. Click **Upload** and upload everything from the `automation/private/`
   folder of the project, keeping the folders inside it:

   ```
   wwt_private/
     bootstrap.php
     schema.sql
     config.sample.php
     cron/
     data/
     lib/
     templates/
     vendor/
   ```

5. Do **not** upload `config.php` if the project has one — you will create a
   fresh one in step 3.

### 2b. The web files

1. Go to `domains/wwwebtech.in/public_html`.
2. Upload from `automation/webroot/`:
   - the folder `admin/`
   - the folder `api/`
   - the files `serve.php` and `_wwt.php`
3. Upload the whole contents of the project's `site/` folder as well, replacing
   what is there. This is the normal website deploy, and it carries the
   markers the panel needs to write blog teasers and tags into the pages.
4. **Check the hidden files came across.** File Manager hides names starting
   with a dot. Turn on *Show hidden files* and confirm you can see
   `.htaccess` in `public_html` and in `public_html/api/` and
   `public_html/admin/`. Without them the panel is far less protected.

---

## Step 3 — Create the configuration file

1. In File Manager, open `/home/uXXXXXXXXX/wwt_private`.
2. Right-click `config.sample.php` → **Copy**, then rename the copy to
   **`config.php`**.
3. Right-click `config.php` → **Edit**.
4. Fill in the database block with the three values from step 1:

   ```php
   'db' => [
     'host'    => 'localhost',
     'name'    => 'uXXXXXXXXX_wwt',
     'user'    => 'uXXXXXXXXX_wwt',
     'pass'    => 'the password you copied',
     'charset' => 'utf8mb4',
   ],
   ```

5. Set `webroot` to the real path:

   ```php
   'webroot' => '/home/uXXXXXXXXX/domains/wwwebtech.in/public_html',
   ```

6. Replace **all four** secret values with different random strings of your
   own. They must not be left as the examples. Any long random text is fine —
   mash the keyboard for 40 characters each, or use a password generator:

   ```php
   'secret_key'   => '...',   // encrypts the passwords the panel stores
   'cron_key'     => '...',
   'session_salt' => '...',
   'geo_salt'     => '...',
   ```

7. Leave everything else as it is. The mailbox and API keys are entered in the
   panel later, not here.
8. **Save**.

---

## Step 4 — Create the mailbox that sends your enquiries

Enquiries are sent from a real mailbox on your domain, so they are properly
signed and land in the inbox instead of spam. `mail()` is never used.

1. hPanel → **Emails** → **Email Accounts** → **Create email account**.
2. Address: `no-reply@wwwebtech.in`. Generate a password and copy it.
3. Note the sending details hPanel shows. They are normally:

   ```
   SMTP server: smtp.hostinger.com
   Port:        465
   Encryption:  SSL
   ```

You will paste these into the panel in step 6.

---

## Step 5 — Open the panel and create your account

1. Go to **https://wwwebtech.in/admin/**.
2. The first time, it offers to create the owner account. Use your own email
   and a password of at least 12 characters.
3. That is the only time this screen appears. Once an account exists it closes
   itself permanently.

If you instead see *"Configuration missing"* or a database error, step 3 has a
typo in it — go back and check the four database values character by character.

---

## Step 6 — Fill in the mailbox

1. In the panel: **Settings** → **Mailbox**.
2. Enter the SMTP server, port and encryption from step 4.
3. Mailbox address: `no-reply@wwwebtech.in`, and the password you copied.
4. **Send enquiries to:** the address you actually read, e.g.
   `contact@wwwebtech.in`.
5. **Save mailbox settings**, then click **Send me a test email**.
6. Check that mailbox. If it arrives, the contact form works. If it does not,
   the panel shows exactly what the mail server said.

---

## Step 6b — Point the contact form at the panel

Until this step, the form has nowhere to post: `api/lead.php` answers with a 503 page that names the address to email,
which emails you but records nothing. Do this **only after the test email in
step 6 arrived**, so you are never without a working form.

Whoever maintains the site does this, in one line:

1. Open `src/data.mjs` and change:

   ```js
   leadEndpoint: '/api/lead.php',
   ```

   to:

   ```js
   leadEndpoint: '/api/lead.php',
   ```

2. Run `node build.mjs`.
3. Upload the `site/` folder again (or run `tools/deploy.sh`).

From then on every enquiry is saved to the database first and emailed second,
and appears under **Leads**.

To check it worked: send yourself one more test enquiry and confirm it appears
in the panel, not just in your inbox.

---

## Step 7 — Set up the scheduled jobs

Hostinger has no `crontab` command; jobs are added through the panel.

1. hPanel → **Advanced** → **Cron Jobs**.
2. For each row in the table below:
   - **Type of cron job:** choose *Custom*
   - **Command to run:** paste the command exactly
   - **Common Settings / Time:** choose the schedule described
   - Click **Create**

| # | Command to run | Schedule to choose |
|---|---|---|
| 1 | `/usr/bin/php /home/uXXXXXXXXX/wwt_private/cron/run.php frequent` | Every 5 minutes |
| 2 | `/usr/bin/php /home/uXXXXXXXXX/wwt_private/cron/run.php hourly` | Once an hour |
| 3 | `/usr/bin/php /home/uXXXXXXXXX/wwt_private/cron/run.php daily` | Once a day — set it to **02:30** |
| 4 | `/usr/bin/php /home/uXXXXXXXXX/wwt_private/cron/run.php weekly` | Once a week — Monday, **03:30** |

Times are your server's time. The panel shows every run and its result on the
**Dashboard**, so you can confirm they are firing without checking hPanel again.

> If hPanel asks for a separate "minute / hour / day" grid instead of a
> dropdown, the four schedules are `*/5 * * * *`, `0 * * * *`, `30 2 * * *`,
> and `30 3 * * 1`.

**Why row 1 matters.** It is what checks for replies, sends approved follow-up
messages, and runs queued website audits. On the hourly schedule alone, someone
could reply to a follow-up and still receive the next one up to an hour later —
which is the single behaviour the whole system is built to avoid.

It is cheap: with nothing to do it makes three quick queries and exits. Row 2
runs the same jobs again as a safety net, so if you skip row 1 entirely
everything still works, just slower.

---

## Step 8 — Optional: switch on the blog

Only do this once you have read a post it wrote and are happy with it.

1. Panel → **Blog**.
2. Paste your Anthropic API key (SETUP-1) and click **Save**.
3. Click **Check the key works**. It costs a fraction of a cent.
4. Click **Write a post**, leaving *publish straight away* unticked. Read the
   draft. Publish it only if you would have been happy to sign it.
5. When you are ready for it to run on its own, click **Start publishing**.
6. The **Stop publishing** button is the first thing on that page, always.

Set a spend limit in the Anthropic console as well as the monthly cap in the
panel. Two brakes are better than one.

---

## Step 9 — Optional: analytics and speed

- **Speed measurement:** panel → **SEO health** → paste a PageSpeed API key
  (SETUP-4). Without it, that panel says so rather than showing a number that
  is not measured.
- **Google Analytics, Meta Pixel, and similar:** panel → **Integrations**.
  Read the warning on that page first — the site currently tells visitors it
  sets no cookies, and most of those tags make that untrue.

---

## Step 10 — Check it worked

Follow **TEST-AFTER-DEPLOY.md**. It is fifteen checks and takes about ten
minutes. Do not skip the contact-form one.

---

## If you need to undo this

Nothing here modifies the website's own pages except the marked blocks used
for blog teasers and tags. To remove the automation layer entirely:

1. hPanel → Cron Jobs → delete the three jobs.
2. File Manager → delete `public_html/admin`, `public_html/api`,
   `public_html/serve.php`, `public_html/_wwt.php`.
3. Delete `/home/uXXXXXXXXX/wwt_private`.
4. hPanel → Databases → delete the `uXXXXXXXXX_wwt` database.

The website keeps working throughout. The contact form falls back to whatever
`site/assets/js/main.js` has as its endpoint — point it at a Formspree-style URL
in `src/data.mjs` and rebuild if you remove the API.

---

## Reference: where everything lives

```
/home/uXXXXXXXXX/
  wwt_private/                 ← never reachable from the internet
    config.php                   your passwords
    schema.sql                   the database structure
    bootstrap.php  lib/  cron/  data/  templates/  vendor/
    logs/                        written by the panel
    posts/                       the source text of generated posts

  domains/wwwebtech.in/public_html/
    index.html  blog/  services/  …    the website
    admin/                       the panel
    api/                         lead.php, hit.php
    serve.php  _wwt.php
```
