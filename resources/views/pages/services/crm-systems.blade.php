@extends('layouts.app')

@section('title', 'Custom CRM Systems for Indian Businesses | Wwwebtech')
@section('meta_description', 'Custom CRM systems for Indian businesses. Manage leads, customers, and operations with systems built around your real workflows.')

@section('content')
<section class="w-full bg-white">
    <div class="max-w-6xl mx-auto px-6 py-24">

        <!-- Header -->
        <div class="max-w-3xl">
            <h1 class="text-3xl md:text-4xl font-semibold text-slate-900 tracking-tight">
                CRM Systems
            </h1>

            <p class="mt-6 text-lg text-slate-600 leading-relaxed">
                We design and develop CRM systems that help businesses manage leads,
                customers, and internal processes — without unnecessary complexity.
            </p>
        </div>

        <!-- Main Content -->
        <div class="mt-14 space-y-12 max-w-4xl">

            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    What we build
                </h2>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    Our CRM systems are tailored to your specific workflows, whether
                    you need lead tracking, customer management, reporting, or internal
                    coordination between teams.
                </p>
            </div>

            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    Why custom CRM
                </h2>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    Off-the-shelf CRMs often force businesses to adapt their processes.
                    We build systems that adapt to how your team actually works,
                    reducing friction and improving adoption.
                </p>
            </div>

            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    Security & scalability
                </h2>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    We focus on secure data handling, role-based access, and scalable
                    architecture so your CRM can grow with your business without
                    constant rework.
                </p>
            </div>

            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    Who this is for
                </h2>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    This service is suitable for businesses that want better control
                    over their sales, support, or internal operations — without relying
                    on rigid third-party platforms.
                </p>
            </div>

        </div>

        <!-- CTA -->
        <div class="mt-20 border-t border-slate-200 pt-10">
            <p class="text-slate-600 max-w-2xl">
                If you’re considering a CRM system or want to replace an existing one,
                we can help you plan the right solution.
            </p>

            <a href="{{ request()->routeIs('home') ? '#contact' : route('contact') }}"
               class="inline-flex mt-6 items-center justify-center px-6 py-3 rounded-md
                      bg-accent text-white text-sm font-medium
                      shadow-button hover:opacity-95 transition">
                Request a consultation
            </a>
        </div>

    </div>
</section>
@endsection
