<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'wwwebtech — Technology & Growth Partner')</title>

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

</body>

</html>