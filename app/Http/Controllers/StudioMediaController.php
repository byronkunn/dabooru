<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\ComicChapter;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudioMediaController extends Controller
{
    public function show(Request $request, Media $media, string $variant)
    {
        $path = $variant === 'thumb' ? $media->thumb_path : $media->path;
        abort_unless($media->disk === 'local' && $path && str_starts_with($path, 'drafts/'), 404);
        $viewer = $request->user();
        $ownsMedia = $viewer && $viewer->id === $media->user_id && ! $viewer->isBanned();
        $posts = Post::whereHas('media', fn ($query) => $query->where('media_id', $media->id));
        $chapters = ComicChapter::whereHas('pages', fn ($query) => $query->where('media_id', $media->id));

        $privateAccess = $ownsMedia && ((clone $posts)->withoutGlobalScope('published')->where('is_draft', true)->where('user_id', $viewer->id)->exists()
            || (clone $chapters)->whereNull('published_at')->whereHas('comic', fn ($query) => $query->where('user_id', $viewer->id))->exists());

        $publicAccess = ! $privateAccess && ($posts->visibleTo($viewer)->visibleRating($ownsMedia || (bool) $viewer?->show_explicit)->exists()
            || $chapters->published()->visibleAfterModeration()
                ->when(! $ownsMedia && ! $viewer?->show_explicit, fn ($query) => $query->where('rating', '!=', 'explicit'))
                ->whereHas('comic', fn ($query) => $query->visibleRating($ownsMedia || (bool) $viewer?->show_explicit)
                    ->when($viewer, fn ($query) => $query->whereNotIn('user_id', Block::counterpartIds($viewer->id))))
                ->exists());

        abort_unless(($privateAccess || $publicAccess) && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
