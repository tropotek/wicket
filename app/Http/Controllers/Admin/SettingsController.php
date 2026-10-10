<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Visibility;
use App\Facades\Settings;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\PublicData;
use App\Support\Settings as SettingsStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class SettingsController extends Controller
{
    private const LOGO_DIR = 'site';

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
        $input['theme.logo'] = Settings::get('theme.logo');

        // Rule keys like "site.name" address nested data, so validate the un-dotted shape.
        // No SVG: the logo is served as a static same-origin file, where an SVG could run script.
        $validator = validator(
            Arr::undot($input) + ['logo' => $request->file('logo')],
            collect($definitions)->map->rules->all() + ['logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:1024']],
        );
        $validator->setAttributeNames(collect($definitions)->keys()->mapWithKeys(fn ($k) => [$k => str_replace(['.', '_'], ' ', $k)])->all());

        if ($validator->fails()) {
            return back()->withInput()->withErrors(collect($validator->errors()->messages())
                ->mapWithKeys(fn ($messages, $key) => [str_replace('.', '__', $key) => $messages])->all());
        }

        $settings = Arr::dot(Arr::except($validator->validated(), 'logo'));
        if ($request->hasFile('logo') || $request->boolean('logo_remove')) {
            $settings['theme.logo'] = $this->replaceLogo($settings['theme.logo'], $request->file('logo'));
        }
        Settings::set($settings);

        return redirect()->route('admin.settings.edit')->with('status', __('Settings saved.'));
    }

    /**
     * Store a new logo (or none) in data/public/site/ and delete the old one.
     * The content hash in the name busts browser caches when the logo changes.
     */
    private function replaceLogo(?string $old, ?UploadedFile $file): string
    {
        $disk = app(PublicData::class)->disk();
        $new = '';

        if ($file) {
            $new = $disk->putFileAs(self::LOGO_DIR, $file, 'logo-'.substr(sha1_file($file->getRealPath()), 0, 12).'.'.$file->extension());
        }
        if ($old && $old !== $new && str_starts_with($old, self::LOGO_DIR.'/')) {
            $disk->delete($old);
        }

        return $new;
    }
}
