<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
