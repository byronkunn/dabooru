<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function attach(string $tag, int $works): void
    {
        $artist = User::factory()->create();
        for ($i = 0; $i < $works; $i++) {
            $post = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => "W{$i}"]);
            Tag::syncFor($post, [$tag]);
        }
    }

    public function test_single_use_multi_word_tags_are_reported_but_not_deleted_by_default(): void
    {
        $this->attach('pocket clip', 1);
        $this->attach('watercolor', 1);

        $this->artisan('tags:prune-weak', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('tags', ['name' => 'pocket clip']);
        $this->assertDatabaseHas('tags', ['name' => 'watercolor']);
    }

    public function test_pruning_removes_only_weak_multi_word_tags(): void
    {
        $this->attach('pocket clip', 1);
        $this->attach('watercolor', 1);
        $this->attach('sketch', 5);

        $this->artisan('tags:prune-weak')->assertSuccessful();

        $this->assertDatabaseMissing('tags', ['name' => 'pocket clip']);
        // a single-use but genuine one-word tag is kept
        $this->assertDatabaseHas('tags', ['name' => 'watercolor']);
        $this->assertDatabaseHas('tags', ['name' => 'sketch']);
    }

    public function test_the_all_flag_also_prunes_single_word_tags(): void
    {
        $this->attach('rareword', 1);

        $this->artisan('tags:prune-weak', ['--all' => true])->assertSuccessful();

        $this->assertDatabaseMissing('tags', ['name' => 'rareword']);
    }
}
