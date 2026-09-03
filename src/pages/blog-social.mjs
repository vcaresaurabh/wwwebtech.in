import { postPage } from '../partials/post.mjs';

const body = `
<p>Watch how someone under thirty-five looks for a caterer, a gym, a boutique or a coaching centre.
A good number of them do not open Google. They open Instagram and type into the search bar.</p>

<p>That changes what a social account is for. It is no longer only a feed that decays in forty-eight
hours — it is an index. And indexes reward the things indexes have always rewarded: clear words,
consistent signals, and content that stays useful after the trend dies.</p>

<p>Almost nobody in your category is treating it this way, which is exactly why it works.</p>

<h2 id="what-instagram-reads">What Instagram actually reads</h2>

<p>When you search on Instagram, it is matching your words against text it can read. Specifically:</p>

<ul>
  <li><strong>Your captions.</strong> The single biggest input, and the one most often wasted on
    three emojis and a full stop.</li>
  <li><strong>Text on screen in your reels.</strong> Read via OCR. The words you burn into the first
    frame are doing double duty.</li>
  <li><strong>What is said out loud.</strong> Transcribed automatically. If you never say the name
    of the thing you sell, it never gets indexed.</li>
  <li><strong>Alt text.</strong> Manually set, almost never used, genuinely read.</li>
  <li><strong>Your name and bio.</strong> Not just your handle — the display name field is
    searchable, which is why “Sharma Caterers | Wedding Catering Delhi” outperforms “Sharma
    Caterers”.</li>
  <li><strong>Location tags.</strong> Both for search and for the local surfaces.</li>
</ul>

<p>Note what is not on that list: hashtags are no longer doing the heavy lifting. Thirty hashtags
in a comment is a 2019 habit that survived past its usefulness.</p>

<h2 id="retention">Why retention beats reach</h2>

<p>For reels, the number that decides distribution is not likes. It is whether people finish
watching, and whether they watch twice.</p>

<p>That has one practical consequence worth more than any other tip here: <strong>your first two
seconds decide everything</strong>. Not your intro animation, not “hi guys, welcome back”. The
first frame should show the thing or state the problem. Everything else is negotiable.</p>

<p>Second practical consequence: shorter is usually better, because completion is a ratio. A
tight fifteen seconds that everyone finishes will out-travel a rambling sixty seconds that half
the audience abandons.</p>

<h2 id="the-loop">The loop back to Google</h2>

<p>Here is the part most businesses miss, and the reason we treat social and search as one job.</p>

<p>Someone sees your reel. They do not enquire — almost nobody enquires on first contact. But now
they know your name, and a week later, when they actually need the thing, they type your business
name into Google.</p>

<p>That is a branded search: the easiest search you will ever rank for, with the highest intent of
any query in your account. Social does not just generate direct enquiries. It manufactures the
demand that shows up in Google as people looking specifically for you.</p>

<p>Which means two things. Your social work should be measured partly by whether branded search
volume goes up — a number sitting in Search Console right now that most people never look at. And
your website had better be ready when they land, because that traffic is your warmest.</p>

{{DIAGRAM}}

<h2 id="thirty-days">A 30-day starter plan</h2>

<p>Not a strategy deck. Four weeks of specific work.</p>

<p><strong>Week 1 — fix the foundations.</strong> Rewrite your display name to include what you do
and where. Rewrite your bio so it says the service and the city in plain words. Convert to a
professional account if you have not. Pick the eight to ten phrases customers actually use for
your service — the words they say on the phone, not the industry terms.</p>

<p><strong>Week 2 — batch the content.</strong> One shoot day. Film ten to fifteen short pieces
answering the questions you get asked most often. One question per video, answered directly.
Resist the urge to make them beautiful; make them clear.</p>

<p><strong>Week 3 — publish properly.</strong> Post three or four times a week. Every post gets a
real caption with your phrases in normal sentences, on-screen text in the first frame, alt text
written by hand, and a location tag. Reply to every comment and every DM within a day — that reply
rate is both a ranking signal and, more importantly, where the money is.</p>

<p><strong>Week 4 — measure and prune.</strong> Look at which posts are still getting views from
search rather than from the feed; those are your evergreen winners and you should make more like
them. Check Search Console for branded search volume against the month before. Then stop doing
whichever format produced nothing.</p>

<h2 id="whatsapp">One India-specific note: the handover</h2>

<p>In India the journey almost always ends in WhatsApp. Someone watches, gets interested, and
messages — often outside working hours, often on a Sunday.</p>

<p>This is where most social budgets quietly leak. The content worked. The enquiry arrived. And it
sat unread until Tuesday, by which time they had messaged two competitors.</p>

<p>So before you spend anything on content, fix the handover: WhatsApp Business set up properly,
away messages that set an honest expectation, and enquiries logged somewhere that is not one
person's phone. A CRM that captures social enquiries alongside website ones is not a luxury here —
it is the difference between measuring your social spend and guessing at it.</p>

<p>Good content with a broken handover is an expensive way to send customers to whoever replies
faster.</p>
`;

export const page = postPage({
  slug: 'instagram-social-seo-india',
  caption: 'Social does not only produce direct enquiries. It manufactures branded searches on Google — the highest-intent queries you will ever receive.',
  links: ['socialseo', 'organic', 'crm'],
  body,
});
