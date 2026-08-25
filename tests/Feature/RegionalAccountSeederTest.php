<?php

namespace Tests\Feature;

use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\RegionalAccountSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegionalAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('rams.demo_accounts', [
            'enabled' => true,
            'daop_password' => 'daop1234',
        ]);

        $this->seed(UnitKerjaSeeder::class);
    }

    public function test_seeder_creates_one_login_for_each_daop(): void
    {
        $this->seed(RegionalAccountSeeder::class);
        $this->seed(RegionalAccountSeeder::class);

        $this->assertDatabaseCount('users', 9);

        foreach (range(1, 9) as $number) {
            $unit = UnitKerja::query()->where('code', "DAOP-{$number}")->sole();
            $account = User::query()->where('username', "daop{$number}")->sole();

            $this->assertSame("Operator Daop {$number}", $account->name);
            $this->assertSame('unit', $account->role->value);
            $this->assertSame($unit->id, $account->unit_kerja_id);
            $this->assertNull($account->email);
            $this->assertTrue($account->is_active);
            $this->assertTrue(Hash::check('daop1234', $account->password));
        }
    }

    public function test_seeder_does_nothing_when_demo_accounts_are_disabled(): void
    {
        config()->set('rams.demo_accounts.enabled', false);

        app(RegionalAccountSeeder::class)->run();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_seeder_creates_daop_accounts_in_uat_when_enabled(): void
    {
        $this->app->detectEnvironment(fn (): string => 'uat');

        app(RegionalAccountSeeder::class)->run();

        $this->assertDatabaseCount('users', 9);
    }

    public function test_seeder_does_nothing_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        app(RegionalAccountSeeder::class)->run();

        $this->assertDatabaseCount('users', 0);
    }
}
