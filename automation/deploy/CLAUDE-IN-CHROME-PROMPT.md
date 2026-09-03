# Prompt for Claude in Chrome

Copy everything inside the box below and paste it into Claude in Chrome, with
hPanel already open and signed in.

---

You are working inside my Hostinger hPanel. Finish setting up an admin panel
whose files are already uploaded to my server. Work carefully and stop if
anything does not match what I describe.

## Scope — read this first

- **The only account you may touch is the one hosting `wwwebtech.in`.**
  I have a second Hostinger account for an unrelated business. Do not open,
  change or even navigate into it. If a screen shows more than one hosting
  plan, pick the `wwwebtech.in` one and confirm the domain before doing
  anything.
- Do not delete anything, ever.
- Do not touch DNS, domains, billing, subscriptions, or the
  `flow.wwwebtech.in` subdomain.
- The only file you may edit is `/home/uXXXXXXXXX/wwt_private/config.php`.
  Change only the three lines I name. Do not reformat or rewrite the file.
- If a step's screen does not look like my description, stop and tell me what
  you see instead of improvising.

## Task 1 — Create the MySQL database

1. Go to **Hosting → wwwebtech.in → Databases → Management**.
2. In *Create a New MySQL Database And Database User*:
   - Database name: `wwt`
   - Database username: `wwt`
   - Password: generate a strong one (20+ characters, letters and digits
     only — avoid quotes, backslashes and spaces, because it goes into a PHP
     file in the next task).
3. Click **Create**.
4. **Write down all three values exactly as the page now lists them.** Hostinger
   adds an account prefix, so the real names will look like `uXXXXXXXXX_wwt`.
   You will need them in the next task and you must report them to me at the end.

## Task 2 — Put those values into the config file

1. Go to **Files → File Manager**.
2. Navigate to `/home/uXXXXXXXXX/wwt_private/` — note this is *beside*
   `domains`, not inside `public_html`. If you cannot see a `wwt_private`
   folder at that level, stop and tell me.
3. Open `config.php` in the editor.
4. Near the top there is a `'db' => [` block containing three placeholder
   lines. Replace only the placeholder text inside the quotes:

   | Line | Replace with |
   |---|---|
   | `'name'    => 'PASTE_DATABASE_NAME_HERE',` | the full database name, e.g. `uXXXXXXXXX_wwt` |
   | `'user'    => 'PASTE_DATABASE_USER_HERE',` | the full username, e.g. `uXXXXXXXXX_wwt` |
   | `'pass'    => 'PASTE_DATABASE_PASSWORD_HERE',` | the password you generated |

   Keep the single quotes, the commas and the spacing exactly as they are.
   Change nothing else in the file — in particular, leave every long random
   value under "Secrets" alone.
5. Save.

## Task 3 — Check it worked

Open **https://wwwebtech.in/admin/** in a new tab.

- **A "Create your account" screen** means everything is correct. Do not fill
  it in — that is my login and I will do it myself. Just confirm you saw it.
- **"The panel cannot reach its database"** means one of the three values in
  Task 2 is wrong. Go back and compare them character by character with what
  hPanel shows, including the `uXXXXXXXXX_` prefix.
- **Anything else** — stop and describe exactly what you see.

## Task 4 — Create the sending mailbox

1. Go to **Emails → Email Accounts** for wwwebtech.in.
2. Create an account named `no-reply@wwwebtech.in` and generate a password.
3. Report that password to me at the end. Do not put it in any file.

## Task 5 — Add the three scheduled jobs

Go to **Advanced → Cron Jobs**. Create these three, choosing *Custom* for the
type and pasting the command exactly:

| Command | Schedule |
|---|---|
| `/usr/bin/php /home/uXXXXXXXXX/wwt_private/cron/run.php hourly` | Once an hour |
| `/usr/bin/php /home/uXXXXXXXXX/wwt_private/cron/run.php daily` | Once a day, at 02:30 |
| `/usr/bin/php /home/uXXXXXXXXX/wwt_private/cron/run.php weekly` | Once a week, Monday 03:30 |

If hPanel asks for minute/hour/day fields instead of a dropdown, use
`0 * * * *`, `30 2 * * *` and `30 3 * * 1` respectively.

Do not run them manually and do not worry if they show no output yet.

## When you are done, report back

1. The database name, username and password from Task 1.
2. The `no-reply@wwwebtech.in` password from Task 4.
3. What you saw at https://wwwebtech.in/admin/ in Task 3.
4. Confirmation that all three cron jobs are listed.
5. Anything you could not do, or that looked different from my description.

Treat both passwords as sensitive: put them in your reply to me and nowhere else.
