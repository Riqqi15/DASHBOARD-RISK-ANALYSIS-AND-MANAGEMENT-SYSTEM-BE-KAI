<?php

namespace Tests\Feature;

use App\Models\UnitKerja;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logger_records_actor_subject_unit_and_changes(): void
    {
        $actor = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create();

        $this->actingAs($actor);
        app(AuditLogger::class)->record(
            action: 'unit.created',
            subject: $unit,
            before: [],
            after: $unit->only(['code', 'name', 'type', 'is_active']),
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $actor->id,
            'action' => 'unit.created',
            'auditable_type' => UnitKerja::class,
            'auditable_id' => $unit->id,
            'unit_kerja_id' => $unit->id,
        ]);
    }

    public function test_logger_never_persists_sensitive_authentication_fields(): void
    {
        $actor = User::factory()->pusat()->create();

        $this->actingAs($actor);
        $audit = app(AuditLogger::class)->record(
            action: 'user.updated',
            subject: $actor,
            before: ['name' => 'Lama', 'password' => 'secret', 'remember_token' => 'token'],
            after: ['name' => 'Baru', 'password' => 'new-secret', 'session_payload' => 'payload'],
        );

        $this->assertSame(['name' => 'Lama'], $audit->old_values);
        $this->assertSame(['name' => 'Baru'], $audit->new_values);
    }
}
