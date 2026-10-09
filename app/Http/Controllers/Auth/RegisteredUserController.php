<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        abort_unless(config('wicket.registration'), 404);

        return view('auth.register');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(config('wicket.registration'), 404);

        $validated = $request->validate([
            'username' => ['required', 'string', 'alpha_dash:ascii', 'max:64', 'unique:'.User::class],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create($validated);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect(route('home', absolute: false));
    }
}
