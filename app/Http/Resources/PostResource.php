<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Post */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $credited = $this->credited_artist_id ? $this->creditedArtist : null;
        $visibleTags = $this->relationLoaded('tags') ? $this->tags->where('is_approved', true) : collect();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'rating' => $this->rating,
            'rating_label' => \App\Support\Rating::label($this->rating),
            'title' => $this->title,
            'tags' => $visibleTags->pluck('name')->all(),
            'tagCategories' => $visibleTags->mapWithKeys(fn ($tag) => [$tag->name => $tag->category])->all(),
            'caption' => $this->caption,
            'ai_tool' => $this->ai_tool,
            'ai_tool_type' => $this->ai_tool_type,
            'is_original' => (bool) $this->is_original,
            'artist_name' => $this->artist_name,
            'source_url' => $this->source_url,
            'credited_artist' => $credited && ! $credited->isBanned() && $credited->email_verified_at
                ? $credited->only(['username', 'name']) : null,
            'external_artist' => ($this->artist_name || ! $this->is_original) ? [
                'name' => $this->artist_name ?: 'External Artist',
                'url' => $this->source_url,
                'page_url' => $this->artist_name ? route('artists.show', ['name' => $this->artist_name], false) : null,
            ] : null,
            'views' => $this->views,
            'likes' => $this->likes_count,
            'comments' => $this->comments_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans(short: true),
            'user' => ($this->relationLoaded('user') && $this->user) ? (new UserResource($this->user))->resolve() : null,
            'media' => MediaResource::collection(
                $this->relationLoaded('media') ? $this->media->pluck('media')->filter() : collect()
            )->resolve(),
            'chapter' => $this->chapter ? [
                'id' => $this->chapter->id,
                'number' => $this->chapter->number,
                'title' => $this->chapter->title,
                'pages' => $this->chapter->pages()->count(),
                'comic' => [
                    'id' => $this->chapter->comic->id,
                    'slug' => $this->chapter->comic->slug,
                    'title' => $this->chapter->comic->title,
                ],
                'url' => route('comics.read', [$this->chapter->comic->slug, $this->chapter->number], false),
            ] : null,
            'url' => route('posts.show', $this->id, false),
            'saved' => (bool) $this->viewer_saved,
        ];
    }
}
