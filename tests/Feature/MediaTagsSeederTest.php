<?php

namespace Tests\Feature;

use App\Models\KnowledgeRevision;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\MediaTagsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaTagsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewed_media_get_thirty_persistent_tags_without_losing_existing_tags(): void
    {
        $owner = User::factory()->create();
        $posts = [];
        foreach ([
            ['image', ['/sfw/content/image/148a0f0eae39998a954d56c2290dc65e.png']],
            ['video', ['/sfw/content/video/18a140f0b65bb1dfa2b7aeaff6fc366e.mp4']],
            ['album', ['/sfw/content/image/sample_d1760f647e9e6489ee7e974f411c48fe.jpg', '/sfw/content/image/sample_d297c6b40f5880a0e3afada84f5f149e.jpg']],
        ] as [$type, $paths]) {
            $post = Post::create(['user_id' => $owner->id, 'type' => $type, 'rating' => 'sfw']);
            foreach ($paths as $position => $path) {
                $media = Media::create([
                    'user_id' => $owner->id, 'disk' => 'public', 'path' => $path,
                    'kind' => $type === 'video' ? 'video' : 'image', 'status' => 'ready',
                ]);
                PostMedia::create(['post_id' => $post->id, 'media_id' => $media->id, 'position' => $position]);
            }
            Tag::syncFor($post, ['existing-tag']);
            $posts[] = $post;
        }
        $this->seed(MediaTagsSeeder::class);
        foreach ($posts as $post) {
            $this->assertSame(30, $post->tags()->count());
            $this->assertTrue($post->tags()->where('name', 'existing-tag')->exists());
            $this->get(route('posts.show', $post))->assertInertia(fn ($page) => $page->has('post.tags', 30));
        }
        $albumTags = $posts[2]->tags()->pluck('name')->all();
        $this->assertContains('teal hair', $albumTags);
        $this->assertContains('brown hair', $albumTags);
        $versions = array_map(fn ($post) => $post->fresh()->tag_version, $posts);
        $revisions = KnowledgeRevision::count();
        $this->seed(MediaTagsSeeder::class);
        $this->assertSame($versions, array_map(fn ($post) => $post->fresh()->tag_version, $posts));
        $this->assertSame($revisions, KnowledgeRevision::count());
    }

    public function test_unreviewed_media_and_private_drafts_are_not_assigned_guessed_tags(): void
    {
        $owner = User::factory()->create();
        $unknown = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => 'sfw']);
        $draft = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => 'sfw', 'is_draft' => true, 'draft_tags' => ['private-tag']]);
        $this->seed(MediaTagsSeeder::class);
        $this->assertSame(0, $unknown->tags()->count());
        $this->assertSame(['private-tag'], $draft->fresh()->draft_tags);
    }

    public function test_each_reviewed_mapping_has_at_least_thirty_valid_distinct_tags(): void
    {
        $data = require database_path('data/sfw/tags.php');
        foreach ($data['media'] as [$group, $description]) {
            $names = array_values(array_unique(array_merge(explode(', ', $description), $data['groups'][$group])));
            $this->assertGreaterThanOrEqual(30, count($names));
            $this->assertSame($names, Tag::parse(implode(',', $names), 50));
        }
    }
}
