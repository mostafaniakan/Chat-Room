<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_send_private_text_message(): void
    {
        Event::fake([MessageSent::class]);

        $sender = User::factory()->create(['username' => 'ali_sender']);
        $recipient = User::factory()->create(['username' => 'sara_target']);

        $response = $this->actingAs($sender)->postJson('/messages', [
            'recipient_id' => $recipient->username,
            'message' => 'Hello Sara',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message.sender_username', $sender->username)
            ->assertJsonPath('message.recipient_username', $recipient->username)
            ->assertJsonPath('message.message', 'Hello Sara');

        $this->assertDatabaseHas('messages', [
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'message' => 'Hello Sara',
        ]);

        Event::assertDispatched(MessageSent::class);
    }

    public function test_it_blocks_sending_message_to_self(): void
    {
        $sender = User::factory()->create(['username' => 'self_user']);

        $response = $this->actingAs($sender)->postJson('/messages', [
            'recipient_id' => $sender->username,
            'message' => 'Self message',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recipient_id');
    }

    public function test_authenticated_user_can_find_recipient_by_id(): void
    {
        $sender = User::factory()->create(['username' => 'sender_user']);
        $recipient = User::factory()->create(['username' => 'target_user']);

        $response = $this->actingAs($sender)->getJson('/users/find?id='.$recipient->username);

        $response
            ->assertOk()
            ->assertJson(['id' => $recipient->username]);
    }

    public function test_guests_cannot_access_chat_endpoints(): void
    {
        $recipient = User::factory()->create(['username' => 'target_user']);

        $this->get('/chat')->assertRedirect(route('login'));
        $this->post('/messages', [
            'recipient_id' => $recipient->username,
            'message' => 'Blocked',
        ])->assertRedirect(route('login'));
    }

    public function test_chat_page_only_shows_messages_related_to_logged_in_user(): void
    {
        $targetUser = User::factory()->create(['username' => 'target_user']);
        $visibleSender = User::factory()->create(['username' => 'visible_sender']);
        $hiddenSender = User::factory()->create(['username' => 'hidden_sender']);
        $otherRecipient = User::factory()->create(['username' => 'other_recipient']);

        Message::query()->create([
            'sender_id' => $visibleSender->id,
            'recipient_id' => $targetUser->id,
            'user_name' => $visibleSender->username,
            'message' => 'Visible thread message',
        ]);

        Message::query()->create([
            'sender_id' => $hiddenSender->id,
            'recipient_id' => $otherRecipient->id,
            'user_name' => $hiddenSender->username,
            'message' => 'Hidden thread message',
        ]);

        $response = $this->actingAs($targetUser)->get('/chat');

        $response
            ->assertOk()
            ->assertSee('Visible thread message')
            ->assertDontSee('Hidden thread message');
    }

    public function test_it_accepts_voice_uploads_for_private_messages(): void
    {
        Storage::fake('public');
        Event::fake([MessageSent::class]);

        $sender = User::factory()->create(['username' => 'voice_sender']);
        $recipient = User::factory()->create(['username' => 'voice_target']);

        $response = $this->actingAs($sender)->post('/messages', [
            'recipient_id' => $recipient->username,
            'voice' => UploadedFile::fake()->create('voice-note.webm', 200, 'audio/webm'),
        ]);

        $response->assertCreated();

        $savedMessage = Message::query()->first();

        $this->assertNotNull($savedMessage);
        $this->assertNotNull($savedMessage->voice_path);
        Storage::disk('public')->assertExists($savedMessage->voice_path);

        Event::assertDispatched(MessageSent::class);
    }
}
