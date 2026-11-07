<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\ComicPage;
use App\Models\Media;
use App\Models\Tag;
use App\Support\ChapterPublishing;
use App\Support\ImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ComicChapterController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Comics/Upload', [
            'comics' => $request->user()->comics()->withCount('chapters')->latest()->get()
                ->map(fn ($c) => ['id' => $c->id, 'title' => $c->title, 'slug' => $c->slug, 'chapters' => $c->chapters_count]),
        ]);
    }

    public function storeSeries(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:2000'],
            'rating' => ['required', 'in:sfw,suggestive,explicit'],
            'status' => ['required', 'in:ongoing,completed,hiatus'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:15360'],
        ]);

        $coverMediaId = null;
        if ($request->hasFile('cover')) {
            $coverFile = $request->file('cover');
            $disk = config('filesystems.upload_disk', 'public');
            $user = $request->user();
            $path = ImageProcessor::storeClean($coverFile->getRealPath(), "uploads/{$user->id}/comics", $disk);
            $thumbPath = ImageProcessor::storeThumbnail($coverFile->getRealPath(), "uploads/{$user->id}/comics/thumbs", $disk);
            [$width, $height] = ImageProcessor::dimensions($coverFile->getRealPath());

            $coverMedia = Media::create([
                'user_id' => $user->id,
                'disk' => $disk,
                'path' => $path,
                'thumb_path' => $thumbPath,
                'kind' => 'image',
                'width' => $width,
                'height' => $height,
                'size_bytes' => $coverFile->getSize(),
                'status' => 'ready',
                'mime_type' => $coverFile->getMimeType(),
            ]);

            $coverMediaId = $coverMedia->id;
        }

        $comic = Comic::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title']),
            'cover_media_id' => $coverMediaId,
            'description' => $data['description'] ?? null,
            'rating' => $data['rating'],
            'status' => $data['status'],
        ]);

        return redirect()->route('comics.chapters.create', ['comic' => $comic->slug])
            ->with('success', 'Series created — add your first chapter.');
    }

    public function storeChapter(Request $request, Comic $comic): RedirectResponse
    {
        abort_unless($comic->user_id === $request->user()->id, 403);

        $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:100000'],
            'title' => ['nullable', 'string', 'max:160'],
            'rating' => ['required', 'in:sfw,suggestive,explicit'],
            'tags' => ['nullable', 'string', 'max:500'],
            'pages' => ['required', 'array', 'min:1', 'max:60'],
            'pages.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:15360'],
            'publish' => ['boolean'],
        ]);

        $tags = Tag::parse($request->input('tags'));

        $publish = $request->boolean('publish', true);
        DB::transaction(function () use ($request, $comic, $tags, $publish) {
            $comic = Comic::lockForUpdate()->findOrFail($comic->id);
            $chapter = ComicChapter::create([
                'comic_id' => $comic->id,
                'number' => $request->integer('number'),
                'title' => $request->input('title'),
                'rating' => $request->input('rating'),
                'draft_tags' => $tags,
            ]);

            $disk = $publish ? config('filesystems.upload_disk', 'public') : 'local';
            $directory = ($publish ? 'uploads/' : 'drafts/').$request->user()->id.'/comics';
            foreach (array_values($request->file('pages')) as $i => $file) {
                // strip EXIF/GPS from every comic page before it is stored
                $path = ImageProcessor::storeClean($file->getRealPath(), $directory, $disk);
                [$w, $h] = ImageProcessor::dimensions($file->getRealPath());

                $media = Media::create([
                    'user_id' => $request->user()->id,
                    'disk' => $disk,
                    'path' => $path,
                    'kind' => 'image',
                    'width' => $w,
                    'height' => $h,
                    'size_bytes' => $file->getSize(),
                    'status' => 'ready',
                    'mime_type' => $file->getMimeType(),
                ]);

                ComicPage::create(['comic_chapter_id' => $chapter->id, 'media_id' => $media->id, 'position' => $i + 1]);
            }

            if ($publish) {
                ChapterPublishing::publish($comic, $chapter, $tags);
            }
        });

        if (! $publish) {
            return redirect()->route('studio.index')->with('success', 'Private chapter draft saved.');
        }

        return redirect()->route('comics.show', $comic->slug)
            ->with('success', 'Chapter published.');
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'series';
        $slug = $base;
        $i = 2;

        while (Comic::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
