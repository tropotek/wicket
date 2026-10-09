<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\MediaStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Intervention\Image\Interfaces\ExceptionInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The media browser over data/media, plus TinyMCE's image upload. HTMX writes get the
 * #media-browser partial back (422 with errors); plain form posts redirect back.
 */
class MediaController extends Controller
{
    public function __construct(private MediaStore $media) {}

    /** TinyMCE image upload: JSON with the image's `location`. Anyone who can create pages may upload. */
    public function upload(Request $request): JsonResponse
    {
        Gate::authorize('create', Page::class);

        $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:'.config('wicket.upload_max_kb')],
        ]);

        return response()->json($this->media->storeImage($request->file('file')));
    }

    /** Full page, or the #media-browser partial for HTMX folder navigation. */
    public function index(Request $request): View
    {
        Gate::authorize('media');

        $dir = $this->directory($request->query('dir'));
        $view = $request->header('HX-Target') === 'media-browser' ? 'media._browser' : 'media.index';

        return view($view, $this->browserData($request, $dir));
    }

    public function files(Request $request): Response|RedirectResponse
    {
        Gate::authorize('media');

        $dir = $this->directory($request->input('dir'));
        $files = (array) $request->file('files', []);
        $validator = Validator::make($request->all(), [
            'files' => ['required', 'array'],
            'files.*' => ['file', 'extensions:'.implode(',', config('wicket.media_extensions')), 'max:'.config('wicket.upload_max_kb')],
        ], [
            'files.required' => __('Choose files to upload (the total may be over the upload limit).'),
        ], collect($files)->mapWithKeys(fn ($f, $i) => ["files.$i" => $f->getClientOriginalName()])->all());

        if ($validator->fails()) {
            return $this->invalid($request, $dir, $validator->errors());
        }

        try {
            foreach ($files as $file) {
                $this->media->store($file, $dir);
            }
        } catch (ValidationException $e) {
            return $this->invalid($request, $dir, new MessageBag($e->errors()));
        }

        return $this->done($request, $dir, trans_choice(':count file uploaded.|:count files uploaded.', count($files)));
    }

    public function folder(Request $request): Response|RedirectResponse
    {
        Gate::authorize('media');

        $dir = $this->directory($request->input('dir'));

        return $this->attempt($request, $dir, ['name' => ['required', 'string', 'max:100']], function (array $data) use ($dir) {
            $this->media->makeFolder($dir, $data['name']);

            return __('Folder created.');
        });
    }

    public function rename(Request $request): Response|RedirectResponse
    {
        Gate::authorize('media');

        $path = $this->existing($request->input('path'));
        $dir = $this->parentOf($path);

        return $this->attempt($request, $dir, ['name' => ['required', 'string', 'max:100']], function (array $data) use ($path) {
            $this->media->rename($path, $data['name']);

            return __('Renamed.');
        });
    }

    public function destroy(Request $request): Response|RedirectResponse
    {
        Gate::authorize('media');

        $path = $this->existing($request->input('path'));
        $this->media->delete($path);

        return $this->done($request, $this->parentOf($path), __(':name deleted.', ['name' => basename($path)]));
    }

    /** An image's thumbnail, made on first request when missing. */
    public function thumb(Request $request): BinaryFileResponse
    {
        Gate::authorize('media');

        $path = $this->existing($request->query('path'));
        abort_unless($this->media->isFile($path) && $this->media->isImage($path), 404);

        try {
            $file = $this->media->thumbnailFile($path);
        } catch (ExceptionInterface) {
            abort(404);
        }

        return response()->file($file, ['Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * Validate, run $action (which returns the status message), and turn its ValidationException
     * into the usual error response.
     */
    private function attempt(Request $request, string $dir, array $rules, \Closure $action): Response|RedirectResponse
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->invalid($request, $dir, $validator->errors());
        }

        try {
            return $this->done($request, $dir, $action($validator->validated()));
        } catch (ValidationException $e) {
            return $this->invalid($request, $dir, new MessageBag($e->errors()));
        }
    }

    private function directory(?string $dir): string
    {
        $dir = $this->media->safePath($dir);
        abort_unless($this->media->isDirectory($dir), 404);

        return $dir;
    }

    /** A file or folder other than the root. */
    private function existing(?string $path): string
    {
        $path = $this->media->safePath($path);
        abort_if($path === '' || ! ($this->media->isFile($path) || $this->media->isDirectory($path)), 404);

        return $path;
    }

    private function parentOf(string $path): string
    {
        return dirname($path) === '.' ? '' : dirname($path);
    }

    private function done(Request $request, string $dir, string $status): Response|RedirectResponse
    {
        if ($request->header('HX-Request')) {
            return response()->view('media._browser', $this->browserData($request, $dir) + ['status' => $status]);
        }

        return redirect()->route('media.index', array_filter(['dir' => $dir, 'picker' => $this->picker($request)]))->with('status', $status);
    }

    private function invalid(Request $request, string $dir, MessageBag $errors): Response
    {
        if ($request->header('HX-Request')) {
            return response()->view('media._browser', $this->browserData($request, $dir) + ['errors' => $errors], 422);
        }

        throw ValidationException::withMessages($errors->toArray());
    }

    /** image | file when the browser runs as TinyMCE's picker. */
    private function picker(Request $request): ?string
    {
        $picker = $request->input('picker');

        return in_array($picker, ['image', 'file'], true) ? $picker : null;
    }

    /** @return array<string, mixed> */
    private function browserData(Request $request, string $dir): array
    {
        $crumbs = [];
        $walk = '';
        foreach ($dir === '' ? [] : explode('/', $dir) as $segment) {
            $walk = ltrim($walk.'/'.$segment, '/');
            $crumbs[] = ['name' => $segment, 'path' => $walk];
        }

        return [
            'dir' => $dir,
            'crumbs' => $crumbs,
            'entries' => $this->media->list($dir),
            'picker' => $this->picker($request),
            'extensions' => config('wicket.media_extensions'),
            'counts' => fn (string $path) => $this->media->countFiles($path),
        ];
    }
}
