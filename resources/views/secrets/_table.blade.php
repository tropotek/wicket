{{-- Paging links are boosted into #secret-table. Values other than the username never appear here. --}}
<div id="secret-table" hx-boost="true" hx-target="#secret-table" hx-swap="outerHTML" hx-push-url="true">
    @if ($secrets->isEmpty())
        <p class="text-body-secondary">{{ __('No secrets found.') }}</p>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th class="d-none d-md-table-cell">{{ __('Username') }}</th>
                        <th>{{ __('Visibility') }}</th>
                        <th class="d-none d-lg-table-cell">{{ __('Author') }}</th>
                        <th class="d-none d-lg-table-cell">{{ __('Updated') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($secrets as $secret)
                        <tr>
                            <td>
                                @can('update', $secret)
                                    <a href="{{ route('secrets.edit', $secret) }}" hx-boost="false">{{ $secret->name }}</a>
                                @else
                                    {{ $secret->name }}
                                @endcan
                                @if ($secret->otp)
                                    <span class="badge text-bg-info ms-1" title="{{ __('Has a 2FA key') }}">2FA</span>
                                @endif
                                @unless ($secret->publish)
                                    <span class="badge text-bg-warning ms-1">{{ __('Unpublished') }}</span>
                                @endunless
                            </td>
                            <td class="d-none d-md-table-cell text-body-secondary">{{ $secret->username }}</td>
                            <td><span class="badge text-bg-secondary">{{ $secret->visibility->label() }}</span></td>
                            <td class="d-none d-lg-table-cell">{{ $secret->author?->name ?? '—' }}</td>
                            <td class="d-none d-lg-table-cell text-body-secondary">{{ $secret->updated_at?->diffForHumans() }}</td>
                            <td class="text-end">
                                @can('delete', $secret)
                                    <form method="POST" action="{{ route('secrets.destroy', $secret) }}" hx-boost="false"
                                          onsubmit="return confirm(@js(__('Delete secret ":name"? Pages showing it will lose it.', ['name' => $secret->name])))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete :name', ['name' => $secret->name]) }}">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $secrets->links() }}
    @endif
</div>
