<?php

use App\Http\Controllers\Admin\LinkController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PageHistoryController;
use App\Http\Controllers\PagePickerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SecretController;
use App\Http\Middleware\ApplySettings;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// The home page set in Settings, or the welcome page.
Route::get('/', [PageController::class, 'home'])->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/pages/create', [PageController::class, 'create'])->name('pages.create');
    Route::get('/pages/picker', [PagePickerController::class, 'index'])->name('pages.picker');
    Route::get('/pages/picker/slug', [PagePickerController::class, 'slug'])->name('pages.picker.slug');
    Route::get('/pages/picker/suggest', [PagePickerController::class, 'suggest'])->name('pages.picker.suggest');
    Route::post('/pages', [PageController::class, 'store'])->name('pages.store');
    Route::get('/pages/{page:slug}/edit', [PageController::class, 'edit'])->name('pages.edit');
    Route::put('/pages/{page:slug}', [PageController::class, 'update'])->name('pages.update');
    Route::post('/pages/{page:slug}/lock', [PageController::class, 'heartbeat'])->name('pages.lock');
    Route::post('/pages/{page:slug}/unlock', [PageController::class, 'unlock'])->name('pages.unlock');

    Route::post('/media/upload', [MediaController::class, 'upload'])->name('media.upload');
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::post('/media/files', [MediaController::class, 'files'])->name('media.files');
    Route::post('/media/folders', [MediaController::class, 'folder'])->name('media.folders');
    Route::patch('/media/rename', [MediaController::class, 'rename'])->name('media.rename');
    Route::delete('/media', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::get('/media/thumb', [MediaController::class, 'thumb'])->name('media.thumb');

    Route::get('/secrets/picker', [SecretController::class, 'picker'])->name('secrets.picker');
    Route::post('/secrets/{secret}/reveal', [SecretController::class, 'reveal'])->middleware('throttle:60,1')->name('secrets.reveal');
    Route::resource('secrets', SecretController::class);

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
    Route::get('links', [LinkController::class, 'index'])->name('links.index');
    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('menu', [MenuController::class, 'index'])->name('menu.index');
    Route::post('menu', [MenuController::class, 'store'])->name('menu.store');
    Route::put('menu/order', [MenuController::class, 'order'])->name('menu.order');
    Route::patch('menu/{item}', [MenuController::class, 'update'])->name('menu.update');
    Route::delete('menu/{item}', [MenuController::class, 'destroy'])->name('menu.destroy');
});

Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'send'])->name('contact.send');

Route::get('/up', HealthController::class)
    ->name('health')
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        PreventRequestForgery::class,
        ApplySettings::class,
    ]);

require __DIR__.'/auth.php';

// Catch-all page route: keep last so fixed routes above take precedence.
Route::get('/{page:slug}', [PageController::class, 'show'])
    ->where('page', '[A-Za-z0-9._-]+')
    ->name('pages.show')
    ->missing(fn (Request $request) => PageController::redirectOldSlug($request));
