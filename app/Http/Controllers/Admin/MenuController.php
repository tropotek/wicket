<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MenuItemType;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin menu editor. HTMX writes get the #menu-editor partial back (422 with errors on a bad
 * request); plain form posts redirect back.
 */
class MenuController extends Controller
{
    public function index(): View
    {
        return view('admin.menu.index', $this->editorData());
    }

    /** Append a page link, dropdown or divider to the top level. */
    public function store(Request $request): Response|RedirectResponse
    {
        $type = MenuItemType::tryFrom((string) $request->input('type'));

        $validator = Validator::make($request->all(), [
            'type' => ['required', Rule::enum(MenuItemType::class)],
            'page_id' => [Rule::requiredIf($type === MenuItemType::Item), 'nullable', 'integer', Rule::exists(Page::class, 'id')->withoutTrashed()],
            'name' => [Rule::requiredIf($type === MenuItemType::Dropdown), 'nullable', 'string', 'max:255'],
        ], [
            'page_id.required' => __('Choose a page.'),
            'page_id.exists' => __('That page no longer exists.'),
            'name.required' => __('A dropdown needs a name.'),
        ]);

        if ($validator->fails()) {
            return $this->invalid($request, $validator->errors());
        }

        $data = $validator->validated();
        $page = $type === MenuItemType::Item ? Page::find($data['page_id']) : null;

        MenuItem::create([
            'type' => $type,
            'page_id' => $page?->id,
            'name' => $type === MenuItemType::Divider ? '' : (trim((string) ($data['name'] ?? '')) ?: $page->title),
            'sort' => (int) MenuItem::whereNull('parent_id')->max('sort') + 1,
        ]);

        return $this->done($request, __('Menu item added.'));
    }

    /**
     * Save the whole tree: order[] lists every id in display order, parent[id] its dropdown (or blank).
     */
    public function order(Request $request): Response|RedirectResponse
    {
        $order = array_map('intval', (array) $request->input('order', []));
        $parents = array_map(fn ($p) => $p === null || $p === '' ? null : (int) $p, (array) $request->input('parent', []));
        $items = MenuItem::all()->keyBy('id');

        $error = match (true) {
            count($order) !== count(array_unique($order)) || collect($order)->sort()->values()->all() !== $items->keys()->sort()->values()->all() => __('The menu changed in another window. It has been reloaded.'),
            collect($order)->contains(fn (int $id) => ! $this->validParent($items, $parents, $id)) => __('Only top-level dropdowns can hold items.'),
            default => null,
        };

        if ($error) {
            return $this->invalid($request, new MessageBag(['order' => $error]));
        }

        DB::transaction(function () use ($order, $parents, $items) {
            $positions = [];
            foreach ($order as $id) {
                $parent = $parents[$id] ?? null;
                $sort = $positions[$parent ?? 0] = ($positions[$parent ?? 0] ?? 0) + 1;
                $items[$id]->update(['parent_id' => $parent, 'sort' => $sort]);
            }
        });

        return $this->done($request, __('Menu saved.'));
    }

    public function update(Request $request, MenuItem $item): Response|RedirectResponse
    {
        $validator = Validator::make($request->all(), ['name' => ['required', 'string', 'max:255']]);

        if ($validator->fails()) {
            return $this->invalid($request, $validator->errors());
        }

        $item->update(['name' => trim($validator->validated()['name'])]);

        return $this->done($request, __('Menu item renamed.'));
    }

    /** Deleting a dropdown deletes its items (FK cascade). */
    public function destroy(Request $request, MenuItem $item): Response|RedirectResponse
    {
        $item->delete();

        return $this->done($request, __('Menu item deleted.'));
    }

    /**
     * Top level, or a non-dropdown inside a top-level dropdown.
     *
     * @param  Collection<int, MenuItem>  $items
     * @param  array<int, ?int>  $parents
     */
    private function validParent(Collection $items, array $parents, int $id): bool
    {
        $parentId = $parents[$id] ?? null;

        if ($parentId === null) {
            return true;
        }

        return $items[$id]->type !== MenuItemType::Dropdown
            && $items->get($parentId)?->type === MenuItemType::Dropdown
            && ($parents[$parentId] ?? null) === null;
    }

    private function done(Request $request, string $status): Response|RedirectResponse
    {
        if ($request->header('HX-Request')) {
            return response()->view('admin.menu._editor', $this->editorData() + ['status' => $status]);
        }

        return back()->with('status', $status);
    }

    private function invalid(Request $request, MessageBag $errors): Response
    {
        if ($request->header('HX-Request')) {
            return response()->view('admin.menu._editor', $this->editorData() + [
                'errors' => $errors,
                'input' => $request->only(['type', 'page_id', 'name']),
            ], 422);
        }

        throw ValidationException::withMessages($errors->toArray());
    }

    /** @return array<string, mixed> */
    private function editorData(): array
    {
        $items = MenuItem::with(['page' => fn ($q) => $q->withTrashed()])->orderBy('sort')->orderBy('id')->get();
        $byParent = $items->groupBy('parent_id');

        return [
            'items' => $byParent[''] ?? collect(),
            'children' => $byParent,
            'pages' => Page::orderBy('title')->get(['id', 'title', 'slug']),
        ];
    }
}
