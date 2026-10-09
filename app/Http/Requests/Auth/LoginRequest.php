<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    private ?string $throttleKey = null;

    /**
     * Attempt to authenticate by username or email. Inactive users fail with
     * the same generic message as a bad password.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        [$field, $login] = $this->identifier();

        $credentials = [
            $field => $login,
            'password' => $this->string('password')->value(),
            'active' => true,
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), config('wicket.login.lockout_minutes') * 60);

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), config('wicket.login.max_attempts'))) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Keyed on the account when the identifier resolves to one, so switching
     * between username and email doesn't buy a fresh budget.
     */
    public function throttleKey(): string
    {
        if ($this->throttleKey === null) {
            [$field, $login] = $this->identifier();
            $id = User::where($field, $login)->value('id');

            $subject = $id ? "user:{$id}" : Str::transliterate(Str::lower($login));
            $this->throttleKey = "login:{$subject}|{$this->ip()}";
        }

        return $this->throttleKey;
    }

    /**
     * @return array{0: 'email'|'username', 1: string}
     */
    private function identifier(): array
    {
        $login = $this->string('login')->trim()->value();

        return filter_var($login, FILTER_VALIDATE_EMAIL)
            ? ['email', Str::lower($login)]
            : ['username', $login];
    }
}
