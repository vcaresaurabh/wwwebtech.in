<footer class="w-full bg-slate-50 text-slate-600">
    <div class="max-w-7xl mx-auto px-6 py-16">

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-12">

            <!-- Brand -->
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <img
                        src="{{ asset('assets/logos/wwwebtech.svg') }}"
                        alt="Wwwebtech logo"
                        class="h-8"
                         loading="lazy"
                    />
                </div>

                <p class="text-sm leading-relaxed">
                    Empowering Indian businesses with world-class IT solutions.
                    From local startups to global enterprises, we are your
                    technology partner.
                </p>

                <!-- Social icons (placeholders for now) -->
                <div class="mt-4 flex gap-4 text-slate-500">
                    <span><a href="https://www.linkedin.com/company/wwwebtech/">in</a></span>
                    <!-- <span><a href="#">f</a></span> -->
                    <span><a href="https://www.instagram.com/wwwebtech.in/">◎</a></span>
                    <!-- <span>𝕏</span> -->
                </div>
            </div>

            <!-- Services -->
            <div>
                <h4 class="text-sm font-semibold text-slate-900 mb-4">
                    Services
                </h4>
                <ul class="space-y-2 text-sm">
                    <li class="hover:text-accent transition-colors"><a href="{{ route('services') }}">IT Support</a></li>
                    <li class="hover:text-accent transition-colors"><a href="{{ route('services.crm') }}">Ecommerce CRM</a></li>
                    <li class="hover:text-accent transition-colors"><a href="{{ route('services.website') }}">Web Development</a></li>
                    <li class="hover:text-accent transition-colors"><a href="{{ route('services.automation') }}">Business Automation</a></li>
                    <li class="hover:text-accent transition-colors"><a href="{{ route('services.support') }}">Ongoing Technical Support</a></li>
                    <li class="hover:text-accent transition-colors"><a href="{{ route('services') }}">App Development</a></li>
                    <li class="hover:text-accent transition-colors">Cloud Security</li>
                </ul>
            </div>

            <!-- Company -->
            <div>
                <h4 class="text-sm font-semibold text-slate-900 mb-4">
                    Company
                </h4>
                <ul class="space-y-2 text-sm">
                    <li class="hover:text-accent transition-colors"><a href="{{ route('about') }}">About Us</a></li>
                    <li class="hover:text-accent transition-colors">Careers</li>
                    <li class="hover:text-accent transition-colors">Blog</li>
                    <li class="hover:text-accent transition-colors">Case Studies</li>
                    <li class="hover:text-accent transition-colors"><a href="{{ route('contact') }}">Contact</a></li>
                </ul>
            </div>

            <!-- Legal -->
            <div>
                <h4 class="text-sm font-semibold text-slate-900 mb-4">
                    Legal
                </h4>
                <ul class="space-y-2 text-sm">
                    <li class="hover:text-accent transition-colors">Privacy Policy</li>
                    <li class="hover:text-accent transition-colors">Terms of Service</li>
                    <li class="hover:text-accent transition-colors">Cookie Policy</li>
                    <li class="hover:text-accent transition-colors">Sitemap</li>
                </ul>
            </div>

        </div>

        <!-- Bottom bar -->
        <div class="mt-12 pt-6 border-t border-slate-200
                    flex flex-col md:flex-row justify-between gap-4 text-sm text-slate-500">
            <p>© {{ date('Y') }} Wwwebtech Solutions Pvt Ltd. All rights reserved.</p>
            <p>Made with <span class="text-red-500">❤</span> in India</p>
        </div>

    </div>
</footer>
