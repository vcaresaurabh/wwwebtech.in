import { postPage } from '../partials/post.mjs';

const body = `
<p>Everyone can tell when a website is slow. Almost nobody can tell you <em>why</em>, which is
how agencies get away with charging for “speed optimisation” that moves a score by four points
and changes nothing a customer would notice.</p>

<p>This is the version for the person paying the invoice. No jargon you have to keep, no tools
you have to learn — just what actually makes a site feel slow in India, in the order that
matters, and how to check your own in about ten minutes.</p>

<h2 id="what-google-measures">What Google actually measures</h2>

<p>Google grades every site on three things, collected from real Chrome users rather than from a
test machine. That last part matters: your site is being scored on what your customers on a
mid-range Android phone in Ghaziabad experience, not on what your MacBook on office wi-fi does.</p>

<p>The three numbers are:</p>

<ul>
  <li><strong>LCP — how long until the main thing appears.</strong> Usually your headline or hero
    image. Under 2.5 seconds is good. Over 4 and Google calls it poor.</li>
  <li><strong>INP — how long the page takes to respond when someone taps.</strong> Under 200
    milliseconds is good. This is the one that makes a site feel “stuck”.</li>
  <li><strong>CLS — how much the page jumps around while loading.</strong> The thing that makes
    you tap the wrong button because an ad loaded above it. Under 0.1.</li>
</ul>

<p>You do not need to memorise these. You need to know they exist, that they are measured from
your real visitors, and that they feed into what Google shows.</p>

{{DIAGRAM}}

<h2 id="why-india">Why India is a harder test</h2>

<p>Two things make the Indian web meaningfully harder than the average benchmark suggests.</p>

<p>The first is the device. The median Indian smartphone is not a flagship. It has a slower
processor, and processors are what run JavaScript. A page loaded with scripts might feel fine on
a recent iPhone and take four times as long to become interactive on a mid-range Android. That
gap is the single most under-appreciated fact in Indian web performance.</p>

<p>The second is the network. Mobile data coverage is broad, but real-world conditions vary
enormously — in a lift, on a train, in a basement showroom, in a crowded market at 7pm. Your site
does not get to choose which of those conditions your customer is in when they look you up.</p>

<p>The practical consequence: <strong>page weight and script weight cost you more here than they
would in most markets</strong>. A homepage that ships three megabytes is not a small mistake.</p>

<h2 id="seven-fixes">The seven fixes that actually matter</h2>

<p>In rough order of return on effort:</p>

<ol>
  <li><strong>Fix your images.</strong> This is nearly always the biggest single win and the least
    interesting. Photos uploaded straight from a camera or phone at four or five megabytes, resized
    with CSS rather than actually resized. Convert to WebP, size them to what the page needs, and
    set width and height so nothing jumps.</li>
  <li><strong>Delete the scripts nobody uses.</strong> Chat widgets from a trial, three analytics
    tools, a popup builder, an abandoned A/B test. Each one costs you on every page load forever.
    Open the list and be ruthless.</li>
  <li><strong>Host closer to your customers.</strong> If your buyers are in India and your server
    is in Ohio, every single request pays for the round trip. Choose an Indian or Singapore region,
    or put a CDN in front.</li>
  <li><strong>Stop the layout jumping.</strong> Reserve space for images, ads and embeds before
    they arrive. Load fonts so text does not reflow when the real one lands. This is pure craft and
    costs nothing but attention.</li>
  <li><strong>Cut the fonts.</strong> Four families in nine weights is a design decision with a
    performance invoice. Two families, self-hosted, subset to the characters you use.</li>
  <li><strong>Turn on caching and compression.</strong> Sitting in your hosting panel, usually off,
    usually free. Ask whoever manages your hosting: “is Brotli or gzip enabled, and what are the
    cache headers on static files?” The answer tells you a lot about them, too.</li>
  <li><strong>Reduce what runs before the first paint.</strong> If your page cannot display a
    headline until a JavaScript bundle has downloaded, parsed and executed, you have chosen an
    architecture that punishes exactly the customers you are trying to reach.</li>
</ol>

<h2 id="what-not-to-buy">What not to spend money on</h2>

<p>A few things get sold as speed work and mostly are not.</p>

<p><strong>Chasing a 100 in PageSpeed Insights.</strong> The lab score is a diagnostic, not the
goal. What counts is the field data — real users — and a site can score 92 and feel excellent
while another scores 99 and feels sluggish on a real phone.</p>

<p><strong>Another caching plugin on top of the two you have.</strong> Stacked caching layers
usually create bugs, not speed.</p>

<p><strong>A migration you did not need.</strong> Sometimes the platform genuinely is the problem.
Often the problem is six megabytes of images and a chat widget, and the migration is a way of
selling you a rebuild. Ask for the measurement first.</p>

<h2 id="checklist">Ten minutes: audit your own site</h2>

<p>Do this on your phone, on mobile data, away from your office wi-fi.</p>

<ul>
  <li>Open your homepage. Count seconds until you can read the headline. Over three, you have a
    problem worth money.</li>
  <li>Tap a menu item immediately. Does it respond, or does nothing happen for a beat?</li>
  <li>Watch whether anything jumps as it loads.</li>
  <li>Now do the same on your most important product or service page — not just the homepage, which
    is usually the only page anyone optimises.</li>
  <li>Run the page through Google PageSpeed Insights and look only at the top section, the real-user
    data. If it says “not enough data”, your traffic is low enough that the lab score is all you have.</li>
  <li>Ask your developer for the total page weight of your homepage in megabytes. If nobody can
    answer, that is the answer.</li>
</ul>

<p>None of this requires you to become technical. It requires you to check, on the device your
customers actually use, and to ask for a number rather than a reassurance.</p>
`;

export const page = postPage({
  slug: 'website-speed-india',
  caption: 'Google grades each metric from real visitors. The middle line is the one that matters — beyond it, the experience is officially classed as poor.',
  links: ['techseo', 'websites', 'crm'],
  body,
});
