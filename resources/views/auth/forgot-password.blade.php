@extends('layouts.app')

@section('title', __('Forgot password').' · '.config('app.name'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-3">{{ __('Forgot password') }}</h1>

                    <p class="text-body-secondary small">
                        {{ __('Enter your email address and we will email you a link to choose a new password.') }}
                    </p>

                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}" novalidate>
                        @csrf

                        <div class="mb-4">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror"
                                   required autofocus autocomplete="email">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex align-items-center justify-content-between">
                            <a class="small" href="{{ route('login') }}">{{ __('Back to log in') }}</a>

                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-envelope me-1"></i>{{ __('Email reset link') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
