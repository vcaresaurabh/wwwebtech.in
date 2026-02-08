@extends('layouts.app')

@section('title', 'Website Development Services for Businesses | Wwwebtech')
@section('meta_description', 'Professional website development services for Indian businesses. Fast, secure, and scalable websites built for real business needs.')

@section('content')
<section class="w-full bg-white">
    <div class="max-w-6xl mx-auto px-6 py-24">

        <!-- Header -->
        <div class="max-w-3xl">
            <h1 class="text-3xl md:text-4xl font-semibold text-slate-900 tracking-tight">
                Website Development
            </h1>

            <p class="mt-6 text-lg text-slate-600 leading-relaxed">
                We design and build websites that are fast, reliable, and easy to maintain —
                focused on business clarity, performance, and long-term scalability.
            </p>
        </div>

        <!-- Main Content -->
        <div class="mt-14 space-y-12 max-w-4xl">

            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    What we build
                </h2>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    We develop business websites, landing pages, and web platforms that are
                    tailored to your goals — whether that’s lead generation, brand presence,
                    or operational support.
                </p>
            </div>

            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    Our approach
                </h2>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    Every project starts with understanding your business, audience, and
                    requirements. We focus on clean structure, responsive design, and
                    maintainable code — avoiding unnecessary complexity.
                </p>
            </div>

            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    Technology & performance
                </h2>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    Our websites are built with performance and security in mind.
                    We ensure fast load times, mobile responsiveness, SEO-friendly structure,
                    and reliable hosting compatibility.
                </p>
            </div>

            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    Who this is for
                </h2>
                <p class="mt-4 text-slate-600 leading-relaxed">
                    This service is ideal for startups, small businesses, and teams that need
                    a professional web presence without over-engineered solutions or ongoing
                    dependency.
                </p>
            </div>

        </div>

        <!-- CTA -->
        <div class="mt-20 border-t border-slate-200 pt-10">
            <p class="text-slate-600 max-w-2xl">
                If you’re planning a new website or want to improve an existing one,
                we can help you define the right approach.
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
