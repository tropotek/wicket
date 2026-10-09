<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Full page, or just the table when HTMX targets #user-table (search, paging).
     */
    public function index(Request $request): View
    {
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
        return view('admin.users.form', ['user' => new User, 'roles' => Role::cases()]);
    }

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
        return view('admin.users.form', ['user' => $user, 'roles' => Role::cases()]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
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

        return redirect()->route('admin.users.index')
            ->with('status', __('Saved :username.', ['username' => $user->username]));
    }
}
