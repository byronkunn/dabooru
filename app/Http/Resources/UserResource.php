<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'avatar' => $this->avatarUrl(),
            'banner' => $this->bannerUrl(),
            'avatarSource' => $request->user()?->is($this->resource) ? route('settings.profile.image.source', 'avatar', false) : null,
            'bannerSource' => $request->user()?->is($this->resource) ? route('settings.profile.image.source', 'banner', false) : null,
            'bio' => $this->bio,
            'location' => $this->location,
            'website' => $this->website,
            'ai_disclosure' => $this->ai_disclosure ?? 'none',
            'followers' => $this->followers_count ?? null,
            'following' => $this->following_count ?? null,
            'posts_count' => $this->posts_count ?? null,
            'joined' => $this->created_at?->toIso8601String(),
        ];
    }
}
