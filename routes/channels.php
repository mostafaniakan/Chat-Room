<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.user.{username}', function (?User $user, string $username) {
    return $user?->username !== null && hash_equals($user->username, $username);
});
