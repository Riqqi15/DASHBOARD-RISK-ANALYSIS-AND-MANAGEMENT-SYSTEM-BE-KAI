# DAOP Demo Accounts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create deterministic local demo accounts `daop1` through `daop9`, each bound to the matching DAOP unit and using `daop1234`.

**Architecture:** A guarded, idempotent Laravel seeder reads an explicit demo-account configuration and upserts unit-role users after `UnitKerjaSeeder`. The weak demo credential is available only in `local` and `testing`, while UI-created passwords keep the existing 12-character minimum.

**Tech Stack:** Laravel 13, Eloquent, MySQL 8.4, PHPUnit 12, PHP password hashing casts.

---

## File Map

- Create `database/seeders/RegionalAccountSeeder.php`: guarded DAOP account upsert.
- Create `tests/Feature/RegionalAccountSeederTest.php`: account count, mapping, password, guard, and idempotency behavior.
- Modify `config/rams.php`: demo-account enable flag and password.
- Modify `.env.example`: safe local configuration example.
- Modify `database/seeders/DatabaseSeeder.php`: run regional seeder after units.
- Modify `README.md`: document local DAOP credentials and production warning.

### Task 1: Specify demo account behavior with failing tests

**Files:**
- Create: `tests/Feature/RegionalAccountSeederTest.php`

- [ ] **Step 1: Write the failing seeder tests**

```php
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

        $this->seed(RegionalAccountSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_seeder_does_nothing_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->seed(RegionalAccountSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }
}
```

- [ ] **Step 2: Run the focused test and verify RED**

Run:

```powershell
php artisan test tests/Feature/RegionalAccountSeederTest.php
```

Expected: FAIL because `Database\Seeders\RegionalAccountSeeder` does not exist.

- [ ] **Step 3: Commit the red test**

```powershell
git add tests/Feature/RegionalAccountSeederTest.php
git commit -m "test: specify DAOP demo accounts"
```

### Task 2: Implement the guarded, idempotent seeder

**Files:**
- Create: `database/seeders/RegionalAccountSeeder.php`
- Modify: `config/rams.php`
- Modify: `.env.example`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/RegionalAccountSeederTest.php`

- [ ] **Step 1: Add the explicit demo-account configuration**

Append to the array returned by `config/rams.php`:

```php
'demo_accounts' => [
    'enabled' => (bool) env('RAMS_SEED_DEMO_ACCOUNTS', false),
    'daop_password' => env('RAMS_DAOP_PASSWORD'),
],
```

Append to `.env.example`:

```dotenv
RAMS_SEED_DEMO_ACCOUNTS=true
RAMS_DAOP_PASSWORD=daop1234
```

- [ ] **Step 2: Implement `RegionalAccountSeeder`**

```php
<?php

namespace Database\Seeders;

use App\Enums\UnitType;
use App\Enums\UserRole;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class RegionalAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('rams.demo_accounts.enabled')) {
            return;
        }

        $password = config('rams.demo_accounts.daop_password');

        if (! is_string($password) || trim($password) === '') {
            throw new RuntimeException('Set RAMS_DAOP_PASSWORD before seeding demo DAOP accounts.');
        }

        UnitKerja::query()
            ->where('type', UnitType::Daop->value)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->each(function (UnitKerja $unit) use ($password): void {
                $number = Str::after($unit->code, 'DAOP-');

                User::query()->updateOrCreate(
                    ['username' => 'daop'.Str::lower($number)],
                    [
                        'name' => "Operator Daop {$number}",
                        'email' => null,
                        'password' => $password,
                        'role' => UserRole::Unit,
                        'unit_kerja_id' => $unit->id,
                        'is_active' => true,
                    ],
                );
            });
    }
}
```

- [ ] **Step 3: Register the seeder after unit creation**

Set `DatabaseSeeder::run()` to:

```php
public function run(): void
{
    $this->call([
        UnitKerjaSeeder::class,
        AdminUserSeeder::class,
        RegionalAccountSeeder::class,
    ]);
}
```

- [ ] **Step 4: Run the focused test and verify GREEN**

Run:

```powershell
php artisan test tests/Feature/RegionalAccountSeederTest.php
```

Expected: 3 tests pass; 9 accounts exist only when the environment and feature flag allow them.

- [ ] **Step 5: Run related account tests**

```powershell
php artisan test tests/Feature/AdminUserSeederTest.php tests/Feature/Admin/RegionalAccountManagementTest.php tests/Feature/RegionalAccountSeederTest.php
```

Expected: all tests pass; admin and UI-managed regional accounts retain existing behavior.

- [ ] **Step 6: Commit the implementation**

```powershell
git add .env.example config/rams.php database/seeders/DatabaseSeeder.php database/seeders/RegionalAccountSeeder.php tests/Feature/RegionalAccountSeederTest.php
git commit -m "feat: seed local DAOP accounts"
```

### Task 3: Document and apply local DAOP credentials

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Add the DAOP credential section**

Add after the Akun Pusat setup section:

````markdown
### Akun demo Daop

Seeder lokal membuat akun `daop1` sampai `daop9`. Setiap akun terikat pada Daop dengan nomor yang sama dan memakai password lokal `daop1234`.

```text
daop1 / daop1234
daop2 / daop1234
...
daop9 / daop1234
```

Akun ini hanya dibuat ketika `APP_ENV` adalah `local` atau `testing` dan `RAMS_SEED_DEMO_ACCOUNTS=true`. Jangan aktifkan kredensial demo pada production.
````

- [ ] **Step 2: Enable demo accounts in the ignored local `.env`**

Set these local values without committing `.env`:

```dotenv
RAMS_SEED_DEMO_ACCOUNTS=true
RAMS_DAOP_PASSWORD=daop1234
```

- [ ] **Step 3: Seed the development database without deleting data**

Run:

```powershell
php artisan optimize:clear
php artisan db:seed --class=RegionalAccountSeeder
```

Expected: command succeeds without running `migrate:fresh`.

- [ ] **Step 4: Verify all local accounts and passwords**

Run:

```powershell
php artisan tinker --execute="dump(App\Models\User::query()->where('role', 'unit')->where('username', 'like', 'daop%')->count());"
```

Expected: `9`.

Run:

```powershell
php artisan tinker --execute="dump(Illuminate\Support\Facades\Hash::check('daop1234', App\Models\User::query()->where('username', 'daop1')->value('password') ?? ''));"
```

Expected: `true`.

- [ ] **Step 5: Commit the documentation**

```powershell
git add README.md
git commit -m "docs: document DAOP demo logins"
```

- [ ] **Step 6: Run the complete backend suite**

```powershell
php artisan test
```

Expected: all PHPUnit tests pass on MySQL 8.4 test database.
