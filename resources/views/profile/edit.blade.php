@extends('layouts.app')

@section('title', __('Profile').' · '.config('app.name'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="h3 mb-4"><i class="fa-solid fa-id-card me-2"></i>{{ __('Profile') }}</h1>

            @if (session('status'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
                </div>
            @endif

            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">{{ __('Details') }}</h2>

                    <form method="POST" action="{{ route('profile.update') }}" novalidate
                          x-data="{ email: @js(old('email', $user->email)), original: @js($user->email) }">
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label for="username" class="form-label">{{ __('Username') }}</label>
                            <input id="username" type="text" class="form-control" value="{{ $user->username }}" disabled readonly>
                            <div class="form-text">{{ __('Ask an admin to change your username.') }}</div>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">{{ __('Name') }}</label>
                            <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}"
                                   class="form-control @error('name') is-invalid @enderror"
                                   required autocomplete="name">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input id="email" type="email" name="email" x-model="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   required autocomplete="email">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3" x-show="email !== original || @js($errors->has('current_password'))" x-cloak>
                            <label for="profile_current_password" class="form-label">{{ __('Current password') }}</label>
                            <input id="profile_current_password" type="password" name="current_password"
                                   class="form-control @error('current_password') is-invalid @enderror"
                                   autocomplete="current-password">
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @else
                                <div class="form-text">{{ __('Needed to change your email address.') }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="timezone" class="form-label">{{ __('Timezone') }}</label>
                            <select id="timezone" name="timezone" class="form-select @error('timezone') is-invalid @enderror">
                                <option value="">{{ __('Default (:tz)', ['tz' => config('app.timezone')]) }}</option>
                                @foreach ($timezones as $tz)
                                    <option value="{{ $tz }}" @selected(old('timezone', $user->timezone) === $tz)>{{ $tz }}</option>
                                @endforeach
                            </select>
                            @error('timezone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk me-1"></i>{{ __('Save') }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">{{ __('Change password') }}</h2>

                    <form method="POST" action="{{ route('profile.password') }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="current_password" class="form-label">{{ __('Current password') }}</label>
                            <input id="current_password" type="password" name="current_password"
                                   class="form-control @error('current_password', 'updatePassword') is-invalid @enderror"
                                   required autocomplete="current-password">
                            @error('current_password', 'updatePassword')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('New password') }}</label>
                            <input id="password" type="password" name="password"
                                   class="form-control @error('password', 'updatePassword') is-invalid @enderror"
                                   required autocomplete="new-password">
                            @error('password', 'updatePassword')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">{{ __('Confirm new password') }}</label>
                            <input id="password_confirmation" type="password" name="password_confirmation"
                                   class="form-control" required autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-key me-1"></i>{{ __('Change password') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
