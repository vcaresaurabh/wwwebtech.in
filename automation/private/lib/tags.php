<?php
/* ============================================================
   tags.php — the tag manager.

   The owner pastes an ID; this file turns it into the canonical snippet
   for that product and writes it into every page of the site. No
   third-party tag manager, no container, no runtime dependency on
   anyone else's uptime.

   Two things this file is opinionated about, because the site is sold on
   them:

   1. WEIGHT. Every tag is JavaScript from someone else's server, loaded
      on every page. The panel states each one's real cost so the owner
      is choosing, not guessing.

   2. PRIVACY. Most of these set cookies and send data abroad. Turning
      one on changes what the privacy policy has to say, and in some
      cases means the site needs a consent banner it currently does not.
      The panel says so at the point of the decision.
   ============================================================ */

declare(strict_types=1);

final class Tags
{
    public const HEAD_START = '<!--WWT_TAGS_HEAD_START-->';
    public const HEAD_END   = '<!--WWT_TAGS_HEAD_END-->';
    public const BODY_START = '<!--WWT_TAGS_BODY_START-->';
    public const BODY_END   = '<!--WWT_TAGS_BODY_END-->';

    /**
     * Everything the panel offers.
     *
     * `pattern`  what a valid ID looks like, so a typo is caught here
     *            rather than by silence three weeks later.
     * `cookies`  whether switching it on creates a consent obligation.
     * `weight`   rough transferred size, for the honesty column.
     */
    public const DEFS = [
        'ga4' => [
            'label'   => 'Google Analytics 4',
            'hint'    => 'Measurement ID, from Admin → Data streams.',
            'example' => 'G-XXXXXXXXXX',
            'pattern' => '/^G-[A-Z0-9]{6,12}$/i',
            'cookies' => true,
            'weight'  => '~50KB',
            'note'    => 'Sets cookies and sends data to Google. The built-in analytics already '
                       . 'covers visits, sources and pages without either.',
        ],
        'gtm' => [
            'label'   => 'Google Tag Manager',
            'hint'    => 'Container ID. Everything inside it loads on every page.',
            'example' => 'GTM-XXXXXXX',
            'pattern' => '/^GTM-[A-Z0-9]{4,10}$/i',
            'cookies' => true,
            'weight'  => '~80KB, plus whatever is in the container',
            'note'    => 'A container can be changed by anyone with access, without touching this '
                       . 'site. That is its point and its risk.',
        ],
        'ads' => [
            'label'   => 'Google Ads conversion tracking',
            'hint'    => 'Conversion ID from Google Ads.',
            'example' => 'AW-XXXXXXXXX',
            'pattern' => '/^AW-[0-9]{8,12}$/i',
            'cookies' => true,
            'weight'  => '~50KB (shared with GA4 if both are on)',
            'note'    => 'Only worth adding while you are actually running ads.',
        ],
        'meta_pixel' => [
            'label'   => 'Meta (Facebook) Pixel',
            'hint'    => 'Pixel ID, from Events Manager.',
            'example' => '1234567890123456',
            'pattern' => '/^[0-9]{10,20}$/',
            'cookies' => true,
            'weight'  => '~70KB',
            'note'    => 'Sends visitor data to Meta. Needs consent in most readings of privacy law.',
        ],
        'clarity' => [
            'label'   => 'Microsoft Clarity',
            'hint'    => 'Project ID. Records sessions and heatmaps.',
            'example' => 'abcdefghij',
            'pattern' => '/^[a-z0-9]{8,15}$/i',
            'cookies' => true,
            'weight'  => '~40KB',
            'note'    => 'Session recording captures what people type unless you mask fields. '
                       . 'Check the masking settings before turning it on.',
        ],
        'gsc' => [
            'label'   => 'Google Search Console verification',
            'hint'    => 'The content value from the HTML tag method.',
            'example' => 'abc123def456...',
            'pattern' => '/^[A-Za-z0-9_-]{20,100}$/',
            'cookies' => false,
            'weight'  => 'none — a meta tag, no JavaScript',
            'note'    => 'Safe to add. Verification only; it loads nothing and tracks nobody.',
        ],
        'bing' => [
            'label'   => 'Bing Webmaster Tools verification',
            'hint'    => 'The content value from the meta tag method.',
            'example' => 'ABC123...',
            'pattern' => '/^[A-Za-z0-9_-]{10,100}$/',
            'cookies' => false,
            'weight'  => 'none — a meta tag, no JavaScript',
            'note'    => 'Safe to add. Verification only.',
        ],
    ];

    /** The two free-form slots, for anything not listed above. */
    public const SLOTS = [
        'custom_head' => 'Custom code in <head>',
        'custom_body' => 'Custom code after <body>',
    ];

    public const MAX_SLOT = 4000;

    /* ── Reading and writing settings ──────────────────────── */

    public static function id(string $key): string
    {
        return trim((string)Settings::get('tag_' . $key, ''));
    }

    public static function enabled(string $key): bool
    {
        return self::id($key) !== '' && Settings::bool('tag_' . $key . '_on', true);
    }

    /** @throws InvalidArgumentException when the ID is not the right shape. */
    public static function set(string $key, string $value): void
    {
        $value = trim($value);
        if ($value !== '' && isset(self::DEFS[$key])) {
            if (!preg_match(self::DEFS[$key]['pattern'], $value)) {
                throw new InvalidArgumentException(
                    self::DEFS[$key]['label'] . ': "' . cut($value, 40) . '" is not the right shape. '
                    . 'It should look like ' . self::DEFS[$key]['example'] . '.');
            }
        }
        if (isset(self::SLOTS[$key]) && mb_strlen($value) > self::MAX_SLOT) {
            throw new InvalidArgumentException('That snippet is too long (limit ' . self::MAX_SLOT . ' characters).');
        }
        Settings::set('tag_' . $key, $value);
    }

    /** Anything switched on that sets cookies. */
    public static function cookieSetters(): array
    {
        $out = [];
        foreach (self::DEFS as $k => $d) {
            if ($d['cookies'] && self::enabled($k)) $out[] = $d['label'];
        }
        if (self::enabled('custom_head') || self::enabled('custom_body')) $out[] = 'your custom code';
        return $out;
    }

    /* ── Snippets ──────────────────────────────────────────── */

    /**
     * The canonical snippet for one product, built from the stored ID.
     * These are the vendors' own documented tags, written out here rather
     * than fetched, so nothing is loaded from a third party to decide what
     * to load from a third party.
     */
    public static function snippet(string $key): string
    {
        $id = self::id($key);
        if ($id === '' || !self::enabled($key)) return '';
        $j = static fn(string $s): string => str_replace(['\\', "'", '<'], ['\\\\', "\\'", '\\u003c'], $s);
        $a = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        return match ($key) {
            'ga4' => "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$a($id)}\"></script>\n"
                   . "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}\n"
                   . "gtag('js',new Date());gtag('config','{$j($id)}');</script>",

            'gtm' => "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),"
                   . "event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),"
                   . "dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;"
                   . "f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$j($id)}');</script>",

            'ads' => "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$a($id)}\"></script>\n"
                   . "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}\n"
                   . "gtag('js',new Date());gtag('config','{$j($id)}');</script>",

            'meta_pixel' => "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?"
                   . "n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;"
                   . "n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;"
                   . "s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',"
                   . "'https://connect.facebook.net/en_US/fbevents.js');fbq('init','{$j($id)}');fbq('track','PageView');</script>",

            'clarity' => "<script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};"
                   . "t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;"
                   . "y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})"
                   . "(window,document,'clarity','script','{$j($id)}');</script>",

            'gsc'  => "<meta name=\"google-site-verification\" content=\"{$a($id)}\">",
            'bing' => "<meta name=\"msvalidate.01\" content=\"{$a($id)}\">",

            default => '',
        };
    }

    /** The GTM <noscript> iframe, which has to go immediately after <body>. */
    public static function bodySnippet(string $key): string
    {
        if ($key !== 'gtm') return '';
        $id = self::id('gtm');
        if ($id === '' || !self::enabled('gtm')) return '';
        $a = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        return "<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id={$a}\""
             . " height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>";
    }

    /** Everything destined for <head>, in a deterministic order. */
    public static function headHtml(): string
    {
        $parts = [];
        /* Verification tags first: they are meta tags and cost nothing. */
        foreach (['gsc', 'bing', 'gtm', 'ga4', 'ads', 'meta_pixel', 'clarity'] as $k) {
            $s = self::snippet($k);
            if ($s !== '') $parts[] = $s;
        }
        if (self::enabled('custom_head')) $parts[] = self::id('custom_head');
        /* Empty means EMPTY, not a newline: with nothing switched on the
           pages must go back byte for byte to what the build produced, so
           that removing tags is provably a no-op. */
        return $parts ? "\n" . implode("\n", $parts) . "\n" : '';
    }

    public static function bodyHtml(): string
    {
        $parts = [];
        $g = self::bodySnippet('gtm');
        if ($g !== '') $parts[] = $g;
        if (self::enabled('custom_body')) $parts[] = self::id('custom_body');
        return $parts ? "\n" . implode("\n", $parts) . "\n" : '';
    }

    /* ── Injection ─────────────────────────────────────────── */

    /** Every HTML file in the web root that the site owns. */
    public static function pages(): array
    {
        $root  = Blog::webroot();
        $found = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            /** @var SplFileInfo $f */
            if (!$f->isFile() || strtolower($f->getExtension()) !== 'html') continue;
            $rel = substr($f->getPathname(), strlen($root));
            if (preg_match('#^/(admin|api|_)#', $rel)) continue;
            $found[] = $f->getPathname();
        }
        sort($found);
        return $found;
    }

    /**
     * Write the current tags into every page.
     *
     * Only the text between the markers is ever touched, so this is
     * idempotent and reversible: clearing every tag and running again
     * restores the pages exactly.
     *
     * @return array{written:int, skipped:int, missing:string[]}
     */
    public static function apply(): array
    {
        $head = self::headHtml();
        $body = self::bodyHtml();
        $written = 0; $skipped = 0; $missing = [];

        foreach (self::pages() as $file) {
            $src = (string)file_get_contents($file);
            $new = self::replaceBetween($src, self::HEAD_START, self::HEAD_END, $head);
            if ($new === null) { $missing[] = $file; continue; }
            $new2 = self::replaceBetween($new, self::BODY_START, self::BODY_END, $body);
            if ($new2 === null) { $missing[] = $file; continue; }

            if ($new2 === $src) { $skipped++; continue; }
            Publisher::put($file, $new2);
            $written++;
        }

        audit('tags_apply', $written . ' pages updated, ' . count($missing) . ' without markers');
        return ['written' => $written, 'skipped' => $skipped, 'missing' => $missing];
    }

    /** Returns null when the markers are absent, so the caller can report it. */
    private static function replaceBetween(string $src, string $start, string $end, string $with): ?string
    {
        $a = strpos($src, $start);
        $b = strpos($src, $end);
        if ($a === false || $b === false || $b < $a) return null;
        return substr($src, 0, $a + strlen($start)) . $with . substr($src, $b);
    }

    /**
     * Confirm the tags are really on the live site, by fetching a page
     * over HTTP rather than trusting that the write succeeded.
     */
    public static function verify(): array
    {
        $url = rtrim((string)cfg('site.url', ''), '/') . '/';
        $r = Http::get($url, ['follow' => true]);
        if ($r['status'] !== 200) {
            return ['ok' => false, 'detail' => 'Could not fetch the homepage (HTTP ' . $r['status'] . ').', 'found' => []];
        }
        $found = [];
        foreach (self::DEFS as $k => $d) {
            if (!self::enabled($k)) continue;
            $found[$d['label']] = str_contains($r['body'], self::id($k));
        }
        $allOk = !in_array(false, $found, true);
        return ['ok' => $allOk, 'found' => $found,
                'detail' => $found
                    ? ($allOk ? 'Every switched-on tag was found in the live homepage.'
                              : 'Some tags are saved but not present on the live site — apply them, and check the site has been deployed.')
                    : 'Nothing is switched on, so there is nothing to find.'];
    }
}
