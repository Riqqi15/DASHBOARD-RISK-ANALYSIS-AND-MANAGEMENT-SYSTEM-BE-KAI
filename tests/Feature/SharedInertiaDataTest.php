<?php

namespace Tests\Feature;

use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SharedInertiaDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_pages_share_a_minimal_user_payload(): void
    {
        $user = User::factory()->pusat()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.id', $user->id)
                ->where('auth.user.role', 'pusat')
                ->where('auth.user.unit_kerja_id', null)
                ->where('auth.user.unit_kerja', null)
                ->missing('auth.user.password'));
    }

    public function test_regional_user_payload_includes_only_the_assigned_unit_summary(): void
    {
        $unit = UnitKerja::factory()->create();
        $user = User::factory()->unit($unit)->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.unit_kerja.id', $unit->id)
                ->where('auth.user.unit_kerja.code', $unit->code)
                ->where('auth.user.unit_kerja.name', $unit->name));
    }

    public function test_guest_pages_share_a_null_user(): void
    {
        $this->get('/login')
            ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
    }
}
