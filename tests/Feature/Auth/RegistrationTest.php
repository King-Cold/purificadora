<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_new_users_can_register_with_initial_bottles(): void
    {
        $response = $this->post('/register', [
            'name' => 'Cliente Con Garrafones',
            'email' => 'garrafones@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'fixed_bottles_count' => 3,
            'exchange_bottles_count' => 5,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = \App\Models\User::where('email', 'garrafones@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->customer);
        $this->assertEquals(3, $user->customer->fixed_bottles_count);
        $this->assertEquals(5, $user->customer->exchange_bottles_count);
        $this->assertEquals(8, $user->customer->total_bottles);
    }
}

