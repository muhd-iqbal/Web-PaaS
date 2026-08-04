<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_register_with_an_active_plan(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->post('/register', [
            'name' => 'Student User',
            'email' => 'student@example.com',
            'plan_id' => $plan->id,
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'student@example.com',
            'plan_id' => $plan->id,
            'is_admin' => false,
        ]);
    }

    public function test_a_guest_cannot_register_with_an_inactive_plan(): void
    {
        $plan = Plan::factory()->create(['is_active' => false]);

        $this->post('/register', [
            'name' => 'Student User',
            'email' => 'student@example.com',
            'plan_id' => $plan->id,
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ])->assertSessionHasErrors('plan_id');

        $this->assertGuest();
    }

    public function test_a_user_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create(['password' => 'secure-password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secure-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
