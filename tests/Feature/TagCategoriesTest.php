<?php

namespace Tests\Feature;

use App\Models\KnowledgeRevision;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Support\KnowledgeService;
use Database\Seeders\TagCategoriesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_tags_use_descriptive_categories_and_resources_keep_names(): void
    {
        $owner = User::factory()->create();
        $post = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => 'sfw']);
        Tag::syncFor($post, ['red skirt', 'seated', 'blue hair', 'lingerie', 'nudity', 'new unknown tag']);
        $this->get(route('posts.show', $post))->assertInertia(fn ($page) => $page
            ->has('post.tags', 6)
            ->where('post.tagCategories.red skirt', 'clothing')
            ->where('post.tagCategories.seated', 'position')
            ->where('post.tagCategories.blue hair', 'appearance')
            ->where('post.tagCategories.lingerie', 'clothing')
            ->where('post.tagCategories.nudity', 'content warnings')
            ->where('post.tagCategories.new unknown tag', 'general'));
        $this->get(route('knowledge.index', ['category' => 'position']))->assertInertia(fn ($page) => $page
            ->has('tags.data', 1)->where('tags.data.0.name', 'seated')->where('filters.category', 'position'));
        $this->assertSame('position', Tag::create(['name' => 'kneeling'])->category);
        $this->assertSame('clothing', Tag::create(['name' => 'swimwear'])->category);
    }

    public function test_backfill_preserves_curated_categories_and_records_complete_repeatable_history(): void
    {
        $tag = Tag::create(['name' => 'standing']);
        $tag->update(['category' => 'general']);
        $locked = Tag::create(['name' => 'red jacket', 'locked' => true]);
        $locked->update(['category' => 'general']);
        $curated = Tag::create(['name' => 'black boots', 'category' => 'subject']);
        $this->seed(TagCategoriesSeeder::class);
        $this->assertSame('position', $tag->fresh()->category);
        $this->assertSame('general', $locked->fresh()->category);
        $this->assertSame('subject', $curated->fresh()->category);
        $revision = KnowledgeRevision::where('target_id', $tag->id)->firstOrFail();
        $this->assertSame('general', $revision->before['category']);
        $this->assertSame(KnowledgeService::snapshot($tag->fresh()), $revision->after);
        $this->assertSame(1, $tag->fresh()->version);
        $this->seed(TagCategoriesSeeder::class);
        $this->assertSame(1, KnowledgeRevision::count());
        $this->assertSame(1, $tag->fresh()->version);
    }
}
