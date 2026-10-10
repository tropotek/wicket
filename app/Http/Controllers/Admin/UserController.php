<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Support\Avatars;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Full page, or just the table when HTMX targets #user-table (search, paging).
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $q = trim((string) $request->query('q'));

        $users = User::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('username', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")))
            ->orderBy('username')
            ->paginate(25)
            ->withQueryString();

        $view = $request->header('HX-Target') === 'user-table' ? 'admin.users._table' : 'admin.users.index';

        return view($view, ['users' => $users, 'q' => $q]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.form', ['user' => new User, 'roles' => Role::cases()]);
    }

    /** Authorised by UserRequest. */
    public function store(UserRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->only(['username', 'name', 'email', 'password']));
        $user->forceFill([
            'role' => Role::from($request->validated('role')),
            'active' => $request->boolean('active'),
            'email_verified_at' => now(),
        ])->save();

        return redirect()->route('admin.users.index')
            ->with('status', __('Created :username.', ['username' => $user->username]));
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.form', ['user' => $user, 'roles' => Role::cases()]);
    }

    /** Authorised by UserRequest. */
    public function update(UserRequest $request, User $user, Avatars $avatars): RedirectResponse
    {
        $user->fill($request->safe()->only(['username', 'name', 'email']));
        $user->forceFill([
            'role' => Role::from($request->validated('role')),
            'active' => $request->boolean('active'),
        ]);

        if ($request->filled('password')) {
            $user->forceFill([
                'password' => $request->validated('password'),
                'remember_token' => Str::random(60),
            ]);
        }

        $user->save();

        // Admins can take a photo down, not upload one for someone else.
        if ($request->boolean('avatar_remove')) {
            $avatars->remove($user);
        }

        return redirect()->route('admin.users.index')
            ->with('status', __('Saved :username.', ['username' => $user->username]));
    }
}
