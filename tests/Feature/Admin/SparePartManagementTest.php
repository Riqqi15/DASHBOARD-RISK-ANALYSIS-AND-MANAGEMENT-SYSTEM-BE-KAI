<?php

namespace Tests\Feature\Admin;

use App\Models\AssetSubsystem;
use App\Models\AssetSystem;
use App\Models\AuditLog;
use App\Models\InventoryStock;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SparePartManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_pusat_can_create_a_normalized_manual_spare_part(): void
    {
        $pusat = User::factory()->pusat()->create();
        $subsystem = AssetSubsystem::factory()->create();

        $this->actingAs($pusat)
            ->post(route('admin.spare-parts.store'), [
                'asset_subsystem_id' => $subsystem->id,
                'code' => '  SP   001  ',
                'equipment' => '  Brake   Assembly  ',
                'detail_equipment' => '  Main   cylinder  ',
                'max_yearly_failure' => '12.50',
                'average_yearly_failure' => '6.25',
                'max_lead_time_months' => '4.50',
                'average_lead_time_months' => '2.25',
                'safety_stock' => 5,
                'lead_time_demand' => 10,
                'reorder_point' => 15,
                'severity' => '  Vital  ',
                'unit_of_measure' => '  unit  ',
                'source_key' => 'client-controlled',
                'is_active' => false,
            ])
            ->assertRedirect(route('inventory', ['tab' => 'master']));

        $part = SparePart::query()->sole();
        $this->assertSame('SP 001', $part->code);
        $this->assertSame(hash('sha256', 'manual|SP 001'), $part->source_key);
        $this->assertSame('Brake Assembly', $part->equipment);
        $this->assertSame('Main cylinder', $part->detail_equipment);
        $this->assertNull($part->function_criterion);
        $this->assertNull($part->production_impact);
        $this->assertSame('Vital', $part->severity);
        $this->assertSame('unit', $part->unit_of_measure);
        $this->assertTrue($part->is_active);

        $audit = AuditLog::query()->where('action', 'spare_part.created')->sole();
        $this->assertSame($pusat->id, $audit->actor_id);
        $this->assertSame([], $audit->old_values);
        $this->assertSame($part->source_key, $audit->new_values['source_key']);
        $this->assertSame('SP 001', $audit->new_values['code']);
    }

    public function test_manual_spare_part_calculates_reorder_values_from_required_excel_inputs(): void
    {
        $pusat = User::factory()->pusat()->create();
        $subsystem = AssetSubsystem::factory()->create();

        $this->actingAs($pusat)
            ->post(
                route('admin.spare-parts.store'),
                $this->payload($subsystem, [
                    'max_yearly_failure' => '12.50',
                    'average_yearly_failure' => '6.25',
                    'max_lead_time_months' => '4.50',
                    'average_lead_time_months' => '2.25',
                    'safety_stock' => 999,
                    'lead_time_demand' => 999,
                    'reorder_point' => 999,
                ]),
            )
            ->assertRedirect(route('inventory', ['tab' => 'master']));

        $part = SparePart::query()->sole();
        $this->assertSame(43, $part->safety_stock);
        $this->assertSame(15, $part->lead_time_demand);
        $this->assertSame(57, $part->reorder_point);
        $this->assertSame('calculated', $part->reorder_calculation_status);
    }

    public function test_manual_spare_part_derives_average_values_from_maximum_inputs(): void
    {
        $pusat = User::factory()->pusat()->create();
        $subsystem = AssetSubsystem::factory()->create();

        $this->actingAs($pusat)
            ->post(
                route('admin.spare-parts.store'),
                $this->payload($subsystem, [
                    'max_yearly_failure' => '12.50',
                    'average_yearly_failure' => '1.00',
                    'max_lead_time_months' => '4.50',
                    'average_lead_time_months' => '1.00',
                ]),
            )
            ->assertRedirect(route('inventory', ['tab' => 'master']));

        $part = SparePart::query()->sole();
        $this->assertSame('6.25', $part->average_yearly_failure);
        $this->assertSame('2.25', $part->average_lead_time_months);
        $this->assertSame(43, $part->safety_stock);
        $this->assertSame(15, $part->lead_time_demand);
        $this->assertSame(57, $part->reorder_point);
    }

    public function test_manual_spare_part_requires_excel_source_fields(): void
    {
        $pusat = User::factory()->pusat()->create();
        $subsystem = AssetSubsystem::factory()->create();

        $this->actingAs($pusat)
            ->post(
                route('admin.spare-parts.store'),
                $this->payload($subsystem, [
                    'equipment' => '',
                    'max_yearly_failure' => '',
                    'average_yearly_failure' => '',
                    'max_lead_time_months' => '',
                    'average_lead_time_months' => '',
                    'severity' => '',
                ]),
            )
            ->assertSessionHasErrors([
                'equipment' => 'Equipment wajib diisi.',
                'max_yearly_failure' => 'Maksimum kegagalan wajib diisi.',
                'average_yearly_failure' => 'Rata-rata kegagalan wajib diisi.',
                'max_lead_time_months' => 'Maksimum lead time wajib diisi.',
                'average_lead_time_months' => 'Rata-rata lead time wajib diisi.',
                'severity' => 'Criticality wajib dipilih.',
            ]);

        $this->assertSame(0, SparePart::query()->count());
    }

    public function test_pusat_updates_fields_without_changing_import_semantics(): void
    {
        $clock = Carbon::parse('2026-08-08 10:00:00');
        Carbon::setTestNow($clock);
        $this->beforeApplicationDestroyed(fn () => Carbon::setTestNow());
        $pusat = User::factory()->pusat()->create();
        $targetSubsystem = AssetSubsystem::factory()->create();
        $part = SparePart::factory()->create([
            'code' => 'OLD-CODE',
            'source_key' => hash('sha256', 'Reorder Stock|imported-row'),
            'detail_equipment' => 'Old detail',
        ]);
        $sourceKey = $part->source_key;
        $payload = $this->payload($targetSubsystem, [
            'code' => '  NEW   CODE  ',
            'equipment' => '  Updated   equipment  ',
            'detail_equipment' => '  Updated   detail  ',
            'source_key' => 'client-overwrite',
            'is_active' => false,
        ]);
        $lockingSql = [];
        DB::listen(function (QueryExecuted $query) use (&$lockingSql): void {
            if (str_contains(mb_strtolower($query->sql), 'for update')) {
                $lockingSql[] = mb_strtolower($query->sql);
            }
        });

        $this->actingAs($pusat)
            ->put(route('admin.spare-parts.update', $part), $payload)
            ->assertRedirect(route('inventory', ['tab' => 'master']))
            ->assertSessionHas('success', 'Suku cadang berhasil diperbarui.');

        $part->refresh();
        $this->assertSame($targetSubsystem->id, $part->asset_subsystem_id);
        $this->assertSame('NEW CODE', $part->code);
        $this->assertSame($sourceKey, $part->source_key);
        $this->assertTrue($part->is_active);
        $audit = AuditLog::query()->where('action', 'spare_part.updated')->sole();
        $this->assertSame($pusat->id, $audit->actor_id);
        $this->assertSame('OLD-CODE', $audit->old_values['code']);
        $this->assertSame('NEW CODE', $audit->new_values['code']);
        $this->assertSame($sourceKey, $audit->old_values['source_key']);
        $this->assertSame($sourceKey, $audit->new_values['source_key']);
        $this->assertStringContainsString('from `spare_parts`', implode("\n", $lockingSql));
        $this->assertStringContainsString('from `asset_subsystems`', implode("\n", $lockingSql));
        $this->assertStringContainsString('from `asset_systems`', implode("\n", $lockingSql));
        $this->assertStringContainsString('from `asset_groups`', implode("\n", $lockingSql));

        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            $sql = mb_strtolower($query->sql);
            if (str_starts_with($sql, 'update `spare_parts`') || str_starts_with($sql, 'insert into `audit_logs`')) {
                $writes[] = $sql;
            }
        });

        Carbon::setTestNow($clock->addSecond());

        $this->actingAs($pusat)
            ->put(route('admin.spare-parts.update', $part), $payload)
            ->assertRedirect(route('inventory', ['tab' => 'master']))
            ->assertSessionHas('success', 'Data suku cadang tidak berubah.');

        $this->assertSame([], $writes);
        $this->assertSame(1, AuditLog::query()->where('action', 'spare_part.updated')->count());
    }

    public function test_pusat_deactivates_spare_part_without_deleting_history(): void
    {
        $pusat = User::factory()->pusat()->create();
        $part = SparePart::factory()->create();
        $stock = InventoryStock::factory()->for($part)->create();
        $movement = StockMovement::factory()->for($part)->for($stock->unitKerja)->create();

        $this->actingAs($pusat)
            ->delete(route('admin.spare-parts.destroy', $part))
            ->assertRedirect(route('inventory', ['tab' => 'master']))
            ->assertSessionHas('success', 'Suku cadang berhasil dinonaktifkan.');

        $part->refresh();
        $this->assertFalse($part->is_active);
        $this->assertNull($part->deleted_at);
        $this->assertDatabaseHas('inventory_stocks', ['id' => $stock->id, 'spare_part_id' => $part->id]);
        $this->assertDatabaseHas('stock_movements', ['id' => $movement->id, 'spare_part_id' => $part->id]);
        $audit = AuditLog::query()->where('action', 'spare_part.deactivated')->sole();
        $this->assertSame($pusat->id, $audit->actor_id);
        $this->assertTrue($audit->old_values['is_active']);
        $this->assertFalse($audit->new_values['is_active']);

        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            $sql = mb_strtolower($query->sql);
            if (str_starts_with($sql, 'update `spare_parts`') || str_starts_with($sql, 'insert into `audit_logs`')) {
                $writes[] = $sql;
            }
        });

        $this->actingAs($pusat)
            ->delete(route('admin.spare-parts.destroy', $part))
            ->assertRedirect(route('inventory', ['tab' => 'master']))
            ->assertSessionHas('success', 'Suku cadang sudah nonaktif.');

        $this->assertSame([], $writes);
        $this->assertSame(1, AuditLog::query()->where('action', 'spare_part.deactivated')->count());
    }

    public function test_guest_regional_and_inactive_users_cannot_mutate_global_spare_parts(): void
    {
        $subsystem = AssetSubsystem::factory()->create();
        $part = SparePart::factory()->create();
        $payload = $this->payload($subsystem, ['code' => 'AUTH-TEST']);

        $this->post(route('admin.spare-parts.store'), $payload)->assertRedirect('/login');
        $this->put(route('admin.spare-parts.update', $part), $payload)->assertRedirect('/login');
        $this->delete(route('admin.spare-parts.destroy', $part))->assertRedirect('/login');

        $regional = User::factory()->unit()->create();
        $this->actingAs($regional)->post(route('admin.spare-parts.store'), $payload)->assertForbidden();
        $this->actingAs($regional)->put(route('admin.spare-parts.update', $part), $payload)->assertForbidden();
        $this->actingAs($regional)->delete(route('admin.spare-parts.destroy', $part))->assertForbidden();

        $inactive = User::factory()->pusat()->inactive()->create();
        $this->actingAs($inactive)
            ->post(route('admin.spare-parts.store'), $payload)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username']);

        $this->assertSame(1, SparePart::query()->count());
        $this->assertTrue($part->fresh()->is_active);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_store_and_update_reject_inactive_or_deleted_category_hierarchies_without_writes(): void
    {
        $pusat = User::factory()->pusat()->create();

        $inactiveGroupSubsystem = AssetSubsystem::factory()->create();
        $inactiveGroupSubsystem->assetSystem->assetGroup->update(['is_active' => false]);
        $this->actingAs($pusat)
            ->post(
                route('admin.spare-parts.store'),
                $this->payload($inactiveGroupSubsystem, ['code' => 'INACTIVE-GROUP']),
            )
            ->assertSessionHasErrors([
                'asset_subsystem_id' => 'Subsistem aset atau kategori induknya tidak aktif atau tidak ditemukan.',
            ]);

        $deletedSystem = AssetSystem::factory()->create();
        $deletedSystemSubsystem = AssetSubsystem::factory()->for($deletedSystem)->create();
        $deletedSystem->delete();
        $this->actingAs($pusat)
            ->post(
                route('admin.spare-parts.store'),
                $this->payload($deletedSystemSubsystem, ['code' => 'DELETED-SYSTEM']),
            )
            ->assertSessionHasErrors([
                'asset_subsystem_id' => 'Subsistem aset atau kategori induknya tidak aktif atau tidak ditemukan.',
            ]);

        $inactiveSubsystem = AssetSubsystem::factory()->create(['is_active' => false]);
        $part = SparePart::factory()->create(['code' => 'UNCHANGED-CODE']);
        $this->actingAs($pusat)
            ->put(
                route('admin.spare-parts.update', $part),
                $this->payload($inactiveSubsystem, ['code' => 'MUST-NOT-CHANGE']),
            )
            ->assertSessionHasErrors([
                'asset_subsystem_id' => 'Subsistem aset tidak aktif, terhapus, atau tidak ditemukan.',
            ]);

        $this->assertSame('UNCHANGED-CODE', $part->fresh()->code);
        $this->assertDatabaseMissing('spare_parts', ['code' => 'INACTIVE-GROUP']);
        $this->assertDatabaseMissing('spare_parts', ['code' => 'DELETED-SYSTEM']);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_code_must_be_unique_across_existing_and_soft_deleted_spare_parts(): void
    {
        $pusat = User::factory()->pusat()->create();
        $subsystem = AssetSubsystem::factory()->create();
        SparePart::factory()->create(['code' => 'DUPLICATE']);
        $archived = SparePart::factory()->create(['code' => 'ARCHIVED']);
        $archived->delete();

        $this->actingAs($pusat)
            ->post(route('admin.spare-parts.store'), $this->payload($subsystem, ['code' => '  DUPLICATE  ']))
            ->assertSessionHasErrors(['code' => 'Kode suku cadang sudah digunakan.']);

        $this->actingAs($pusat)
            ->post(route('admin.spare-parts.store'), $this->payload($subsystem, ['code' => 'ARCHIVED']))
            ->assertSessionHasErrors(['code' => 'Kode suku cadang sudah digunakan.']);

        $this->assertSame(2, SparePart::withTrashed()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_manual_source_code_remains_reserved_after_the_spare_part_is_renamed(): void
    {
        $pusat = User::factory()->pusat()->create();
        $subsystem = AssetSubsystem::factory()->create();

        $this->actingAs($pusat)
            ->post(route('admin.spare-parts.store'), $this->payload($subsystem, ['code' => 'MANUAL-A']))
            ->assertRedirect(route('inventory', ['tab' => 'master']));

        $part = SparePart::query()->where('code', 'MANUAL-A')->sole();
        $sourceKey = hash('sha256', 'manual|MANUAL-A');
        $this->assertSame($sourceKey, $part->source_key);

        $this->actingAs($pusat)
            ->put(route('admin.spare-parts.update', $part), $this->payload($subsystem, ['code' => 'MANUAL-B']))
            ->assertRedirect(route('inventory', ['tab' => 'master']));

        $part->refresh();
        $this->assertSame('MANUAL-B', $part->code);
        $this->assertSame($sourceKey, $part->source_key);

        $this->actingAs($pusat)
            ->post(route('admin.spare-parts.store'), $this->payload($subsystem, ['code' => 'MANUAL-A']))
            ->assertSessionHasErrors([
                'code' => 'Kode suku cadang pernah digunakan sebagai identitas sumber dan tidak dapat dipakai ulang.',
            ]);

        $this->assertSame(1, SparePart::withTrashed()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'spare_part.created')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'spare_part.updated')->count());
    }

    public function test_validation_enforces_database_bounds_with_indonesian_messages(): void
    {
        $pusat = User::factory()->pusat()->create();
        $subsystem = AssetSubsystem::factory()->create();

        $response = $this->actingAs($pusat)->post(
            route('admin.spare-parts.store'),
            $this->payload($subsystem, [
                'code' => str_repeat('C', 51),
                'equipment' => str_repeat('E', 256),
                'detail_equipment' => '',
                'max_yearly_failure' => 100000000,
                'average_yearly_failure' => -0.01,
                'max_lead_time_months' => 'not-a-number',
                'average_lead_time_months' => '1.234',
                'safety_stock' => 4294967296,
                'lead_time_demand' => -1,
                'reorder_point' => 1.5,
                'severity' => 'Major',
                'unit_of_measure' => str_repeat('U', 31),
            ]),
        );

        $response->assertSessionHasErrors([
            'code',
            'equipment',
            'detail_equipment',
            'max_yearly_failure',
            'average_yearly_failure',
            'max_lead_time_months',
            'average_lead_time_months',
            'safety_stock',
            'lead_time_demand',
            'reorder_point',
            'severity',
            'unit_of_measure',
        ]);
        $response->assertSessionHasErrors([
            'code' => 'Kode suku cadang maksimal 50 karakter.',
            'max_yearly_failure' => 'Nilai melebihi batas penyimpanan.',
            'safety_stock' => 'Nilai melebihi batas penyimpanan.',
            'max_lead_time_months' => 'Nilai harus berupa angka.',
            'average_lead_time_months' => 'Nilai maksimal dua angka di belakang koma.',
            'severity' => 'Criticality harus Desirable, Essential, atau Vital.',
        ]);
        $this->assertSame(0, SparePart::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_non_string_text_values_are_rejected_without_normalization_errors(): void
    {
        $pusat = User::factory()->pusat()->create();
        $subsystem = AssetSubsystem::factory()->create();

        $this->actingAs($pusat)
            ->post(
                route('admin.spare-parts.store'),
                $this->payload($subsystem, [
                    'code' => ['SP-ARRAY'],
                    'equipment' => ['Equipment array'],
                    'detail_equipment' => ['Detail array'],
                    'severity' => ['Essential'],
                    'unit_of_measure' => ['unit'],
                ]),
            )
            ->assertSessionHasErrors([
                'code' => 'Kode suku cadang harus berupa teks.',
                'equipment' => 'Equipment harus berupa teks.',
                'detail_equipment' => 'Detail Equipment harus berupa teks.',
                'severity' => 'Criticality harus Desirable, Essential, atau Vital.',
                'unit_of_measure' => 'Satuan harus berupa teks.',
            ]);

        $this->assertSame(0, SparePart::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    /** @param array<string, mixed> $overrides */
    private function payload(AssetSubsystem $subsystem, array $overrides = []): array
    {
        return array_merge(
            [
                'asset_subsystem_id' => $subsystem->id,
                'code' => 'SP-VALID',
                'equipment' => 'Equipment',
                'detail_equipment' => 'Detail equipment',
                'max_yearly_failure' => '10.00',
                'average_yearly_failure' => '5.00',
                'max_lead_time_months' => '4.00',
                'average_lead_time_months' => '2.00',
                'safety_stock' => 5,
                'lead_time_demand' => 10,
                'reorder_point' => 15,
                'severity' => 'Essential',
                'unit_of_measure' => 'unit',
            ],
            $overrides,
        );
    }
}
