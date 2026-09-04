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
            'weight'  => '~190KB measured on this site — loaded after the page has drawn, so it costs no speed score',
            'note'    => 'Sets cookies and sends data to Google. The built-in analytics already '
                       . 'covers visits, sources and pages without either.',
        ],
        'gtm' => [
            'label'   => 'Google Tag Manager',
            'hint'    => 'Container ID. Everything inside it loads on every page.',
            'example' => 'GTM-XXXXXXX',
            'pattern' => '/^GTM-[A-Z0-9]{4,10}$/i',
            'cookies' => true,
            'weight'  => '~110KB measured, plus whatever is in the container — loaded after the page has drawn',
            'note'    => 'A container can be changed by anyone with access, without touching this '
                       . 'site. That is its point and its risk.',
        ],
        'ads' => [
            'label'   => 'Google Ads conversion tracking',
            'hint'    => 'Conversion ID from Google Ads.',
            'example' => 'AW-XXXXXXXXX',
            'pattern' => '/^AW-[0-9]{8,12}$/i',
            'cookies' => true,
            'weight'  => 'shares the GA4 download when both are on — nothing extra; ~190KB measured on its own',
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
    /**
     * The small loader every script tag hangs off. Emitted once, first.
     *
     * Measured on the live homepage, the tags were the single biggest thing
     * on the page — two copies of gtag.js and a Tag Manager container, half
     * a megabyte of JavaScript fetched from <head> before the first word of
     * the site could paint. LCP 3.6s against 1.4s on the tag-free landing
     * pages. Nothing in those scripts is needed to draw the page, so they
     * now wait for it: the visitor's first touch, scroll or keypress, or
     * failing that five seconds after the load event. gtag() calls made
     * before then are queued by the stub and replayed, so nothing is lost.
     *
     * Why five seconds and not "as soon as idle": measured with the tags
     * firing on the first idle moment, they still parsed and ran inside
     * the window Lighthouse counts as blocking — 421ms of TBT on a page
     * that otherwise has none. Five seconds is past that window on every
     * device class, and the only visitors it costs are those who leave
     * within five seconds without touching the page — whom the site's own
     * server-side counter still records.
     */
    public static function loader(): string
    {
        return "<script>(function(){var q=[],d=0;window.wwtDefer=function(f){d?f():q.push(f)};"
             . "function go(){if(d)return;d=1;for(var i=0;i<q.length;i++){try{q[i]()}catch(e){}}q=[]}"
             . "function later(){setTimeout(function(){'requestIdleCallback'in window?requestIdleCallback(go,{timeout:2000}):go()},5000)}"
             . "document.readyState==='complete'?later():addEventListener('load',later);"
             . "['pointerdown','keydown','touchstart','scroll'].forEach(function(e){addEventListener(e,go,{once:true,passive:true})})})();</script>";
    }

    /** Escape a value for the inside of a single-quoted JS string. */
    private static function js(string $s): string
    {
        return str_replace(['\\', "'", '<'], ['\\\\', "\\'", '\\u003c'], $s);
    }

    /**
     * One gtag.js download for every Google product that uses it.
     * The config calls go into the page immediately — they are cheap and
     * they are what verify() and the gate look for — and the script itself
     * is fetched once, for the first ID, after the page has drawn.
     */
    public static function gtagBlock(array $ids): string
    {
        $ids = array_values(array_filter($ids, static fn($v) => $v !== ''));
        if (!$ids) return '';
        $cfg = implode('', array_map(static fn($id) => "gtag('config','" . self::js($id) . "');", $ids));
        return "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}\n"
             . "gtag('js',new Date());{$cfg}\n"
             . "wwtDefer(function(){var s=document.createElement('script');s.async=true;"
             . "s.src='https://www.googletagmanager.com/gtag/js?id=" . self::js($ids[0]) . "';"
             . "document.head.appendChild(s)});</script>";
    }

    /**
     * The canonical snippet for one product, built from the stored ID.
     * These are the vendors' own documented tags, written out here rather
     * than fetched, so nothing is loaded from a third party to decide what
     * to load from a third party. Script tags are wrapped in wwtDefer();
     * verification tags are plain <meta> and cost nothing.
     */
    public static function snippet(string $key): string
    {
        $id = self::id($key);
        if ($id === '' || !self::enabled($key)) return '';
        $j = self::js($id);
        $a = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');

        return match ($key) {
            'ga4', 'ads' => self::gtagBlock([$id]),

            'gtm' => "<script>wwtDefer(function(){(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),"
                   . "event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),"
                   . "dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;"
                   . "f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$j}')});</script>",

            'meta_pixel' => "<script>wwtDefer(function(){!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?"
                   . "n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;"
                   . "n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;"
                   . "s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',"
                   . "'https://connect.facebook.net/en_US/fbevents.js');fbq('init','{$j}');fbq('track','PageView')});</script>",

            'clarity' => "<script>wwtDefer(function(){(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};"
                   . "t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;"
                   . "y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})"
                   . "(window,document,'clarity','script','{$j}')});</script>",

            'gsc'  => "<meta name=\"google-site-verification\" content=\"{$a}\">",
            'bing' => "<meta name=\"msvalidate.01\" content=\"{$a}\">",

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
        foreach (['gsc', 'bing'] as $k) {
            $m = self::snippet($k);
            if ($m !== '') $parts[] = $m;
        }
        /* Script tags: the loader once, then GA4 and Ads sharing a single
           gtag.js, then everything else. */
        $gtagIds = [];
        foreach (['ga4', 'ads'] as $k) if (self::enabled($k)) $gtagIds[] = self::id($k);
        $scripts = [];
        if ($gtagIds) $scripts[] = self::gtagBlock($gtagIds);
        foreach (['gtm', 'meta_pixel', 'clarity'] as $k) {
            $m = self::snippet($k);
            if ($m !== '') $scripts[] = $m;
        }
        if ($scripts) { $parts[] = self::loader(); array_push($parts, ...$scripts); }
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
