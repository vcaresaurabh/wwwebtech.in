#!/usr/bin/env php
<?php
/* ============================================================
   funnel_test.php — the rules that must never quietly change.

   The HTTP gates in tools/ prove the plumbing. This proves the
   judgement: when the funnel stops, when it refuses to send, what it
   costs, and what it will never say. Those are the parts where a
   regression is silent — nothing errors, a customer just gets a message
   they should not have got.

   Runs against the dev database and cleans up after itself.
     php automation/tests/funnel_test.php
   ============================================================ */

declare(strict_types=1);
require_once dirname(__DIR__) . '/private/bootstrap.php';

$pass = 0; $fail = 0; $group = '';

function grp(string $t): void { global $group; $group = $t; echo "\n\033[1m── $t\033[0m\n"; }
function ok(string $what): void { global $pass; $pass++; printf("  \033[32mPASS\033[0m  %s\n", $what); }
function no(string $what, string $why): void { global $fail; $fail++; printf("  \033[31mFAIL\033[0m  %s — %s\n", $what, $why); }
function is_same(string $what, mixed $got, mixed $want): void {
    $got === $want ? ok($what) : no($what, sprintf('got %s, want %s', var_export($got, true), var_export($want, true)));
}
function is_true(string $what, mixed $got): void  { $got ? ok($what) : no($what, 'got false'); }
function is_false(string $what, mixed $got): void { !$got ? ok($what) : no($what, 'got true'); }
function has(string $what, string $hay, string $needle): void {
    str_contains($hay, $needle) ? ok($what) : no($what, "missing '" . $needle . "' in: " . substr($hay, 0, 120));
}
function hasnt(string $what, string $hay, string $needle): void {
    !str_contains($hay, $needle) ? ok($what) : no($what, "unexpected '" . $needle . "'");
}

/* A disposable lead, so the suite never touches a real one. */
function makeLead(array $over = []): int
{
    $id = DB::insert(
        "INSERT INTO wwt_leads (ts, name, email, phone, service, budget, message, page,
         consent_at, consent_text, status, is_test)
         VALUES (UTC_TIMESTAMP(), ?, ?, ?, 'SEO', '₹50k – ₹1.5L', 'Testing the funnel rules.',
                 '/lp/seo-delhi/', UTC_TIMESTAMP(), ?, 'new', 0)",
        [$over['name'] ?? 'Test Person',
         $over['email'] ?? ('funnel-test-' . bin2hex(random_bytes(4)) . '@example.com'),
         $over['phone'] ?? '+919999900001',
         Leads::CONSENT_VERSION]);
    foreach ($over as $k => $v) {
        if (in_array($k, ['name', 'email', 'phone'], true)) continue;
        DB::run("UPDATE wwt_leads SET `$k` = ? WHERE id = ?", [$v, $id]);   // SAFE-SQL: keys are literals below
    }
    return $id;
}
$made = [];
function lead(array $over = []): int { global $made; $id = makeLead($over); $made[] = $id; return $id; }

/* ══ 1. Stopping ═════════════════════════════════════════════
   §5.1: automation stops the moment a human engages. Every one of
   these is a way that can happen. */
grp('Stopping the sequence');

$id = lead();
Funnel::enrol($id, 'standard');
is_same('a fresh lead is enrolled',
    (string)DB::val("SELECT status FROM wwt_sequence_enrollments WHERE lead_id = ?", [$id]), 'active');
is_same('nothing stops it yet', Funnel::checkStop($id), '');

DB::insert("INSERT INTO wwt_messages (lead_id, ts, direction, channel, subject, body, status)
            VALUES (?, UTC_TIMESTAMP(), 'in', 'email', 'Re: your site', 'Yes please call me', 'received')", [$id]);
is_same('an inbound reply stops it', Funnel::checkStop($id), 'they replied');
is_same('the enrolment is marked stopped',
    (string)DB::val("SELECT status FROM wwt_sequence_enrollments WHERE lead_id = ?", [$id]), 'stopped');

/* A queued message left behind after a stop is how someone gets chased
   after they have already answered. */
$id = lead();
Funnel::enrol($id, 'standard');
$mid = DB::insert("INSERT INTO wwt_messages (lead_id, ts, direction, channel, subject, body, status)
                   VALUES (?, UTC_TIMESTAMP(), 'out', 'email', 'Following up', 'body', 'queued')", [$id]);
Funnel::stop($id, 'they replied');
is_same('a queued message is cancelled too',
    (string)DB::val('SELECT status FROM wwt_messages WHERE id = ?', [$mid]), 'skipped');

foreach ([['meeting', 'status is meeting'], ['won', 'status is won'], ['lost', 'status is lost']] as [$st, $want]) {
    $id = lead(); Funnel::enrol($id, 'standard');
    DB::run('UPDATE wwt_leads SET status = ? WHERE id = ?', [$st, $id]);
    is_same("status '$st' stops it", Funnel::checkStop($id), $want);
}

$id = lead(); Funnel::enrol($id, 'standard');
Funnel::optOut($id, 'test');
is_same('unsubscribing stops it', Funnel::checkStop($id), 'do not contact');

$id = lead(); Funnel::enrol($id, 'standard');
DB::insert("INSERT INTO wwt_bookings (lead_id, ts, starts_at, status, source)
            VALUES (?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 'booked', 'test')", [$id]);
is_same('a booked call stops it', Funnel::checkStop($id), 'a call is booked');

$id = lead();
for ($i = 0; $i < 2; $i++) {
    DB::insert("INSERT INTO wwt_messages (lead_id, ts, direction, channel, subject, body, status, error)
                VALUES (?, UTC_TIMESTAMP(), 'out', 'email', 's', 'b', 'failed', 'hard bounce')", [$id]);
}
Funnel::enrol($id, 'standard');
is_same('two hard bounces stop it', Funnel::checkStop($id), 'two hard bounces');

$id = lead(['is_partial' => 1]);
is_same('a partial lead is never enrolled', Funnel::stopReason(DB::one('SELECT * FROM wwt_leads WHERE id=?', [$id])), 'partial lead');
$id = lead(['is_test' => 1]);
is_same('a test lead is never enrolled', Funnel::stopReason(DB::one('SELECT * FROM wwt_leads WHERE id=?', [$id])), 'test lead');

/* ══ 2. Matching an inbound email to a lead ══════════════════ */
grp('Matching a reply to a lead');

$id = lead(['email' => 'reply-match-' . bin2hex(random_bytes(3)) . '@example.com']);
$email = (string)DB::val('SELECT email FROM wwt_leads WHERE id = ?', [$id]);

is_same('the +token wins',
    Inbox::matchLead("To: info+lead{$id}@wwwebtech.in\r\nFrom: someone@else.com\r\n", 'someone@else.com', ''), $id);

/* Unique per run: a Message-ID left behind by an earlier run would make
   this pass or fail depending on what else is in the table. */
$sentId = '<sent-' . bin2hex(random_bytes(6)) . '@wwwebtech.in>';
DB::insert("INSERT INTO wwt_messages (lead_id, ts, direction, channel, subject, body, provider_id, status)
            VALUES (?, UTC_TIMESTAMP(), 'out', 'email', 's', 'b', ?, 'sent')", [$id, $sentId]);
is_same('threading headers match',
    Inbox::matchLead("From: someone@else.com\r\n", 'someone@else.com', $sentId), $id);

is_same('the sender address matches', Inbox::matchLead("From: {$email}\r\n", $email, ''), $id);
is_same('an unknown sender matches nothing', Inbox::matchLead("From: nobody@example.org\r\n", 'nobody@example.org', ''), 0);
is_same('a +token for a lead that does not exist is ignored',
    Inbox::matchLead("To: info+lead99999999@wwwebtech.in\r\n", 'nobody@example.org', ''), 0);

grp('What is not a reply');
is_true('out-of-office',    Inbox::isAutomated('', 'Out of Office: Re: your website'));
is_true('automatic reply',  Inbox::isAutomated('', 'Automatic reply: enquiry'));
is_true('auto-submitted',   Inbox::isAutomated("Auto-Submitted: auto-replied\r\n", 'Re: hello'));
is_true('a bounce',         Inbox::isAutomated("Return-Path: <>\r\n", 'Undeliverable'));
is_true('bulk precedence',  Inbox::isAutomated("Precedence: bulk\r\n", 'Newsletter'));
is_false('a real reply',    Inbox::isAutomated("Auto-Submitted: no\r\nFrom: a@b.com\r\n", 'Re: your website'));

grp('Trimming the quoted history');
$threaded = "Yes, Tuesday works.\n\nOn Mon, 1 Sep 2026 at 10:04, Wwwebtech <info@wwwebtech.in> wrote:\n> Would Tuesday suit?\n> — Saurabh";
is_same('the reply survives, the quote goes', Inbox::trimQuoted($threaded), 'Yes, Tuesday works.');
is_same('an unquoted message is untouched', Inbox::trimQuoted('Just this.'), 'Just this.');

/* ══ 3. When it is allowed to send ═══════════════════════════
   §6.5. Quiet hours are law-shaped, not a preference. */
grp('Quiet hours and business time');

$tz = Funnel::tz();
$at = static fn(string $s) => new DateTimeImmutable($s, $tz);
is_true ('22:30 is quiet',        Funnel::inQuietHours($at('2026-09-07 22:30')));
is_true ('07:00 is quiet',        Funnel::inQuietHours($at('2026-09-07 07:00')));
is_false('11:00 is not quiet',    Funnel::inQuietHours($at('2026-09-07 11:00')));
is_true ('Monday 11:00 is business time', Funnel::isBusinessTime($at('2026-09-07 11:00')));
is_false('Sunday 11:00 is not',   Funnel::isBusinessTime($at('2026-09-06 11:00')));
is_false('Monday 08:00 is not',   Funnel::isBusinessTime($at('2026-09-07 08:00')));
is_false('Monday 21:00 is not',   Funnel::isBusinessTime($at('2026-09-07 21:00')));

/* A send scheduled inside quiet hours must move forward, never back. */
$night = new DateTimeImmutable('2026-09-07 19:00', new DateTimeZone('UTC'));  // 00:30 IST, Tuesday
$moved = Funnel::nextSendableUtc($night);
is_true('a night-time send is pushed forward', $moved > $night);
is_true('and lands inside business hours', Funnel::isBusinessTime($moved->setTimezone($tz)));

grp('Throttling');
$id = lead();
is_same('nothing sent yet, so nothing blocks', Funnel::throttleReason($id, 'email'), '');
DB::insert("INSERT INTO wwt_messages (lead_id, ts, direction, channel, subject, body, status)
            VALUES (?, UTC_TIMESTAMP(), 'out', 'email', 's', 'b', 'sent')", [$id]);
is_true('a second email the same day is blocked', Funnel::throttleReason($id, 'email') !== '');

/* ══ 4. Consent and unsubscribe ══════════════════════════════ */
grp('Consent and unsubscribe');
$id = lead();
$tok = Funnel::unsubToken($id);
is_true ('the token verifies',            Funnel::checkUnsubToken($id, $tok));
is_false('a tampered token does not',     Funnel::checkUnsubToken($id, strrev($tok)));
is_false('another lead cannot use it',    Funnel::checkUnsubToken($id + 1, $tok));
has('the link carries the token', Funnel::unsubscribeUrl($id), $tok);
Funnel::optOut($id, 'test');
is_same('opting out sets do-not-contact', (int)DB::val('SELECT do_not_contact FROM wwt_leads WHERE id=?', [$id]), 1);
is_true('consent wording is recorded with the lead', Leads::CONSENT_VERSION !== '');

/* ══ 5. What the AI is allowed to say ════════════════════════
   §4.4: no fabricated proof, one CTA, no unearned promises. */
grp('Message quality gates');

$leadRow = DB::one('SELECT * FROM wwt_leads WHERE id = ?', [lead(['name' => 'Anita'])]);
/* gates() returns the reasons it would refuse to send. Empty means send. */
$why = static fn(string $body): array => FunnelWriter::gates($body, $leadRow, 'email', []);
$passes = static function (string $what, string $body) use ($why): void {
    $f = $why($body);
    $f === [] ? ok($what) : no($what, 'refused: ' . implode('; ', $f));
};
$refused = static function (string $what, string $body, string $expect) use ($why): void {
    $f = $why($body);
    if ($f === []) { no($what, 'it was allowed through'); return; }
    str_contains(strtolower(implode(' | ', $f)), $expect)
        ? ok($what) : no($what, 'refused for the wrong reason: ' . implode('; ', $f));
};

$passes('a plain, honest message passes',
    "Hi Anita,\n\nYou asked about SEO for your site last week. I had a look, and the biggest "
  . "thing holding it back is how slowly it loads on a phone — about four seconds before anything "
  . "useful appears. That is fixable in a day, and it usually moves rankings on its own.\n\n"
  . "Want me to walk you through what I found? Just reply here.\n\nSaurabh");

$refused('a message that invents a client is refused',
    "Hi Anita,\n\nWe helped Sharma Textiles triple their traffic in sixty days, and we guarantee "
  . "first page rankings for every client we take on. There is no risk at all here. Reply and I "
  . "will send you the same plan we used for them.\n\nSaurabh", 'guarantee');

$refused('an unfilled token is refused',
    "Hi {{name}},\n\nI had a look at your site this morning and there is one quick win on the "
  . "mobile layout that is worth doing before anything else. It would take an afternoon. Reply "
  . "here and I will explain what I mean.\n\nSaurabh", 'placeholder');

$refused('a link to somewhere else is refused',
    "Hi Anita,\n\nI wrote up what I found on your site and put it here: "
  . "https://random-example.org/article-about-your-website — have a read and tell me whether it "
  . "matches what you are seeing at your end. It is about a five minute read.\n\nSaurabh", 'link');

$refused('not using their name is refused',
    "Hello there,\n\nI looked at the site this week and there is one quick win on mobile speed "
  . "that would be worth doing before anything else on the list. It is an afternoon of work. "
  . "Reply here and I will explain what I found.\n\nSaurabh", 'name');

/* ══ 6. WhatsApp cost and category ═══════════════════════════ */
grp('WhatsApp guard rails');
/* Digits, no '+': the form the Cloud API wants in a `to` field. */
is_same('an Indian mobile gets its country code', WhatsApp::e164('98765 43210'), '919876543210');
is_same('a number written with + is normalised', WhatsApp::e164('+91 98765 43210'), '919876543210');
is_same('nonsense is rejected', WhatsApp::e164('12'), '');

/* ══ 7. Offline conversions ══════════════════════════════════
   Google matches on a SHA-256 of a normalised address. Get the
   normalisation wrong and every conversion silently fails to match. */
grp('Ads conversion hashing');
is_same('lowercased and trimmed before hashing',
    Ads::hashEmail('  Test@Example.COM '),
    hash('sha256', 'test@example.com'));
is_same('gmail dots are stripped',
    Ads::hashEmail('first.last@gmail.com'), Ads::hashEmail('firstlast@gmail.com'));
is_same('other domains keep their dots',
    Ads::hashEmail('first.last@outlook.com'), hash('sha256', 'first.last@outlook.com'));
is_same('phones hash in E.164 with the plus',
    Ads::hashPhone('98765 43210'), hash('sha256', '+919876543210'));

/* ══ 8. The audit tool ═══════════════════════════════════════ */
grp('Audit tool input handling');
is_same('a bare domain becomes a URL', AuditTool::normaliseUrl('mysite.in'), 'https://mysite.in/');
is_same('a path is kept', AuditTool::normaliseUrl('http://mysite.in/about'), 'http://mysite.in/about');
is_same('nonsense is rejected', AuditTool::normaliseUrl('not a website'), '');
is_true ('localhost is refused',  AuditTool::isPrivateHost('localhost'));
is_true ('a private IP is refused', AuditTool::isPrivateHost('192.168.1.1'));
is_false('a public host is allowed', AuditTool::isPrivateHost('wwwebtech.in'));

$sample = '<html><head><title>A shop</title><meta name=description content=\'Short one\'>'
        . '<script type="application/ld+json">{"@context":"https://schema.org","@graph":'
        . '[{"@type":["Organization","LocalBusiness"],"name":"X"}]}</script></head>'
        . '<body><h1>Hello</h1></body></html>';
is_same('single-quoted attributes are read', AuditTool::meta($sample, 'description'), 'Short one');
is_same('the title is read', AuditTool::tag($sample, 'title'), 'A shop');
$r = new ReflectionMethod(AuditTool::class, 'checkStructuredData');
$r->setAccessible(true);
is_same('types nested in @graph are found', $r->invoke(null, $sample)[0]['state'], 'ok');

/* ── Clean up ─────────────────────────────────────────────── */
foreach ($made as $id) {
    DB::run('DELETE FROM wwt_messages WHERE lead_id = ?', [$id]);
    DB::run('DELETE FROM wwt_sequence_enrollments WHERE lead_id = ?', [$id]);
    DB::run('DELETE FROM wwt_lead_events WHERE lead_id = ?', [$id]);
    DB::run('DELETE FROM wwt_bookings WHERE lead_id = ?', [$id]);
    DB::run('DELETE FROM wwt_consents WHERE lead_id = ?', [$id]);
    DB::run('DELETE FROM wwt_leads WHERE id = ?', [$id]);
}

printf("\n\033[1m%d passed, %d failed\033[0m  (%d test leads cleaned up)\n", $pass, $fail, count($made));
exit($fail === 0 ? 0 : 1);
