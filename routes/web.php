<?php

use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PageHistoryController;
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
    Route::post('/pages/{page:slug}/lock', [PageController::class, 'heartbeat'])->name('pages.lock');
    Route::post('/pages/{page:slug}/unlock', [PageController::class, 'unlock'])->name('pages.unlock');

    Route::get('/pages/{page:slug}/history', [PageHistoryController::class, 'index'])->name('pages.history');
    Route::get('/pages/{page:slug}/diff', [PageHistoryController::class, 'diff'])->name('pages.diff');
    Route::scopeBindings()->group(function () {
        Route::get('/pages/{page:slug}/revisions/{content}', [PageHistoryController::class, 'show'])->name('pages.revision');
        Route::post('/pages/{page:slug}/revisions/{content}/restore', [PageHistoryController::class, 'restore'])->name('pages.restore');
    });
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.index')->name('index');
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::get('pages', [AdminPageController::class, 'index'])->name('pages.index');
    Route::post('pages/bulk', [AdminPageController::class, 'bulk'])->name('pages.bulk');
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
