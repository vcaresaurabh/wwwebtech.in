<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function submit(Request $request)
    {
        // dump($request->all());
        if ($request->filled('company')) {
            abort(403);
        }

        $validated = $request->validate([
            'name'    => 'required|string|max:100',
            'email'   => 'required|email|max:150',
            'message' => 'required|string|max:2000',
        ]);

        Mail::raw(
            "Name: {$validated['name']}\nEmail: {$validated['email']}\n\nMessage:\n{$validated['message']}",
            function ($mail) use ($validated) {
                $mail->to(config('mail.from.address'))
                    ->replyTo($validated['email'])
                    ->subject('New Website Enquiry | wwwebtech.in');
            }
        );

        // 2️⃣ Send auto-reply to user
        Mail::raw(
            "Hello {$validated['name']},

Thank you for contacting Wwwebtech.

We have received your message and will review your requirements carefully.

Our typical response time is 1–2 business days.

If your matter is urgent, you may reply directly to this email.

Regards,
Wwwebtech
https://wwwebtech.in",
            function ($mail) use ($validated) {
                $mail->to($validated['email'])
                    ->subject('We’ve received your enquiry — Wwwebtech');
            }
        );

        return back()->with('success', 'Thank you. We will get back to you shortly.');
    }
}
