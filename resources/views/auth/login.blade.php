@extends('layouts.app')

@section('title', __('Log in').' · '.config('app.name'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-4">{{ __('Log in') }}</h1>

                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="login" class="form-label">{{ __('Username or email') }}</label>
                            <input id="login" type="text" name="login" value="{{ old('login') }}"
                                   class="form-control @error('login') is-invalid @enderror"
                                   required autofocus autocomplete="username">
                            @error('login')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('Password') }}</label>
                            <input id="password" type="password" name="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   required autocomplete="current-password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check mb-4">
                            <input id="remember" type="checkbox" name="remember" class="form-check-input">
                            <label for="remember" class="form-check-label">{{ __('Remember me') }}</label>
                        </div>

                        <div class="d-flex align-items-center justify-content-between">
                            @if (Route::has('password.request'))
                                <a class="small" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
                            @else
                                <span></span>
                            @endif

                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-right-to-bracket me-1"></i>{{ __('Log in') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
