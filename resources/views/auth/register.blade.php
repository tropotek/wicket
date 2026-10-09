@extends('layouts.app')

@section('title', __('Register').' · '.config('app.name'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-4">{{ __('Register') }}</h1>

                    <form method="POST" action="{{ route('register') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="username" class="form-label">{{ __('Username') }}</label>
                            <input id="username" type="text" name="username" value="{{ old('username') }}"
                                   class="form-control @error('username') is-invalid @enderror"
                                   required autofocus autocomplete="username">
                            @error('username')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @else
                                <div class="form-text">{{ __('Letters, numbers, dashes and underscores.') }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">{{ __('Name') }}</label>
                            <input id="name" type="text" name="name" value="{{ old('name') }}"
                                   class="form-control @error('name') is-invalid @enderror"
                                   required autocomplete="name">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror"
                                   required autocomplete="email">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('Password') }}</label>
                            <input id="password" type="password" name="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   required autocomplete="new-password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">{{ __('Confirm password') }}</label>
                            <input id="password_confirmation" type="password" name="password_confirmation"
                                   class="form-control" required autocomplete="new-password">
                        </div>

                        <div class="d-flex align-items-center justify-content-between">
                            <a class="small" href="{{ route('login') }}">{{ __('Already registered?') }}</a>

                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-user-plus me-1"></i>{{ __('Register') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
