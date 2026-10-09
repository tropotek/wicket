<?php

namespace App\Http\Middleware;

use App\Facades\Settings;
use App\Support\Settings as SettingsStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts admin settings into effect for this request: config-backed settings override config,
 * and maintenance mode turns everyone but admins away.
 */
class ApplySettings
{
    /** Routes that stay open during maintenance so admins can still sign in. */
    private const OPEN_ROUTES = ['login', 'logout', 'password.*', 'health'];

    public function handle(Request $request, Closure $next): Response
    {
        $settings = Settings::all();

        foreach (SettingsStore::CONFIG as $key => $configKey) {
            if ($settings[$key] !== null && $settings[$key] !== '') {
                config([$configKey => $settings[$key]]);
            }
        }

        if ($settings['maintenance.enabled'] && ! $request->user()?->isAdmin() && ! $request->routeIs(...self::OPEN_ROUTES)) {
            return response()->view('maintenance', ['message' => $settings['maintenance.message']], 503);
        }

        return $next($request);
    }
}
