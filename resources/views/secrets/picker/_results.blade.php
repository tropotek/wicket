<div id="secret-picker" hx-target="#secret-picker" hx-swap="outerHTML" x-data>
    @if ($secrets->isEmpty())
        <p class="text-body-secondary">{{ $q === '' ? __('There are no secrets yet.') : __('No secrets match ":q".', ['q' => $q]) }}</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-2">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th class="d-none d-sm-table-cell">{{ __('Username') }}</th>
                        <th>{{ __('Visibility') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($secrets as $secret)
                        <tr>
                            <td>
                                {{ $secret->name }}
                                @if ($secret->otp)
                                    <span class="badge text-bg-info ms-1">2FA</span>
                                @endif
                            </td>
                            <td class="d-none d-sm-table-cell text-body-secondary">{{ $secret->username }}</td>
                            <td><span class="badge text-bg-secondary">{{ $secret->visibility->label() }}</span></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-primary"
                                        @click="window.parent.postMessage({ mceAction: 'wicketSecretPick', id: {{ $secret->id }}, name: @js($secret->name) }, window.location.origin)">
                                    {{ __('Insert') }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div hx-boost="true">{{ $secrets->links() }}</div>
    @endif
</div>
