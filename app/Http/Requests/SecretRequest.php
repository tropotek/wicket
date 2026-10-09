<?php

namespace App\Http\Requests;

use App\Enums\Visibility;
use App\Models\Secret;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SecretRequest extends FormRequest
{
    /** Base32 TOTP seed, at least 80 bits. */
    public const OTP_PATTERN = '/^[A-Z2-7]{16,}$/';

    public function authorize(): bool
    {
        $secret = $this->route('secret');

        return $secret ? Gate::allows('update', $secret) : Gate::allows('create', Secret::class);
    }

    /**
     * The OTP field takes a seed as shown by most sites (spaces, lower case, padding) or a
     * whole otpauth:// URI, whose `secret` parameter is the seed.
     */
    protected function prepareForValidation(): void
    {
        $otp = trim((string) $this->input('otp'));

        if (str_starts_with(strtolower($otp), 'otpauth://')) {
            parse_str((string) parse_url($otp, PHP_URL_QUERY), $query);
            // A link without a seed stays as-is so validation rejects it rather than dropping 2FA.
            $otp = filled($query['secret'] ?? null) ? (string) $query['secret'] : $otp;
        }

        $this->merge(['otp' => strtoupper(str_replace([' ', '-', '='], '', $otp))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $text = ['nullable', 'string', 'max:65535'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'visibility' => ['required', Rule::in(array_map(fn (Visibility $v) => $v->value, Secret::visibilities()))],
            'publish' => ['boolean'],
            'url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'username' => $text,
            'password' => $text,
            'otp' => ['nullable', 'string', 'regex:'.self::OTP_PATTERN],
            'keys' => $text,
            'notes' => $text,
        ];
    }

    public function messages(): array
    {
        return [
            'otp.regex' => __('That isn\'t a 2FA key. Paste the setup key (letters A–Z and digits 2–7) or the otpauth:// link.'),
        ];
    }

    /** Validated values ready for the model; blanks stored as null. */
    public function secretAttributes(): array
    {
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'visibility' => Visibility::from($data['visibility']),
            'publish' => $this->boolean('publish'),
        ] + collect(['url', 'username', 'password', 'otp', 'keys', 'notes'])
            ->mapWithKeys(fn (string $f) => [$f => filled($data[$f] ?? null) ? $data[$f] : null])
            ->all();
    }
}
