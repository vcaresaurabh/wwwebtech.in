@extends('layouts.app')

@section('title', 'Business Automation Services | Wwwebtech')
@section('meta_description', 'Business automation services to reduce manual work, improve efficiency, and streamline operations for Indian businesses.')

@section('content')
<section class="w-full bg-white">
    <div class="max-w-6xl mx-auto px-6 py-24">

        <!-- Header -->
        <div class="max-w-3xl">
            <h1 class="text-3xl md:text-4xl font-semibold text-slate-900 tracking-tight">
                Business Automation
            </h1>

            <p class="mt-6 text-lg text-slate-600 leading-relaxed">
                We help businesses automate repetitive tasks and workflows,
                reducing manual effort and improving operational efficiency.
            </p>
        </div>

        <!-- Main Content -->
        <div class="mt-14 space-y-12 max-w-4xl">

            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    What we automate
                </h2>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    From internal processes to system integrations, we identify
                    areas where automation can save time, reduce errors, and
                    improve consistency across operations.
                </p>
            </div>

            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    Our approach
                </h2>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    We start by understanding how your business operates today.
                    Automation is applied only where it adds real value —
                    not where it introduces unnecessary complexity.
                </p>
            </div>

            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    Tools & integrations
                </h2>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    We work with APIs, dashboards, internal tools, and third-party
                    services to create seamless workflows that fit your existing
                    technology stack.
                </p>
            </div>

            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    Who this is for
                </h2>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    This service is suitable for businesses experiencing growth,
                    operational bottlenecks, or inefficiencies caused by manual
                    or disconnected systems.
                </p>
            </div>

        </div>

        <!-- CTA -->
        <div class="mt-20 border-t border-slate-200 pt-10">
            <p class="text-slate-600 max-w-2xl">
                If your team is spending too much time on repetitive tasks,
                we can help you identify and automate the right processes.
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
