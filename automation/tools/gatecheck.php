#!/usr/bin/env php
<?php
/* ============================================================
   gatecheck.php — run one fixture through Blog::gates().

     php automation/tools/gatecheck.php good
     php automation/tools/gatecheck.php stat_percent

   Prints PASS, or the reasons the piece was rejected. Every named
   case below is the good article with exactly ONE thing broken, so a
   case that reports PASS means that gate is not working.
   ============================================================ */
declare(strict_types=1);
require_once dirname(__DIR__) . '/private/bootstrap.php';

$case = (string)($argv[1] ?? 'good');
$good = json_decode((string)file_get_contents(__DIR__ . '/fixtures/good.json'), true);
if (!is_array($good)) { fwrite(STDERR, "fixture missing\n"); exit(2); }

$p = $good;
$recent = [];

switch ($case) {
    case 'good': break;

    case 'short':
        $p['body'] = '<h2 id="a">A</h2><p>Too short to be an article.</p>'
                   . '<h2 id="b">B</h2><p>Still short.</p><h2 id="c">C</h2><p>And short.</p>';
        break;

    case 'no_h1_rule':
        $p['body'] = '<h1>A second heading one</h1>' . $p['body'];
        break;

    case 'few_h2':
        $p['body'] = preg_replace('/<h2\b[^>]*>.*?<\/h2>/i', '<p>was a heading</p>', $p['body'], 4);
        break;

    case 'h2_no_id':
        $p['body'] = preg_replace('/<h2 id="[^"]+">/', '<h2>', $p['body'], 1);
        break;

    case 'long_title':
        $p['title'] = 'What a website redesign actually involves, week by week, in practice';
        break;

    case 'no_faq':
        $p['faq'] = [$good['faq'][0]];
        break;

    case 'script_tag':
        $p['body'] .= '<script>alert(1)</script>';
        break;

    case 'inline_handler':
        $p['body'] .= '<p onclick="steal()">tap</p>';
        break;

    case 'external_link':
        $p['body'] .= '<p>See <a href="https://example.com/thing">this</a>.</p>';
        break;

    case 'dead_link':
        $p['body'] .= '<p>See <a href="/services/quantum-seo/">this</a>.</p>';
        break;

    case 'stat_percent':
        $p['body'] .= '<p>Around 73% of businesses never check this.</p>';
        break;

    case 'stat_studies':
        $p['body'] .= '<p>Studies show that page speed drives conversion.</p>';
        break;

    case 'stat_experts':
        $p['body'] .= '<p>Experts agree this is the single biggest factor.</p>';
        break;

    case 'invented_client':
        $p['body'] .= '<p>One of our clients had exactly this problem.</p>';
        break;

    case 'invented_result':
        $p['body'] .= '<p>We increased traffic for a retailer in Delhi.</p>';
        break;

    case 'case_study':
        $p['body'] .= '<p>Our case study on this is worth reading.</p>';
        break;

    case 'guarantee':
        $p['body'] .= '<p>We guarantee results within ninety days.</p>';
        break;

    case 'ai_filler':
        $p['body'] .= '<p>Let us delve into the details.</p>';
        break;

    case 'cliche':
        $p['body'] = '<p>In today\'s fast-paced digital world, websites matter.</p>' . $p['body'];
        break;

    case 'dup':
        /* Same argument, reworded — what a lazy regeneration looks like. */
        $recent = [['title' => 'What a redesign involves',
                    'text'  => Blog::plainText($good['body'])]];
        break;

    default:
        fwrite(STDERR, "unknown case: $case\n");
        exit(2);
}

$fails = Blog::gates($p, $recent);
echo $fails ? implode("\n", $fails) . "\n" : "PASS\n";
exit($fails ? 1 : 0);
