<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Visibility;
use App\Facades\Settings;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Settings as SettingsStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'settings' => Settings::all(),
            'pages' => Page::where('visibility', Visibility::Public)->orderBy('title')->get(['id', 'title', 'slug']),
        ]);
    }

    public function update(Request $request, SettingsStore $store): RedirectResponse
    {
        $definitions = $store->definitions();

        // Form fields can't hold dots; the form uses "site__name" for "site.name".
        $input = collect($definitions)->keys()
            ->mapWithKeys(fn (string $key) => [$key => $request->input(str_replace('.', '__', $key))])
            ->all();

        // Rule keys like "site.name" address nested data, so validate the un-dotted shape.
        $validator = validator(Arr::undot($input), collect($definitions)->map->rules->all());
        $validator->setAttributeNames(collect($definitions)->keys()->mapWithKeys(fn ($k) => [$k => str_replace(['.', '_'], ' ', $k)])->all());

        if ($validator->fails()) {
            return back()->withInput()->withErrors(collect($validator->errors()->messages())
                ->mapWithKeys(fn ($messages, $key) => [str_replace('.', '__', $key) => $messages])->all());
        }

        Settings::set(Arr::dot($validator->validated()));

        return redirect()->route('admin.settings.edit')->with('status', __('Settings saved.'));
    }
}
