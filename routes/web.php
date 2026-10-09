<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/pages/create', [PageController::class, 'create'])->name('pages.create');
    Route::post('/pages', [PageController::class, 'store'])->name('pages.store');
    Route::get('/pages/{page:slug}/edit', [PageController::class, 'edit'])->name('pages.edit');
    Route::put('/pages/{page:slug}', [PageController::class, 'update'])->name('pages.update');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.index')->name('index');
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
});

Route::get('/up', HealthController::class)
    ->name('health')
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        PreventRequestForgery::class,
    ]);

require __DIR__.'/auth.php';

// Catch-all page route: keep last so fixed routes above take precedence.
Route::get('/{page:slug}', [PageController::class, 'show'])
    ->where('page', '[A-Za-z0-9._-]+')
    ->name('pages.show');
