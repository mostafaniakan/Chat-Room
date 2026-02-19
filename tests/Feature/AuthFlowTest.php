<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_username_and_password(): void
    {
        $response = $this->post('/register', [
            'username' => 'new_user_01',
            'password' => 'securepass123',
            'password_confirmation' => 'securepass123',
        ]);

        $response->assertRedirect(route('chat.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'username' => 'new_user_01',
        ]);
    }

    public function test_username_must_be_unique_on_registration(): void
    {
        User::factory()->create(['username' => 'taken_id']);

        $response = $this->from('/register')->post('/register', [
            'username' => 'taken_id',
            'password' => 'securepass123',
            'password_confirmation' => 'securepass123',
        ]);

        $response
            ->assertRedirect('/register')
            ->assertSessionHasErrors('username');
    }

    public function test_user_can_login_with_username_and_password(): void
    {
        $user = User::factory()->create([
            'username' => 'login_id',
            'password' => Hash::make('strongpassword'),
        ]);

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'strongpassword',
        ]);

        $response->assertRedirect(route('chat.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'username' => 'login_id',
            'password' => Hash::make('strongpassword'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'username' => 'login_id',
            'password' => 'wrongpassword',
        ]);

        $response
            ->assertRedirect('/login')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }
}
