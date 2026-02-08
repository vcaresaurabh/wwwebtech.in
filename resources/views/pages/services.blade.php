@extends('layouts.app')

@section('title', 'Our Services — Web, CRM & Automation | Wwwebtech')
@section('meta_description', 'Explore Wwwebtech services including web development, CRM systems, automation, and ongoing technical support for Indian businesses.')

@section('content')
<section class="w-full bg-white">
    <div class="max-w-6xl mx-auto px-6 py-24">

        <!-- Page Header -->
        <div class="max-w-3xl">
            <h1 class="text-3xl md:text-4xl font-semibold text-slate-900 tracking-tight">
                Our Services
            </h1>
            <p class="mt-6 text-lg text-slate-600 leading-relaxed">
                We help businesses design, build, and maintain digital systems that
                support real operations and long-term growth.
            </p>
        </div>

        <!-- Services List -->
        <div class="mt-16 space-y-14">

            <!-- Service 1 -->
            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    Website Development
                </h2>
                <p class="mt-3 text-slate-600 leading-relaxed max-w-3xl">
                    We design and develop fast, secure, and maintainable websites and web platforms.
                    Our focus is on clarity, performance, and scalability — not templates or shortcuts.
                </p>
                <a href="{{ route('services.website') }}"
                    class="text-accent font-medium hover:underline">
                    Learn more →
                </a>
            </div>

            <!-- Service 2 -->
            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    CRM Systems
                </h2>
                <p class="mt-3 text-slate-600 leading-relaxed max-w-3xl">
                    We build custom CRM systems tailored to your sales, support, and internal workflows.
                    From lead tracking to reporting, our systems are designed to match how your team actually works.
                </p>
                <a href="{{ route('services.crm') }}"
                    class="text-accent font-medium hover:underline">
                    Learn more →
                </a>
            </div>

            <!-- Service 3 -->
            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    Business Automation
                </h2>
                <p class="mt-3 text-slate-600 leading-relaxed max-w-3xl">
                    We automate repetitive tasks and manual processes to improve efficiency and reduce errors.
                    This includes integrations between tools, internal dashboards, and workflow optimization.
                </p>
                <a href="{{ route('services.automation') }}"
                    class="text-accent font-medium hover:underline">
                    Learn more →
                </a>
            </div>

            <!-- Service 4 -->
            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    Ongoing Technical Support
                </h2>
                <p class="mt-3 text-slate-600 leading-relaxed max-w-3xl">
                    We provide ongoing technical support, monitoring, and system improvements to ensure
                    your digital infrastructure remains reliable as your business grows.
                </p>
                <a href="{{ route('services.support') }}"
                    class="text-accent font-medium hover:underline">
                    Learn more →
                </a>

            </div>

        </div>

        <!-- CTA -->
        <div class="mt-20 border-t border-slate-200 pt-10">
            <p class="text-slate-600 max-w-2xl">
                Not sure which service fits your needs? We can help you assess your requirements
                and recommend the right approach.
            </p>

            <a href="{{ route('contact') }}"
                class="inline-flex mt-6 items-center justify-center px-6 py-3 rounded-md
                      bg-accent text-white text-sm font-medium
                      shadow-button hover:opacity-95 transition">
                Request a consultation
            </a>
        </div>

    </div>
</section>
@endsection