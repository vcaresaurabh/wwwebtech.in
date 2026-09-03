<?php
/* ============================================================
   publisher.php — turn an approved post into files on the site.

   A published post has to appear in five places or it is only half
   published: its own page, the blog index, the homepage teasers, the
   sitemap, and the database. This file does all five, and unpublishing
   undoes all five.

   Every write goes to a temporary file and is renamed into place.
   rename() is atomic on the same filesystem, so a visitor can never be
   served a half-written page, and a crash mid-publish leaves the old
   file intact rather than a truncated one.
   ============================================================ */

declare(strict_types=1);

final class Publisher
{
    public const START = '<!--BLOG_TEASERS_START-->';
    public const END   = '<!--BLOG_TEASERS_END-->';

    /** Teasers shown on the homepage. The blog index lists everything. */
    public const HOME_TEASERS = 3;

    private static function tpl(string $name): string
    {
        $f = WWT_PRIVATE . '/templates/' . $name;
        if (!is_file($f)) {
            throw new RuntimeException("Template $name is missing — run `node build.mjs` and redeploy.");
        }
        return (string)file_get_contents($f);
    }

    /** Write $content to $path atomically, creating directories as needed. */
    public static function put(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create ' . $dir);
        }
        $tmp = $path . '.tmp' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
            throw new RuntimeException('Cannot write ' . $tmp);
        }
        @chmod($tmp, 0644);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Cannot replace ' . $path);
        }
    }

    /* ── Rendering ─────────────────────────────────────────── */

    /** The contents list, built from the body's own h2 ids. */
    public static function toc(string $body): string
    {
        preg_match_all('/<h2\b[^>]*\bid="([^"]+)"[^>]*>(.*?)<\/h2>/is', $body, $m, PREG_SET_ORDER);
        $out = '';
        foreach ($m as $h) {
            $text = trim(strip_tags($h[2]));
            if ($text === '') continue;
            $out .= '<li><a class="u-small" href="#' . e($h[1]) . '">' . e($text) . "</a></li>\n";
        }
        return $out;
    }

    /** The FAQ block, in the same markup the hand-written pages use. */
    public static function faqHtml(array $faq): string
    {
        if (!$faq) return '';
        $items = '';
        foreach ($faq as $f) {
            $q = trim((string)($f['q'] ?? ''));
            $a = trim((string)($f['a'] ?? ''));
            if ($q === '' || $a === '') continue;
            $items .= '<details class="faq__item"><summary>' . e($q)
                    . '<span class="faq__sign" aria-hidden="true"></span></summary>'
                    . '<div class="faq__body"><p>' . e($a) . '</p></div></details>' . "\n";
        }
        if ($items === '') return '';
        return '<h2 id="questions">Questions we get asked</h2>' . "\n"
             . '<div class="faq">' . "\n" . $items . '</div>' . "\n";
    }

    /**
     * FAQPage structured data, injected next to the article schema so the
     * questions can appear in search results.
     */
    public static function faqSchema(array $faq): string
    {
        $items = [];
        foreach ($faq as $f) {
            $q = trim((string)($f['q'] ?? ''));
            $a = trim((string)($f['a'] ?? ''));
            if ($q === '' || $a === '') continue;
            $items[] = ['@type' => 'Question', 'name' => $q,
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
        }
        if (!$items) return '';
        return json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage',
                            'mainEntity' => $items],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

    /** Render one post page from the build-time template. */
    public static function renderPost(array $post, array $faq): string
    {
        $body = (string)$post['body'] . "\n" . self::faqHtml($faq);
        $date = (string)$post['date'];

        $html = strtr(self::tpl('post.html'), [
            '{{SLUG}}'       => $post['slug'],
            '{{TITLE}}'      => e((string)$post['title']),
            '{{DEK}}'        => e((string)$post['dek']),
            '{{DATE_ISO}}'   => e($date),
            '{{DATE_HUMAN}}' => e(self::humanDate($date)),
            '{{READ}}'       => (string)max(1, (int)$post['read']),
            '{{TOC}}'        => self::toc($body),
            '{{BODY}}'       => $body,
        ]);

        /* The FAQ schema goes in as its own block rather than being merged
           into the page's @graph — simpler, and valid either way. */
        $faqLd = self::faqSchema($faq);
        if ($faqLd !== '') {
            $html = str_replace('</head>',
                '<script type="application/ld+json">' . $faqLd . "</script>\n</head>", $html);
        }
        return $html;
    }

    public static function humanDate(string $iso): string
    {
        try {
            return (new DateTimeImmutable($iso, new DateTimeZone('UTC')))->format('d M Y');
        } catch (Throwable) { return $iso; }
    }

    public static function teaser(array $p): string
    {
        return strtr(self::tpl('teaser.html'), [
            '{{SLUG}}'       => (string)$p['slug'],
            '{{TITLE}}'      => e((string)$p['title']),
            '{{DEK}}'        => e((string)$p['dek']),
            '{{DATE_ISO}}'   => e((string)$p['date']),
            '{{DATE_HUMAN}}' => e(self::humanDate((string)$p['date'])),
            '{{READ}}'       => (string)max(1, (int)$p['read']),
        ]);
    }

    /* ── Site-wide updates ─────────────────────────────────── */

    /**
     * Replace everything between the teaser markers in one file.
     * A file without the markers is left alone and reported, because
     * silently doing nothing is how a post ends up published but invisible.
     */
    public static function replaceTeasers(string $file, string $html): bool
    {
        if (!is_file($file)) return false;
        $src = (string)file_get_contents($file);
        $a = strpos($src, self::START);
        $b = strpos($src, self::END);
        if ($a === false || $b === false || $b < $a) {
            wwt_log('publish', 'teaser markers missing', ['file' => $file]);
            return false;
        }
        $new = substr($src, 0, $a + strlen(self::START)) . "\n" . $html
             . substr($src, $b);
        if ($new === $src) return true;
        self::put($file, $new);
        return true;
    }

    /**
     * Rebuild the teaser lists from the database.
     * Hand-written posts live in the static build and are already inside
     * the markers; generated ones are added in front of them, newest first.
     */
    public static function refreshTeasers(): array
    {
        $root = Blog::webroot();
        $rows = DB::all(
            "SELECT slug, title, dek, DATE(published_at) d, word_count
             FROM wwt_posts WHERE status='published' ORDER BY published_at DESC, id DESC");

        $cards = [];
        foreach ($rows as $r) {
            $cards[] = self::teaser([
                'slug' => $r['slug'], 'title' => $r['title'], 'dek' => $r['dek'],
                'date' => (string)$r['d'],
                'read' => max(1, (int)round(((int)$r['word_count']) / 220)),
            ]);
        }

        $done = [];
        $done['blog']  = self::replaceTeasers($root . '/blog/index.html', implode("\n", $cards));
        $done['home']  = self::replaceTeasers($root . '/index.html',
                            implode("\n", array_slice($cards, 0, self::HOME_TEASERS)));
        return $done;
    }

    /**
     * Add or remove a post's URL in sitemap.xml. The static build owns the
     * file; this only edits the generated block inside it.
     */
    public static function refreshSitemap(): bool
    {
        $file = Blog::webroot() . '/sitemap.xml';
        if (!is_file($file)) return false;
        $src = (string)file_get_contents($file);

        $START = '<!--GENERATED_POSTS_START-->';
        $END   = '<!--GENERATED_POSTS_END-->';
        $a = strpos($src, $START);
        $b = strpos($src, $END);
        if ($a === false || $b === false) {
            wwt_log('publish', 'sitemap markers missing', ['file' => $file]);
            return false;
        }

        $base = rtrim((string)cfg('site.url', 'https://wwwebtech.in'), '/');
        $xml = '';
        foreach (DB::all("SELECT slug, DATE(published_at) d FROM wwt_posts
                          WHERE status='published' ORDER BY published_at DESC") as $r) {
            $xml .= "\n  <url><loc>" . e($base . '/blog/' . $r['slug'] . '/')
                  . '</loc><lastmod>' . e((string)$r['d'])
                  . '</lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>';
        }
        $new = substr($src, 0, $a + strlen($START)) . $xml . "\n  " . substr($src, $b);
        if ($new !== $src) self::put($file, $new);
        return true;
    }

    /* ── Publish / unpublish ───────────────────────────────── */

    /** Write the page and wire it into the site. */
    public static function publish(int $postId, array $post, array $faq): array
    {
        $root = Blog::webroot();
        $dir  = $root . '/blog/' . $post['slug'];
        self::put($dir . '/index.html', self::renderPost($post, $faq));

        DB::run("UPDATE wwt_posts SET status='published', published_at=UTC_TIMESTAMP(),
                 html_path=? WHERE id=?", ['/blog/' . $post['slug'] . '/', $postId]);

        $t = self::refreshTeasers();
        $s = self::refreshSitemap();

        audit('post_publish', 'id=' . $postId . ' ' . $post['slug']);
        return ['page' => true, 'blog_index' => $t['blog'], 'homepage' => $t['home'], 'sitemap' => $s];
    }

    /** Take a post off the site without deleting the record. */
    public static function unpublish(int $postId): array
    {
        $p = DB::one('SELECT * FROM wwt_posts WHERE id=?', [$postId]);
        if (!$p) throw new RuntimeException('No such post.');

        $dir = Blog::webroot() . '/blog/' . $p['slug'];
        if (is_file($dir . '/index.html')) @unlink($dir . '/index.html');
        if (is_dir($dir)) @rmdir($dir);

        DB::run("UPDATE wwt_posts SET status='unpublished' WHERE id=?", [$postId]);
        self::refreshTeasers();
        self::refreshSitemap();

        audit('post_unpublish', 'id=' . $postId . ' ' . $p['slug']);
        return ['removed' => true];
    }
}
