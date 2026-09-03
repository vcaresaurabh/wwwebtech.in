#!/usr/bin/env php
<?php
/* ============================================================
   publish-fixture.php — run the reference article through the real
   publish path, so the gate can check the files it produces.

     php automation/tools/publish-fixture.php
     php automation/tools/publish-fixture.php --unpublish

   This uses Publisher exactly as the cron job does. It is a TEST
   FIXTURE: the post is flagged in the database and removed again by
   --unpublish, and it must never be left on the live site.
   ============================================================ */
declare(strict_types=1);
require_once dirname(__DIR__) . '/private/bootstrap.php';

const FIXTURE_SLUG = 'gate-fixture-website-redesign';

if (in_array('--unpublish', $argv, true)) {
    $row = DB::one('SELECT id FROM wwt_posts WHERE slug = ?', [FIXTURE_SLUG]);
    if ($row) {
        Publisher::unpublish((int)$row['id']);
        DB::run('DELETE FROM wwt_posts WHERE id = ?', [(int)$row['id']]);
    }
    // Leave no orphan directory or stored source behind.
    @rmdir(Blog::webroot() . '/blog/' . FIXTURE_SLUG);
    @unlink(Blog::sourcePath(FIXTURE_SLUG));
    echo "UNPUBLISHED slug=" . FIXTURE_SLUG . "\n";
    exit(0);
}

$p = json_decode((string)file_get_contents(__DIR__ . '/fixtures/good.json'), true);
if (!is_array($p)) { fwrite(STDERR, "fixture missing\n"); exit(2); }

$fails = Blog::gates($p, []);
if ($fails) { fwrite(STDERR, "fixture fails its own gates: " . implode('; ', $fails) . "\n"); exit(1); }

DB::run('DELETE FROM wwt_posts WHERE slug = ?', [FIXTURE_SLUG]);
$words = Blog::wordCount((string)$p['body']);
$id = DB::insert(
    "INSERT INTO wwt_posts (slug, title, dek, cluster, status, created_at, model,
       tokens_in, tokens_out, cost_usd, word_count, first_para)
     VALUES (?,?,?,'web','queued',UTC_TIMESTAMP(),'fixture',0,0,0,?,?)",
    [FIXTURE_SLUG, $p['title'], $p['dek'], $words, Blog::plainText((string)$p['body'])]);

/* Store the article the same way the generator does. republish_all rebuilds
   pages from this file after a deploy overwrites them, so a fixture that skips
   it would not exercise the path that actually matters. */
Blog::saveSource(FIXTURE_SLUG, $p);

$r = Publisher::publish($id, [
    'slug' => FIXTURE_SLUG, 'title' => $p['title'], 'dek' => $p['dek'],
    'date' => gmdate('Y-m-d'), 'read' => (int)$p['read'], 'body' => (string)$p['body'],
], (array)$p['faq']);

echo "PUBLISHED slug=" . FIXTURE_SLUG . " id=$id " . json_encode($r) . "\n";
