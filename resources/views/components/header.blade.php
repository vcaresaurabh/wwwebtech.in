<header class="w-full bg-white border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-6">
        <div class="flex items-center justify-between h-16">

            {{-- Logo --}}
            <a href="/" class="flex items-center">
                <img
                    src="{{ asset('assets/logos/wwwebtech.svg') }}"
                    alt="Wwwebtech logo"
                    class="h-10 w-auto" />
            </a>

            {{-- Desktop Navigation --}}
            <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-700">
                <a href="{{ route('services') }}" class="hover:text-brand transition">Services</a>
                <a href="/#crm" class="hover:text-brand transition">CRM</a>
                <a href="{{ route('about') }}" class="hover:text-brand transition">About</a>
                <a href="{{ route('contact') }}" class="hover:text-brand transition">Contact</a>
            </nav>

            {{-- Desktop CTA --}}
            <div class="hidden md:flex">
                <a href="{{ request()->routeIs('home') ? '#contact' : route('contact') }}"
                    class="inline-flex items-center justify-center px-4 py-2 rounded-md
                           bg-accent text-white text-sm font-medium
                           shadow-button hover:opacity-95 transition">
                    Get a quote
                </a>
            </div>

            {{-- Mobile Hamburger (ADD THIS) --}}
            <button
                id="mobile-menu-button"
                class="md:hidden inline-flex items-center justify-center p-2 rounded-md
                       text-slate-700 hover:bg-slate-100 transition"
                aria-label="Open menu"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

        </div>
    </div>
</header>
<div
    id="mobile-menu"
    class="md:hidden bg-white border-b border-slate-200
           overflow-hidden
           max-h-0 opacity-0
           transition-all duration-300 ease-out"
>
    <nav class="px-6 py-6 space-y-4 text-slate-700 text-sm font-medium">
        <a href="{{ route('services') }}" class="block">Services</a>
        <a href="#crm" class="block">CRM</a>
        <a href="{{ route('about') }}" class="block">About</a>
        <a href="{{ route('contact') }}" class="block">Contact</a>
        <a href="{{ request()->routeIs('home') ? '#contact' : route('contact') }}"
           class="mt-4 inline-flex items-center justify-center w-full px-4 py-2
                  rounded-md bg-accent text-white text-sm font-medium">
            Get a quote
        </a>
    </nav>
</div>
