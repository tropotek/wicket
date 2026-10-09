<?php

namespace App\Providers;

use App\Html\Pipeline;
use App\Html\Sanitizer;
use App\Models\Page;
use App\Models\User;
use App\Support\Menu;
use App\Support\Settings;
use App\Support\SiteHosts;
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

        $this->app->singleton(Settings::class);
        $this->app->singleton(Sanitizer::class, fn ($app) => new Sanitizer($app['config']->get('wicket.embed_hosts', []), $app->make(SiteHosts::class)));
        $this->app->bind(Pipeline::class, fn ($app) => new Pipeline($app, $app['config']->get('wicket.html_passes', [])));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->isAdmin());
        // The media browser: whoever can write pages can manage the files they use.
        Gate::define('media', fn (User $user) => $user->can('create', Page::class));

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
