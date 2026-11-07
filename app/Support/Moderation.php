<?php

namespace App\Support;

use App\Models\DmcaNotice;
use App\Models\Post;
use App\Models\User;

class Moderation
{
    public static function ban(User $actor, User $target, ?string $reason): void
    {
        abort_if($actor->id === $target->id, 422, 'You cannot ban yourself.');
        abort_if($target->isAdmin(), 422, 'Remove admin rights before banning an account.');
        abort_if(! $actor->isAdmin() && ($target->staff_roles ?? []) !== [], 403, 'Only administrators can ban staff.');

        $target->forceFill(['banned_at' => now(), 'banned_reason' => $reason])->save();
    }

    public static function hide(Post $post): void
    {
        if (! $post->trashed()) {
            $post->forceFill(['moderation_hidden_at' => now()])->save();
            $post->delete();
        }
    }

    public static function restore(Post $post): void
    {
        abort_unless($post->trashed() && $post->moderation_hidden_at, 422, 'This work has no active moderation removal.');
        abort_if(DmcaNotice::where('post_id', $post->id)->where('status', 'actioned')->exists(), 409, 'Resolve the active takedown before restoring this work.');

        $post->forceFill(['moderation_hidden_at' => null])->restore();
    }
}
