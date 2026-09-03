#!/usr/bin/env php
<?php
/* ============================================================
   bancheck.php — prove the banned-pattern list is precise.

   A ban that also rejects the correct statement is worse than no ban.
   The first version of the guarantee rule rejected an article for saying
   "nobody can guarantee a ranking" — the exact point the brief wants
   made — and cost a real API call to discover.

   Each case is [text, should_be_flagged].
   ============================================================ */
declare(strict_types=1);
require_once dirname(__DIR__) . '/private/bootstrap.php';

$cases = [
    // Must be caught: we are making the claim.
    ['we guarantee first-page rankings',            true],
    ['We can guarantee results in 90 days',         true],
    ['we offer a money-back guarantee',             true],
    ['our case study shows a large lift',           true],
    ['one of our clients had this problem',         true],
    ['we increased traffic for a retailer',         true],
    ['studies show that speed matters',             true],
    ['around 73% of businesses never check',        true],
    ['experts agree this is the biggest factor',    true],
    ['let us delve into the details',               true],
    ["In today's fast-paced digital world",         true],

    // Must NOT be caught: honest, useful writing.
    ['nobody can guarantee a ranking',              false],
    ['there is no guarantee this will work',        false],
    ['be wary of anyone offering a guarantee',      false],
    ['any agency that guarantees rankings is lying', false],
    ['ask the agency for a case study',             false],
    ['Google documents 2.5 seconds as the threshold', false],
    ['a photo can easily be four megabytes',        false],
    ['clients often ask about this',                false],
    ['research is worth doing before you commit',   false],
    ['the study of your own analytics is free',     false],
];

/* The context-sensitive ones. The same phrase is a promise in one sentence
   and the honest warning against it in the next, so each case carries the
   sentence it lives in. */
$contextual = [
    ['We offer guaranteed rankings on the first page.',                     true],
    ['Our package includes guaranteed results within a month.',             true],
    ['You will see instant results after the switch.',                      true],
    ['Guaranteed rankings do not exist; nobody can promise a position.',    false],
    ['Be wary of any agency selling guaranteed rankings.',                  false],
    ['A first-page guarantee is a red flag, not a feature.',                false],
    ['Anyone who promises instant results is selling you something.',       false],
    ['Walk away from guaranteed traffic offers.',                           false],
];

$wrong = [];

/* Run each case through the real gate logic, not a copy of it. */
$check = static function (string $text): array {
    foreach (Blog::BANNED as $re => $reason) {
        if (preg_match($re, $text)) return [true, $reason];
    }
    foreach (Blog::BANNED_UNLESS_WARNING as $re => $reason) {
        if (!preg_match($re, $text, $m, PREG_OFFSET_CAPTURE)) continue;
        [$hit, $at] = $m[0];
        if (Blog::readsAsWarning($text, (int)$at, strlen($hit))) continue;
        return [true, $reason];
    }
    return [false, ''];
};

foreach (array_merge($cases, $contextual) as [$text, $want]) {
    [$hit, $why] = $check($text);
    if ($hit !== $want) {
        $wrong[] = ($want ? 'MISSED:  ' : 'FALSE +: ') . '"' . $text . '"'
                 . ($hit ? ' — flagged as ' . $why : '');
    }
}
$cases = array_merge($cases, $contextual);

if ($wrong) { echo implode("\n", $wrong), "\n"; exit(1); }
echo "all ", count($cases), " cases correct\n";
