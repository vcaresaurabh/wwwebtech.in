<?php
/* ============================================================
   /lp/website-development-delhi/ — the local variant of T1-2.

   Demonstrates the pattern for future city pages: load the national
   page's data and override only what is genuinely local. Duplicating
   the whole file would mean six near-identical pages drifting apart
   the first time a price or a claim changes.

   What is local: the H1, the sub-line, the proof of presence, the
   keyword whitelist, and two FAQ entries. Everything else is shared.
   ============================================================ */
declare(strict_types=1);

$base = require __DIR__ . '/website-development.php';

return array_replace($base, [
  'slug'  => 'website-development-delhi',
  'title' => 'Website development company in Delhi NCR | Wwwebtech',
  'desc'  => 'A Delhi-based team building fast business websites. Meet us in person in East Delhi. You own the code, the domain and every account.',

  'h1'  => 'A website development company in Delhi you can actually meet.',
  'sub' => 'We are in East Delhi. You can sit across a table from the people building your site, which is not true of most quotes you will get.',

  'chips' => ['Based in East Delhi', 'Under 2 seconds on mobile', 'Meet before you commit'],

  'match' => [
    'website-development-company-delhi' => 'A Delhi team, a fixed written scope, and the code handed to you at the end.',
    'web-design-delhi'                  => 'Designed around what your customer needs to decide — in a meeting, not over email.',
    'website-company-near-me'           => 'East Delhi. Come and see us before you commit to anything.',
    'website-development-noida'         => 'We work across Delhi NCR and meet in person where it helps.',
    'website-development-gurgaon'       => 'We work across Delhi NCR and meet in person where it helps.',
    'best-web-development-company-delhi' => 'We would rather show you the work and let you decide than claim to be the best.',
    'website-cost-delhi'                => 'A fixed written price after one meeting, and nothing added without your say-so.',
  ],

  'trust' => [
    'GSTIN-registered business in East Delhi',
    'Meet us in person before you commit',
    'You own the code, the domain and every account',
    'Fixed written scope before any work starts',
  ],

  'faq' => array_merge([
    ['Where in Delhi are you based?',
     'East Delhi. We work with businesses across Delhi NCR — Noida, Gurgaon, Faridabad and Ghaziabad — and we are happy to meet in person before you commit to anything. For most clients one meeting at the start and weekly calls afterwards is the right balance.'],
    ['Do you only work with Delhi businesses?',
     'No. Most of the work happens over calls and shared documents, so location matters less than it used to. Being in Delhi means we can meet when it genuinely helps, which is usually at the start and at handover.'],
  ], $base['faq']),

  'final_h2'  => 'Come and talk to us.',
  'final_sub' => 'Two questions to start, then a call or a meeting in East Delhi — whichever suits you.',
]);
