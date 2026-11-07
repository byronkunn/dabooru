<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
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
            'tags' => ['nullable', 'string', 'max:500'],
            'rating' => ['required', 'in:sfw,suggestive,explicit'],
            'draft' => ['sometimes', 'boolean'],
            'is_original' => ['nullable', 'boolean'],
            'artist_name' => ['nullable', 'string', 'max:120'],
            'source_url' => ['nullable', 'url', 'max:500'],
        ]);

        $type = $request->input('type');

        $request->validate([
            'files' => $type === 'album' ? self::RULES['album'] : self::RULES[$type],
            'files.*' => $type === 'album' ? self::RULES['album.*'] : null,
        ]);

        $files = $type === 'album' ? $request->file('files') : [$request->file('files')];

        $tags = Tag::parse($request->input('tags'));
        $isOriginal = $request->boolean('is_original', true);
        $artistName = $isOriginal ? null : $request->input('artist_name');
        $sourceUrl = $isOriginal ? null : $request->input('source_url');

        $draft = $request->boolean('draft');
        $post = DB::transaction(function () use ($request, $type, $files, $tags, $isOriginal, $artistName, $sourceUrl, $draft) {
            $post = Post::create([
                'user_id' => $request->user()->id,
                'type' => $type,
                'is_draft' => $draft,
                'draft_tags' => $draft ? $tags : null,
                'rating' => $request->input('rating'),
                'is_original' => $isOriginal,
                'artist_name' => $artistName,
                'source_url' => $sourceUrl,
                'title' => $request->input('title'),
                'caption' => $request->input('caption'),
            ]);

            if (! $draft) {
                Tag::syncFor($post, $tags);
            }

            foreach (array_values($files) as $i => $file) {
                $media = $this->storeMedia($request->user(), $file, $type, $draft);
                PostMedia::create(['post_id' => $post->id, 'media_id' => $media->id, 'position' => $i]);
            }

            return $post;
        });

        if ($draft) {
            return redirect()->route('studio.index')->with('success', 'Private draft saved.');
        }

        return redirect()
            ->route('posts.show', $post)
            ->with('success', 'Work published.');
    }

    private function storeMedia($user, $file, string $type, bool $draft): Media
    {
        $disk = $draft ? 'local' : config('filesystems.upload_disk', 'public');
        $directory = ($draft ? 'drafts/' : 'uploads/').$user->id;

        $isImage = $type !== 'video';

        // Re-encode images so EXIF / GPS never reaches storage.
        $path = $isImage
            ? ImageProcessor::storeClean($file->getRealPath(), $directory, $disk)
            : $file->store($directory, $disk);
        $thumbPath = $isImage
            ? ImageProcessor::storeThumbnail($file->getRealPath(), $directory.'/thumbs', $disk)
            : null;

        [$width, $height] = ImageProcessor::dimensions($file->getRealPath());

        return Media::create([
            'user_id' => $user->id,
            'disk' => $disk,
            'path' => $path,
            'thumb_path' => $thumbPath,
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
