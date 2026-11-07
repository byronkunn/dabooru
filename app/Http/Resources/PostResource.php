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
        return [
            'id' => $this->id,
            'type' => $this->type,
            'rating' => $this->rating,
            'rating_label' => \App\Support\Rating::label($this->rating),
            'title' => $this->title,
            'caption' => $this->caption,
            'views' => $this->views,
            'likes' => $this->likes_count,
            'comments' => $this->comments_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans(short: true),
            'user' => $this->relationLoaded('user') ? (new UserResource($this->user))->resolve() : null,
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
        ];
    }
}
