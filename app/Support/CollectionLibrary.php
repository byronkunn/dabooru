<?php

namespace App\Support;

use App\Http\Resources\PostResource;
use App\Models\Bookmark;
use App\Models\Post;
use App\Models\SavedCollection;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CollectionLibrary
{
    /** The settings key holding a reader's muted tag names for the whole site. */
    public const MUTE_KEY = 'muted_tags';

    /**
     * Muted tags are stored on the account, not the session, so they follow the
     * reader across devices and apply to Gallery, Discover, Following and search
     * as well as Library. Signed-out visitors simply have none.
     */
    public static function mutedTags(Request $request): array
    {
        $viewer = $request->user();

        return $viewer ? self::mutedFor($viewer) : [];
    }

    public static function mutedFor(User $viewer): array
    {
        $raw = Setting::query()->where('user_id', $viewer->id)->where('key', self::MUTE_KEY)->value('value');

        return self::normaliseMutes($raw);
    }

    /** Replace the reader's muted tags with the given list. */
    public static function setMutedTags(Request $request, array|string|null $input): void
    {
        $viewer = $request->user();
        if (! $viewer) {
            return;
        }

        self::saveMutes($viewer, self::normaliseMutes($input));
    }

    public static function addMute(User $viewer, string $name): void
    {
        $name = self::normaliseMutes($name)[0] ?? null;
        if (! $name) {
            return;
        }

        self::saveMutes($viewer, array_values(array_unique([...self::mutedFor($viewer), $name])));
    }

    public static function removeMute(User $viewer, string $name): void
    {
        $name = self::normaliseMutes($name)[0] ?? null;

        self::saveMutes($viewer, array_values(array_filter(self::mutedFor($viewer), fn ($t) => $t !== $name)));
    }

    /** Accepts a comma string, an array, or a JSON array, and returns clean tag names. */
    public static function normaliseMutes(array|string|null $input): array
    {
        if (is_array($input)) {
            $parts = $input;
        } else {
            $text = trim((string) $input);
            if ($text !== '' && str_starts_with($text, '[')) {
                $decoded = json_decode($text, true);
                $parts = is_array($decoded) ? $decoded : [$text];
            } else {
                $parts = explode(',', $text);
            }
        }

        return collect($parts)
            ->filter(fn ($name) => is_string($name))
            ->map(fn ($name) => Tag::canonicalName(Str::squish(Str::lower($name))))
            ->filter(fn ($name) => $name !== '' && Str::length($name) <= 40)
            ->unique()
            ->take(60)
            ->values()
            ->all();
    }

    private static function saveMutes(User $viewer, array $names): void
    {
        Setting::query()->updateOrCreate(
            ['user_id' => $viewer->id, 'key' => self::MUTE_KEY],
            ['value' => json_encode(array_values($names))],
        );
    }

    public static function posts(int $ownerId, ?User $viewer, ?SavedCollection $collection, array $muted = []): Builder
    {
        $query = Post::query()->select('posts.*')
            ->visibleTo($viewer)->visibleRating((bool) $viewer?->show_explicit)->excludingTags($muted)
            ->join('bookmarks as saved', 'saved.post_id', '=', 'posts.id')->where('saved.user_id', $ownerId);
        $query->withExists(['bookmarks as viewer_saved' => fn ($saved) => $saved->where('user_id', $viewer?->id ?? 0)]);
        if ($collection) {
            $query->join('bookmark_saved_collection as membership', 'membership.bookmark_id', '=', 'saved.id')
                ->where('membership.saved_collection_id', $collection->id);
        }

        return $query;
    }

    public static function ordered(Builder $query, ?SavedCollection $collection, string $order): Builder
    {
        if ($collection && $order === 'custom') {
            return $query->orderBy('membership.position')->orderBy('saved.id');
        }

        return $collection
            ? $query->orderByRaw('COALESCE(membership.added_at, saved.created_at) DESC')
                ->orderByRaw('COALESCE(membership.added_order, 0) DESC')->orderByDesc('posts.id')
            : $query->orderByDesc('saved.created_at')->orderByRaw('COALESCE(saved.saved_order, saved.id) DESC');
    }

    public static function summary(int $ownerId, ?User $viewer, ?SavedCollection $collection, array $muted = []): array
    {
        $query = self::posts($ownerId, $viewer, $collection, $muted);
        $count = (clone $query)->count('posts.id');
        $cover = $collection?->cover_post_id ? (clone $query)->where('posts.id', $collection->cover_post_id)->first() : null;
        $cover ??= self::ordered($query, $collection, 'recent')->first();
        $coverData = $cover ? (new PostResource($cover->load(['user', 'media.media', 'chapter.comic'])))->resolve() : null;

        return [
            'id' => $collection?->id,
            'name' => $collection?->name ?? 'All Saved',
            'description' => $collection?->description,
            'count' => $count,
            'cover' => $coverData,
            'cover_post_id' => $cover?->id === $collection?->cover_post_id ? $cover?->id : null,
            'shared' => $collection?->share_token !== null && $collection !== null,
            'isPublic' => (bool) $collection?->is_public,
        ];
    }

    public static function attach(SavedCollection $collection, Bookmark $bookmark): void
    {
        $last = DB::table('bookmark_saved_collection')->where('saved_collection_id', $collection->id)
            ->selectRaw('MAX(position) AS position, MAX(added_order) AS added_order')->first();

        DB::table('bookmark_saved_collection')->insertOrIgnore([
            'bookmark_id' => $bookmark->id, 'saved_collection_id' => $collection->id,
            'position' => (int) $last->position + 1,
            'added_at' => now(), 'added_order' => (int) $last->added_order + 1,
        ]);
    }
}
