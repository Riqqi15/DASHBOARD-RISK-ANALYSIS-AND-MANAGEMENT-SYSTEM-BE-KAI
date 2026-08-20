<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_login_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
    }

    public function test_active_user_can_log_in_and_log_out(): void
    {
        $user = User::factory()->pusat()->create(['password' => 'secret-password']);

        $this->post('/login', [
            'username' => strtoupper($user->username),
            'password' => 'secret-password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->pusat()->inactive()->create([
            'password' => 'secret-password',
        ]);

        $this->from('/login')->post('/login', [
            'username' => $user->username,
            'password' => 'secret-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_email_cannot_be_used_as_a_login_identifier(): void
    {
        $user = User::factory()->pusat()->create([
            'email' => 'admin.pusat@example.test',
            'password' => 'admin1234',
        ]);

        $this->post('/login', [
            'username' => $user->email,
            'password' => 'admin1234',
        ])->assertSessionHasErrors([
            'username' => 'Username, kata sandi, atau status akun tidak valid.',
        ]);

        $this->assertGuest();
    }

    public function test_inactive_authenticated_session_is_revoked(): void
    {
        $user = User::factory()->pusat()->inactive()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_from_application_pages(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/risk-matrix')->assertRedirect('/login');
    }
}
