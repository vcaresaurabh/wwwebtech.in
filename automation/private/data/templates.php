<?php
/* ============================================================
   templates.php — the static fallback for every step (§6.7).

   These send when the model is unavailable, when its draft fails the
   gates twice, or when the owner turns AI generation off entirely. They
   are the safety net, so they are written to be good enough to ship on
   their own — not placeholders apologising for the absence of something
   better.

   Tokens: {{first_name}} {{full_name}} {{service}} {{company}}
           {{booking_link}} {{sender}} {{site}} {{audit_link}}

   WhatsApp templates are ALL category `utility`. A persuasion message
   sent as a template costs several times more in India and risks the
   whole WhatsApp account; the sending path refuses anything else.
   ============================================================ */

declare(strict_types=1);

return [

'ack' => ['email', 'utility', 'Thanks {{first_name}} — a couple of things while you wait', <<<TXT
Hi {{first_name}},

Thanks for getting in touch about {{service}}. I have read what you sent and
I will come back to you properly within one business day.

Two things that might be useful before we speak. First, whatever we end up
recommending, you should own the code, the domain and every account — if a
quote does not say that plainly, ask. Second, the thing that decides most of
the cost is not the build, it is how much content and how many integrations
are involved, so it is worth thinking about both before you collect quotes.

If it is easier to talk than to type, you can pick a time here:
{{booking_link}}

Out of interest — what made you start looking now rather than six months ago?

{{sender}}
TXT],

'ack_hot' => ['email', 'utility', 'Thanks {{first_name}} — when suits you for a call?', <<<TXT
Hi {{first_name}},

Thanks for getting in touch about {{service}}. From what you have described
this is worth a proper conversation rather than an email exchange, so let us
find twenty minutes.

You can pick a time directly here: {{booking_link}}

If none of those work, reply with two times that suit you and I will make one
of them work. Before we speak I will have a look at what you sent so we are
not starting from the beginning.

{{sender}}
TXT],

'ack_cold' => ['email', 'utility', 'Thanks {{first_name}} — no pressure, here is something useful', <<<TXT
Hi {{first_name}},

Thanks for getting in touch about {{service}}. It sounds like you are working
out what you need rather than ready to start, which is a sensible place to be
and not one I am going to rush you out of.

Two things that might help while you look. Our free website audit checks the
things that actually cost you enquiries — speed, whether search engines and
AI assistants can read your site, and the technical basics most sites get
wrong. It takes two minutes and there is nothing to sit through:
{{audit_link}}

And when you do start collecting quotes, ask each one whether you will own the
code, the domain and the accounts at the end. The answers vary more than you
would expect.

If anything comes up, just reply.

{{sender}}
TXT],

'useful' => ['email', 'utility', 'One thing worth checking, {{first_name}}', <<<TXT
Hi {{first_name}},

One thing worth doing while you think about {{service}}, whether or not you
end up working with us.

Open your own website on a mid-range Android phone on mobile data, not on
your office wi-fi and not on a laptop. That is what most of your customers
actually experience, and it is usually slower than anyone in the business
realises. If it takes more than a few seconds before you can read anything,
that alone is losing you enquiries before anyone judges your prices.

Our free audit measures exactly that, using Google's own data rather than
our opinion: {{audit_link}}

{{sender}}
TXT],

'first30' => ['email', 'utility', 'What the first 30 days would look like', <<<TXT
Hi {{first_name}},

In case it helps to see how this actually runs, here is what the first month
on a {{service}} project looks like with us.

Week one is a working session on what you have now and what the work has to
achieve, written down so you can correct it. Week two is a fixed written
scope with a fixed price — if it is not in the document it is not in the
price, and nothing gets added without you agreeing to it. Weeks three and
four you see something working in your own browser every week, on your own
phone, so you are never more than seven days from being able to say this is
not what I meant.

No part of that requires you to commit before you have seen the scope.

If you would like to talk it through: {{booking_link}}

{{sender}}
TXT],

'lower_commit' => ['email', 'utility', 'A smaller way to start, {{first_name}}', <<<TXT
Hi {{first_name}},

If a full {{service}} project is more than you want to take on right now,
there is a smaller way in.

The free audit checks your site the way a search engine and an AI assistant
see it, and names the three things worth fixing first. You can hand the
result to any developer, including one who is not us — the point of it is
that the diagnosis is useful on its own: {{audit_link}}

If a couple of those fixes turn out to be worth paying someone to do, we can
quote for just those. Starting small is a perfectly reasonable way to find
out whether you want to work with someone.

{{sender}}
TXT],

'closeout' => ['email', 'utility', 'I will stop emailing now', <<<TXT
Hi {{first_name}},

I have written a few times about {{service}} and not heard back, which
usually means the timing is wrong or you have gone another way. Either is
completely fine, and I am not going to keep chasing you.

So this is the last one. If it becomes relevant again — this month or next
year — just reply to this email and I will pick it straight back up. Nothing
to fill in again.

Good luck with it either way.

{{sender}}
TXT],

'reactivate' => ['email', 'utility', 'One thing that changed since we spoke', <<<TXT
Hi {{first_name}},

Not chasing — one thing that has changed since you got in touch, in case it
is useful.

AI assistants now answer a growing share of the questions people used to
type into Google, and they cite some businesses and not others. The first
thing worth checking costs nothing: whether their crawlers are even allowed
to read your site. A surprising number of businesses are blocking them by
accident, through a robots.txt line nobody remembers adding.

Our free audit checks it among other things: {{audit_link}}

That is all. No reply needed.

{{sender}}
TXT],

'fallback_email' => ['email', 'utility', 'Following up on your enquiry', <<<TXT
Hi {{first_name}},

Following up on your enquiry about {{service}}. I wanted to check whether you
would like to talk it through, or whether the timing is not right at the
moment — both are useful to know.

If a short call would help: {{booking_link}}

{{sender}}
TXT],

/* ── WhatsApp. Utility only, and short. ─────────────────── */

'wa_slots' => ['whatsapp', 'utility', '', <<<TXT
Hi {{first_name}}, {{sender}} from Wwwebtech here about your {{service}}
enquiry. Would tomorrow morning or Thursday afternoon suit for a short call?
Reply with whichever is easier and I will send an invite.
TXT],

'fallback_wa' => ['whatsapp', 'utility', '', <<<TXT
Hi {{first_name}}, {{sender}} from Wwwebtech following up on your enquiry.
Happy to answer anything by message if that is easier than a call.
TXT],

];
