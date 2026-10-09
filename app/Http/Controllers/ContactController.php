<?php

namespace App\Http\Controllers;

use App\Facades\Settings;
use App\Mail\ContactMessage;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContactController extends Controller
{
    /** Humans take longer than this to fill the form; bots usually don't. */
    private const MIN_SECONDS = 3;

    private const MAX_SECONDS = 7200;

    private const PER_HOUR = 5;

    public function create(): View
    {
        abort_unless(Settings::get('wiki.contact'), 404);

        return view('contact.create', ['stamp' => Crypt::encryptString((string) now()->timestamp)]);
    }

    public function send(Request $request): RedirectResponse
    {
        abort_unless(Settings::get('wiki.contact'), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $sent = redirect()->route('contact')->with('status', __('Thanks — your message has been sent.'));

        // Bots: filled the hidden field, or answered impossibly fast. Look successful, send nothing.
        if (filled($request->input('website')) || ! $this->plausiblyHuman((string) $request->input('stamp'))) {
            return $sent;
        }

        $keys = ['contact:ip:'.$request->ip(), 'contact:email:'.strtolower($data['email'])];
        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, self::PER_HOUR)) {
                throw ValidationException::withMessages([
                    'message' => __('Too many messages. Try again in :minutes minutes.', ['minutes' => ceil(RateLimiter::availableIn($key) / 60)]),
                ]);
            }
        }
        foreach ($keys as $key) {
            RateLimiter::hit($key, 3600);
        }

        Mail::to(config('mail.from.address'), config('app.name'))->send(new ContactMessage($data));

        return $sent;
    }

    private function plausiblyHuman(string $stamp): bool
    {
        try {
            $age = now()->timestamp - (int) Crypt::decryptString($stamp);
        } catch (DecryptException) {
            return false;
        }

        return $age >= self::MIN_SECONDS && $age <= self::MAX_SECONDS;
    }
}
