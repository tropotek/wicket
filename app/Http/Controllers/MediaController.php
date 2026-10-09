<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\MediaStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MediaController extends Controller
{
    /** TinyMCE image upload: JSON with the image's `location`. Anyone who can create pages may upload. */
    public function upload(Request $request, MediaStore $media): JsonResponse
    {
        Gate::authorize('create', Page::class);

        $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:'.config('wicket.upload_max_kb')],
        ]);

        return response()->json($media->storeImage($request->file('file')));
    }
}
