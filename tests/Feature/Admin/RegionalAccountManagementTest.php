<?php

namespace Tests\Feature\Admin;

use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegionalAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_pusat_creates_only_a_regional_account_for_an_active_unit(): void
    {
        $pusat = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create();

        $this->actingAs($pusat)->post('/admin/accounts', [
            'name' => 'Operator Daop 1',
            'email' => 'operator.daop1@example.test',
            'unit_kerja_id' => $unit->id,
            'password' => 'long-secret-password',
            'password_confirmation' => 'long-secret-password',
        ])->assertRedirect('/admin/accounts');

        $this->assertDatabaseHas('users', [
            'email' => 'operator.daop1@example.test',
            'role' => 'unit',
            'unit_kerja_id' => $unit->id,
            'is_active' => true,
        ]);
    }

    public function test_pusat_can_update_deactivate_and_reset_a_regional_account(): void
    {
        $pusat = User::factory()->pusat()->create();
        $account = User::factory()->unit()->create();

        $this->actingAs($pusat)->patch("/admin/accounts/{$account->id}/status", [
            'is_active' => false,
        ])->assertRedirect('/admin/accounts');

        $this->actingAs($pusat)->put("/admin/accounts/{$account->id}/password", [
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])->assertRedirect('/admin/accounts');

        $this->assertFalse($account->fresh()->is_active);
        $this->assertTrue(Hash::check('replacement-password', $account->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.status_changed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.password_reset']);
        $this->assertStringNotContainsString('replacement-password', $this->app['db']->table('audit_logs')->pluck('new_values')->implode(' '));
    }

    public function test_inactive_unit_duplicate_email_and_missing_unit_fail_validation(): void
    {
        $pusat = User::factory()->pusat()->create();
        $inactiveUnit = UnitKerja::factory()->create(['is_active' => false]);
        $existing = User::factory()->unit()->create();

        $payload = [
            'name' => 'Operator',
            'email' => $existing->email,
            'unit_kerja_id' => $inactiveUnit->id,
            'password' => 'long-secret-password',
            'password_confirmation' => 'long-secret-password',
        ];

        $this->actingAs($pusat)->post('/admin/accounts', $payload)
            ->assertSessionHasErrors(['email', 'unit_kerja_id']);

        $payload['email'] = 'unique@example.test';
        $payload['unit_kerja_id'] = 999999;
        $this->actingAs($pusat)->post('/admin/accounts', $payload)
            ->assertSessionHasErrors('unit_kerja_id');
    }

    public function test_regional_user_cannot_access_account_management(): void
    {
        $user = User::factory()->unit()->create();

        $this->actingAs($user)->get('/admin/accounts')->assertForbidden();
        $this->actingAs($user)->post('/admin/accounts', [])->assertForbidden();
    }

    public function test_pusat_account_cannot_be_targeted_as_a_regional_account(): void
    {
        $actor = User::factory()->pusat()->create();
        $target = User::factory()->pusat()->create();

        $this->actingAs($actor)->get("/admin/accounts/{$target->id}/edit")->assertNotFound();
        $this->actingAs($actor)->patch("/admin/accounts/{$target->id}/status", ['is_active' => false])->assertNotFound();
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_account_on_an_inactive_unit_can_keep_its_unit_while_updating_profile(): void
    {
        $pusat = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create(['is_active' => false]);
        $account = User::factory()->unit($unit)->create();

        $this->actingAs($pusat)->put("/admin/accounts/{$account->id}", [
            'name' => 'Nama Diperbarui',
            'email' => 'updated@example.test',
            'unit_kerja_id' => $unit->id,
        ])->assertRedirect('/admin/accounts');

        $this->assertDatabaseHas('users', [
            'id' => $account->id,
            'name' => 'Nama Diperbarui',
            'unit_kerja_id' => $unit->id,
        ]);
    }
}
