<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\FakeImage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * GD's image factory writes via tmpfile(), which this machine's PHP can only
 * do when sys_temp_dir is writable. Tests build real bytes instead so they
 * do not depend on the host's temp configuration.
 */

class UploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public', ['url' => '/storage']);
    }

    public function test_upload_page_requires_auth(): void
    {
        $this->get('/upload')->assertRedirect('/login');
    }

    public function test_a_user_can_upload_an_image_post(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('upload.store'), [
            'type' => 'image',
            'title' => 'Mountain quiet',
            'caption' => 'Did a study up near the lake.',
            'rating' => 'sfw',
            'files' => FakeImage::upload('quiet.png', 1200, 800),
        ]);

        $post = Post::firstOrFail();
        $response->assertRedirect(route('posts.show', $post));

        $this->assertSame($user->id, $post->user_id);
        $this->assertSame('image', $post->type);
        $this->assertSame(1, $post->media()->count());

        $media = Media::firstOrFail();
        $this->assertSame(1200, $media->width);
        $this->assertSame(800, $media->height);
        $this->assertSame('ready', $media->status);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $disk->assertExists($media->path);
    }

    public function test_a_user_can_upload_an_album(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('upload.store'), [
            'type' => 'album',
            'rating' => 'suggestive',
            'files' => [
                FakeImage::upload('a.png'),
                FakeImage::upload('b.png'),
                FakeImage::upload('c.png'),
            ],
        ])->assertRedirect();

        $this->assertSame(3, Post::firstOrFail()->media()->count());
    }

    public function test_uploads_accept_thirty_full_length_tags(): void
    {
        $tags = array_map(fn ($number) => str_repeat('a', 38).sprintf('%02d', $number), range(1, 30));
        $this->actingAs(User::factory()->create())->post(route('upload.store'), [
            'type' => 'image', 'rating' => 'sfw', 'tags' => implode(', ', $tags),
            'files' => FakeImage::upload('tagged.png'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(30, Post::firstOrFail()->tags()->count());
    }

    public function test_uploads_reject_thirty_one_tags_before_saving_media(): void
    {
        $this->actingAs(User::factory()->create())->post(route('upload.store'), [
            'type' => 'image', 'rating' => 'sfw',
            'tags' => implode(', ', array_map(fn ($number) => 'tag-'.$number, range(1, 31))),
            'files' => FakeImage::upload('too-many.png'),
        ])->assertSessionHasErrors('tags');

        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_explicit_rating_is_stored_and_hidden_from_guests(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('upload.store'), [
            'type' => 'image',
            'rating' => 'explicit',
            'files' => FakeImage::upload('rain.png'),
        ]);

        $post = Post::firstOrFail();
        $this->assertSame('explicit', $post->rating);

        $this->post(route('logout'));

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts', 0));
    }

    public function test_a_rejected_file_type_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('upload.store'), [
            'type' => 'image',
            'rating' => 'sfw',
            'files' => new UploadedFile(storage_path('framework/testing/not-an-image.txt'), 'notes.txt', 'text/plain', null, true),
        ])->assertSessionHasErrors('files');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_a_post_needs_a_valid_rating(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('upload.store'), [
            'type' => 'image',
            'rating' => 'nuclear',
            'files' => FakeImage::upload('x.png'),
        ])->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_uploaded_images_are_reencoded_without_metadata(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('upload.store'), [
            'type' => 'image',
            'rating' => 'sfw',
            'files' => FakeImage::upload('phone-photo.png', 40, 30),
        ])->assertRedirect();

        $media = Media::firstOrFail();
        $bytes = Storage::disk('public')->get($media->path);

        // re-encoded to JPEG and no EXIF/XMP markers survive
        $this->assertStringStartsWith("\xFF\xD8", $bytes);
        $this->assertStringNotContainsString('Exif', $bytes);
        $this->assertStringNotContainsString('<x:xmpmeta', $bytes);
    }

    public function test_user_can_upload_work_with_external_artist_attribution(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('upload.store'), [
            'type' => 'image',
            'title' => 'Ghost In Rain',
            'caption' => 'Reposting with permission',
            'rating' => 'sfw',
            'is_original' => false,
            'artist_name' => 'Sakimichan',
            'source_url' => 'https://artstation.com/artwork/demo',
            'files' => FakeImage::upload('ghost.png', 800, 600),
        ]);

        $post = Post::firstOrFail();
        $response->assertRedirect(route('posts.show', $post));

        $this->assertFalse($post->is_original);
        $this->assertSame('Sakimichan', $post->artist_name);
        $this->assertSame('https://artstation.com/artwork/demo', $post->source_url);
        $this->assertTrue($post->isExternalArtist());

        // detail page reflects the external artist
        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Posts/Show')
                ->where('post.is_original', false)
                ->where('post.artist_name', 'Sakimichan')
                ->where('post.source_url', 'https://artstation.com/artwork/demo')
                ->has('post.external_artist'));
    }
}
