# RAMS Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a tested RAMS foundation with MySQL 8.4, Laravel 13, Inertia 3, Vue 3, session authentication, two roles, unit management, regional-account management, and immutable audit records.

**Architecture:** Laravel owns routing, validation, authorization, sessions, business operations, and persistence. Inertia connects Laravel controllers to Vue pages in the same repository. Development and automated tests use separate MySQL 8.4 Docker services so tests never fall back to SQLite.

**Tech Stack:** PHP 8.3+, Laravel 13, PHPUnit 12, MySQL 8.4 LTS, Docker Compose, Inertia 3, Vue 3 Composition API, Vite 8, Tailwind CSS 4, Vitest, Vue Test Utils.

---

## Scope decomposition

This plan implements only the first vertical foundation slice from the approved design. Write separate design-aligned plans after this one for:

1. Master Aset and database-managed reference data.
2. Risk Matrix and versioned risk snapshots.
3. Failure logs and reliability calculations.
4. Predictive inventory and reorder stock.
5. One-time XLSM importer and issue reporting.
6. Dashboard, reports, exports, and full-system regression tests.

Do not add those modules while executing this plan.

## Execution amendment after frontend integration

Commit `2367e35` added an existing Inertia/Vue prototype after this plan was first written. Preserve its `domain`, `application`, `infrastructure`, and `presentation` directories. The implementation steps below remain authoritative for backend behavior, security, database structure, and tests, with these path adaptations:

- Keep page entry files in `resources/js/pages` and reusable UI in `resources/js/presentation`.
- Extend `resources/js/presentation/layouts/MainLayout.vue` instead of creating a second authenticated layout.
- Extend `resources/js/presentation/views/auth/LoginView.vue` instead of creating a duplicate login design.
- Replace mock authentication with Laravel session authentication during Task 4; do not keep role switching in production UI.
- Preserve prototype RAMS views and dummy repositories until their corresponding backend module plan replaces each data source.
- Use the existing KAI logo asset and refine the current interface rather than discarding the pulled frontend.

## Execution skills

Before implementation, invoke these skills when applicable:

- `using-git-worktrees` before creating an isolated implementation worktree.
- `subagent-driven-development` or `executing-plans` for plan execution.
- `test-driven-development` before each feature or bug fix.
- `frontend-design` before styling Vue pages.
- `systematic-debugging` for any unexpected failure.
- `verification-before-completion` before claiming the foundation complete.
- `requesting-code-review` before integration.

## File structure

### Environment

- Create `compose.yaml`: MySQL 8.4 development and test services.
- Modify `.env.example`: committed development variable template.
- Create `.env.testing.example`: committed test variable template.
- Modify `phpunit.xml`: force MySQL test connection.
- Modify `config/app.php`: read the committed application timezone variable.

### Inertia and frontend

- Modify `composer.json` and `composer.lock`: add the Laravel Inertia adapter.
- Modify `package.json` and `package-lock.json`: add Vue, Inertia, Vite integration, and component-test dependencies.
- Modify `vite.config.js`: register Vue and Inertia plugins.
- Replace `resources/js/app.js`: boot Inertia 3 and import application CSS.
- Modify `resources/css/app.css`: scan Vue sources and define the basic KAI theme tokens.
- Create `resources/views/app.blade.php`: minimal Inertia root.
- Delete `resources/views/welcome.blade.php`: remove the unused Laravel welcome page.
- Create `app/Http/Middleware/HandleInertiaRequests.php`: shared auth and flash props.
- Create `resources/js/Layouts/GuestLayout.vue`: login-page shell.
- Create `resources/js/Layouts/AppLayout.vue`: authenticated shell.
- Create `resources/js/Pages/Auth/Login.vue`: session login page.
- Create `resources/js/Pages/Dashboard.vue`: authenticated landing page.
- Create `resources/js/Pages/Admin/Units/*`: unit management pages and form.
- Create `resources/js/Pages/Admin/Accounts/*`: regional-account pages and form.
- Create `resources/js/Pages/Admin/AuditLogs/Index.vue`: read-only audit page.
- Create `resources/js/Components/FlashMessage.vue`: shared flash feedback.
- Create `vitest.config.js` and `tests/js/setup.js`: Vue component-test setup.

### Identity and organization

- Create `app/Enums/UserRole.php` and `app/Enums/UnitType.php`: authoritative string enums.
- Create `database/migrations/2026_07_27_000000_create_unit_kerjas_table.php`.
- Create `database/migrations/2026_07_27_000001_add_rams_fields_to_users_table.php`.
- Create `database/migrations/2026_07_27_000002_create_audit_logs_table.php`.
- Create `app/Models/UnitKerja.php` and `app/Models/AuditLog.php`.
- Modify `app/Models/User.php`: role, unit, status, helpers, and relationships.
- Create `database/factories/UnitKerjaFactory.php` and modify `database/factories/UserFactory.php`.
- Create `database/seeders/UnitKerjaSeeder.php` and `database/seeders/AdminUserSeeder.php`.
- Modify `database/seeders/DatabaseSeeder.php`.
- Create `config/rams.php`: initial Pusat account settings.

### Authentication and authorization

- Create `app/Http/Requests/Auth/LoginRequest.php`.
- Create `app/Http/Controllers/Auth/AuthenticatedSessionController.php`.
- Create `app/Http/Middleware/EnsureUserIsActive.php`.
- Create `app/Http/Middleware/EnsureUserIsPusat.php`.
- Modify `bootstrap/app.php`: Inertia middleware and aliases.
- Modify `routes/web.php`: guest, authenticated, and Pusat-only route groups.

### Admin operations

- Create `app/Services/AuditLogger.php`: explicit immutable audit writes.
- Create `app/Http/Controllers/Admin/UnitKerjaController.php`.
- Create `app/Http/Requests/Admin/StoreUnitKerjaRequest.php`.
- Create `app/Http/Requests/Admin/UpdateUnitKerjaRequest.php`.
- Create `app/Http/Controllers/Admin/RegionalAccountController.php`.
- Create `app/Http/Requests/Admin/StoreRegionalAccountRequest.php`.
- Create `app/Http/Requests/Admin/UpdateRegionalAccountRequest.php`.
- Create `app/Http/Requests/Admin/ResetRegionalAccountPasswordRequest.php`.
- Create `app/Http/Controllers/Admin/AuditLogController.php`.

### Tests and documentation

- Replace default example tests with focused PHPUnit feature tests under `tests/Feature`.
- Create Vue component tests under `tests/js`.
- Modify `README.md`: local setup, Docker, seeding, tests, and development commands.

## Task 1: Pin MySQL 8.4 for development and testing

**Files:**

- Create: `compose.yaml`
- Modify: `.env.example`
- Create: `.env.testing.example`
- Modify: `phpunit.xml`
- Modify: `config/app.php`

- [ ] **Step 1: Create the two MySQL services**

Create `compose.yaml`:

```yaml
services:
  mysql:
    image: mysql:8.4
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: ${DB_DATABASE:-rams}
      MYSQL_USER: ${DB_USERNAME:-rams}
      MYSQL_PASSWORD: ${DB_PASSWORD:-rams_local}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD:-rams_root_local}
      TZ: Asia/Jakarta
    ports:
      - "${DB_FORWARD_PORT:-3306}:3306"
    command:
      - --character-set-server=utf8mb4
      - --collation-server=utf8mb4_unicode_ci
      - --sql-mode=STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
    volumes:
      - rams_mysql_data:/var/lib/mysql
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h 127.0.0.1 -uroot -p$${MYSQL_ROOT_PASSWORD} --silent"]
      interval: 5s
      timeout: 5s
      retries: 20

  mysql-test:
    image: mysql:8.4
    profiles: ["test"]
    environment:
      MYSQL_DATABASE: rams_testing
      MYSQL_USER: rams_test
      MYSQL_PASSWORD: rams_test
      MYSQL_ROOT_PASSWORD: rams_test_root
      TZ: Asia/Jakarta
    ports:
      - "3307:3306"
    command:
      - --character-set-server=utf8mb4
      - --collation-server=utf8mb4_unicode_ci
      - --sql-mode=STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
    tmpfs:
      - /var/lib/mysql
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h 127.0.0.1 -uroot -p$${MYSQL_ROOT_PASSWORD} --silent"]
      interval: 3s
      timeout: 5s
      retries: 30

volumes:
  rams_mysql_data:
```

- [ ] **Step 2: Replace the SQLite template with MySQL variables**

Set this database section in `.env.example`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_FORWARD_PORT=3306
DB_DATABASE=rams
DB_USERNAME=rams
DB_PASSWORD=rams_local
DB_ROOT_PASSWORD=rams_root_local
```

Also set:

```dotenv
APP_NAME="KAI RAMS"
APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_FAKER_LOCALE=id_ID
APP_TIMEZONE=Asia/Jakarta
```

Create `.env.testing.example`:

```dotenv
APP_ENV=testing
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=rams_testing
DB_USERNAME=rams_test
DB_PASSWORD=rams_test

CACHE_STORE=array
MAIL_MAILER=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
BCRYPT_ROUNDS=4
```

Set the timezone entry in `config/app.php` to:

```php
'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
```

Before running tests locally, create the ignored test environment file and give it the same generated `APP_KEY` as `.env`:

```powershell
rtk proxy powershell -NoProfile -Command Copy-Item .env.testing.example .env.testing
```

- [ ] **Step 3: Force PHPUnit to use the MySQL test service**

Replace the SQLite entries in `phpunit.xml` with:

```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_HOST" value="127.0.0.1"/>
<env name="DB_PORT" value="3307"/>
<env name="DB_DATABASE" value="rams_testing"/>
<env name="DB_USERNAME" value="rams_test"/>
<env name="DB_PASSWORD" value="rams_test"/>
<env name="DB_URL" value=""/>
```

- [ ] **Step 4: Validate and start both database services**

Run:

```powershell
rtk docker compose config
rtk docker compose --profile test up -d --wait
rtk docker compose ps
```

Expected: both services report `healthy`; the development server binds port `3306` and the test server binds port `3307`.

- [ ] **Step 5: Verify the exact database version and strict mode**

Run:

```powershell
rtk docker compose exec mysql mysql -urams -prams_local -e "SELECT VERSION(), @@character_set_server, @@collation_server, @@sql_mode;" rams
rtk docker compose exec mysql-test mysql -urams_test -prams_test -e "SELECT VERSION(), DATABASE();" rams_testing
```

Expected: both versions start with `8.4`; the development connection reports `utf8mb4`, `utf8mb4_unicode_ci`, and strict mode; the test connection reports `rams_testing`.

- [ ] **Step 6: Commit the database environment**

```powershell
rtk git add compose.yaml .env.example .env.testing.example phpunit.xml config/app.php
rtk git commit -m "build: pin MySQL 8.4 environments"
```

## Task 2: Install and boot Inertia 3 with Vue 3

**Files:**

- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `package.json`
- Modify: `package-lock.json`
- Modify: `vite.config.js`
- Replace: `resources/js/app.js`
- Modify: `resources/css/app.css`
- Create: `resources/views/app.blade.php`
- Delete: `resources/views/welcome.blade.php`
- Create: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Create: `resources/js/Pages/Dashboard.vue`
- Test: `tests/Feature/InertiaShellTest.php`

- [ ] **Step 1: Install the official adapters and test libraries**

Run:

```powershell
rtk composer require inertiajs/inertia-laravel
rtk npm install vue @vitejs/plugin-vue @inertiajs/vue3 @inertiajs/vite
rtk npm install --save-dev vitest @vue/test-utils jsdom
rtk php artisan inertia:middleware
```

Expected: Composer and NPM complete successfully; Artisan creates `app/Http/Middleware/HandleInertiaRequests.php`.

- [ ] **Step 2: Add JavaScript test scripts**

Add these entries to `package.json` under `scripts`:

```json
"test:js": "vitest run --passWithNoTests",
"test:js:watch": "vitest"
```

- [ ] **Step 3: Write the failing Inertia shell test**

Create `tests/Feature/InertiaShellTest.php`:

```php
<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaShellTest extends TestCase
{
    public function test_root_renders_the_dashboard_inertia_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('applicationName', 'KAI RAMS'));
    }
}
```

- [ ] **Step 4: Run the test and verify the expected failure**

Run:

```powershell
rtk php artisan test tests/Feature/InertiaShellTest.php
```

Expected: FAIL because `/` still returns the Laravel welcome view.

- [ ] **Step 5: Configure Vite and the Inertia client**

Replace `vite.config.js` with:

```javascript
import inertia from '@inertiajs/vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/js/app.js'], refresh: true }),
        vue(),
        inertia(),
        tailwindcss(),
    ],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**', '**/.codex/**'],
        },
    },
});
```

Replace `resources/js/app.js` with:

```javascript
import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({
    title: (title) => (title ? `${title} - KAI RAMS` : 'KAI RAMS'),
});
```

Add this Vue source line to `resources/css/app.css`:

```css
@source '../js/**/*.vue';
```

- [ ] **Step 6: Create the Inertia root and middleware registration**

Create `resources/views/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @vite('resources/js/app.js')
        <x-inertia::head />
    </head>
    <body class="bg-slate-50 text-slate-900 antialiased">
        <x-inertia::app />
    </body>
</html>
```

Register the generated middleware in `bootstrap/app.php`:

```php
use App\Http\Middleware\HandleInertiaRequests;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [
        HandleInertiaRequests::class,
    ]);
})
```

- [ ] **Step 7: Add the first page and route**

Create `resources/js/Pages/Dashboard.vue`:

```vue
<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    applicationName: { type: String, required: true },
});
</script>

<template>
    <Head title="Dashboard" />
    <main class="mx-auto max-w-7xl p-6">
        <h1 class="text-2xl font-semibold">{{ applicationName }}</h1>
    </main>
</template>
```

Replace `routes/web.php` temporarily with:

```php
<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Dashboard', [
    'applicationName' => 'KAI RAMS',
]))->name('dashboard');
```

Delete `resources/views/welcome.blade.php`.

- [ ] **Step 8: Run backend, frontend, and build checks**

Run:

```powershell
rtk php artisan test tests/Feature/InertiaShellTest.php
rtk npm run build
rtk npm run test:js
```

Expected: PHPUnit passes; Vite builds; Vitest exits successfully with no test files because Task 2 temporarily uses `--passWithNoTests`. Task 10 removes that flag after adding component tests.

- [ ] **Step 9: Commit the Inertia shell**

```powershell
rtk git add composer.json composer.lock package.json package-lock.json vite.config.js resources app/Http/Middleware/HandleInertiaRequests.php bootstrap/app.php routes/web.php tests/Feature/InertiaShellTest.php
rtk git commit -m "feat: bootstrap Inertia 3 and Vue 3"
```

## Task 3: Model units, roles, and account status

**Files:**

- Create: `app/Enums/UserRole.php`
- Create: `app/Enums/UnitType.php`
- Create: `database/migrations/2026_07_27_000000_create_unit_kerjas_table.php`
- Create: `database/migrations/2026_07_27_000001_add_rams_fields_to_users_table.php`
- Create: `app/Models/UnitKerja.php`
- Modify: `app/Models/User.php`
- Create: `database/factories/UnitKerjaFactory.php`
- Modify: `database/factories/UserFactory.php`
- Create: `database/seeders/UnitKerjaSeeder.php`
- Create: `database/seeders/AdminUserSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Create: `config/rams.php`
- Modify: `.env.example`
- Test: `tests/Feature/OrganizationSchemaTest.php`

- [ ] **Step 1: Write the failing organization schema test**

Create `tests/Feature/OrganizationSchemaTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\UnitType;
use App\Enums\UserRole;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_units_and_user_roles_are_persisted_with_enum_casts(): void
    {
        $unit = UnitKerja::factory()->create(['type' => UnitType::Daop]);
        $user = User::factory()->unit($unit)->create();

        $this->assertSame(UnitType::Daop, $unit->fresh()->type);
        $this->assertSame(UserRole::Unit, $user->fresh()->role);
        $this->assertTrue($user->fresh()->is_active);
        $this->assertTrue($user->fresh()->unitKerja->is($unit));
    }

    public function test_unit_seeder_creates_all_thirteen_regional_units(): void
    {
        $this->seed(UnitKerjaSeeder::class);

        $this->assertDatabaseCount('unit_kerjas', 13);
        $this->assertDatabaseHas('unit_kerjas', ['code' => 'DAOP-1']);
        $this->assertDatabaseHas('unit_kerjas', ['code' => 'DIVRE-IV']);
    }
}
```

- [ ] **Step 2: Run the schema test and verify it fails**

```powershell
rtk php artisan test tests/Feature/OrganizationSchemaTest.php
```

Expected: FAIL because the enums, table, model, and factory states do not exist.

- [ ] **Step 3: Create authoritative enums**

Create `app/Enums/UserRole.php`:

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Pusat = 'pusat';
    case Unit = 'unit';
}
```

Create `app/Enums/UnitType.php`:

```php
<?php

namespace App\Enums;

enum UnitType: string
{
    case Daop = 'daop';
    case Divre = 'divre';
}
```

- [ ] **Step 4: Create the unit and user migrations**

Create `database/migrations/2026_07_27_000000_create_unit_kerjas_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('unit_kerjas', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 20)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_kerjas');
    }
};
```

Create `database/migrations/2026_07_27_000001_add_rams_fields_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('unit')->index()->after('email');
            $table->foreignId('unit_kerja_id')->nullable()->after('role')
                ->constrained('unit_kerjas')->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_kerja_id');
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
```

- [ ] **Step 5: Implement the models and factory states**

Create `app/Models/UnitKerja.php` with fillable `code`, `name`, `type`, and `is_active`; cast `type` to `UnitType` and `is_active` to boolean; add `users(): HasMany`; and use `HasFactory` plus `SoftDeletes`.

Update `app/Models/User.php` with this public contract:

```php
#[Fillable(['name', 'email', 'password', 'role', 'unit_kerja_id', 'is_active'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function isPusat(): bool
    {
        return $this->role === UserRole::Pusat;
    }

    public function isUnit(): bool
    {
        return $this->role === UserRole::Unit;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }
}
```

Create `database/factories/UnitKerjaFactory.php` with valid unique codes and active status. Add these states to `UserFactory`:

```php
// Add to definition():
'role' => UserRole::Unit,
'unit_kerja_id' => UnitKerja::factory(),
'is_active' => true,

public function pusat(): static
{
    return $this->state(fn () => [
        'role' => UserRole::Pusat,
        'unit_kerja_id' => null,
        'is_active' => true,
    ]);
}

public function unit(?UnitKerja $unit = null): static
{
    return $this->state(fn () => [
        'role' => UserRole::Unit,
        'unit_kerja_id' => $unit?->id ?? UnitKerja::factory(),
        'is_active' => true,
    ]);
}

public function inactive(): static
{
    return $this->state(fn () => ['is_active' => false]);
}
```

- [ ] **Step 6: Seed the regional units and initial Pusat account**

Create `UnitKerjaSeeder` with this fixed source list and `updateOrCreate` loop:

```php
$units = [
    ['code' => 'DAOP-1', 'name' => 'Daerah Operasi 1', 'type' => UnitType::Daop],
    ['code' => 'DAOP-2', 'name' => 'Daerah Operasi 2', 'type' => UnitType::Daop],
    ['code' => 'DAOP-3', 'name' => 'Daerah Operasi 3', 'type' => UnitType::Daop],
    ['code' => 'DAOP-4', 'name' => 'Daerah Operasi 4', 'type' => UnitType::Daop],
    ['code' => 'DAOP-5', 'name' => 'Daerah Operasi 5', 'type' => UnitType::Daop],
    ['code' => 'DAOP-6', 'name' => 'Daerah Operasi 6', 'type' => UnitType::Daop],
    ['code' => 'DAOP-7', 'name' => 'Daerah Operasi 7', 'type' => UnitType::Daop],
    ['code' => 'DAOP-8', 'name' => 'Daerah Operasi 8', 'type' => UnitType::Daop],
    ['code' => 'DAOP-9', 'name' => 'Daerah Operasi 9', 'type' => UnitType::Daop],
    ['code' => 'DIVRE-I', 'name' => 'Divisi Regional I', 'type' => UnitType::Divre],
    ['code' => 'DIVRE-II', 'name' => 'Divisi Regional II', 'type' => UnitType::Divre],
    ['code' => 'DIVRE-III', 'name' => 'Divisi Regional III', 'type' => UnitType::Divre],
    ['code' => 'DIVRE-IV', 'name' => 'Divisi Regional IV', 'type' => UnitType::Divre],
];

foreach ($units as $unit) {
    UnitKerja::query()->updateOrCreate(
        ['code' => $unit['code']],
        ['name' => $unit['name'], 'type' => $unit['type'], 'is_active' => true],
    );
}
```

Create `config/rams.php`:

```php
<?php

return [
    'admin' => [
        'name' => env('RAMS_ADMIN_NAME'),
        'email' => env('RAMS_ADMIN_EMAIL'),
        'password' => env('RAMS_ADMIN_PASSWORD'),
    ],
];
```

Create `AdminUserSeeder::run()`:

```php
$admin = config('rams.admin');

if (! $admin['name'] || ! $admin['email'] || ! $admin['password']) {
    throw new RuntimeException('Set RAMS_ADMIN_NAME, RAMS_ADMIN_EMAIL, and RAMS_ADMIN_PASSWORD before seeding.');
}

User::query()->updateOrCreate(
    ['email' => $admin['email']],
    [
        'name' => $admin['name'],
        'password' => $admin['password'],
        'role' => UserRole::Pusat,
        'unit_kerja_id' => null,
        'is_active' => true,
        'email_verified_at' => now(),
    ],
);
```

The model's `hashed` cast hashes the assigned password. `DatabaseSeeder::run()` calls:

```php
$this->call([
    UnitKerjaSeeder::class,
    AdminUserSeeder::class,
]);
```

Add blank variables to `.env.example`:

```dotenv
RAMS_ADMIN_NAME="Admin Pusat"
RAMS_ADMIN_EMAIL=
RAMS_ADMIN_PASSWORD=
```

- [ ] **Step 7: Run migrations, tests, and seeding**

Set non-empty `RAMS_ADMIN_EMAIL` and `RAMS_ADMIN_PASSWORD` in the existing ignored `.env`, then run:

```powershell
rtk php artisan migrate:fresh --seed
rtk php artisan test tests/Feature/OrganizationSchemaTest.php
```

Expected: development migrations and seed succeed; both organization tests pass against MySQL 8.4 on port `3307`.

- [ ] **Step 8: Commit the organization domain**

```powershell
rtk git add app/Enums app/Models database/migrations database/factories database/seeders config/rams.php .env.example tests/Feature/OrganizationSchemaTest.php
rtk git commit -m "feat: model RAMS units and user roles"
```

## Task 4: Implement session authentication and active-account enforcement

**Files:**

- Create: `app/Http/Requests/Auth/LoginRequest.php`
- Create: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Create: `app/Http/Middleware/EnsureUserIsActive.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Create: `resources/js/Layouts/GuestLayout.vue`
- Create: `resources/js/Pages/Auth/Login.vue`
- Modify: `resources/js/Pages/Dashboard.vue`
- Modify: `tests/Feature/InertiaShellTest.php`
- Test: `tests/Feature/Auth/AuthenticationTest.php`

- [ ] **Step 1: Write failing authentication tests**

Create `tests/Feature/Auth/AuthenticationTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_login_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_active_user_can_log_in_and_log_out(): void
    {
        $user = User::factory()->pusat()->create(['password' => 'secret-password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->pusat()->inactive()->create([
            'password' => 'secret-password',
        ]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_authenticated_session_is_revoked(): void
    {
        $user = User::factory()->pusat()->inactive()->create();

        $this->actingAs($user)->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }
}
```

- [ ] **Step 2: Run the authentication tests and verify failure**

```powershell
rtk php artisan test tests/Feature/Auth/AuthenticationTest.php
```

Expected: FAIL because login routes, controller, request, and active middleware do not exist.

- [ ] **Step 3: Implement the login request**

Create `app/Http/Requests/Auth/LoginRequest.php`:

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $authenticated = Auth::attempt([
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
            'is_active' => true,
        ], $this->boolean('remember'));

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('Email, kata sandi, atau status akun tidak valid.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));
        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('Terlalu banyak percobaan. Coba lagi dalam :seconds detik.', [
                'seconds' => $seconds,
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
```

- [ ] **Step 4: Implement the session controller and active middleware**

Create `AuthenticatedSessionController` with these methods:

```php
public function create(): Response
{
    return Inertia::render('Auth/Login');
}

public function store(LoginRequest $request): RedirectResponse
{
    $request->authenticate();
    $request->session()->regenerate();

    return redirect()->intended(route('dashboard', absolute: false));
}

public function destroy(Request $request): RedirectResponse
{
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
}
```

Create `app/Http/Middleware/EnsureUserIsActive.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if ($request->user() && ! $request->user()->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda sedang tidak aktif.',
            ]);
        }

        return $next($request);
    }
}
```

Register the alias in `bootstrap/app.php`:

```php
$middleware->alias([
    'active' => EnsureUserIsActive::class,
]);
```

- [ ] **Step 5: Define guest and authenticated routes**

Replace the temporary public routes with:

```php
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/', fn () => Inertia::render('Dashboard', [
        'applicationName' => 'KAI RAMS',
    ]))->name('dashboard');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
```

- [ ] **Step 6: Build the minimal login page**

Create `resources/js/Layouts/GuestLayout.vue` as a centered card with a `main` slot and application name. Create `resources/js/Pages/Auth/Login.vue` using `useForm`:

```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const form = useForm({ email: '', password: '', remember: false });

const submit = () => form.post('/login', {
    onFinish: () => form.reset('password'),
});
</script>

<template>
    <GuestLayout>
        <Head title="Masuk" />
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="email">Email</label>
                <input id="email" v-model="form.email" type="email" autocomplete="username" required>
                <p v-if="form.errors.email" role="alert">{{ form.errors.email }}</p>
            </div>
            <div>
                <label for="password">Kata sandi</label>
                <input id="password" v-model="form.password" type="password" autocomplete="current-password" required>
                <p v-if="form.errors.password" role="alert">{{ form.errors.password }}</p>
            </div>
            <label><input v-model="form.remember" type="checkbox"> Ingat saya</label>
            <button type="submit" :disabled="form.processing">Masuk</button>
        </form>
    </GuestLayout>
</template>
```

Update `InertiaShellTest` to create an active Pusat account and request `/` with `actingAs($user)`, because the root route is now protected.

- [ ] **Step 7: Run auth tests and the asset build**

```powershell
rtk php artisan test tests/Feature/Auth/AuthenticationTest.php
rtk npm run build
```

Expected: all authentication tests pass; Vite resolves the layouts and builds successfully.

- [ ] **Step 8: Commit session authentication**

```powershell
rtk git add app/Http bootstrap/app.php routes/web.php resources/js tests/Feature/Auth tests/Feature/InertiaShellTest.php
rtk git commit -m "feat: add session authentication"
```

## Task 5: Share authenticated state and add the application shell

**Files:**

- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Create: `resources/js/Layouts/AppLayout.vue`
- Create: `resources/js/Components/FlashMessage.vue`
- Modify: `resources/js/Pages/Dashboard.vue`
- Test: `tests/Feature/SharedInertiaDataTest.php`

- [ ] **Step 1: Write the failing shared-props test**

Create `tests/Feature/SharedInertiaDataTest.php`:

```php
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

        $this->actingAs($user)->get('/')
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

        $this->actingAs($user)->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.unit_kerja.id', $unit->id)
                ->where('auth.user.unit_kerja.code', $unit->code)
                ->where('auth.user.unit_kerja.name', $unit->name));
    }
}
```

- [ ] **Step 2: Verify the shared-props test fails**

```powershell
rtk php artisan test tests/Feature/SharedInertiaDataTest.php
```

Expected: FAIL because `auth.user` is not shared.

- [ ] **Step 3: Share only safe user and flash fields**

In `HandleInertiaRequests::share()`, merge these props with `parent::share($request)`:

```php
'auth' => [
    'user' => function () use ($request): ?array {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $user->loadMissing('unitKerja:id,code,name');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'unit_kerja_id' => $user->unit_kerja_id,
            'unit_kerja' => $user->unitKerja?->only(['id', 'code', 'name']),
            'is_active' => $user->is_active,
        ];
    },
],
'flash' => [
    'success' => fn () => $request->session()->get('success'),
    'error' => fn () => $request->session()->get('error'),
],
```

- [ ] **Step 4: Build the authenticated layout**

Create `FlashMessage.vue` with optional string props `success` and `error`; render success with `role="status"` and error with `role="alert"`. `AppLayout.vue` passes `$page.props.flash.success` and `$page.props.flash.error` into this component and contains:

- application name;
- authenticated user's name and unit;
- Dashboard link;
- Pusat-only links for Unit Kerja, Akun Wilayah, and Audit Log;
- a POST logout link;
- responsive main-content slot.

Use `$page.props.auth.user.role === 'pusat'` for display only. Server middleware remains the security boundary.

Wrap `Dashboard.vue` in `AppLayout` and show an explicit empty-state card instead of fabricated metrics.

- [ ] **Step 5: Run tests and build**

```powershell
rtk php artisan test tests/Feature/SharedInertiaDataTest.php tests/Feature/Auth/AuthenticationTest.php
rtk npm run build
```

Expected: all tests pass and Vite builds the authenticated shell.

- [ ] **Step 6: Commit the application shell**

```powershell
rtk git add app/Http/Middleware/HandleInertiaRequests.php resources/js tests/Feature/SharedInertiaDataTest.php
rtk git commit -m "feat: add authenticated application shell"
```

## Task 6: Enforce Pusat authorization and record immutable audits

**Files:**

- Create: `app/Http/Middleware/EnsureUserIsPusat.php`
- Modify: `bootstrap/app.php`
- Create: `database/migrations/2026_07_27_000002_create_audit_logs_table.php`
- Create: `app/Models/AuditLog.php`
- Create: `app/Services/AuditLogger.php`
- Test: `tests/Feature/Admin/PusatAuthorizationTest.php`
- Test: `tests/Feature/AuditLoggerTest.php`

- [ ] **Step 1: Write failing authorization and audit tests**

`PusatAuthorizationTest` registers a test route with `auth`, `active`, and `pusat` middleware, then asserts:

```php
$this->actingAs(User::factory()->unit()->create())
    ->get('/_test/pusat')
    ->assertForbidden();

$this->actingAs(User::factory()->pusat()->create())
    ->get('/_test/pusat')
    ->assertOk();
```

Create `AuditLoggerTest`:

```php
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
```

- [ ] **Step 2: Run both tests and verify failure**

```powershell
rtk php artisan test tests/Feature/Admin/PusatAuthorizationTest.php tests/Feature/AuditLoggerTest.php
```

Expected: FAIL because the middleware, table, model, and service do not exist.

- [ ] **Step 3: Implement Pusat middleware**

Create `EnsureUserIsPusat`:

```php
public function handle(Request $request, Closure $next): Response
{
    abort_unless($request->user()?->isPusat(), 403);

    return $next($request);
}
```

Register it in `bootstrap/app.php`:

```php
'pusat' => EnsureUserIsPusat::class,
```

- [ ] **Step 4: Create the immutable audit table and service**

The migration creates:

```php
$table->id();
$table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
$table->string('action', 100)->index();
$table->nullableMorphs('auditable');
$table->foreignId('unit_kerja_id')->nullable()->constrained('unit_kerjas')->nullOnDelete();
$table->json('old_values')->nullable();
$table->json('new_values')->nullable();
$table->string('ip_address', 45)->nullable();
$table->text('user_agent')->nullable();
$table->timestamp('created_at')->useCurrent();
```

`AuditLog` uses `HasFactory`, casts both JSON columns to arrays, and defines `actor(): BelongsTo`, `unitKerja(): BelongsTo`, and `auditable(): MorphTo`. The application exposes no update or delete operation for audit rows. `AuditLogger::record(string $action, Model $subject, array $before, array $after): AuditLog` derives actor, request metadata, morph type/id, and unit as follows:

```php
$unitId = $subject instanceof UnitKerja
    ? $subject->getKey()
    : ($subject->getAttribute('unit_kerja_id') ?? Auth::user()?->unit_kerja_id);
```

Insert the audit row with `AuditLog::query()->create(...)`. Never store password hashes, remember tokens, or session payloads in `old_values` or `new_values`.

- [ ] **Step 5: Run migrations and tests**

```powershell
rtk php artisan migrate
rtk php artisan test tests/Feature/Admin/PusatAuthorizationTest.php tests/Feature/AuditLoggerTest.php
```

Expected: all authorization and audit tests pass.

- [ ] **Step 6: Commit authorization and audit infrastructure**

```powershell
rtk git add app/Http/Middleware app/Models/AuditLog.php app/Services bootstrap/app.php database/migrations tests/Feature/Admin/PusatAuthorizationTest.php tests/Feature/AuditLoggerTest.php
rtk git commit -m "feat: enforce Pusat access and audit changes"
```

## Task 7: Deliver Unit Kerja management as a vertical slice

**Files:**

- Create: `app/Http/Controllers/Admin/UnitKerjaController.php`
- Create: `app/Http/Requests/Admin/StoreUnitKerjaRequest.php`
- Create: `app/Http/Requests/Admin/UpdateUnitKerjaRequest.php`
- Modify: `routes/web.php`
- Create: `resources/js/Pages/Admin/Units/Index.vue`
- Create: `resources/js/Pages/Admin/Units/Create.vue`
- Create: `resources/js/Pages/Admin/Units/Edit.vue`
- Create: `resources/js/Pages/Admin/Units/Partials/UnitForm.vue`
- Test: `tests/Feature/Admin/UnitKerjaManagementTest.php`

- [ ] **Step 1: Write failing Unit Kerja feature tests**

Create tests that prove:

```php
public function test_pusat_can_create_and_update_a_unit_with_audit_records(): void
{
    $pusat = User::factory()->pusat()->create();

    $this->actingAs($pusat)->post('/admin/units', [
        'code' => 'DAOP-X',
        'name' => 'Daerah Operasi X',
        'type' => 'daop',
        'is_active' => true,
    ])->assertRedirect('/admin/units');

    $unit = UnitKerja::query()->where('code', 'DAOP-X')->firstOrFail();
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'unit.created',
        'auditable_id' => $unit->id,
    ]);

    $this->actingAs($pusat)->put("/admin/units/{$unit->id}", [
        'code' => 'DAOP-X',
        'name' => 'Daerah Operasi Sepuluh',
        'type' => 'daop',
        'is_active' => false,
    ])->assertRedirect('/admin/units');

    $this->assertDatabaseHas('unit_kerjas', [
        'id' => $unit->id,
        'name' => 'Daerah Operasi Sepuluh',
        'is_active' => false,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'unit.updated',
        'auditable_id' => $unit->id,
    ]);
}

public function test_unit_account_cannot_access_unit_management(): void
{
    $user = User::factory()->unit()->create();

    $this->actingAs($user)->get('/admin/units')->assertForbidden();
    $this->actingAs($user)->post('/admin/units', [])->assertForbidden();
}

public function test_unit_code_must_be_unique_and_type_must_be_supported(): void
{
    $pusat = User::factory()->pusat()->create();
    UnitKerja::factory()->create(['code' => 'DAOP-1']);

    $this->actingAs($pusat)->post('/admin/units', [
        'code' => 'DAOP-1',
        'name' => 'Duplicate',
        'type' => 'unknown',
        'is_active' => true,
    ])->assertSessionHasErrors(['code', 'type']);
}
```

Also assert the index renders `Admin/Units/Index` with pagination and accepts `search`, `type`, and `status` filters.

- [ ] **Step 2: Run the Unit Kerja tests and verify failure**

```powershell
rtk php artisan test tests/Feature/Admin/UnitKerjaManagementTest.php
```

Expected: FAIL because the controller, requests, routes, and pages do not exist.

- [ ] **Step 3: Implement precise server validation**

`StoreUnitKerjaRequest` authorizes only `user()->isPusat()` and returns:

```php
[
    'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', 'unique:unit_kerjas,code'],
    'name' => ['required', 'string', 'max:255'],
    'type' => ['required', Rule::enum(UnitType::class)],
    'is_active' => ['required', 'boolean'],
]
```

`UpdateUnitKerjaRequest` uses the same rules, except:

```php
Rule::unique('unit_kerjas', 'code')->ignore($this->route('unit'))
```

Normalize `code` to uppercase in `prepareForValidation()`.

- [ ] **Step 4: Implement the resource controller with transactions**

`UnitKerjaController@index` applies these allow-listed filters:

```php
$units = UnitKerja::query()
    ->when($request->string('search')->toString(), fn ($query, $search) => $query
        ->where(fn ($nested) => $nested
            ->where('code', 'like', "%{$search}%")
            ->orWhere('name', 'like', "%{$search}%")))
    ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
    ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->boolean('status')))
    ->orderBy('type')
    ->orderBy('code')
    ->paginate(15)
    ->withQueryString();
```

Return `Inertia::render('Admin/Units/Index', ['units' => $units, 'filters' => ...])`. `create()` and `edit()` return their matching pages plus enum options.

`store()` and `update()` wrap the write and audit call in `DB::transaction()`. For update, capture `$before = $unit->only(['code', 'name', 'type', 'is_active'])` before `update()`. Redirect to `admin.units.index` with a Bahasa Indonesia success flash. Do not add destroy routes.

- [ ] **Step 5: Register the Pusat-only routes**

Inside `Route::middleware(['auth', 'active', 'pusat'])->prefix('admin')->name('admin.')->group(...)`, add:

```php
Route::resource('units', UnitKerjaController::class)
    ->parameters(['units' => 'unit'])
    ->except(['show', 'destroy']);
```

- [ ] **Step 6: Build the four Vue files**

`UnitForm.vue` accepts `unit` and `submitLabel`, uses `useForm`, and contains exactly `code`, `name`, `type`, and `is_active`. `Create.vue` POSTs `/admin/units`; `Edit.vue` PUTs `/admin/units/{id}`.

`Index.vue` must provide:

- search, type, and active-status filters;
- a paginated table with code, name, type, status, and edit action;
- a clear empty state;
- links that preserve filters;
- no delete action.

Use Inertia `<Link>` for navigation and `router.get('/admin/units', filters, { preserveState: true, replace: true })` for filters.

- [ ] **Step 7: Run the focused tests and production build**

```powershell
rtk php artisan test tests/Feature/Admin/UnitKerjaManagementTest.php tests/Feature/AuditLoggerTest.php
rtk npm run build
```

Expected: feature tests pass and all Unit Kerja pages compile.

- [ ] **Step 8: Commit the Unit Kerja slice**

```powershell
rtk git add app/Http/Controllers/Admin/UnitKerjaController.php app/Http/Requests/Admin routes/web.php resources/js/Pages/Admin/Units tests/Feature/Admin/UnitKerjaManagementTest.php
rtk git commit -m "feat: add Unit Kerja management"
```

## Task 8: Deliver regional-account management as a vertical slice

**Files:**

- Create: `app/Http/Controllers/Admin/RegionalAccountController.php`
- Create: `app/Http/Requests/Admin/StoreRegionalAccountRequest.php`
- Create: `app/Http/Requests/Admin/UpdateRegionalAccountRequest.php`
- Create: `app/Http/Requests/Admin/ResetRegionalAccountPasswordRequest.php`
- Modify: `routes/web.php`
- Create: `resources/js/Pages/Admin/Accounts/Index.vue`
- Create: `resources/js/Pages/Admin/Accounts/Create.vue`
- Create: `resources/js/Pages/Admin/Accounts/Edit.vue`
- Create: `resources/js/Pages/Admin/Accounts/Partials/AccountForm.vue`
- Create: `resources/js/Pages/Admin/Accounts/ResetPassword.vue`
- Test: `tests/Feature/Admin/RegionalAccountManagementTest.php`

- [ ] **Step 1: Write failing regional-account tests**

Cover all of these behaviors:

```php
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
}
```

Also prove that:

- inactive or missing units fail validation;
- email addresses are unique;
- a regional user receives `403` for every admin-account route;
- a Pusat user cannot target another Pusat account through these routes;
- password values and hashes never appear in audit JSON.

- [ ] **Step 2: Run the account tests and verify failure**

```powershell
rtk php artisan test tests/Feature/Admin/RegionalAccountManagementTest.php
```

Expected: FAIL because no account-management endpoints exist.

- [ ] **Step 3: Implement the three form requests**

All requests authorize only Pusat users.

Store rules:

```php
[
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'email', 'max:255', 'unique:users,email'],
    'unit_kerja_id' => [
        'required',
        Rule::exists('unit_kerjas', 'id')->where(fn ($query) => $query
            ->where('is_active', true)
            ->whereNull('deleted_at')),
    ],
    'password' => ['required', 'confirmed', Password::min(12)],
]
```

Update rules omit password and use `Rule::unique('users', 'email')->ignore($this->route('account'))`. Reset rules contain only confirmed `Password::min(12)`.

- [ ] **Step 4: Implement the controller and protect the target type**

Every action that accepts `User $account` starts with:

```php
abort_unless($account->isUnit(), 404);
```

The index query always includes `where('role', UserRole::Unit)` and supports allow-listed `search`, `unit_kerja_id`, and `status` filters. Eager-load `unitKerja:id,code,name` and paginate 15 rows.

Store assigns `role = UserRole::Unit` and `is_active = true` on the server. Update changes only name, email, and unit. Status changes only `is_active`. Password reset changes only password. Wrap each write and audit entry in a transaction. Audit action names are:

```text
account.created
account.updated
account.status_changed
account.password_reset
```

The password-reset audit stores only `['password_reset' => true]`.

- [ ] **Step 5: Register account routes**

Inside the existing Pusat route group:

```php
Route::resource('accounts', RegionalAccountController::class)
    ->except(['show', 'destroy']);
Route::patch('accounts/{account}/status', [RegionalAccountController::class, 'status'])
    ->name('accounts.status');
Route::get('accounts/{account}/password', [RegionalAccountController::class, 'editPassword'])
    ->name('accounts.password.edit');
Route::put('accounts/{account}/password', [RegionalAccountController::class, 'updatePassword'])
    ->name('accounts.password.update');
```

- [ ] **Step 6: Build the account pages**

The form contains name, email, active unit, and password fields only on creation. The index shows name, email, unit, status, edit, activate/deactivate, and reset-password actions. The reset page contains password and confirmation fields. There is no create-Pusat control and no delete action.

All forms use Inertia `useForm`, display server errors next to fields, disable duplicate submissions with `form.processing`, and show a confirmation dialog before status changes.

- [ ] **Step 7: Run account, auth, and audit regression tests**

```powershell
rtk php artisan test tests/Feature/Admin/RegionalAccountManagementTest.php tests/Feature/Auth/AuthenticationTest.php tests/Feature/AuditLoggerTest.php
rtk npm run build
```

Expected: all tests pass; account pages build successfully.

- [ ] **Step 8: Commit regional-account management**

```powershell
rtk git add app/Http/Controllers/Admin/RegionalAccountController.php app/Http/Requests/Admin routes/web.php resources/js/Pages/Admin/Accounts tests/Feature/Admin/RegionalAccountManagementTest.php
rtk git commit -m "feat: add regional account management"
```

## Task 9: Expose a read-only audit log to Akun Pusat

**Files:**

- Create: `app/Http/Controllers/Admin/AuditLogController.php`
- Create: `database/factories/AuditLogFactory.php`
- Modify: `routes/web.php`
- Create: `resources/js/Pages/Admin/AuditLogs/Index.vue`
- Test: `tests/Feature/Admin/AuditLogIndexTest.php`

- [ ] **Step 1: Write the failing audit-index test**

```php
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
```

Create `database/factories/AuditLogFactory.php`; its default subject is a regional user and its actor is a Pusat user. Set `action` to `account.updated`, both JSON payloads to safe profile fields, and `created_at` to the current time.

- [ ] **Step 2: Run the test and verify failure**

```powershell
rtk php artisan test tests/Feature/Admin/AuditLogIndexTest.php
```

Expected: FAIL because the route, controller, page, and factory do not exist.

- [ ] **Step 3: Implement the read-only query**

`AuditLogController` has only `__invoke(Request $request): Response`. It eager-loads `actor:id,name,email` and `unitKerja:id,code,name`, supports `action`, `unit_kerja_id`, and date-range filters, orders newest first, paginates 25 rows, and returns `Admin/AuditLogs/Index`.

Add only this route:

```php
Route::get('audit-logs', AuditLogController::class)->name('audit-logs.index');
```

Do not add store, update, or delete endpoints.

- [ ] **Step 4: Build the audit page**

The page shows timestamp, actor, action, subject type/id, unit, and expandable before/after JSON. It provides allow-listed filters and a clear empty state. Render JSON as escaped text, never with `v-html`.

- [ ] **Step 5: Run the test and build**

```powershell
rtk php artisan test tests/Feature/Admin/AuditLogIndexTest.php
rtk npm run build
```

Expected: test and build pass.

- [ ] **Step 6: Commit the read-only audit page**

```powershell
rtk git add app/Http/Controllers/Admin/AuditLogController.php database/factories/AuditLogFactory.php routes/web.php resources/js/Pages/Admin/AuditLogs tests/Feature/Admin/AuditLogIndexTest.php
rtk git commit -m "feat: add audit log viewer"
```

## Task 10: Add Vue component tests for critical forms and feedback

**Files:**

- Create: `vitest.config.js`
- Create: `tests/js/setup.js`
- Create: `tests/js/Login.test.js`
- Create: `tests/js/FlashMessage.test.js`
- Modify: `package.json`

- [ ] **Step 1: Configure Vitest**

Create `vitest.config.js`:

```javascript
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['tests/js/setup.js'],
        clearMocks: true,
    },
});
```

Create `tests/js/setup.js`:

```javascript
import { config } from '@vue/test-utils';

config.global.stubs = {
    Head: true,
    Link: true,
};
```

Set `test:js` to `vitest run` without `--passWithNoTests`.

- [ ] **Step 2: Write a failing login-form test**

Create `tests/js/Login.test.js`:

```javascript
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Login from '@/Pages/Auth/Login.vue';

const form = vi.hoisted(() => ({
    errors: {},
    post: vi.fn(),
    reset: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    useForm: (values) => ({
        ...values,
        errors: form.errors,
        processing: false,
        post: form.post,
        reset: form.reset,
    }),
}));

describe('Login', () => {
    beforeEach(() => {
        form.errors = {};
        form.post.mockReset();
        form.reset.mockReset();
    });

    it('submits credentials to the session endpoint', async () => {
        const wrapper = mount(Login);

        await wrapper.get('#email').setValue('admin@example.test');
        await wrapper.get('#password').setValue('secret-password');
        await wrapper.get('form').trigger('submit');

        expect(form.post).toHaveBeenCalledWith('/login', expect.objectContaining({
            onFinish: expect.any(Function),
        }));
    });

    it('renders a server email error accessibly', () => {
        form.errors = { email: 'Email tidak valid.' };

        const wrapper = mount(Login);

        expect(wrapper.get('[role="alert"]').text()).toContain('Email tidak valid.');
    });
});
```

Run:

```powershell
rtk npm run test:js -- Login.test.js
```

Expected: FAIL if the login page does not expose the required labels, errors, or submit behavior.

- [ ] **Step 3: Make the login page satisfy the component contract**

Adjust only accessibility labels, error roles, and form submission behavior required by the failing test. Do not restyle unrelated pages.

- [ ] **Step 4: Write and satisfy the flash-message test**

Create `tests/js/FlashMessage.test.js`:

```javascript
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FlashMessage from '@/Components/FlashMessage.vue';

describe('FlashMessage', () => {
    it('uses accessible roles for success and error feedback', () => {
        const wrapper = mount(FlashMessage, {
            props: { success: 'Tersimpan.', error: 'Gagal.' },
        });

        expect(wrapper.get('[role="status"]').text()).toBe('Tersimpan.');
        expect(wrapper.get('[role="alert"]').text()).toBe('Gagal.');
    });

    it('renders no feedback node when both messages are empty', () => {
        const wrapper = mount(FlashMessage, {
            props: { success: null, error: null },
        });

        expect(wrapper.find('[role="status"]').exists()).toBe(false);
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });
});
```

Run:

```powershell
rtk npm run test:js -- FlashMessage.test.js
```

Expected: PASS after the component renders both states safely.

- [ ] **Step 5: Run the complete frontend test suite and build**

```powershell
rtk npm run test:js
rtk npm run build
```

Expected: all component tests pass and Vite produces the production bundle.

- [ ] **Step 6: Commit frontend tests**

```powershell
rtk git add vitest.config.js tests/js package.json package-lock.json resources/js/Pages/Auth/Login.vue resources/js/Components/FlashMessage.vue
rtk git commit -m "test: cover critical Vue interactions"
```

## Task 11: Document setup and run the foundation verification gate

**Files:**

- Modify: `README.md`
- Delete: `tests/Feature/ExampleTest.php`
- Delete: `tests/Unit/ExampleTest.php`

- [ ] **Step 1: Replace the default README**

Document these exact sections:

1. Project purpose and approved architecture.
2. Prerequisites: PHP, Composer, Node/NPM, Docker Desktop, and VS Code.
3. First setup: copy `.env.example`, generate key, set initial admin variables, start both MySQL services, migrate, and seed.
4. Development: `composer run dev` and `docker compose up -d`.
5. Testing: start the `test` profile, run PHPUnit and Vitest.
6. Account rules and unit isolation.
7. Links to the approved design and this plan.

Use commands that work in PowerShell and state that local secrets remain in `.env`.

- [ ] **Step 2: Remove meaningless example tests**

Delete the two default `ExampleTest.php` files after the focused suites cover the root page and application behavior.

- [ ] **Step 3: Rebuild both databases from scratch**

Run:

```powershell
rtk docker compose --profile test up -d --wait
rtk php artisan migrate:fresh --seed
rtk php artisan migrate:fresh --env=testing
```

Expected: both MySQL 8.4 schemas rebuild without errors; development seeding creates 13 units and one configured Pusat account.

- [ ] **Step 4: Run backend quality and regression checks**

```powershell
rtk php vendor/bin/pint --test
rtk php artisan test
rtk php artisan route:list
```

Expected: Pint reports no style changes needed; all PHPUnit tests pass against MySQL 8.4; routes show login/logout, Dashboard, Unit Kerja, regional accounts, and read-only audit logs with the intended middleware.

- [ ] **Step 5: Run frontend quality checks**

```powershell
rtk npm run test:js
rtk npm run build
```

Expected: all Vitest tests pass and the production bundle builds without warnings that indicate missing pages or unresolved imports.

- [ ] **Step 6: Verify database identity and repository scope**

```powershell
rtk docker compose exec mysql mysql -urams -prams_local -e "SELECT VERSION(), DATABASE();" rams
rtk docker compose exec mysql-test mysql -urams_test -prams_test -e "SELECT VERSION(), DATABASE();" rams_testing
rtk git status --short
rtk git diff --check
```

Expected: both report MySQL `8.4.x` and the correct database; no unplanned files or whitespace errors remain.

- [ ] **Step 7: Commit the documentation and verification cleanup**

```powershell
rtk git add README.md tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php
rtk git commit -m "docs: document RAMS foundation workflow"
```

## Completion checkpoint

Do not start the Master Aset plan until all Task 11 checks pass and a code review confirms:

- MySQL 8.4 serves development and tests.
- Login uses Laravel sessions with CSRF protection and throttling.
- inactive sessions are revoked;
- regional accounts cannot enter Pusat routes;
- Pusat can manage units and regional accounts only;
- account creation never exposes public registration;
- all material writes create audit records without password data;
- Vue pages build and critical components pass Vitest;
- the repository contains no SQLite test override.

After this checkpoint, use the approved design to write `docs/superpowers/plans/YYYY-MM-DD-rams-master-aset-implementation-plan.md` before implementing Master Aset.

## Primary implementation references

- Laravel 13 starter kits and the Vue/Inertia architecture: <https://laravel.com/docs/13.x/starter-kits>
- Laravel 13 browser authentication: <https://laravel.com/docs/13.x/authentication>
- Laravel 13 database testing: <https://laravel.com/docs/13.x/database-testing>
- Inertia 3 Laravel server setup: <https://inertiajs.com/docs/v3/installation/server-side-setup>
- Inertia 3 Vue client setup: <https://inertiajs.com/docs/v3/installation/client-side-setup>
