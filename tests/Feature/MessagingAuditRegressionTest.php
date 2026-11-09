<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use App\Models\Report;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_the_same_draft_from_both_profiles_does_not_choose_the_sender(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $this->actingAs($alice)->get(route('messages.index', ['to' => $bob->username]))->assertRedirect();
        $chat = Conversation::sole();
        $url = route('messages.show', $chat);
        $this->actingAs($bob)->get(route('messages.index', ['to' => $alice->username]))->assertRedirect($url);
        $this->get($url)->assertInertia(fn ($page) => $page->where('conversation.canSend', true));

        $this->actingAs($alice)->post(route('messages.store'), [
            'conversation_id' => $chat->id, 'body' => 'Alice sent first',
        ])->assertRedirect($url);
        $this->assertSame($alice->id, (int) $chat->fresh()->requested_by);
        $this->actingAs($bob)->get($url)->assertInertia(fn ($page) => $page
            ->where('conversation.request.mine', false)->where('conversation.canSend', false));
    }

    public function test_only_the_person_who_declined_can_reopen_the_conversation(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $this->actingAs($alice)->post(route('messages.store'), ['to' => $bob->username, 'body' => 'Request'])->assertRedirect();
        $chat = Conversation::sole();
        $this->actingAs($bob)->post(route('messages.request', $chat), ['decision' => 'declined'])->assertRedirect();
        $this->actingAs($alice)->postJson(route('messages.store'), ['to' => $bob->username, 'body' => 'Retry'])->assertForbidden();
        $this->actingAs($bob)->get(route('messages.show', $chat))->assertInertia(fn ($page) => $page->where('conversation.canSend', true));
        $this->post(route('messages.store'), ['to' => $alice->username, 'body' => 'Reopened'])->assertRedirect(route('messages.show', $chat));
        $this->assertSame('accepted', $chat->fresh()->request_status);
        $this->assertFalse((bool) $chat->participants()->whereKey($bob->id)->firstOrFail()->pivot->archived);
        $this->actingAs($alice)->post(route('messages.store'), ['conversation_id' => $chat->id, 'body' => 'Reply'])->assertRedirect();
    }

    public function test_blocking_hides_typing_and_receipts_while_leaving_history_available(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        foreach ([$alice, $bob] as $user) {
            Setting::create(['user_id' => $user->id, 'key' => 'chat_read_receipts', 'value' => '1']);
        }
        $chat = Conversation::between($alice->id, $bob->id);
        $message = $chat->messages()->create(['user_id' => $alice->id, 'body' => 'Before block']);
        $url = route('messages.show', $chat);
        $this->actingAs($bob)->post(route('messages.typing', $chat))->assertNoContent();
        $this->actingAs($alice)->get($url)->assertInertia(fn ($page) => $page->has('conversation.typing', 1));

        $this->actingAs($bob)->post(route('users.block', $alice))->assertRedirect();
        $this->postJson(route('messages.read', $chat), ['message_id' => $message->id])->assertOk()->assertJsonPath('unread', 0);
        $this->actingAs($alice)->get($url)->assertInertia(fn ($page) => $page
            ->has('conversation.messages', 1)->has('conversation.typing', 0)->where('conversation.readThrough', null));
    }

    public function test_sync_leaves_unread_alone_and_detects_message_changes(): void
    {
        [$alice, $bob, $outsider] = User::factory()->count(3)->create();
        $chat = Conversation::between($alice->id, $bob->id);
        $url = route('messages.show', $chat);
        $sync = route('messages.sync', $chat);
        $page = $this->actingAs($bob)->get($url)->assertOk()->viewData('page');
        $initial = $this->getJson($sync)->assertOk()->json('token');
        $this->assertSame($page['props']['conversation']['syncToken'], $initial);
        $this->getJson($sync)->assertExactJson(['token' => $initial]);
        $this->actingAs($outsider)->getJson($sync)->assertForbidden();

        $this->actingAs($alice)->post(route('messages.store'), ['conversation_id' => $chat->id, 'body' => 'Incoming'])->assertRedirect();
        $message = Message::sole();
        $current = $this->actingAs($bob)->getJson($sync)->assertOk()->json('token');
        $this->assertNotSame($initial, $current);
        $this->get($url, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $page['version'] ?? '',
            'X-Inertia-Partial-Component' => 'Messages/Show',
            'X-Inertia-Partial-Data' => 'conversation,conversations,auth',
        ])->assertOk()->assertJsonPath('props.auth.unread_messages', 1);
        $this->getJson($sync)->assertExactJson(['token' => $current]);
        $this->actingAs($alice)->patch(route('messages.edit', $message), ['body' => 'Edited'])->assertRedirect();
        $this->assertNotSame($current, $this->actingAs($bob)->getJson($sync)->assertOk()->json('token'));
        $this->postJson(route('messages.read', $chat), ['message_id' => $message->id])->assertOk()->assertJsonPath('unread', 0);
        $this->actingAs($outsider)->postJson(route('messages.read', $chat), ['message_id' => $message->id])->assertForbidden();
    }

    public function test_report_keeps_the_artwork_title_and_reference_after_the_message_is_removed(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $admin = User::factory()->admin()->create();
        $work = Post::create(['user_id' => $alice->id, 'type' => 'image', 'title' => 'Shared work', 'rating' => 'sfw']);
        $chat = Conversation::between($alice->id, $bob->id);
        $this->actingAs($alice)->post(route('messages.store'), ['conversation_id' => $chat->id, 'shared_post_id' => $work->id])->assertRedirect();
        $message = Message::sole();
        $this->actingAs($bob)->post(route('reports.store'), [
            'reportable_type' => 'message', 'reportable_id' => $message->id, 'reason' => 'spam',
        ])->assertRedirect();
        $report = Report::sole();
        $this->actingAs($alice)->delete(route('messages.destroy', $message))->assertRedirect();

        $this->get(route('admin.reports.message', $report))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.reports.message', $report))->assertInertia(fn ($page) => $page
            ->where('work.post_id', $work->id)->where('work.title', 'Shared work')->where('work.url', route('posts.show', $work, false)));
    }
}
