<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Always answers with the same status, whether or not the email has an
     * account, so the form can't be used to discover accounts.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // A send failure only happens for real accounts, so surfacing it would
        // reveal which emails exist. Log it and reply as normal.
        try {
            Password::sendResetLink($request->only('email'));
        } catch (Throwable $e) {
            report($e);
        }

        return back()->with('status', __('If that email belongs to an account, a reset link is on its way.'));
    }
}
