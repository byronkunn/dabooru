<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Tag extends Model
{
    public const CATEGORIES = [
        'genre', 'characters', 'franchise', 'anatomies', 'poses', 'activity',
        'substance', 'settings', 'language', 'entity', 'artist',
    ];

    protected $guarded = [];

    protected $attributes = [
        'category' => 'entity', 'wiki_rating' => 'sfw', 'artist_status' => 'unverified',
        'version' => 0, 'locked' => false,
    ];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (Tag $tag): void {
            if (! in_array($tag->category, self::CATEGORIES, true)) {
                $tag->category = \App\Support\TagCategories::forName($tag->name);
            }
        });
    }

    protected function casts(): array
    {
        return ['locked' => 'boolean', 'version' => 'integer', 'is_approved' => 'boolean'];
    }

    public static function canonicalName(string $name): string
    {
        $name = Str::lower(Str::squish($name));
        $tag = static::where('name', $name)->first();
        if (! $tag) {
            $id = DB::table('tag_aliases')->where('name', $name)->value('tag_id');
            $tag = $id ? static::find($id) : null;
        }
        $seen = [];
        while ($tag?->merged_into_id) {
            if (in_array($tag->id, $seen, true)) {
                throw ValidationException::withMessages(['tags' => 'A circular tag redirect needs moderator attention.']);
            }
            $seen[] = $tag->id;
            $tag = static::findOrFail($tag->merged_into_id);
        }

        return $tag?->name ?? $name;
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    public static function parse(?string $input, int $limit = 30): array
    {
        $names = collect(explode(',', $input ?? ''))
            ->map(fn ($name) => Str::lower(Str::squish($name)))
            ->filter(fn ($name) => $name !== '')
            ->unique()
            ->values();

        if ($names->count() > $limit || $names->contains(fn ($name) => Str::length($name) > 40)) {
            throw ValidationException::withMessages(['tags' => "Enter up to {$limit} tags of 40 characters each."]);
        }

        return $names->all();
    }

    public static function expandedNames(array $names): array
    {
        $names = array_values(array_unique(array_map(static::canonicalName(...), $names)));
        $ids = static::query()->whereIn('name', $names)->pluck('id')->all();
        $seen = $ids;
        $frontier = $ids;
        while ($frontier !== []) {
            $next = DB::table('tag_relations')
                ->where('kind', 'implies')->whereIn('tag_id', $frontier)
                ->pluck('related_tag_id')->all();
            $frontier = array_values(array_diff($next, $seen));
            $seen = array_values(array_unique(array_merge($seen, $frontier)));
        }
        $names = array_values(array_unique(array_merge($names, array_map(static::canonicalName(...), static::whereIn('id', $seen)->pluck('name')->all()))));
        sort($names);
        if (count($names) > 50) {
            throw ValidationException::withMessages(['tags' => 'Tag implications exceed the 50-tag safety limit.']);
        }

        return $names;
    }

    public static function syncFor(Post $post, array $names, bool $audit = true, bool $expand = true): void
    {
        DB::transaction(function () use ($post, $names, $audit, $expand) {
            $locked = Post::withoutGlobalScope('published')->withTrashed()->lockForUpdate()->findOrFail($post->id);
            $names = $expand ? static::expandedNames($names) : $names;
            $before = $locked->tags()->pluck('name')->all();
            $version = $locked->tag_version;
            if ($names !== []) {
                static::query()->insertOrIgnore(array_map(fn ($name) => [
                    'name' => $name, 'category' => \App\Support\TagCategories::forName($name),
                    'is_approved' => false,
                ], $names));
            }
            $locked->tags()->sync(static::whereIn('name', $names)->pluck('id')->all());
            $after = $locked->tags()->pluck('name')->all();
            if ($before !== $after) {
                $locked->increment('tag_version');
                if ($audit && Auth::user()) {
                    KnowledgeRevision::create([
                        'target_type' => 'post', 'target_id' => $locked->id,
                        'user_id' => Auth::id(), 'reviewer_id' => Auth::id(),
                        'before' => ['tags' => $before], 'after' => ['tags' => $after],
                        'base_version' => $version, 'summary' => 'Uploader updated work tags.',
                        'status' => 'accepted',
                    ]);
                }
            }
            $post->tag_version = $locked->tag_version;
        });
    }
}
