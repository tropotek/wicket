<?php

use App\Http\Controllers\HealthController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.index')->name('index');
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
