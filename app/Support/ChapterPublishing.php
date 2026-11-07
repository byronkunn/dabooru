<?php

namespace App\Support;

use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Tag;

class ChapterPublishing
{
    // Call while holding the chapter and series write locks.
    public static function publish(Comic $comic, ComicChapter $chapter, array $tags): void
    {
        $chapter->forceFill(['published_at' => now(), 'draft_tags' => null])->save();
        $comic->forceFill(['last_chapter_at' => $chapter->published_at])->save();

        $post = Post::create([
            'user_id' => $comic->user_id,
            'type' => 'comic',
            'rating' => $chapter->rating,
            'title' => $comic->title.' · Ch. '.$chapter->number,
            'caption' => $chapter->title,
            'comic_chapter_id' => $chapter->id,
        ]);
        Tag::syncFor($post, $tags);

        $cover = $chapter->pages()->first();
        if ($cover) {
            PostMedia::create(['post_id' => $post->id, 'media_id' => $cover->media_id, 'position' => 0]);
        }
    }
}
