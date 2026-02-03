@extends('layouts.app')

@section('title', 'Contact Wwwebtech — Let’s Discuss Your Requirements')
@section('meta_description', 'Get in touch with Wwwebtech to discuss web development, CRM systems, automation, or technical support requirements.')

@section('content')
<section class="w-full bg-white">
    <div class="max-w-6xl mx-auto px-6 py-24">

        <!-- Page Header -->
        <div class="max-w-3xl">
            <h1 class="text-3xl md:text-4xl font-semibold text-slate-900 tracking-tight">
                Contact Us
            </h1>
            <p class="mt-6 text-lg text-slate-600 leading-relaxed">
                Have a project in mind or need help improving your existing systems?
                Share a few details and we’ll get back to you.
            </p>
        </div>

        <!-- Content Grid -->
        <div class="mt-16 grid grid-cols-1 md:grid-cols-2 gap-16 items-start">

            <!-- Contact Info -->
            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    Get in touch
                </h2>

                <p class="mt-4 text-slate-600 leading-relaxed max-w-md">
                    We work with businesses across India on web platforms, CRM systems,
                    automation, and ongoing technical support.
                </p>

                <div class="mt-8 space-y-4 text-slate-600">
                    <p>
                        <span class="font-medium text-slate-900">Location:</span><br>
                        India
                    </p>

                    <p>
                        <span class="font-medium text-slate-900">Email:</span><br>
                        <a href="mailto:contact@wwwebtech.in" class="hover:text-accent transition">
                            contact@wwwebtech.in
                        </a>
                    </p>
                </div>
            </div>

            <!-- Contact Form (UI only V1) -->
            <div class="bg-slate-50 border border-slate-200 rounded-lg p-8">
                <form class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">
                            Name
                        </label>
                        <input type="text"
                               class="mt-2 w-full rounded-md border border-slate-300 px-4 py-2
                                      text-sm focus:outline-none focus:ring-2 focus:ring-accent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">
                            Email
                        </label>
                        <input type="email"
                               class="mt-2 w-full rounded-md border border-slate-300 px-4 py-2
                                      text-sm focus:outline-none focus:ring-2 focus:ring-accent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">
                            Message
                        </label>
                        <textarea rows="4"
                                  class="mt-2 w-full rounded-md border border-slate-300 px-4 py-2
                                         text-sm focus:outline-none focus:ring-2 focus:ring-accent"></textarea>
                    </div>

                    <button type="submit"
                            class="inline-flex items-center justify-center px-6 py-3 rounded-md
                                   bg-accent text-white text-sm font-medium
                                   shadow-button hover:opacity-95 transition">
                        Send message
                    </button>

                    <p class="text-xs text-slate-500">
                        We typically respond within 1–2 business days.
                    </p>
                </form>
            </div>

        </div>

    </div>
</section>
@endsection
