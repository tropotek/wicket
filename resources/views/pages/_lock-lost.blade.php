<div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <div>
        {{ __('Your edit lock expired and :name is now editing this page. Saving will be refused — copy your changes somewhere safe.', [
            'name' => $lock?->user?->name ?? __('someone else'),
        ]) }}
    </div>
</div>
