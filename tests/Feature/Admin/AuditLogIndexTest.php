<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditLogIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_pusat_can_view_paginated_audit_logs(): void
    {
        $pusat = User::factory()->pusat()->create();
        $unitUser = User::factory()->unit()->create();
        AuditLog::factory()->count(2)->create();

        $this->actingAs($unitUser)->get('/admin/audit-logs')->assertForbidden();

        $this->actingAs($pusat)->get('/admin/audit-logs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/AuditLogs/Index')
                ->has('logs.data', 2));
    }

    public function test_audit_log_index_supports_allow_listed_filters(): void
    {
        $pusat = User::factory()->pusat()->create();
        AuditLog::factory()->create(['action' => 'unit.created']);
        AuditLog::factory()->create(['action' => 'account.updated']);

        $this->actingAs($pusat)->get('/admin/audit-logs?action=unit.created')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.action', 'unit.created')
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'unit.created'));
    }
}
