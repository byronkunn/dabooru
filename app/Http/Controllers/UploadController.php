<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Post;
use App\Models\PostMedia;
use App\Support\ImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UploadController extends Controller
{
    public const RULES = [
        'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:40960'],
        'gif' => ['required', 'file', 'mimes:gif,mp4,webm', 'max:40960'],
        'video' => ['required', 'file', 'mimes:mp4,mov,webm', 'max:512000'],
        'album' => ['required', 'array', 'min:2', 'max:20'],
        'album.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:40960'],
        'comic' => ['required', 'array', 'min:1', 'max:60'],
        'comic.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:15360'],
    ];

    public function create(Request $request): Response
    {
        return Inertia::render('Upload/Create', [
            'comics' => $request->user()->comics()->latest()->get(['id', 'title', 'slug']),
            'limits' => [
                'image_mb' => 40,
                'video_mb' => 500,
                'comic_page_mb' => 15,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'type' => ['required', 'in:image,gif,video,album'],
            'title' => ['nullable', 'string', 'max:160'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'rating' => ['required', 'in:sfw,suggestive,explicit'],
        ]);

        $type = $request->input('type');

        $request->validate([
            'files' => $type === 'album' ? self::RULES['album'] : self::RULES[$type],
            'files.*' => $type === 'album' ? self::RULES['album.*'] : null,
        ]);

        $files = $type === 'album' ? $request->file('files') : [$request->file('files')];

        $post = DB::transaction(function () use ($request, $type, $files) {
            $post = Post::create([
                'user_id' => $request->user()->id,
                'type' => $type,
                'rating' => $request->input('rating'),
                'title' => $request->input('title'),
                'caption' => $request->input('caption'),
            ]);

            foreach (array_values($files) as $i => $file) {
                $media = $this->storeMedia($request->user(), $file, $type);
                PostMedia::create(['post_id' => $post->id, 'media_id' => $media->id, 'position' => $i]);
            }

            return $post;
        });

        return redirect()
            ->route('posts.show', $post)
            ->with('success', 'Work published.');
    }

    private function storeMedia($user, $file, string $type): Media
    {
        $disk = config('filesystems.upload_disk', 'public');

        $isImage = $type !== 'video';

        // Re-encode images so EXIF / GPS never reaches storage.
        $path = $isImage
            ? ImageProcessor::storeClean($file->getRealPath(), "uploads/{$user->id}", $disk)
            : $file->store("uploads/{$user->id}", $disk);

        [$width, $height] = ImageProcessor::dimensions($file->getRealPath());

        return Media::create([
            'user_id' => $user->id,
            'disk' => config('filesystems.upload_disk', 'public'),
            'path' => $path,
            'thumb_path' => null,
            'kind' => match ($type) {
                'video' => 'video',
                'gif' => $file->getClientOriginalExtension() === 'gif' ? 'gif' : 'video',
                default => 'image',
            },
            'width' => $width,
            'height' => $height,
            'size_bytes' => $file->getSize(),
            'status' => $type === 'video' ? 'processing' : 'ready',
            'mime_type' => $file->getMimeType(),
        ]);
    }
}
