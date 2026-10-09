<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `role:editor` lets through editors and anything ranked above (admins).
 * Put `auth` before it so logged-out visitors are sent to login, not 403'd.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless($request->user()?->hasRole(Role::from($role)), 403);

        return $next($request);
    }
}
