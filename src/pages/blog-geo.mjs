import { postPage } from '../partials/post.mjs';

const body = `
<p>Ask ChatGPT to recommend a CRM developer in Delhi, or a wedding photographer in Noida, or an
industrial supplier in Ghaziabad. You will get an answer. It will name two, three, maybe four
businesses. Then it will stop.</p>

<p>There is no page two. There is no “see more results”. For the person asking, those three names
are the entire market.</p>

<p>That is a genuinely new situation, and it is worth an hour of your attention now rather than a
panic in two years.</p>

<h2 id="how-it-works">How an AI answer actually gets built</h2>

<p>It helps to stop imagining these systems as search engines with better manners. They are not
ranking your page against nine others. They are assembling an <em>understanding</em> of your
category and then writing a short answer from it.</p>

<p>To do that they lean on three sources, and — this is the important part — they cross-check them
against each other:</p>

<ul>
  <li><strong>Your website.</strong> What you say you do, where you say you do it, and how clearly
    it is structured.</li>
  <li><strong>Your Google Business Profile and similar listings.</strong> Independently maintained,
    harder to fake, so treated as corroboration.</li>
  <li><strong>Third-party mentions.</strong> Directories, press, marketplaces, forum posts,
    anywhere your name appears alongside a description.</li>
</ul>

<p>Where those three agree, the model is confident enough to name you. Where they disagree — an old
address on one directory, a service you dropped still listed on another, a different phone number
on Facebook — it becomes uncertain. And an uncertain model does not hedge out loud. It just names
someone else.</p>

{{DIAGRAM}}

<h2 id="the-opportunity">Why this favours small businesses right now</h2>

<p>This is unusual, so it is worth saying plainly: the current moment favours the organised small
business over the large disorganised one.</p>

<p>A big company often has years of accumulated contradiction — regional pages saying different
things, three Google profiles from three office moves, an old brand name still circulating. A
focused ten-person business in East Delhi can make its entire public footprint consistent in a
couple of weeks.</p>

<p>Add to that: almost nobody in your category is doing this deliberately yet. The people who
tidied their Google Business Profile in 2015 spent the next decade in the map pack. This is the
same kind of window, and it will close the same way.</p>

<h2 id="five-steps">Five things to do first</h2>

<ol>
  <li><strong>Find out what they currently say about you.</strong> Open ChatGPT, Gemini and Google
    with AI Overviews on. Ask the questions a customer would actually ask — “best X in Y”, “who does
    Z in Delhi”, “is [your business] any good”. Write down what comes back, including who gets named
    instead of you. This is your baseline, and it takes twenty minutes.</li>

  <li><strong>Make your identity boringly consistent.</strong> Exact same business name, address and
    phone number everywhere: your site footer, your Google profile, Justdial, IndiaMART, LinkedIn,
    Instagram, your invoices. Not “roughly the same”. Identical. This is dull and it is the highest
    return item on the list.</li>

  <li><strong>Say what you do in plain sentences.</strong> Somewhere on your site there should be a
    sentence a machine could quote without editing: “Wwwebtech builds websites, CRM systems and
    search visibility for growing businesses in Delhi and across India.” Not “we deliver synergistic
    digital transformation”. A model cannot extract a claim from a slogan.</li>

  <li><strong>Add structured data that matches reality.</strong> Schema markup describes your
    business in a format machines read directly — Organization, LocalBusiness, Service, FAQ. Two
    warnings. It must describe what is genuinely on the page, and you must never add review or
    rating markup for reviews you do not have. That is not a shortcut; it is a manual action waiting
    to happen.</li>

  <li><strong>Answer the real questions, in public.</strong> Every question you answer on the phone
    five times a week is a page. Put the direct answer in the first two lines, then the detail
    underneath. That structure is what makes content quotable — the model needs a clean claim it can
    lift, not a build-up.</li>
</ol>

<h2 id="what-doesnt-work">What does not work</h2>

<p>Some predictable things are already being sold. They do not work.</p>

<p><strong>Stuffing “as an AI language model” bait into your pages.</strong> Prompt-injection tricks
aimed at assistants are the 2026 equivalent of white text on a white background. They get patched,
and then you are the site that tried it.</p>

<p><strong>Mass-generated “answer” pages.</strong> Two hundred thin pages targeting question
phrases is the tactic that got content farms flattened in ordinary search. Answer engines are, if
anything, more sensitive to it, because they are looking for a source worth trusting.</p>

<p><strong>Buying mentions on low-quality directories.</strong> Corroboration only counts if the
corroborating source has any standing. Fifty junk listings do not add up to one real one.</p>

<h2 id="measuring">How to measure something this new</h2>

<p>Be honest with yourself and with whoever you hire: there is no rank tracker for this yet.</p>

<p>What you can do is keep a fixed set of maybe fifteen prompts — the real questions your customers
ask — and run them monthly, recording whether you are named, how you are described, and who appears
instead. It is a sample, not a ranking report. Anyone selling you a precise “AI visibility score”
is selling you a number they invented.</p>

<p>The reassuring part: nearly everything on the list above also helps you in ordinary Google
results. Clear structure, accurate information, fast pages, real answers to real questions. You are
not gambling on a new channel. You are doing the fundamentals properly and getting the AI surface
as a side effect.</p>

<p>Which is, generally, how the good version of this work has always looked.</p>
`;

export const page = postPage({
  slug: 'chatgpt-ai-search-visibility',
  caption: 'Three sources, cross-checked. Consistency is not a nice-to-have here — a contradiction is what causes a model to name your competitor instead.',
  links: ['geo', 'local', 'content'],
  body,
});
