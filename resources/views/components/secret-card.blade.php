@props(['secret'])
{{--
    A secret for someone allowed to view it. Holds no protected value: password, keys, notes and
    the 2FA code are fetched from secrets.reveal when asked for.
--}}
@php
    $copyLabel = fn (string $what) => __('Copy :what', ['what' => $what]);
@endphp
<div {{ $attributes->class('card wk-secret d-inline-block align-top my-1') }} style="min-width: 16rem; max-width: 26rem"
     x-data="secretCard(@js(route('secrets.reveal', $secret)))">
    <div class="card-body p-2">
        <div class="d-flex align-items-center gap-2 mb-1">
            <i class="fa-solid fa-key text-body-secondary"></i>
            @if ($secret->url && preg_match('#^https?://#i', $secret->url))
                <a href="{{ $secret->url }}" target="_blank" rel="noopener noreferrer" class="fw-semibold me-auto text-truncate" title="{{ __('Visit site') }}">{{ $secret->name }}</a>
            @else
                <span class="fw-semibold me-auto text-truncate">{{ $secret->name }}</span>
            @endif
            @unless ($secret->publish)
                <span class="badge text-bg-warning">{{ __('Unpublished') }}</span>
            @endunless
            @can('update', $secret)
                <a href="{{ route('secrets.edit', $secret) }}" class="text-body-secondary" title="{{ __('Edit') }}" aria-label="{{ __('Edit :name', ['name' => $secret->name]) }}">
                    <i class="fa-solid fa-pen-to-square"></i>
                </a>
            @endcan
        </div>

        <dl class="row g-0 small mb-0">
            @if ($secret->username)
                <dt class="col-3 fw-normal text-body-secondary" :class="copied === 'username' && 'wk-copied'">{{ __('User') }}</dt>
                <dd class="col-9 mb-1 d-flex align-items-center gap-1" :class="copied === 'username' && 'wk-copied'">
                    <span class="font-monospace text-truncate">{{ $secret->username }}</span>
                    <button type="button" class="btn btn-link btn-sm p-0 ms-auto" @click="copy('username', @js($secret->username))"
                            :title="copied === 'username' ? @js(__('Copied')) : @js($copyLabel(__('username')))" aria-label="{{ $copyLabel(__('username')) }}">
                        <i class="fa-solid" :class="copied === 'username' ? 'fa-check text-success' : 'fa-copy'"></i>
                    </button>
                </dd>
            @endif

            @if ($secret->password)
                <dt class="col-3 fw-normal text-body-secondary" :class="copied === 'password' && 'wk-copied'">{{ __('Password') }}</dt>
                <dd class="col-9 mb-1 d-flex align-items-center gap-1" :class="copied === 'password' && 'wk-copied'">
                    <span class="font-monospace text-break" x-show="showPassword" x-text="values?.password" x-cloak></span>
                    <span class="font-monospace" x-show="! showPassword">••••••••</span>
                    <span class="ms-auto d-flex gap-2">
                        <button type="button" class="btn btn-link btn-sm p-0" @click="togglePassword()" :disabled="busy"
                                :aria-label="showPassword ? @js(__('Hide password')) : @js(__('Show password'))">
                            <i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                        <button type="button" class="btn btn-link btn-sm p-0" @click="copy('password')" :disabled="busy"
                                :title="copied === 'password' ? @js(__('Copied')) : @js($copyLabel(__('password')))" aria-label="{{ $copyLabel(__('password')) }}">
                            <i class="fa-solid" :class="copied === 'password' ? 'fa-check text-success' : 'fa-copy'"></i>
                        </button>
                    </span>
                </dd>
            @endif

            @if ($secret->otp)
                <dt class="col-3 fw-normal text-body-secondary" :class="copied === 'otp' && 'wk-copied'">{{ __('2FA') }}</dt>
                <dd class="col-9 mb-1 d-flex align-items-center gap-2" :class="copied === 'otp' && 'wk-copied'">
                    <button type="button" class="btn btn-outline-secondary btn-sm py-0" x-show="! otp" @click="toggleOtp()" :disabled="busy">
                        {{ __('Show code') }}
                    </button>
                    <template x-if="otp">
                        <span class="d-flex align-items-center gap-2">
                            <span class="font-monospace fs-6 fw-semibold" x-text="otp.code.replace(/(\d{3})(\d{3})/, '$1 $2')"></span>
                            <svg width="20" height="20" viewBox="0 0 20 20" role="img" :aria-label="otp.remaining + @js(' '.__('seconds left'))">
                                <circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-opacity=".2" stroke-width="2.5"/>
                                <circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                        transform="rotate(-90 10 10)" :class="otp.remaining <= 5 ? 'text-danger' : 'text-primary'"
                                        :stroke-dasharray="2 * Math.PI * 8" :stroke-dashoffset="ringOffset()"/>
                            </svg>
                            <span class="text-body-secondary" x-text="otp.remaining + 's'"></span>
                        </span>
                    </template>
                    <span class="ms-auto d-flex gap-2">
                        <button type="button" class="btn btn-link btn-sm p-0" @click="copy('otp')" :disabled="busy"
                                :title="copied === 'otp' ? @js(__('Copied')) : @js($copyLabel(__('code')))" aria-label="{{ $copyLabel(__('code')) }}">
                            <i class="fa-solid" :class="copied === 'otp' ? 'fa-check text-success' : 'fa-copy'"></i>
                        </button>
                        <button type="button" class="btn btn-link btn-sm p-0" x-show="otp" x-cloak @click="toggleOtp()" aria-label="{{ __('Hide code') }}">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </span>
                </dd>
            @endif
        </dl>

        @if ($secret->keys || $secret->notes)
            <button type="button" class="btn btn-link btn-sm p-0 small" @click="toggleNotes()" :disabled="busy">
                <i class="fa-solid me-1" :class="showNotes ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                <span x-text="showNotes ? @js(__('Hide details')) : @js(__('Show details'))">{{ __('Show details') }}</span>
            </button>
            <div x-show="showNotes" x-cloak class="small mt-1">
                <template x-if="values?.keys">
                    <div class="mb-1">
                        <div class="d-flex align-items-center" :class="copied === 'keys' && 'wk-copied'">
                            <span class="text-body-secondary me-auto">{{ __('Keys') }}</span>
                            <button type="button" class="btn btn-link btn-sm p-0" @click="copy('keys')" aria-label="{{ $copyLabel(__('keys')) }}">
                                <i class="fa-solid" :class="copied === 'keys' ? 'fa-check text-success' : 'fa-copy'"></i>
                            </button>
                        </div>
                        <pre class="bg-body-tertiary rounded p-1 mb-0 small" style="white-space: pre-wrap; word-break: break-all" x-text="values.keys"></pre>
                    </div>
                </template>
                <template x-if="values?.notes">
                    <div>
                        <span class="text-body-secondary">{{ __('Notes') }}</span>
                        <div style="white-space: pre-wrap" x-text="values.notes"></div>
                    </div>
                </template>
            </div>
        @endif

        <div class="text-danger small mt-1" x-show="error" x-text="error" x-cloak></div>
    </div>
</div>
