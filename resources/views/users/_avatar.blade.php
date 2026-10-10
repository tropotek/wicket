{{-- A user's photo, or their initials in a circle. Optional $class adds a size modifier (wk-avatar-lg). --}}
@if ($url = $user->avatarUrl())
    <img src="{{ $url }}" class="wk-avatar{{ isset($class) ? ' '.$class : '' }}" alt="">
@else
    <span class="wk-avatar{{ isset($class) ? ' '.$class : '' }}" aria-hidden="true">{{ $user->initials() }}</span>
@endif
