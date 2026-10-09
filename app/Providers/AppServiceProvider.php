<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
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
    }
}
