{{-- One menu entry. $level 0 = navbar, 1 = inside a dropdown. --}}
@php($active = $item->page_id !== null && $item->page_id === $currentPageId)
@if ($item->type === App\Enums\MenuItemType::Dropdown)
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            {{ $item->name }}
        </a>
        <ul class="dropdown-menu">
            @foreach ($item->children as $child)
                @include('menu._item', ['item' => $child, 'level' => 1])
            @endforeach
        </ul>
    </li>
@elseif ($item->type === App\Enums\MenuItemType::Divider)
    <li><hr class="dropdown-divider"></li>
@else
    <li @class(['nav-item' => $level === 0])>
        <a @class(['nav-link' => $level === 0, 'dropdown-item' => $level > 0, 'active' => $active])
           href="{{ route('pages.show', $item->page) }}"
           @if ($active) aria-current="page" @endif>{{ $item->name }}</a>
    </li>
@endif
