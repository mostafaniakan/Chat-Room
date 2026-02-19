<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneExpiredMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_command_deletes_messages_older_than_ten_minutes(): void
    {
        Storage::fake('public');

        $sender = User::factory()->create(['username' => 'sender_one']);
        $recipient = User::factory()->create(['username' => 'recipient_one']);

        Storage::disk('public')->put('chat-voices/old-note.webm', 'voice');

        $expired = Message::query()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'user_name' => $sender->username,
            'message' => 'expired',
            'voice_path' => 'chat-voices/old-note.webm',
        ]);

        $expired->forceFill([
            'created_at' => now()->subMinutes(11),
            'updated_at' => now()->subMinutes(11),
        ])->save();

        $fresh = Message::query()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'user_name' => $sender->username,
            'message' => 'fresh',
        ]);

        $fresh->forceFill([
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ])->save();

        $this->artisan('messages:prune')
            ->assertSuccessful();

        $this->assertDatabaseMissing('messages', ['id' => $expired->id]);
        $this->assertDatabaseHas('messages', ['id' => $fresh->id]);
        Storage::disk('public')->assertMissing('chat-voices/old-note.webm');
    }
}
