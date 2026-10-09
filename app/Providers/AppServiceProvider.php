<?php

namespace App\Providers;

use App\Models\Page;
use App\Models\User;
use App\Support\Menu;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $versionFile = base_path('version.md');
        $version = is_file($versionFile) ? trim(file_get_contents($versionFile)) : 'dev';

        config(['app.version' => $version]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        Paginator::useBootstrapFive();

        View::composer('layouts.app', function ($view) {
            $current = request()->route('page');

            $view->with([
                'menu' => app(Menu::class)->for(request()->user()),
                'currentPageId' => $current instanceof Page ? $current->id : null,
            ]);
        });
    }
}
