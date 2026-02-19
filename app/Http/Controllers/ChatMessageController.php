<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ChatMessageController extends Controller
{
    public function index(Request $request): View
    {
        $authUser = $request->user();

        $messages = Message::query()
            ->with(['sender:id,username', 'recipient:id,username'])
            ->where(function ($query) use ($authUser) {
                $query
                    ->where('sender_id', $authUser->id)
                    ->orWhere('recipient_id', $authUser->id);
            })
            ->latest('id')
            ->take(200)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $message) => $this->messagePayload($message, $authUser->id));

        return view('chat', [
            'authUser' => $authUser,
            'messages' => $messages,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'recipient_id' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9_]+$/',
                Rule::exists('users', 'username'),
            ],
            'message' => ['nullable', 'string', 'max:4000'],
            'voice' => [
                'nullable',
                'file',
                'max:10240',
                'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/webm,audio/ogg,audio/mp4,audio/x-m4a,audio/aac',
            ],
        ]);

        $text = trim((string) ($validated['message'] ?? ''));
        $voiceFile = $request->file('voice');

        if ($text === '' && $voiceFile === null) {
            throw ValidationException::withMessages([
                'message' => 'Please send text or a voice note.',
            ]);
        }

        $recipient = User::query()
            ->where('username', $validated['recipient_id'])
            ->firstOrFail();

        if ($recipient->is($authUser)) {
            throw ValidationException::withMessages([
                'recipient_id' => 'You cannot send a message to yourself.',
            ]);
        }

        $voicePath = $voiceFile?->store('chat-voices', 'public');

        $message = Message::query()->create([
            'sender_id' => $authUser->id,
            'recipient_id' => $recipient->id,
            'user_name' => $authUser->username,
            'message' => $text === '' ? null : $text,
            'voice_path' => $voicePath,
        ]);

        $payload = $this->messagePayload(
            $message->load(['sender:id,username', 'recipient:id,username']),
            $authUser->id,
        );

        broadcast(new MessageSent($payload, $authUser->username, $recipient->username));

        return response()->json([
            'message' => $payload,
        ], 201);
    }

    private function messagePayload(Message $message, int $authUserId): array
    {
        return [
            'id' => $message->id,
            'sender_username' => $message->sender?->username ?? $message->user_name,
            'recipient_username' => $message->recipient?->username,
            'message' => $message->message,
            'voice_url' => $message->voice_path ? Storage::disk('public')->url($message->voice_path) : null,
            'created_at' => $message->created_at?->toIso8601String(),
            'time' => $message->created_at?->format('H:i'),
            'is_mine' => $message->sender_id === $authUserId,
        ];
    }
}
