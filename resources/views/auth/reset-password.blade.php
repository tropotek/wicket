@extends('layouts.app')

@section('title', __('Reset password').' · '.config('app.name'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-4">{{ __('Reset password') }}</h1>

                    <form method="POST" action="{{ route('password.store') }}" novalidate>
                        @csrf
                        <input type="hidden" name="token" value="{{ $request->route('token') }}">

                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}"
                                   class="form-control @error('email') is-invalid @enderror"
                                   required autocomplete="username">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('New password') }}</label>
                            <input id="password" type="password" name="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   required autofocus autocomplete="new-password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">{{ __('Confirm password') }}</label>
                            <input id="password_confirmation" type="password" name="password_confirmation"
                                   class="form-control" required autocomplete="new-password">
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-key me-1"></i>{{ __('Reset password') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
