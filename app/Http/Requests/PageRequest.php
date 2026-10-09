<?php

namespace App\Http\Requests;

use App\Enums\Visibility;
use App\Models\Page;
use App\Support\ReservedSlugs;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page ? Gate::allows('update', $page) : Gate::allows('create', Page::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $page = $this->route('page');

        // An unchanged slug is always fine (imported pages may predate the format).
        if ($page && $this->input('slug') === $page->slug) {
            $slugRules = ['required'];
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => $slugRules ?? [
                'bail', 'nullable', 'string', 'max:255', 'regex:'.self::SLUG_PATTERN,
                function (string $attribute, mixed $value, Closure $fail) {
                    if (app(ReservedSlugs::class)->contains($value)) {
                        $fail(__('":slug" is used by the site itself. Pick another.', ['slug' => $value]));
                    }
                },
                // Runs before the generic unique rule so the trash case gets a useful message.
                function (string $attribute, mixed $value, Closure $fail) {
                    if (Page::onlyTrashed()->where('slug', $value)->exists()) {
                        $fail(__('A page in the trash uses this slug. Restore or permanently delete it first.'));
                    }
                },
                Rule::unique(Page::class)->ignore($this->route('page')?->id),
            ],
            'category' => ['nullable', 'string', 'max:128'],
            'visibility' => ['required', Rule::enum(Visibility::class)],
            'publish' => ['boolean'],
            'title_visible' => ['boolean'],
            'html' => ['nullable', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'keywords' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => __('Use lowercase letters and numbers, separated by single dashes.'),
        ];
    }
}
