<?php
/* ============================================================
   jobs.php — the one definition of what scheduled work exists.

   Both callers use this: cron/run.php on a schedule, and the admin
   panel's "run it now" buttons.

   Those buttons used to shell out with exec(). That works on a
   developer's machine and not on this host, where exec, shell_exec,
   system, passthru and popen are all in disable_functions — so every
   button in the panel answered "Call to undefined function exec()".
   Jobs run in-process now, which is simpler, portable, and reports the
   real result instead of a captured stdout line.
   ============================================================ */

declare(strict_types=1);

require_once __DIR__ . '/task.php';

final class Jobs
{
    /** Longest a job may run when triggered from a browser, in seconds. */
    public const WEB_TIME_LIMIT = 300;

    /** @return array<string, callable(): string> */
    public static function registry(): array
    {
        return [

        /* Hourly. Cheap, and keeps the panel current within the hour. */
        'analytics_hourly' => static function (): string {
            $today     = gmdate('Y-m-d');
            $yesterday = gmdate('Y-m-d', strtotime('-1 day'));
            $a = Analytics::rollup($today);
            // Yesterday is re-rolled once more so late-arriving beacons are not lost.
            $b = Analytics::rollup($yesterday);
            RateLimit::sweep();
            return "rolled up $a rows today, $b yesterday";
        },

        /* Daily housekeeping. */
        'analytics_prune' => static function (): string {
            $n = Analytics::prune();
            return $n . ' raw hits pruned past retention';
        },

        /* Blog. Runs whatever is left of today's quota, so a missed run
           catches up rather than silently skipping a day. */
        'blog_daily' => static function (): string {
            if (!Blog::enabled()) return 'skipped — publishing is switched off';
            if (!Claude::configured()) return 'skipped — no API key set';

            $want = Blog::perDay();
            $done = (int)DB::val("SELECT COUNT(*) FROM wwt_posts
                                  WHERE status='published' AND DATE(published_at) = UTC_DATE()", [], 0);
            $todo = max(0, $want - $done);
            if ($todo === 0) return "today's {$want} already published";

            /* Keep going until the day's quota is actually PUBLISHED, not
               merely attempted. A draft that fails the quality gates should
               not cost the day's post — the topic is not burned, and the next
               attempt usually passes. Bounded so a systematic failure cannot
               spend the month's budget in one night. */
            $maxAttempts = $todo + 2;
            $ok = 0; $bad = 0; $spent = 0.0; $notes = [];

            for ($i = 0; $i < $maxAttempts && $ok < $todo; $i++) {
                if (Claude::budgetLeft() <= 0) { $notes[] = 'monthly cap reached'; break; }
                $r = Blog::generateOne(true);
                $spent += (float)($r['cost'] ?? 0);
                if (!empty($r['ok'])) { $ok++; $notes[] = $r['slug']; }
                else { $bad++; $notes[] = 'rejected: ' . cut((string)$r['error'], 90); }
            }

            return sprintf('%d of %d published, %d rejected, $%.4f spent · %s',
                $ok, $todo, $bad, $spent, implode(' | ', $notes) ?: 'nothing attempted');
        },

        /* One-off, and safe to re-run: adds only topics not already present. */
        'blog_seed_topics' => static function (): string {
            $n = Blog::seedTopics();
            return $n . ' topics added (' . Blog::topicsLeft() . ' unused in the bank)';
        },

        /* SEO, daily. Cheap checks that run against the live site over HTTP,
           so they see what a visitor and a crawler see, not what the build
           intended. */
        'seo_daily' => static function (): string {
            Seo::clearToday();
            $urls = Seo::sitemapUrls();

            $results = [
                Seo::checkReachable(),
                Seo::checkSitemap(),
                Seo::checkPagesLive($urls),
                Seo::checkMetadata($urls),
                Seo::checkAiAccess(),
                Seo::checkAiSeen(),
                Seo::checkSchema(),
                Seo::checkHeaders(),
            ];

            $counts = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
            foreach ($results as $r) $counts[$r['status']]++;
            return sprintf('%d checks — %d ok, %d warnings, %d failures',
                count($results), $counts['ok'], $counts['warn'], $counts['fail']);
        },

        /* SEO, weekly. The slow ones: a full link crawl and Google's own
           field data. Separated so the daily run stays quick. */
        'seo_weekly' => static function (): string {
            $urls = Seo::sitemapUrls();
            $out  = [];
            $out[] = Seo::checkLinks($urls);
            $out[] = Seo::writeLlmsTxt();
            $out[] = Seo::writeLlmsFullTxt();
            $out[] = Seo::checkVitals(Seo::base() . '/', 'mobile');
            $out[] = Seo::checkVitals(Seo::base() . '/', 'desktop');

            $bad = 0;
            foreach ($out as $r) if (in_array($r['status'], ['warn', 'fail'], true)) $bad++;
            return count($out) . ' weekly checks, ' . $bad . ' needing attention';
        },

        /* Exercise every query and integration against THIS server.
    
           The development database and the live one are not the same product
           version, and a query the older one accepts can be rejected by the
           newer one — which is exactly how a broken "Write a post" button
           reached production while every local test passed. Run this after any
           deploy; it touches the real database, the real mail server and the
           real APIs, and reports each one separately. */
        'selftest' => static function (): string {
            $results = [];
            $check = static function (string $name, callable $fn) use (&$results): void {
                try {
                    $detail = $fn();
                    $results[] = ['ok', $name, (string)$detail];
                } catch (Throwable $t) {
                    $results[] = ['FAIL', $name, $t->getMessage()];
                }
            };

            /* -- Every non-trivial query the panel runs -- */
            $check('dashboard counters', static fn() =>
                (int)DB::val("SELECT COUNT(*) FROM wwt_leads WHERE ts >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)", [], 0)
                . ' leads in 24h');
            $check('leads listing', static fn() =>
                count(DB::all("SELECT * FROM wwt_leads WHERE 1=1 ORDER BY ts DESC LIMIT 5")) . ' rows');
            $check('lead status counts', static fn() =>
                count(DB::all('SELECT status, COUNT(*) c FROM wwt_leads GROUP BY status')) . ' groups');
            $check('analytics series', static fn() => count(Analytics::series(30)) . ' days');
            $check('analytics totals', static fn() => json_encode(Analytics::totals(30)));
            $check('analytics top pages', static fn() => count(Analytics::topBy('path', 30)) . ' rows');
            $check('analytics sources', static fn() => count(Analytics::topBy('source', 30)) . ' rows');
            $check('analytics devices', static fn() => count(Analytics::topBy('device', 30)) . ' rows');
            $check('crawler rollup', static fn() => count(Analytics::crawlers(30)) . ' crawlers');
            $check('daily rollup', static fn() => Analytics::rollup(gmdate('Y-m-d')) . ' rows folded');
            $check('blog: next topic', static function () {
                $t = Blog::nextTopic();
                return $t ? 'picked "' . cut((string)$t['title_seed'], 48) . '"' : 'the topic bank is empty';
            });
            $check('blog: recent for comparison', static fn() => count(Blog::recentForComparison()) . ' posts');
            $check('blog: spend this month', static fn() => '$' . number_format(Claude::spentThisMonth(), 4));
            $check('blog: topics left', static fn() => Blog::topicsLeft() . ' unused');
            $check('seo: latest checks', static fn() =>
                count(DB::all("SELECT * FROM wwt_seo_checks WHERE d = (SELECT MAX(d) FROM wwt_seo_checks)")) . ' rows');
            $check('task heartbeat', static fn() => count(DB::all('SELECT * FROM wwt_task_runs')) . ' jobs');
            $check('stalled-job check', static fn() => count(Task::stalled()) . ' stalled');
            $check('settings read/write', static function () {
                Settings::set('__selftest', (string)time());
                $v = Settings::get('__selftest', '');
                DB::run('DELETE FROM wwt_settings WHERE k = ?', ['__selftest']);
                return $v !== '' ? 'ok' : 'value did not persist';
            });
            $check('secret encryption', static function () {
                if (!Secrets::available()) throw new RuntimeException('no secret_key in config.php');
                Secrets::put('__selftest_secret', 'round-trip-value');
                $back = Secrets::get('__selftest_secret');
                $raw  = (string)Settings::get('__selftest_secret', '');
                DB::run('DELETE FROM wwt_settings WHERE k = ?', ['__selftest_secret']);
                if ($back !== 'round-trip-value') throw new RuntimeException('did not decrypt back');
                if (str_contains($raw, 'round-trip-value')) throw new RuntimeException('stored in plain text');
                return 'encrypts and decrypts';
            });
            $check('rate limiter', static function () {
                RateLimit::reset('__selftest');
                $a = RateLimit::allow('__selftest', 1, 60);
                $b = RateLimit::allow('__selftest', 1, 60);
                RateLimit::reset('__selftest');
                /* The limiter fails OPEN so a database problem cannot take the
                   contact form down with it — which means "not limiting" is a
                   real finding here, not a pass. */
                if (!$a || $b) throw new RuntimeException('not limiting (it fails open when the database is unreachable)');
                return 'allows, then blocks';
            });

            /* -- The outside world -- */
            $check('mail: configured', static fn() =>
                Mailer::configured() ? 'yes, sending as ' . Mailer::settings()['user'] : 'NO — enquiries will not be emailed');
            $check('anthropic key present', static fn() =>
                Claude::configured() ? 'yes, model ' . Claude::model() : 'not set — the blog is idle');
            $check('pagespeed key present', static fn() =>
                Secrets::get('pagespeed_key', (string)cfg('pagespeed_api_key', '')) !== ''
                    ? 'yes' : 'not set — speed is not measured');
            $check('site reachable', static function () {
                $r = Http::get(rtrim((string)cfg('site.url', ''), '/') . '/', ['follow' => true]);
                if ($r['status'] !== 200) throw new RuntimeException('HTTP ' . $r['status']);
                return '200 in ' . $r['ms'] . 'ms';
            });
            /* Anything the code relies on that a host may switch off. The panel's
           "run it now" buttons once shelled out with exec(), which this host
           disables — the button answered "Call to undefined function". */
        $check('required functions available', static function () {
            $need = ['curl_exec', 'openssl_encrypt', 'random_bytes', 'mb_substr',
                     'set_time_limit', 'ignore_user_abort', 'json_encode', 'iconv'];
            $missing = array_values(array_filter($need, static fn($f) => !function_exists($f)));
            if ($missing) throw new RuntimeException('missing: ' . implode(', ', $missing));
            $off = array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions'))));
            return count($need) . ' present; host disables ' . (count($off) ?: 'nothing');
        });

        $check('templates present', static function () {
                foreach (['post.html', 'teaser.html'] as $f) {
                    if (!is_file(WWT_PRIVATE . '/templates/' . $f)) throw new RuntimeException($f . ' missing');
                }
                return 'post.html and teaser.html';
            });
            $check('webroot writable', static function () {
                $p = Blog::webroot() . '/.wwt-selftest';
                Publisher::put($p, "ok\n");
                $ok = is_file($p);
                @unlink($p);
                if (!$ok) throw new RuntimeException('could not write to ' . Blog::webroot());
                return Blog::webroot();
            });

            $bad = 0;
            foreach ($results as [$status, $name, $detail]) {
                if ($status !== 'ok') $bad++;
                fwrite(STDOUT, sprintf("  %-5s %-28s %s\n", $status, $name, cut($detail, 90)));
            }
            if ($bad) throw new RuntimeException($bad . ' of ' . count($results) . ' self-tests failed');
            return count($results) . ' self-tests passed';
        },

        /* Rebuild every file the automation layer owns, from the database.
           Run after any site deploy: uploading site/ overwrites the blog index,
           the homepage teasers and the sitemap with build-time versions that know
           nothing about posts published on the server. The database does. */
        /* The funnel heartbeat. Each tick advances anything due and
           flushes the outbox. Cheap when there is nothing to do. */
        'funnel_tick' => static function (): string {
            return FunnelRunner::tick();
        },

        /* One-off and safe to re-run: loads the seed sequences and templates
           without overwriting anything the owner has since edited. */
        'funnel_seed' => static function (): string {
            $n = Funnel::seed();
            return sprintf('%d sequences, %d steps, %d templates added',
                $n['sequences'], $n['steps'], $n['templates']);
        },

        /* Poll the funnel mailbox for replies. A reply is the most important
           stop condition there is, so this runs often. */
        'inbox_poll' => static function (): string {
            return Inbox::poll();
        },

        /* Run any website audits people have requested. */
        'audit_run' => static function (): string {
            return AuditTool::runQueued();
        },

        'republish_all' => static function (): string {
            $posts = DB::all("SELECT * FROM wwt_posts WHERE status='published' ORDER BY published_at");
            $rebuilt = 0; $missing = [];

            foreach ($posts as $p) {
                $src = Blog::loadSource((string)$p['slug']);
                if (!$src) { $missing[] = (string)$p['slug']; continue; }
                $dir = Blog::webroot() . '/blog/' . $p['slug'];
                Publisher::put($dir . '/index.html', Publisher::renderPost([
                    'slug'  => $p['slug'], 'title' => $p['title'], 'dek' => $p['dek'],
                    'date'  => substr((string)$p['published_at'], 0, 10),
                    'read'  => (int)($src['read'] ?? max(1, (int)round((int)$p['word_count'] / 220))),
                    'body'  => (string)$src['body'],
                ], (array)($src['faq'] ?? [])));
                $rebuilt++;
            }

            Publisher::refreshTeasers();
            Publisher::refreshSitemap();
            Seo::writeLlmsTxt();
            Seo::writeLlmsFullTxt();

            /* Tags are written into the pages, and a deploy replaces the pages. */
            $tags = Tags::apply();

            return sprintf('%d posts rebuilt%s, teasers and sitemap refreshed, tags re-applied to %d pages',
                $rebuilt,
                $missing ? ' (' . count($missing) . ' had no stored source: ' . implode(', ', array_slice($missing, 0, 3)) . ')' : '',
                (int)$tags['written']);
        },

        /* Regenerate the machine-readable files and ping IndexNow. Runs after
           anything is published; safe to run on its own. */
        'seo_publish_ping' => static function (): string {
            Seo::writeLlmsTxt();
            $urls = array_map(
                static fn($r) => Seo::base() . '/blog/' . $r['slug'] . '/',
                DB::all("SELECT slug FROM wwt_posts WHERE status='published'
                         AND published_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)"));
            $r = Seo::indexNow($urls);
            return $r['detail'];
        },
        ];
    }

    /** Composite schedules, so cron needs one line per frequency. */
    public static function groups(): array
    {
        return [
        /* Every 15 minutes in hPanel: the funnel and the inbox are the
           time-sensitive ones — a reply that stops a sequence should stop
           it in minutes, not at the top of the next hour. */
        'frequent' => ['funnel_tick', 'inbox_poll', 'audit_run'],
        'hourly'   => ['analytics_hourly', 'funnel_tick', 'inbox_poll', 'audit_run'],
        'daily'    => ['analytics_hourly', 'analytics_prune', 'blog_daily', 'seo_daily'],
        'weekly'   => ['seo_weekly'],
        ];
    }

    public static function exists(string $name): bool
    {
        return isset(self::registry()[$name]) || isset(self::groups()[$name]);
    }

    /** Every job name a caller may ask for, groups expanded. */
    public static function expand(string $name): array
    {
        $groups = self::groups();
        return $groups[$name] ?? [$name];
    }

    /**
     * Run one job (or a group) here and now.
     *
     * Used by the panel as well as the CLI, so it raises the time limit and
     * keeps going if the browser goes away — a run abandoned half-way is how
     * a job ends up permanently marked "running".
     *
     * @return array<int, array{task:string, status:string, summary:string}>
     */
    public static function run(string $name): array
    {
        $registry = self::registry();
        $out = [];

        if (PHP_SAPI !== 'cli') {
            @set_time_limit(self::WEB_TIME_LIMIT);
            @ignore_user_abort(true);
        }

        foreach (self::expand($name) as $job) {
            if (!isset($registry[$job])) {
                $out[] = ['task' => $job, 'status' => 'unknown', 'summary' => 'no such job'];
                continue;
            }
            $summary = Task::run($job, $registry[$job]);
            $row = DB::one('SELECT last_status, last_error FROM wwt_task_runs WHERE task = ?', [$job]);
            $out[] = [
                'task'    => $job,
                'status'  => (string)($row['last_status'] ?? 'unknown'),
                'summary' => (string)($summary ?? ($row['last_error'] ?? '')),
            ];
        }
        return $out;
    }

    /** A one-line human summary of a run(), for a flash message. */
    public static function describe(array $results): string
    {
        $parts = [];
        foreach ($results as $r) {
            $parts[] = $r['task'] . ': ' . ($r['status'] === 'ok' ? $r['summary'] : strtoupper($r['status']) . ' — ' . $r['summary']);
        }
        return implode(' · ', $parts);
    }

    public static function allFailed(array $results): bool
    {
        foreach ($results as $r) if ($r['status'] === 'ok') return false;
        return $results !== [];
    }
}
