<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\UnitSubsystemOpening;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PusatAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'active', 'pusat'])
            ->get('/_test/pusat', fn () => response('ok'));
    }

    public function test_unit_account_cannot_access_pusat_routes(): void
    {
        $this->actingAs(User::factory()->unit()->create())
            ->get('/_test/pusat')
            ->assertForbidden();
    }

    public function test_pusat_account_can_access_pusat_routes(): void
    {
        $this->actingAs(User::factory()->pusat()->create())
            ->get('/_test/pusat')
            ->assertOk();
    }

    public function test_opening_correction_requires_login_and_pusat_role_and_valid_unsigned_integers(): void
    {
        $opening = UnitSubsystemOpening::factory()->create();

        $this->put(route('admin.unit-subsystem-openings.update', $opening), [
            'sparepart_in' => 1,
            'sparepart_out' => 2,
        ])->assertRedirect('/login');

        $this->actingAs(User::factory()->unit($opening->unitKerja)->create())
            ->put(route('admin.unit-subsystem-openings.update', $opening), [
                'sparepart_in' => 1,
                'sparepart_out' => 2,
            ])->assertForbidden();

        $pusat = User::factory()->pusat()->create();
        $this->actingAs($pusat)
            ->put(route('admin.unit-subsystem-openings.update', $opening), [
                'sparepart_in' => -1,
                'sparepart_out' => 4294967296,
            ])->assertSessionHasErrors(['sparepart_in', 'sparepart_out']);
        $this->actingAs($pusat)
            ->put(route('admin.unit-subsystem-openings.update', $opening), [])
            ->assertSessionHasErrors(['sparepart_in', 'sparepart_out']);
    }

    public function test_pusat_correction_locks_updates_only_values_and_audits_changes_but_not_noop(): void
    {
        $pusat = User::factory()->pusat()->create();
        $opening = UnitSubsystemOpening::factory()->create([
            'sparepart_in' => 2,
            'sparepart_out' => 1,
        ]);
        $originalUnitId = $opening->unit_kerja_id;
        $originalSubsystemId = $opening->asset_subsystem_id;
        $lockingSql = [];
        DB::listen(function (QueryExecuted $query) use (&$lockingSql): void {
            if (str_contains(mb_strtolower($query->sql), 'for update')) {
                $lockingSql[] = mb_strtolower($query->sql);
            }
        });

        $this->actingAs($pusat)->from('/inventory')
            ->put(route('admin.unit-subsystem-openings.update', $opening), [
                'sparepart_in' => 7,
                'sparepart_out' => 3,
                'unit_kerja_id' => 999999,
                'asset_subsystem_id' => 999999,
            ])
            ->assertRedirect('/inventory')
            ->assertSessionHas('success', 'Stok pembukaan unit berhasil diperbarui.');

        $opening->refresh();
        $this->assertSame(7, $opening->sparepart_in);
        $this->assertSame(3, $opening->sparepart_out);
        $this->assertSame($originalUnitId, $opening->unit_kerja_id);
        $this->assertSame($originalSubsystemId, $opening->asset_subsystem_id);
        $this->assertStringContainsString('from `unit_subsystem_openings`', implode("\n", $lockingSql));
        $audit = AuditLog::query()->where('action', 'unit_subsystem_opening.updated')->sole();
        $this->assertSame(2, $audit->old_values['sparepart_in']);
        $this->assertSame(1, $audit->old_values['sparepart_out']);
        $this->assertSame(7, $audit->new_values['sparepart_in']);
        $this->assertSame(3, $audit->new_values['sparepart_out']);
        $this->assertSame($originalUnitId, $audit->new_values['unit_kerja_id']);
        $this->assertSame($originalSubsystemId, $audit->new_values['asset_subsystem_id']);
        $this->assertSame($opening->source_key, $audit->new_values['source_key']);

        $this->actingAs($pusat)->from('/inventory')
            ->put(route('admin.unit-subsystem-openings.update', $opening), [
                'sparepart_in' => 7,
                'sparepart_out' => 3,
            ])
            ->assertRedirect('/inventory')
            ->assertSessionHas('success', 'Stok pembukaan unit tidak berubah.');

        $this->assertSame(1, AuditLog::query()->where('action', 'unit_subsystem_opening.updated')->count());
    }
}
