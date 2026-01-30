<header class="w-full bg-white border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-6">
        <div class="flex items-center justify-between h-16">

            {{-- Logo --}}
            <a href="/" class="flex items-center">
                <img
                    src="{{ asset('assets/logos/wwwebtech.svg') }}"
                    alt="wwwebtech logo"
                    class="h-10 w-auto" />
            </a>


            {{-- Navigation --}}
            <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-700">
                <a href="#services" class="hover:text-brand transition">Services</a>
                <a href="#crm" class="hover:text-brand transition">CRM</a>
                <a href="#about" class="hover:text-brand transition">About</a>
                <a href="#contact" class="hover:text-brand transition">Contact</a>
            </nav>

            {{-- CTA --}}
            <div class="hidden md:flex">
                <a href="#contact"
                    class="inline-flex items-center justify-center px-4 py-2 rounded-md
                          bg-accent text-white text-sm font-medium
                          shadow-button hover:opacity-95 transition">
                    Get a quote
                </a>
            </div>

        </div>
    </div>
</header>