@extends('layouts.app')

@section('title', 'Ongoing Technical Support for Business Systems | Wwwebtech')
@section('meta_description', 'Reliable ongoing technical support for websites, systems, and internal tools to keep your business running smoothly.')

@section('content')
<section class="w-full bg-white">
    <div class="max-w-6xl mx-auto px-6 py-24">

        <!-- Header -->
        <div class="max-w-3xl">
            <h1 class="text-3xl md:text-4xl font-semibold text-slate-900 tracking-tight">
                Ongoing Technical Support
            </h1>

            <p class="mt-6 text-lg text-slate-600 leading-relaxed">
                We provide dependable technical support to ensure your websites,
                systems, and tools continue to operate reliably as your business grows.
            </p>
        </div>

        <!-- Main Content -->
        <div class="mt-14 space-y-12 max-w-4xl">

            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    What we support
                </h2>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    Our support covers websites, CRM systems, internal dashboards,
                    and custom tools built for your business — whether developed
                    by us or inherited from previous vendors.
                </p>
            </div>

            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    How we work
                </h2>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    We operate with clear communication, defined scope, and
                    predictable response times. Support is structured to
                    prevent issues, not just react to them.
                </p>
            </div>

            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    Reliability & continuity
                </h2>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    As systems evolve, maintaining stability becomes critical.
                    We focus on updates, monitoring, and gradual improvements
                    to keep your infrastructure secure and dependable.
                </p>
            </div>

            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    Who this is for
                </h2>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    This service is ideal for businesses that rely on technology
                    daily and need consistent technical oversight without
                    building an in-house team.
                </p>
            </div>

        </div>

        <!-- CTA -->
        <div class="mt-20 border-t border-slate-200 pt-10">
            <p class="text-slate-600 max-w-2xl">
                If you’re looking for dependable technical support to maintain
                and improve your systems, we can discuss the right support model.
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
