@extends('layouts.app')

@section('title', 'Wwwebtech — Web, CRM & Automation for Indian Businesses')

@section('content')
<section class="w-full bg-slate-50">
    <div class="max-w-7xl mx-auto px-6 py-20">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-14 items-center">

            <!-- Left Content -->
            <div class="max-w-3xl">
                <h1 class="text-4xl md:text-5xl font-semibold tracking-tight text-slate-900">
                    Smart Technology Solutions for
                    <span class="text-brand">Growing Businesses</span>
                </h1>

                <p class="mt-6 text-lg text-slate-600 leading-relaxed">
                    Web platforms, CRM systems, and managed IT services —
                    designed to simplify operations and scale with your business.
                </p>
                <p class="text-sm text-slate-500 mt-3">
                    Focused on long-term reliability, not short-term delivery.
                </p>

                <div class="mt-10 flex flex-col sm:flex-row gap-4">
                    <a href="#contact"
                        class="inline-flex items-center justify-center px-6 py-3 rounded-md
                              bg-accent text-white text-sm font-medium
                              shadow-button hover:opacity-95 transition">
                        Request a Consultation
                    </a>

                    <a href="#services"
                        class="inline-flex items-center justify-center px-6 py-3 rounded-md
                              border border-slate-300 text-slate-700 text-sm font-medium
                              hover:bg-slate-50 transition">
                        Explore Services
                    </a>
                </div>
                <p class="mt-6 text-xs text-slate-500">
                    Based in India. Serving growing businesses nationwide.
                </p>

            </div>
            <!-- Right Visual -->
            <div class="relative">
                <div class="rounded-xl overflow-hidden">
                    <img
                        src="{{ asset('assets/images/hero-dashboard.png') }}"
                        alt="Business dashboard interface"
                        class="w-full h-auto object-cover shadow-[0_20px_40px_-20px_rgba(15,23,42,0.25)]"
                        loading="lazy" />
                </div>
            </div>
        </div>
    </div>
</section>

<section id="services" class="w-full bg-white">
    <div class="max-w-7xl mx-auto px-6 py-24">

        <!-- Section Header -->
        <div class="max-w-2xl mb-16">
            <span class="text-sm font-medium text-accent">
                Our Services
            </span>
            <h2 class="mt-3 text-3xl md:text-4xl font-semibold tracking-tight text-slate-900">
                Everything you need to build and scale digitally
            </h2>
            <p class="mt-4 text-slate-600">
                We work closely with businesses to design, build, and maintain
                technology systems that are reliable, secure, and scalable.
            </p>
        </div>

        <!-- Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">

            <!-- Card 1 -->
            <div class="bg-white rounded-xl p-8
                        border border-slate-200
                        shadow-[0_12px_24px_-18px_rgba(15,23,42,0.25)]">
                <h3 class="text-lg font-medium text-slate-900">
                    Website Development
                </h3>
                <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                    High-performance, responsive websites built with clean
                    architecture and long-term maintainability in mind.
                </p>
            </div>

            <!-- Card 2 -->
            <div class="bg-white rounded-xl p-8
                        border border-slate-200
                        shadow-[0_12px_24px_-18px_rgba(15,23,42,0.25)]">
                <h3 class="text-lg font-medium text-slate-900">
                    CRM & Business Systems
                </h3>
                <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                    Custom CRM solutions and internal tools that streamline
                    operations, improve visibility, and support growth.
                </p>
            </div>

            <!-- Card 3 -->
            <div class="bg-white rounded-xl p-8
                        border border-slate-200
                        shadow-[0_12px_24px_-18px_rgba(15,23,42,0.25)]">
                <h3 class="text-lg font-medium text-slate-900">
                    Managed IT Services
                </h3>
                <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                    Ongoing technical support, system monitoring, and
                    maintenance so your team can stay focused on the business.
                </p>
            </div>

        </div>

    </div>

</section>


<section class="w-full bg-slate-900">
    <div class="max-w-7xl mx-auto px-6 py-24">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-16 items-center">

            <!-- Left Content -->
            <div>
                <span class="text-sm font-medium text-accent">
                    India-focused solutions
                </span>

                <h2 class="mt-3 text-3xl md:text-4xl font-semibold tracking-tight text-white">
                    Built for the Indian market
                </h2>

                <p class="mt-6 text-slate-300 leading-relaxed max-w-xl">
                    We design technology systems that account for real-world Indian business
                    constraints—cost efficiency, scalability, and compliance—without
                    compromising reliability.
                </p>

                <div class="mt-10 space-y-4 text-slate-200">
                    <p>
                        <strong class="text-white">Ecommerce sellers</strong> — inventory,
                        marketplace workflows, and operational clarity.
                    </p>
                    <p>
                        <strong class="text-white">Service businesses</strong> — CRM,
                        automation, and client lifecycle management.
                    </p>
                    <p>
                        <strong class="text-white">Startups & SMEs</strong> — scalable systems
                        without enterprise overhead.
                    </p>
                </div>
            </div>

            <!-- Right Visual -->
            <div class="rounded-xl overflow-hidden">
                <img
                    src="{{ asset('assets/images/india-team.png') }}"
                    alt="Team collaborating on technology solutions"
                    class="w-full h-auto object-cover opacity-90"
                    loading="lazy" />
            </div>

        </div>
    </div>
</section>


<section id="crm" class="w-full bg-slate-50">
    <div class="max-w-7xl mx-auto px-6 py-24">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-16 items-center">

            <!-- Left Visual -->
            <div class="rounded-xl overflow-hidden">
                <img
                    src="{{ asset('assets/images/crm-dashboard.png') }}"
                    alt="CRM system dashboard"
                    class="w-full h-auto object-cover
                           shadow-[0_20px_40px_-20px_rgba(15,23,42,0.25)]" />
            </div>

            <!-- Right Content -->
            <div>
                <span class="text-sm font-medium text-accent">
                    CRM systems
                </span>

                <h2 class="mt-3 text-3xl md:text-4xl font-semibold tracking-tight text-slate-900">
                    CRM systems designed around your workflow
                </h2>

                <p class="mt-6 text-slate-600 leading-relaxed max-w-xl">
                    We design and implement CRM solutions that fit how your team
                    actually works—connecting leads, customers, and operations
                    into a single, reliable system.
                </p>

                <div class="mt-10 space-y-4 text-slate-700">
                    <p>• Lead and customer lifecycle tracking</p>
                    <p>• Workflow automation and task visibility</p>
                    <p>• Integrations with websites, forms, and tools</p>
                    <p>• Scalable architecture for future growth</p>
                </div>

                <div class="mt-10">
                    <a href="#contact"
                        class="inline-flex items-center justify-center px-6 py-3 rounded-md
                              bg-accent text-white text-sm font-medium
                              shadow-button hover:opacity-95 transition">
                        Discuss CRM requirements
                    </a>
                </div>
            </div>

        </div>
    </div>
</section>


<section class="w-full bg-white">
    <div class="max-w-7xl mx-auto px-6 py-24">

        <!-- Section Header -->
        <div class="max-w-2xl mb-16">
            <span class="text-sm font-medium text-accent">
                Why work with Wwwebtech
            </span>
            <h2 class="mt-3 text-3xl md:text-4xl font-semibold tracking-tight text-slate-900">
                A practical technology partner, not just a vendor
            </h2>
            <p class="mt-4 text-slate-600">
                We focus on clarity, reliability, and long-term value—so your
                systems support growth instead of creating friction.
            </p>
        </div>

        <!-- Value Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12">

            <!-- Value 1 -->
            <div>
                <h3 class="text-lg font-medium text-slate-900">
                    Transparent pricing
                </h3>
                <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                    Clear scopes and honest estimates with no hidden costs.
                    Built for Indian business budgets and realities.
                </p>
            </div>

            <!-- Value 2 -->
            <div>
                <h3 class="text-lg font-medium text-slate-900">
                    Local understanding
                </h3>
                <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                    We understand Indian compliance, infrastructure constraints,
                    and operational challenges—because we work within them.
                </p>
            </div>

            <!-- Value 3 -->
            <div>
                <h3 class="text-lg font-medium text-slate-900">
                    Scalable systems
                </h3>
                <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                    Solutions designed to grow with your business, not
                    rebuilt every time your needs evolve.
                </p>
            </div>

        </div>
    </div>
</section>


<section id="contact" class="w-full bg-slate-900">
    <div class="max-w-7xl mx-auto px-6 py-24">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-16 items-start">

            <!-- Left Content -->
            <div class="text-slate-200">
                <span class="text-sm font-medium text-accent">
                    Get in touch
                </span>
                <h2 class="text-3xl md:text-4xl font-semibold text-white">
                    Ready to transform your business?
                </h2>

                <p class="mt-6 text-slate-300 max-w-xl">
                    Get a free consultation and a clear roadmap for your digital
                    initiatives. No obligations.
                </p>

                <div class="mt-10 space-y-6 text-sm">
                    <div>
                        <p class="font-medium text-white">Headquarters</p>
                        <p>East Delhi, India</p>
                    </div>

                    <div>
                        <p class="font-medium text-white">Email</p>
                        <p>contact@wwwebtech.in</p>
                    </div>

                    <div>
                        <p class="font-medium text-white">Call</p>
                        <p> <a href="tel:+918595250209" class="hover:text-accent transition">
                            +91 85952 50209
                        </a></p>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <div class="bg-white rounded-xl p-8 shadow-[0_20px_40px_-20px_rgba(0,0,0,0.4)]">
                @if(session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 p-6 text-green-800">
                    <h3 class="font-semibold text-base mb-2">
                        Thank you for reaching out.
                    </h3>
                    <p class="text-sm leading-relaxed">
                        Your message has been received. We typically respond within 1–2 business days.
                    </p>
                </div>
                @endif

                @if($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                    Please fill all required fields correctly.
                </div>
                @endif
                <form class="space-y-6" method="POST" action="{{ route('contact.submit') }}">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700">
                            Name
                        </label>
                        <input
                            type="text"
                            name="name"
                            class="mt-2 w-full rounded-md border border-slate-300 px-4 py-2
                                   text-sm focus:outline-none focus:ring-2 focus:ring-accent"
                            placeholder="Your full name" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">
                            Email
                        </label>
                        <input
                            type="email"
                            name="email"
                            class="mt-2 w-full rounded-md border border-slate-300 px-4 py-2
                                   text-sm focus:outline-none focus:ring-2 focus:ring-accent"
                            placeholder="you@company.com" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">
                            Message
                        </label>
                        <textarea
                            rows="4"
                            name="message"
                            class="mt-2 w-full rounded-md border border-slate-300 px-4 py-2
                                   text-sm focus:outline-none focus:ring-2 focus:ring-accent"
                            placeholder="Briefly describe what you’re looking for" required></textarea>
                    </div>
                    <div>
                        <input type="text" name="company" class="hidden">
                    </div>

                    <button
                        type="submit"
                        class="w-full inline-flex items-center justify-center px-6 py-3
                               rounded-md bg-accent text-white text-sm font-medium
                               shadow-button hover:opacity-95 transition">
                        Send message
                    </button>
                    <div class="mt-8 text-sm text-slate-500 leading-relaxed">
                        <p><strong>Response Time:</strong> 1–2 business days</p>
                        <p><strong>Service Areas:</strong> Web, CRM, Automation, Technical Support</p>
                    </div>

                </form>
            </div>

        </div>
    </div>
</section>


@endsection