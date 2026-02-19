<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserLookupController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_]+$/'],
        ]);

        $normalizedId = Str::lower(trim($validated['id']));
        $user = User::query()->where('username', $normalizedId)->first();

        if (! $user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        if ($request->user()?->is($user)) {
            throw ValidationException::withMessages([
                'id' => 'You cannot message yourself.',
            ]);
        }

        return response()->json([
            'id' => $user->username,
        ]);
    }
}
