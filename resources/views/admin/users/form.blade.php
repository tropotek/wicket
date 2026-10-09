@extends('layouts.app')

@php($editing = $user->exists)
@php($self = $editing && $user->is(auth()->user()))

@section('title', ($editing ? __('Edit :username', ['username' => $user->username]) : __('New user')).' · '.config('app.name'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">{{ __('Admin') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">{{ __('Users') }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $editing ? $user->username : __('New') }}</li>
                </ol>
            </nav>

            <h1 class="h3 mb-4">
                <i class="fa-solid {{ $editing ? 'fa-user-pen' : 'fa-user-plus' }} me-2"></i>{{ $editing ? __('Edit user') : __('New user') }}
            </h1>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" novalidate
                          action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}">
                        @csrf
                        @if ($editing)
                            @method('PUT')
                        @endif

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label">{{ __('Username') }}</label>
                                <input id="username" type="text" name="username" value="{{ old('username', $user->username) }}"
                                       class="form-control @error('username') is-invalid @enderror" required>
                                @error('username')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="name" class="form-label">{{ __('Name') }}</label>
                                <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}"
                                       class="form-control @error('name') is-invalid @enderror" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}"
                                   class="form-control @error('email') is-invalid @enderror" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="role" class="form-label">{{ __('Role') }}</label>
                                <select id="role" name="role" class="form-select @error('role') is-invalid @enderror"
                                        @disabled($self)>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->value }}" @selected(old('role', $user->role?->value ?? 'member') === $role->value)>
                                            {{ $role->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($self)
                                    {{-- Disabled fields aren't submitted --}}
                                    <input type="hidden" name="role" value="{{ $user->role->value }}">
                                    <div class="form-text">{{ __('You cannot change your own role.') }}</div>
                                @endif
                                @error('role')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input type="hidden" name="active" value="{{ $self ? '1' : '0' }}">
                                    <input id="active" type="checkbox" name="active" value="1" role="switch"
                                           class="form-check-input @error('active') is-invalid @enderror"
                                           @checked(old('active', $user->exists ? $user->active : true)) @disabled($self)>
                                    <label for="active" class="form-check-label">{{ __('Active') }}</label>
                                    @error('active')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <fieldset class="border rounded p-3 mb-4">
                            <legend class="float-none w-auto px-2 fs-6 mb-0">
                                {{ $editing ? __('Set a new password (optional)') : __('Password') }}
                            </legend>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="password" class="form-label">{{ __('Password') }}</label>
                                    <input id="password" type="password" name="password" autocomplete="new-password"
                                           class="form-control @error('password') is-invalid @enderror" @required(! $editing)>
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="password_confirmation" class="form-label">{{ __('Confirm password') }}</label>
                                    <input id="password_confirmation" type="password" name="password_confirmation"
                                           autocomplete="new-password" class="form-control" @required(! $editing)>
                                </div>
                            </div>
                            @if ($editing)
                                <div class="form-text mt-2">{{ __('Setting a password logs the user out everywhere.') }}</div>
                            @endif
                        </fieldset>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-floppy-disk me-1"></i>{{ $editing ? __('Save') : __('Create user') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
