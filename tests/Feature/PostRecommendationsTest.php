<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Follow;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Support\CollectionLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendations_use_creator_shared_tags_and_follows_with_visibility_filters(): void
    {
        [$viewer, $author, $other, $followed, $blocked] = User::factory()->count(5)->create();
        $create = fn ($user, $attributes = []) => Post::create($attributes + ['user_id' => $user->id, 'type' => 'image', 'rating' => 'sfw', 'is_original' => true]);
        $current = $create($author);
        $sameAuthor = $create($author);
        $similar = $create($other);
        $followedWork = $create($followed);
        $tagFollowWork = $create($other);
        $explicit = $create($followed, ['rating' => 'explicit']);
        $draft = $create($followed, ['is_draft' => true]);
        $hidden = $create($followed);
        $hidden->forceFill(['moderation_hidden_at' => now()])->save();
        $blockedWork = $create($blocked);
        $mutedWork = $create($followed);
        foreach (['shared', 'followed', 'muted'] as $name) {
            Tag::create(['name' => $name, 'is_approved' => true]);
        }
        Tag::syncFor($current, ['shared']);
        Tag::syncFor($similar, ['shared']);
        Tag::syncFor($tagFollowWork, ['followed']);
        Tag::syncFor($mutedWork, ['muted']);
        $viewer->followedTags()->attach(Tag::where('name', 'followed')->firstOrFail());
        Follow::create(['follower_id' => $viewer->id, 'following_id' => $followed->id]);
        Follow::create(['follower_id' => $viewer->id, 'following_id' => $blocked->id]);
        Block::create(['blocker_id' => $viewer->id, 'blocked_id' => $blocked->id]);
        CollectionLibrary::addMute($viewer, 'muted');

        $page = $this->actingAs($viewer)->get(route('posts.show', $current))->assertOk()->viewData('page')['props'];
        $this->assertContains($sameAuthor->id, array_column($page['more'], 'id'));
        $this->assertContains($similar->id, array_column($page['similar'], 'id'));
        $this->assertContains($followedWork->id, array_column($page['forYou'], 'id'));
        $this->assertContains($tagFollowWork->id, array_column($page['forYou'], 'id'));
        foreach (['more', 'similar', 'forYou'] as $section) {
            foreach ([$current, $explicit, $draft, $hidden, $blockedWork, $mutedWork] as $excluded) {
                $this->assertNotContains($excluded->id, array_column($page[$section], 'id'));
            }
        }
        // A fresh guest session has no personalized suggestions.
        auth()->logout();
        $guest = $this->get(route('posts.show', $current))->viewData('page')['props'];
        $this->assertSame([], $guest['forYou']);
    }
}
