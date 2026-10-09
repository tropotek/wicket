@extends('layouts.app')

@php($editing = $secret->exists)

@section('title', ($editing ? __('Edit :name', ['name' => $secret->name]) : __('New secret')).' · '.config('app.name'))

@section('content')
    <form method="POST" novalidate autocomplete="off"
          action="{{ $editing ? route('secrets.update', $secret) : route('secrets.store') }}">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <h1 class="h3 mb-0 me-auto">
                <i class="fa-solid fa-key me-2"></i>{{ $editing ? __('Edit secret') : __('New secret') }}
            </h1>
            <a href="{{ route('secrets.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>{{ __('Save') }}</button>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                @foreach ([
                    ['name', __('Name'), 'text', true],
                    ['url', __('Website'), 'url', false],
                    ['username', __('Username'), 'text', false],
                ] as [$field, $label, $type, $required])
                    <div class="mb-3">
                        <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                        <input id="{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field, $secret->{$field}) }}"
                               @if ($required) required @endif @if ($field === 'name') autofocus @endif
                               class="form-control @error($field) is-invalid @enderror">
                        @error($field)
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @endforeach

                <div class="mb-3" x-data="{ show: false }">
                    <label for="password" class="form-label">{{ __('Password') }}</label>
                    <div class="input-group has-validation">
                        <input id="password" :type="show ? 'text' : 'password'" type="password" name="password"
                               value="{{ old('password', $secret->password) }}" autocomplete="new-password"
                               class="form-control font-monospace @error('password') is-invalid @enderror">
                        <button type="button" class="btn btn-outline-secondary" @click="show = ! show"
                                :aria-label="show ? @js(__('Hide password')) : @js(__('Show password'))">
                            <i class="fa-solid" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="otp" class="form-label">{{ __('2FA key') }}</label>
                    <input id="otp" type="text" name="otp" value="{{ old('otp', $secret->otp) }}" spellcheck="false"
                           class="form-control font-monospace @error('otp') is-invalid @enderror">
                    <div class="form-text">{{ __('The setup key from the site\'s authenticator-app step, or its otpauth:// link. Pages then show the current code.') }}</div>
                    @error('otp')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                @foreach ([['keys', __('Keys'), 'font-monospace'], ['notes', __('Notes'), '']] as [$field, $label, $class])
                    <div class="mb-3">
                        <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                        <textarea id="{{ $field }}" name="{{ $field }}" rows="4" spellcheck="{{ $field === 'notes' ? 'true' : 'false' }}"
                                  class="form-control {{ $class }} @error($field) is-invalid @enderror">{{ old($field, $secret->{$field}) }}</textarea>
                        @error($field)
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @endforeach
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="visibility" class="form-label">{{ __('Who can see it') }}</label>
                            <select id="visibility" name="visibility" class="form-select @error('visibility') is-invalid @enderror">
                                @foreach ($visibilities as $v)
                                    <option value="{{ $v->value }}" @selected(old('visibility', $secret->visibility?->value) === $v->value)>{{ $v->label() }}</option>
                                @endforeach
                            </select>
                            @error('visibility')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">{{ __('Private: only you and admins.') }}</div>
                        </div>
                        <div class="form-check form-switch">
                            <input type="hidden" name="publish" value="0">
                            <input id="publish" type="checkbox" name="publish" value="1" role="switch" class="form-check-input"
                                   @checked(old('publish', $secret->publish))>
                            <label for="publish" class="form-check-label">{{ __('Published') }}</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
