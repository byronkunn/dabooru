<?php

namespace App\Http\Resources;

use App\Http\Controllers\CommentController;
use App\Models\Block;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comment */
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $deleted = $this->trashed();
        $owner = ! $deleted && $request->user() !== null && $request->user()->id === $this->user_id;

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'body' => $deleted ? null : $this->body,
            'deleted' => $deleted,
            'edited' => ! $deleted && $this->updated_at->gt($this->created_at),
            'created_at_human' => $this->created_at->diffForHumans(short: true),
            'user' => ! $deleted && $this->user ? (new UserResource($this->user))->resolve() : null,
            'canEdit' => $owner && CommentController::canComment($request, $this->post),
            'canDelete' => $owner,
            'canReply' => ! $deleted && CommentController::canComment($request, $this->post)
                && (! $this->user_id || ! Block::existsBetween($request->user()->id, $this->user_id)),
            'replies' => $this->relationLoaded('replies') ? self::collection($this->replies)->resolve() : [],
        ];
    }
}
