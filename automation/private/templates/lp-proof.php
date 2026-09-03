<?php
/* ============================================================
   Block 7 — proof.

   §2.4: where the owner has no cleared client names or testimonials,
   these honest alternatives run instead. They are not placeholders
   apologising for missing content — a delivery plan and a live
   PageSpeed score answer the buyer's real anxiety better than a
   logo wall they cannot verify.

   Switch $lp['proof_mode'] to 'cases' once [SETUP-7] supplies material.
   ============================================================ */
declare(strict_types=1);

$cwv = null;
try {
    $cwv = DB::one("SELECT perf, lcp_ms, cls, strategy, d FROM wwt_cwv
                    WHERE strategy = 'mobile' ORDER BY d DESC, id DESC LIMIT 1");
} catch (Throwable) { /* proof degrades to the plan cards; never fatal */ }
?>
<section class="sec sec--rule">
  <div class="shell">
    <div class="sec__head">
      <p class="mono">Proof</p>
      <h2><?= e($lp['proof_head']) ?></h2>
      <p><?= e($lp['proof_sub']) ?></p>
    </div>

    <div class="grid grid--3">

      <?php /* Self-proof. For a web and SEO firm this is the most relevant
               evidence available, and it is checkable in ten seconds. */ ?>
      <div class="card">
        <p class="mono">Our own numbers</p>
        <h3 style="margin-top:.4rem">This site, measured by Google</h3>
        <?php if ($cwv && $cwv['perf'] !== null): ?>
          <p style="font-family:var(--display);font-size:2.6rem;color:var(--ink);
                    line-height:1;margin:.6rem 0 .3rem"><?= (int)$cwv['perf'] ?>/100</p>
          <p>PageSpeed performance, mobile, measured
             <?= e(date('j M Y', strtotime((string)$cwv['d']))) ?>.
             <?= $cwv['lcp_ms'] !== null ? 'Largest paint ' . (int)$cwv['lcp_ms'] . 'ms.' : '' ?>
             Run it yourself — the URL is in your address bar.</p>
        <?php else: ?>
          <p style="margin-top:.6rem">We measure this site's own Core Web Vitals every week with
             Google's PageSpeed API and publish whatever it says. Run it against this page
             yourself; the URL is in your address bar.</p>
        <?php endif; ?>
      </div>

      <div class="card">
        <p class="mono">What the first 30 days look like</p>
        <h3 style="margin-top:.4rem">Week by week, before you commit</h3>
        <p style="margin-top:.6rem">Week 1, we map how you sell today and write it down. Week 2,
           a fixed written scope you approve. Weeks 3–4, working software in your browser every
           Friday. You are never more than seven days from saying “this is not what I meant”.</p>
      </div>

      <div class="card">
        <p class="mono">On the record</p>
        <h3 style="margin-top:.4rem">We are a young firm</h3>
        <p style="margin-top:.6rem">We would rather say that than show you case studies we cannot
           evidence. We will walk you through recent work on a call — no NDAs, no vague numbers,
           and you can speak to the people we built it for.</p>
      </div>

    </div>
  </div>
</section>
