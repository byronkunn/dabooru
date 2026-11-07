<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\ComicPage;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostMedia;
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
        ]);

        $comic = Comic::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title']),
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
            'pages' => ['required', 'array', 'min:1', 'max:60'],
            'pages.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:15360'],
            'publish' => ['boolean'],
        ]);

        $chapter = DB::transaction(function () use ($request, $comic) {
            $chapter = ComicChapter::create([
                'comic_id' => $comic->id,
                'number' => $request->integer('number'),
                'title' => $request->input('title'),
                'rating' => $request->input('rating'),
                'published_at' => $request->boolean('publish', true) ? now() : null,
            ]);

            foreach (array_values($request->file('pages')) as $i => $file) {
                $disk = config('filesystems.upload_disk', 'public');
                // strip EXIF/GPS from every comic page before it is stored
                $path = ImageProcessor::storeClean($file->getRealPath(), "uploads/{$request->user()->id}/comics", $disk);
                [$w, $h] = ImageProcessor::dimensions($file->getRealPath());

                $media = Media::create([
                    'user_id' => $request->user()->id,
                    'disk' => config('filesystems.upload_disk', 'public'),
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

            $comic->forceFill(['last_chapter_at' => now()])->save();

            // every published chapter gets exactly one feed post
            if ($chapter->published_at) {
                $post = Post::create([
                    'user_id' => $request->user()->id,
                    'type' => 'comic',
                    'rating' => $chapter->rating,
                    'title' => $comic->title.' · Ch. '.$chapter->number,
                    'caption' => $request->input('title'),
                    'comic_chapter_id' => $chapter->id,
                ]);
                $cover = $chapter->pages()->with('media')->first()?->media;
                if ($cover) {
                    PostMedia::create(['post_id' => $post->id, 'media_id' => $cover->id, 'position' => 0]);
                }
            }

            return $chapter;
        });

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
