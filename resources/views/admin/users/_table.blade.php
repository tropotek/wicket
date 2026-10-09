<div id="user-table" hx-boost="true" hx-target="#user-table" hx-swap="outerHTML" hx-push-url="true">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>{{ __('Username') }}</th>
                    <th>{{ __('Name') }}</th>
                    <th class="d-none d-md-table-cell">{{ __('Email') }}</th>
                    <th>{{ __('Role') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="d-none d-lg-table-cell">{{ __('Last login') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="fw-semibold">{{ $user->username }}</td>
                        <td>{{ $user->name }}</td>
                        <td class="d-none d-md-table-cell">{{ $user->email }}</td>
                        <td>
                            <span @class([
                                'badge',
                                'text-bg-danger' => $user->role === \App\Enums\Role::Admin,
                                'text-bg-primary' => $user->role === \App\Enums\Role::Editor,
                                'text-bg-secondary' => $user->role === \App\Enums\Role::Member,
                                'text-bg-light border' => $user->role === \App\Enums\Role::Guest,
                            ])>{{ $user->role->label() }}</span>
                        </td>
                        <td>
                            @if ($user->active)
                                <span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>{{ __('Active') }}</span>
                            @else
                                <span class="text-body-secondary"><i class="fa-solid fa-circle-minus me-1"></i>{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="d-none d-lg-table-cell text-body-secondary small">
                            {{ $user->last_login_at?->diffForHumans() ?? __('never') }}
                        </td>
                        <td class="text-end">
                            {{-- Full navigation, not an HTMX table swap --}}
                            <a href="{{ route('admin.users.edit', $user) }}" hx-boost="false"
                               class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Edit :username', ['username' => $user->username]) }}">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-body-secondary py-4">{{ __('No users match.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</div>
