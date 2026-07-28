<?php

namespace Tests\Feature;

use App\Models\UnitKerja;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
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

    public function test_logger_can_record_an_explicit_actor_without_an_authenticated_session(): void
    {
        $actor = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create();

        $audit = app(AuditLogger::class)->record(
            action: 'unit.updated',
            subject: $unit,
            before: ['name' => 'Lama'],
            after: ['name' => 'Baru'],
            actor: $actor,
        );

        $this->assertSame($actor->id, $audit->actor_id);
        $this->assertGuest();
    }

    public function test_logger_rejects_an_explicit_unsaved_actor_instead_of_falling_back_to_auth(): void
    {
        $authenticatedActor = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create();
        $this->actingAs($authenticatedActor);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Actor audit eksplisit harus sudah tersimpan.');

        try {
            app(AuditLogger::class)->record('unit.updated', $unit, [], ['name' => 'Baru'], new User([
                'name' => 'Belum tersimpan',
                'username' => 'unsaved.actor',
            ]));
        } finally {
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }
}
