<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>@yield('title', 'wwwebtech — Technology & Growth Partner')</title>
  <link rel="canonical" href="{{ url()->current() }}">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Wwwebtech">
  <meta property="og:title" content="@yield('title', 'Wwwebtech')">
  <meta property="og:description" content="@yield('meta_description')">
  <meta property="og:url" content="{{ url()->current() }}">
  <meta property="og:image" content="{{ asset('assets/images/og-image.png') }}">
  <link rel="shortcut icon" href="{{ asset('assets/logos/fav.png') }}" type="image/x-icon">
  
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "Wwwebtech",
      "url": "https://wwwebtech.in",
      "logo": "https://wwwebtech.in/assets/logos/wwwebtech.svg",
      "sameAs": [
        "https://www.linkedin.com/company/wwwebtech/",
        "https://www.instagram.com/wwwebtech.in/"
      ],
      "address": {
        "@type": "PostalAddress",
        "addressCountry": "IN"
      }
    }
  </script>

  {{-- Tailwind CDN --}}
  <script src="https://cdn.tailwindcss.com"></script>

  {{-- Tailwind config (inline for now) --}}
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: '#0E0E11',
            accent: '#4F46E5',
            surface: '#F6F9FC'
          },
          boxShadow: {
            card: '0px 8px 24px rgba(15,23,42,0.06)',
            cardHover: '0px 12px 32px rgba(15,23,42,0.08)',
            button: '0px 6px 16px rgba(37,99,235,0.25)'
          },
          fontFamily: {
            sans: ['Satoshi', 'ui-sans-serif', 'system-ui']
          }
        }
      }
    }
  </script>

  {{-- Font (temporary system-safe, we’ll upgrade) --}}
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI",
        Roboto, Helvetica, Arial, sans-serif;
    }
  </style>
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-3EMCNLKC8Q"></script>
  <script>
    window.dataLayer = window.dataLayer || [];

    function gtag() {
      dataLayer.push(arguments);
    }
    gtag('js', new Date());

    gtag('config', 'G-3EMCNLKC8Q');
  </script>
</head>

<body class="bg-white text-brand font-sans antialiased">

  {{-- Header --}}
  @include('components.header')

  {{-- Page Content --}}
  <main class="min-h-screen">
    @yield('content')
  </main>

  {{-- Footer --}}
  @include('components.footer')

  <script>
    const menuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');

    if (menuButton && mobileMenu) {
      menuButton.addEventListener('click', () => {
        mobileMenu.classList.toggle('max-h-0');
        mobileMenu.classList.toggle('opacity-0');
        mobileMenu.classList.toggle('max-h-96');
      });
    }
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const contactLink = document.querySelector('a[href="#contact"]');
      const contactSection = document.getElementById('contact');

      if (contactLink && contactSection) {
        contactLink.addEventListener('click', (e) => {
          e.preventDefault();
          contactSection.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        });
      }
    });
  </script>



</body>

</html>