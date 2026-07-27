# Master Assets Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Master Aset dummy repository with a region-scoped, audited MySQL CRUD and a repeatable import from the five RAMS workbooks.

**Architecture:** Laravel owns persistence, validation, authorization, filtering, imports, and audit events. Inertia sends paginated assets and form options to focused Vue pages; imported rows carry a deterministic `source_key` so repeated imports update source fields while preserving user-edited names, locations, and statuses.

**Tech Stack:** Laravel 13, Eloquent, MySQL 8.4, Inertia.js 3, Vue 3, Tailwind CSS 4, PhpSpreadsheet 5.9, PHPUnit 12, Vitest 4.

---

## File Map

### Persistence and domain

- Create `database/migrations/2026_07_27_000004_create_assets_table.php`: asset schema, indexes, foreign key, soft delete.
- Create `app/Enums/AssetStatus.php`: three allowed status values and labels.
- Create `app/Models/Asset.php`: casts, relationships, visibility/search scopes.
- Create `database/factories/AssetFactory.php`: realistic test data.
- Modify `app/Models/UnitKerja.php`: `assets()` relationship.

### HTTP and authorization

- Create `app/Policies/AssetPolicy.php`: Pusat/global and Unit/regional authorization.
- Create `app/Http/Requests/AssetDataRequest.php`: common normalization, validation, and unit derivation.
- Create `app/Http/Requests/StoreAssetRequest.php`: create request type.
- Create `app/Http/Requests/UpdateAssetRequest.php`: update request type.
- Create `app/Http/Controllers/MasterAssetController.php`: Inertia list/forms and audited mutations.
- Modify `routes/web.php`: replace prototype closure with resource routes at `/master-asset`.

### Excel import

- Modify `composer.json` and `composer.lock`: add `phpoffice/phpspreadsheet:^5.9`.
- Create `app/Services/MasterAssetWorkbookImporter.php`: validate and import one workbook transactionally.
- Create `app/Console/Commands/ImportMasterAssets.php`: CLI interface and summary.

### Vue presentation

- Replace `resources/js/pages/master-data/assets/MasterAsset.vue`: server-driven index.
- Create `resources/js/pages/master-data/assets/Create.vue`: create screen.
- Create `resources/js/pages/master-data/assets/Edit.vue`: edit screen.
- Create `resources/js/pages/master-data/assets/Partials/AssetForm.vue`: shared form.
- Create `resources/js/pages/master-data/assets/Partials/DeleteAssetDialog.vue`: accessible confirmation.
- Modify `resources/js/layouts/MainLayout.vue`: keep navigation aligned with resource URL.
- Stop using `resources/js/infrastructure/dummy-data/assets.json`, `mock-asset.repository.js`, `asset.model.js`, `i-asset.repository.js`, and `get-assets.use-case.js` from Master Aset; delete files only when no other import references remain.

### Tests and documentation

- Create `tests/Feature/MasterAssetSchemaTest.php`.
- Create `tests/Feature/MasterAssetAuthorizationTest.php`.
- Create `tests/Feature/MasterAssetManagementTest.php`.
- Create `tests/Feature/ImportMasterAssetsTest.php`.
- Create `tests/js/MasterAsset.test.js`.
- Create `tests/js/AssetForm.test.js`.
- Modify `README.md`: CRUD and workbook import commands.

### Task 1: Persist the Master Aset core model

**Files:**
- Create: `tests/Feature/MasterAssetSchemaTest.php`
- Create: `database/migrations/2026_07_27_000004_create_assets_table.php`
- Create: `app/Enums/AssetStatus.php`
- Create: `app/Models/Asset.php`
- Create: `database/factories/AssetFactory.php`
- Modify: `app/Models/UnitKerja.php`

- [ ] **Step 1: Write the failing schema and model test**

```php
<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MasterAssetSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_assets_store_core_data_and_support_soft_delete(): void
    {
        $unit = UnitKerja::factory()->create();
        $asset = Asset::factory()->for($unit)->create([
            'nama_aset' => 'Track Circuit Gambir',
            'status' => AssetStatus::DalamPerbaikan,
            'tanggal_pemasangan' => '2019-06-10',
        ]);

        $this->assertTrue(Schema::hasColumns('assets', [
            'unit_kerja_id',
            'nama_aset',
            'aset_prasarana_sintel',
            'system',
            'subsystem',
            'lokasi',
            'jumlah_unit',
            'tanggal_pemasangan',
            'status',
            'source_key',
            'deleted_at',
        ]));
        $this->assertSame(AssetStatus::DalamPerbaikan, $asset->status);
        $this->assertSame('2019-06-10', $asset->tanggal_pemasangan->toDateString());

        $asset->delete();

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }
}
```

- [ ] **Step 2: Run the test and verify RED**

```powershell
php artisan test tests/Feature/MasterAssetSchemaTest.php
```

Expected: FAIL because `App\Models\Asset` and the `assets` table do not exist.

- [ ] **Step 3: Create the status enum**

```php
<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Aktif = 'aktif';
    case Nonaktif = 'nonaktif';
    case DalamPerbaikan = 'dalam_perbaikan';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Nonaktif => 'Nonaktif',
            self::DalamPerbaikan => 'Dalam perbaikan',
        };
    }
}
```

- [ ] **Step 4: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_kerja_id')->constrained()->restrictOnDelete();
            $table->string('nama_aset');
            $table->string('aset_prasarana_sintel');
            $table->string('system');
            $table->string('subsystem');
            $table->string('lokasi')->nullable();
            $table->unsignedInteger('jumlah_unit')->default(0);
            $table->date('tanggal_pemasangan')->nullable();
            $table->string('status', 32)->default('aktif');
            $table->char('source_key', 64)->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['unit_kerja_id', 'status']);
            $table->index(['unit_kerja_id', 'system', 'subsystem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
```

- [ ] **Step 5: Create the model and factory**

```php
<?php

namespace App\Models;

use App\Enums\AssetStatus;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'unit_kerja_id',
    'nama_aset',
    'aset_prasarana_sintel',
    'system',
    'subsystem',
    'lokasi',
    'jumlah_unit',
    'tanggal_pemasangan',
    'status',
    'source_key',
])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, SoftDeletes;

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    protected function casts(): array
    {
        return [
            'jumlah_unit' => 'integer',
            'tanggal_pemasangan' => 'date',
            'status' => AssetStatus::class,
        ];
    }
}
```

```php
<?php

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Models\UnitKerja;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_kerja_id' => UnitKerja::factory(),
            'nama_aset' => fake()->words(3, true),
            'aset_prasarana_sintel' => 'PERALATAN LUAR SINYAL ELEKTRIK',
            'system' => 'PERAGA SINYAL ELEKTRIK',
            'subsystem' => fake()->randomElement(['TRACK CIRCUIT', 'AXLE COUNTER']),
            'lokasi' => fake()->optional()->city(),
            'jumlah_unit' => fake()->numberBetween(0, 100),
            'tanggal_pemasangan' => fake()->optional()->dateTimeBetween('-20 years'),
            'status' => AssetStatus::Aktif,
            'source_key' => null,
        ];
    }
}
```

Add to `UnitKerja`:

```php
public function assets(): HasMany
{
    return $this->hasMany(Asset::class);
}
```

- [ ] **Step 6: Run the focused test and verify GREEN**

```powershell
php artisan test tests/Feature/MasterAssetSchemaTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit persistence**

```powershell
git add app/Enums/AssetStatus.php app/Models/Asset.php app/Models/UnitKerja.php database/factories/AssetFactory.php database/migrations/2026_07_27_000004_create_assets_table.php tests/Feature/MasterAssetSchemaTest.php
git commit -m "feat: model master assets"
```

### Task 2: Enforce regional visibility and authorization

**Files:**
- Create: `tests/Feature/MasterAssetAuthorizationTest.php`
- Create: `app/Policies/AssetPolicy.php`
- Modify: `app/Models/Asset.php`

- [ ] **Step 1: Write failing scope and policy tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MasterAssetAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pusat_sees_all_assets_while_unit_sees_only_its_own(): void
    {
        $firstUnit = UnitKerja::factory()->create();
        $secondUnit = UnitKerja::factory()->create();
        $firstAsset = Asset::factory()->for($firstUnit)->create();
        $secondAsset = Asset::factory()->for($secondUnit)->create();
        $pusat = User::factory()->pusat()->create();
        $regional = User::factory()->unit($firstUnit)->create();

        $this->assertEqualsCanonicalizing(
            [$firstAsset->id, $secondAsset->id],
            Asset::query()->visibleTo($pusat)->pluck('id')->all(),
        );
        $this->assertSame(
            [$firstAsset->id],
            Asset::query()->visibleTo($regional)->pluck('id')->all(),
        );
    }

    public function test_policy_rejects_an_asset_from_another_unit(): void
    {
        $ownerUnit = UnitKerja::factory()->create();
        $otherUnit = UnitKerja::factory()->create();
        $asset = Asset::factory()->for($ownerUnit)->create();
        $owner = User::factory()->unit($ownerUnit)->create();
        $outsider = User::factory()->unit($otherUnit)->create();

        $this->assertTrue(Gate::forUser($owner)->allows('update', $asset));
        $this->assertFalse(Gate::forUser($outsider)->allows('update', $asset));
        $this->assertTrue(Gate::forUser(User::factory()->pusat()->create())->allows('delete', $asset));
    }
}
```

- [ ] **Step 2: Verify RED**

```powershell
php artisan test tests/Feature/MasterAssetAuthorizationTest.php
```

Expected: FAIL because `visibleTo()` and `AssetPolicy` do not exist.

- [ ] **Step 3: Add the visibility and search scopes to `Asset`**

```php
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

public function scopeVisibleTo(Builder $query, User $user): Builder
{
    return $query->when($user->isUnit(), fn (Builder $visible) => $visible->where('unit_kerja_id', $user->unit_kerja_id));
}

public function scopeSearch(Builder $query, ?string $search): Builder
{
    return $query->when($search, fn (Builder $assets, string $term) => $assets->where(
        fn (Builder $fields) => $fields
            ->where('nama_aset', 'like', "%{$term}%")
            ->orWhere('system', 'like', "%{$term}%")
            ->orWhere('subsystem', 'like', "%{$term}%")
            ->orWhere('lokasi', 'like', "%{$term}%"),
    ));
}
```

- [ ] **Step 4: Create `AssetPolicy`**

```php
<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPusat() || ($user->isUnit() && $user->unit_kerja_id !== null);
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->isPusat() || $asset->unit_kerja_id === $user->unit_kerja_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $this->view($user, $asset);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $this->view($user, $asset);
    }
}
```

- [ ] **Step 5: Verify GREEN and commit**

```powershell
php artisan test tests/Feature/MasterAssetAuthorizationTest.php
git add app/Models/Asset.php app/Policies/AssetPolicy.php tests/Feature/MasterAssetAuthorizationTest.php
git commit -m "feat: scope assets by unit"
```

Expected: both tests pass before commit.

### Task 3: Serve the filtered Master Aset index from Laravel

**Files:**
- Create: `tests/Feature/MasterAssetManagementTest.php`
- Create: `app/Http/Controllers/MasterAssetController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write the failing index test**

```php
<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MasterAssetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_paginated_filtered_and_scoped_to_the_user(): void
    {
        $ownUnit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
        $otherUnit = UnitKerja::factory()->create(['code' => 'DAOP-2']);
        $user = User::factory()->unit($ownUnit)->create();
        Asset::factory()->for($ownUnit)->create([
            'nama_aset' => 'Track Circuit Gambir',
            'jumlah_unit' => 12,
            'status' => AssetStatus::Aktif,
        ]);
        Asset::factory()->for($ownUnit)->create(['nama_aset' => 'Axle Counter']);
        Asset::factory()->for($otherUnit)->create(['nama_aset' => 'Track Circuit Cirebon']);

        $this->actingAs($user)->get('/master-asset?search=Gambir&status=aktif')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('master-data/assets/MasterAsset')
                ->has('assets.data', 1)
                ->where('assets.data.0.nama_aset', 'Track Circuit Gambir')
                ->where('stats.total_assets', 1)
                ->where('stats.total_units', 12)
                ->where('filters.search', 'Gambir')
                ->where('filters.status', 'aktif')
                ->where('can.choose_unit', false));
    }

    public function test_pusat_can_filter_assets_by_unit(): void
    {
        $pusat = User::factory()->pusat()->create();
        $unit = UnitKerja::factory()->create();
        Asset::factory()->for($unit)->create();
        Asset::factory()->create();

        $this->actingAs($pusat)->get("/master-asset?unit_kerja_id={$unit->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('assets.data', 1)
                ->where('can.choose_unit', true)
                ->has('units'));
    }
}
```

- [ ] **Step 2: Verify RED**

```powershell
php artisan test tests/Feature/MasterAssetManagementTest.php --filter=index
```

Expected: FAIL because `/master-asset` still renders the prototype without props.

- [ ] **Step 3: Implement the index controller**

Create `MasterAssetController` with this index method and private base query:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\UnitKerja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MasterAssetController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Asset::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $unitId = $request->filled('unit_kerja_id') ? $request->integer('unit_kerja_id') : null;
        $query = $this->filteredQuery($request, $search, $status, $unitId);

        $stats = [
            'total_assets' => (clone $query)->count(),
            'total_units' => (int) (clone $query)->sum('jumlah_unit'),
            'active_assets' => (clone $query)->where('status', AssetStatus::Aktif->value)->count(),
            'unique_subsystems' => (clone $query)->distinct()->count('subsystem'),
        ];

        $assets = $query
            ->with('unitKerja:id,code,name')
            ->orderBy('unit_kerja_id')
            ->orderBy('system')
            ->orderBy('subsystem')
            ->orderBy('nama_aset')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('master-data/assets/MasterAsset', [
            'assets' => $assets,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'unit_kerja_id' => $unitId ? (string) $unitId : '',
            ],
            'units' => $request->user()->isPusat()
                ? UnitKerja::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])
                : [],
            'statusOptions' => collect(AssetStatus::cases())->map(fn (AssetStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->all(),
            'can' => ['choose_unit' => $request->user()->isPusat()],
        ]);
    }

    private function filteredQuery(Request $request, string $search, string $status, ?int $unitId): Builder
    {
        return Asset::query()
            ->visibleTo($request->user())
            ->search($search)
            ->when(AssetStatus::tryFrom($status), fn (Builder $query, AssetStatus $validStatus) => $query->where('status', $validStatus->value))
            ->when($request->user()->isPusat() && $unitId, fn (Builder $query) => $query->where('unit_kerja_id', $unitId));
    }
}
```

- [ ] **Step 4: Replace the prototype route with resource routing**

Add the controller import, remove the closure route, and add inside the authenticated group:

```php
Route::resource('master-asset', MasterAssetController::class)
    ->parameters(['master-asset' => 'asset'])
    ->except(['show'])
    ->names('master-assets');
```

- [ ] **Step 5: Verify GREEN and commit**

```powershell
php artisan test tests/Feature/MasterAssetManagementTest.php --filter=index
git add app/Http/Controllers/MasterAssetController.php routes/web.php tests/Feature/MasterAssetManagementTest.php
git commit -m "feat: list filtered master assets"
```

Expected: both index tests pass before commit.

### Task 4: Create, update, and soft-delete assets safely

**Files:**
- Create: `app/Http/Requests/AssetDataRequest.php`
- Create: `app/Http/Requests/StoreAssetRequest.php`
- Create: `app/Http/Requests/UpdateAssetRequest.php`
- Modify: `app/Http/Controllers/MasterAssetController.php`
- Modify: `tests/Feature/MasterAssetManagementTest.php`

- [ ] **Step 1: Add failing mutation and boundary tests**

Append to `MasterAssetManagementTest`:

```php
public function test_unit_creates_an_asset_only_for_its_own_unit(): void
{
    $unit = UnitKerja::factory()->create();
    $otherUnit = UnitKerja::factory()->create();
    $user = User::factory()->unit($unit)->create();

    $this->actingAs($user)->post('/master-asset', [
        'unit_kerja_id' => $otherUnit->id,
        'nama_aset' => '  Track   Circuit Gambir  ',
        'aset_prasarana_sintel' => 'Peralatan Luar Sinyal Elektrik',
        'system' => 'Peraga Sinyal Elektrik',
        'subsystem' => 'Track Circuit',
        'lokasi' => '',
        'jumlah_unit' => 12,
        'tanggal_pemasangan' => '2019-06-10',
        'status' => 'aktif',
    ])->assertSessionHasErrors('unit_kerja_id');

    $payload = [
        'nama_aset' => '  Track   Circuit Gambir  ',
        'aset_prasarana_sintel' => 'Peralatan Luar Sinyal Elektrik',
        'system' => 'Peraga Sinyal Elektrik',
        'subsystem' => 'Track Circuit',
        'lokasi' => '',
        'jumlah_unit' => 12,
        'tanggal_pemasangan' => '2019-06-10',
        'status' => 'aktif',
    ];

    $this->actingAs($user)->post('/master-asset', $payload)
        ->assertRedirect('/master-asset');

    $this->assertDatabaseHas('assets', [
        'unit_kerja_id' => $unit->id,
        'nama_aset' => 'Track Circuit Gambir',
        'lokasi' => null,
    ]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'asset.created']);
}

public function test_cross_unit_mutations_return_not_found(): void
{
    $ownerUnit = UnitKerja::factory()->create();
    $otherUnit = UnitKerja::factory()->create();
    $asset = Asset::factory()->for($ownerUnit)->create();
    $outsider = User::factory()->unit($otherUnit)->create();

    $this->actingAs($outsider)->get("/master-asset/{$asset->id}/edit")->assertNotFound();
    $this->actingAs($outsider)->put("/master-asset/{$asset->id}", [])->assertNotFound();
    $this->actingAs($outsider)->delete("/master-asset/{$asset->id}")->assertNotFound();
}

public function test_pusat_updates_and_soft_deletes_an_asset_with_audit_logs(): void
{
    $pusat = User::factory()->pusat()->create();
    $asset = Asset::factory()->create();
    $payload = [
        'unit_kerja_id' => $asset->unit_kerja_id,
        'nama_aset' => 'Nama Aset Diperbarui',
        'aset_prasarana_sintel' => $asset->aset_prasarana_sintel,
        'system' => $asset->system,
        'subsystem' => $asset->subsystem,
        'lokasi' => 'Stasiun Gambir',
        'jumlah_unit' => 20,
        'tanggal_pemasangan' => '2018-01-01',
        'status' => 'dalam_perbaikan',
    ];

    $this->actingAs($pusat)->put("/master-asset/{$asset->id}", $payload)
        ->assertRedirect('/master-asset');
    $this->actingAs($pusat)->delete("/master-asset/{$asset->id}")
        ->assertRedirect('/master-asset');

    $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'asset.updated']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'asset.deleted']);
}
```

- [ ] **Step 2: Verify RED**

```powershell
php artisan test tests/Feature/MasterAssetManagementTest.php
```

Expected: index tests pass; mutation tests fail because request classes and controller methods do not exist.

- [ ] **Step 3: Implement the shared request contract**

```php
<?php

namespace App\Http\Requests;

use App\Enums\AssetStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

abstract class AssetDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'unit_kerja_id' => $this->user()->isPusat()
                ? ['required', Rule::exists('unit_kerjas', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))]
                : ['prohibited'],
            'nama_aset' => ['required', 'string', 'max:255'],
            'aset_prasarana_sintel' => ['required', 'string', 'max:255'],
            'system' => ['required', 'string', 'max:255'],
            'subsystem' => ['required', 'string', 'max:255'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'jumlah_unit' => ['required', 'integer', 'min:0'],
            'tanggal_pemasangan' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::enum(AssetStatus::class)],
        ];
    }

    public function assetData(): array
    {
        $data = $this->validated();
        $data['unit_kerja_id'] = $this->user()->isPusat()
            ? $data['unit_kerja_id']
            : $this->user()->unit_kerja_id;

        return $data;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['nama_aset', 'aset_prasarana_sintel', 'system', 'subsystem', 'lokasi'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = preg_replace('/\s+/u', ' ', trim($this->string($field)->toString()));
            $normalized[$field] = $field === 'lokasi' && $value === '' ? null : Str::of($value)->toString();
        }

        $this->merge($normalized);
    }
}
```

Create the two focused subclasses:

```php
<?php

namespace App\Http\Requests;

class StoreAssetRequest extends AssetDataRequest {}
```

```php
<?php

namespace App\Http\Requests;

class UpdateAssetRequest extends AssetDataRequest {}
```

- [ ] **Step 4: Add audited CRUD methods to `MasterAssetController`**

Add constructor and imports for `AuditLogger`, the requests, `DB`, and `RedirectResponse`, then add:

```php
public function __construct(private readonly AuditLogger $auditLogger) {}

public function create(Request $request): Response
{
    Gate::authorize('create', Asset::class);

    return Inertia::render('master-data/assets/Create', $this->formProps($request));
}

public function store(StoreAssetRequest $request): RedirectResponse
{
    Gate::authorize('create', Asset::class);

    DB::transaction(function () use ($request): void {
        $asset = Asset::query()->create($request->assetData());
        $this->auditLogger->record('asset.created', $asset, [], $this->auditValues($asset));
    });

    return redirect()->route('master-assets.index')->with('success', 'Aset berhasil ditambahkan.');
}

public function edit(Request $request, int $asset): Response
{
    $asset = $this->visibleAsset($request, $asset);
    Gate::authorize('update', $asset);

    return Inertia::render('master-data/assets/Edit', [
        ...$this->formProps($request),
        'asset' => $this->assetPayload($asset),
    ]);
}

public function update(UpdateAssetRequest $request, int $asset): RedirectResponse
{
    $asset = $this->visibleAsset($request, $asset);
    Gate::authorize('update', $asset);

    DB::transaction(function () use ($request, $asset): void {
        $before = $this->auditValues($asset);
        $asset->update($request->assetData());
        $this->auditLogger->record('asset.updated', $asset, $before, $this->auditValues($asset->fresh()));
    });

    return redirect()->route('master-assets.index')->with('success', 'Aset berhasil diperbarui.');
}

public function destroy(Request $request, int $asset): RedirectResponse
{
    $asset = $this->visibleAsset($request, $asset);
    Gate::authorize('delete', $asset);

    DB::transaction(function () use ($asset): void {
        $before = $this->auditValues($asset);
        $asset->delete();
        $this->auditLogger->record('asset.deleted', $asset, $before, []);
    });

    return redirect()->route('master-assets.index')->with('success', 'Aset berhasil dihapus.');
}

private function visibleAsset(Request $request, int $id): Asset
{
    return Asset::query()->visibleTo($request->user())->findOrFail($id);
}

private function formProps(Request $request): array
{
    return [
        'units' => $request->user()->isPusat()
            ? UnitKerja::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])
            : [],
        'statusOptions' => collect(AssetStatus::cases())->map(fn (AssetStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ])->all(),
        'can' => ['choose_unit' => $request->user()->isPusat()],
    ];
}

private function assetPayload(Asset $asset): array
{
    return [
        ...$asset->only([
            'id', 'unit_kerja_id', 'nama_aset', 'aset_prasarana_sintel',
            'system', 'subsystem', 'lokasi', 'jumlah_unit', 'status',
        ]),
        'status' => $asset->status->value,
        'tanggal_pemasangan' => $asset->tanggal_pemasangan?->toDateString(),
    ];
}

private function auditValues(Asset $asset): array
{
    return $this->assetPayload($asset);
}
```

- [ ] **Step 5: Verify GREEN and commit**

```powershell
php artisan test tests/Feature/MasterAssetManagementTest.php tests/Feature/MasterAssetAuthorizationTest.php
git add app/Http/Controllers/MasterAssetController.php app/Http/Requests/AssetDataRequest.php app/Http/Requests/StoreAssetRequest.php app/Http/Requests/UpdateAssetRequest.php tests/Feature/MasterAssetManagementTest.php
git commit -m "feat: manage regional master assets"
```

Expected: all focused tests pass before commit.

### Task 5: Import workbook rows without overwriting user-owned fields

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Create: `tests/Feature/ImportMasterAssetsTest.php`
- Create: `app/Services/MasterAssetWorkbookImporter.php`
- Create: `app/Console/Commands/ImportMasterAssets.php`

- [ ] **Step 1: Install the spreadsheet reader**

```powershell
composer require phpoffice/phpspreadsheet:^5.9 --with-all-dependencies
```

Expected: Composer selects PhpSpreadsheet 5.9.x and updates only dependency manifests/vendor metadata.

- [ ] **Step 2: Write failing import tests using a generated workbook fixture**

```php
<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportMasterAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_imports_and_updates_source_fields_without_duplicates(): void
    {
        $unit = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $path = $this->workbook([
            ['Kelompok Sinyal', 'Interlocking Elektrik', 'Track Circuit', 12, 40909],
        ]);

        $this->artisan('rams:import-master-assets', ['workbook' => $path, '--unit' => 'DAOP-1'])
            ->assertSuccessful();

        $asset = Asset::query()->sole();
        $this->assertSame($unit->id, $asset->unit_kerja_id);
        $this->assertSame('Track Circuit', $asset->nama_aset);
        $this->assertSame(12, $asset->jumlah_unit);
        $this->assertSame('2012-01-01', $asset->tanggal_pemasangan->toDateString());

        $asset->update([
            'nama_aset' => 'Nama yang disunting pengguna',
            'lokasi' => 'Stasiun Gambir',
            'status' => AssetStatus::DalamPerbaikan,
        ]);
        $this->rewriteTotal($path, 18);

        $this->artisan('rams:import-master-assets', ['workbook' => $path, '--unit' => 'DAOP-1'])
            ->assertSuccessful();

        $this->assertDatabaseCount('assets', 1);
        $asset->refresh();
        $this->assertSame(18, $asset->jumlah_unit);
        $this->assertSame('Nama yang disunting pengguna', $asset->nama_aset);
        $this->assertSame('Stasiun Gambir', $asset->lokasi);
        $this->assertSame(AssetStatus::DalamPerbaikan, $asset->status);
    }

    public function test_invalid_headers_roll_back_the_entire_import(): void
    {
        UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $path = $this->workbook([['Kelompok', 'System', 'Subsystem', 1, 40909]], false);

        $this->artisan('rams:import-master-assets', ['workbook' => $path, '--unit' => 'DAOP-1'])
            ->assertFailed();

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_merged_group_and_system_values_are_forward_filled(): void
    {
        UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $path = $this->workbook([
            ['Kelompok Sinyal', 'Peraga Sinyal Elektrik', 'Track Circuit', 12, 40909],
            ['', '', 'Axle Counter', '-', 40909],
        ]);

        $this->artisan('rams:import-master-assets', ['workbook' => $path, '--unit' => 'DAOP-1'])
            ->assertSuccessful();

        $this->assertDatabaseHas('assets', [
            'subsystem' => 'Axle Counter',
            'aset_prasarana_sintel' => 'Kelompok Sinyal',
            'system' => 'Peraga Sinyal Elektrik',
            'jumlah_unit' => 0,
        ]);
    }

    public function test_import_skips_a_matching_soft_deleted_asset(): void
    {
        UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
        $path = $this->workbook([['Kelompok', 'System', 'Subsystem', 1, 40909]]);
        $this->artisan('rams:import-master-assets', ['workbook' => $path, '--unit' => 'DAOP-1']);
        Asset::query()->sole()->delete();

        $this->artisan('rams:import-master-assets', ['workbook' => $path, '--unit' => 'DAOP-1'])
            ->expectsOutputToContain('Dilewati: 1')
            ->assertSuccessful();

        $this->assertSame(1, Asset::withTrashed()->count());
        $this->assertSame(0, Asset::query()->count());
    }

    private function workbook(array $rows, bool $validHeaders = true): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Predictive Data Asset');
        $sheet->fromArray([
            'ASET PRASARANA SINTEL', 'System', 'Subsystem', 'TOTAL',
        ], null, 'A2');
        $sheet->setCellValue('AA2', $validHeaders ? 'Tanggal Pemasangan' : 'Tanggal Salah');

        foreach ($rows as $offset => [$group, $system, $subsystem, $total, $date]) {
            $row = $offset + 3;
            $sheet->fromArray([$group, $system, $subsystem, $total], null, "A{$row}");
            $sheet->setCellValue("AA{$row}", $date);
        }

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rams-assets-'.Str::uuid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function rewriteTotal(string $path, int $total): void
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $spreadsheet->getSheetByName('Predictive Data Asset')->setCellValue('D3', $total);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }
}
```

- [ ] **Step 3: Verify RED**

```powershell
php artisan test tests/Feature/ImportMasterAssetsTest.php
```

Expected: FAIL because `rams:import-master-assets` is not defined.

- [ ] **Step 4: Implement the workbook importer service**

```php
<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\UnitKerja;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class MasterAssetWorkbookImporter
{
    private const SHEET = 'Predictive Data Asset';

    private const REQUIRED_HEADERS = [
        'ASET PRASARANA SINTEL', 'System', 'Subsystem', 'TOTAL', 'Tanggal Pemasangan',
    ];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function import(string $path, UnitKerja $unit): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Workbook tidak ditemukan: {$path}");
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        try {
            $sheet = $spreadsheet->getSheetByName(self::SHEET)
                ?? throw new InvalidArgumentException('Sheet Predictive Data Asset tidak ditemukan.');
            $headers = $this->headers($sheet);

            return DB::transaction(fn (): array => $this->importRows($sheet, $headers, $unit));
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function headers(Worksheet $sheet): array
    {
        $headers = [];

        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn(2));

        for ($column = 1; $column <= $highestColumn; $column++) {
            $value = trim((string) $sheet->getCell([$column, 2])->getValue());
            if ($value !== '') {
                $headers[$value] = $column;
            }
        }

        $missing = array_values(array_diff(self::REQUIRED_HEADERS, array_keys($headers)));
        if ($missing !== []) {
            throw new InvalidArgumentException('Header wajib tidak ditemukan: '.implode(', ', $missing));
        }

        return $headers;
    }

    private function importRows(Worksheet $sheet, array $headers, UnitKerja $unit): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $currentGroup = '';
        $currentSystem = '';

        for ($row = 3; $row <= $sheet->getHighestDataRow(); $row++) {
            $group = $this->text($sheet, $headers['ASET PRASARANA SINTEL'], $row);
            $system = $this->text($sheet, $headers['System'], $row);
            $subsystem = $this->text($sheet, $headers['Subsystem'], $row);

            $currentGroup = $group !== '' ? $group : $currentGroup;
            $currentSystem = $system !== '' ? $system : $currentSystem;

            if ($currentGroup === '' || $currentSystem === '' || $subsystem === '') {
                $result['skipped']++;
                continue;
            }

            $sourceKey = hash('sha256', implode('|', [$unit->code, self::SHEET, $currentSystem, $subsystem]));
            $source = [
                'unit_kerja_id' => $unit->id,
                'aset_prasarana_sintel' => $currentGroup,
                'system' => $currentSystem,
                'subsystem' => $subsystem,
                'jumlah_unit' => $this->quantity($sheet->getCell([$headers['TOTAL'], $row])->getValue()),
                'tanggal_pemasangan' => $this->date($sheet->getCell([$headers['Tanggal Pemasangan'], $row])->getValue()),
                'source_key' => $sourceKey,
            ];
            $asset = Asset::withTrashed()->where('source_key', $sourceKey)->first();

            if ($asset?->trashed()) {
                $result['skipped']++;
                continue;
            }

            if ($asset) {
                $before = $asset->only(array_keys($source));
                $asset->update($source);
                $this->auditLogger->record('asset.import_updated', $asset, $before, $asset->fresh()->only(array_keys($source)));
                $result['updated']++;
                continue;
            }

            $asset = Asset::query()->create([
                ...$source,
                'nama_aset' => $subsystem,
                'lokasi' => null,
                'status' => AssetStatus::Aktif,
            ]);
            $this->auditLogger->record('asset.import_created', $asset, [], $asset->only(array_keys($source)));
            $result['created']++;
        }

        return $result;
    }

    private function text(Worksheet $sheet, int $column, int $row): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $sheet->getCell([$column, $row])->getValue()));
    }

    private function quantity(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $date = DateTimeImmutable::createFromFormat('d/m/Y', trim((string) $value));
        if (! $date) {
            throw new InvalidArgumentException("Tanggal pemasangan tidak valid: {$value}");
        }

        return $date->format('Y-m-d');
    }
}
```

- [ ] **Step 5: Implement the Artisan command**

```php
<?php

namespace App\Console\Commands;

use App\Models\UnitKerja;
use App\Services\MasterAssetWorkbookImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportMasterAssets extends Command
{
    protected $signature = 'rams:import-master-assets {workbook} {--unit= : Kode unit, misalnya DAOP-1}';

    protected $description = 'Import Master Aset dari sheet Predictive Data Asset';

    public function handle(MasterAssetWorkbookImporter $importer): int
    {
        $unit = UnitKerja::query()
            ->where('code', $this->option('unit'))
            ->where('is_active', true)
            ->first();

        if (! $unit) {
            $this->error('Unit aktif tidak ditemukan. Gunakan --unit=DAOP-1 atau kode unit aktif lain.');
            return self::FAILURE;
        }

        try {
            $result = $importer->import((string) $this->argument('workbook'), $unit);
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info("Dibuat: {$result['created']}");
        $this->info("Diperbarui: {$result['updated']}");
        $this->info("Dilewati: {$result['skipped']}");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 6: Verify GREEN and commit**

```powershell
php artisan test tests/Feature/ImportMasterAssetsTest.php
git add composer.json composer.lock app/Console/Commands/ImportMasterAssets.php app/Services/MasterAssetWorkbookImporter.php tests/Feature/ImportMasterAssetsTest.php
git commit -m "feat: import master assets from workbooks"
```

Expected: all import tests pass before commit.

### Task 6: Replace the dummy index with a professional Inertia table

**Files:**
- Create: `tests/js/MasterAsset.test.js`
- Replace: `resources/js/pages/master-data/assets/MasterAsset.vue`
- Create: `resources/js/pages/master-data/assets/Partials/DeleteAssetDialog.vue`

The visual direction uses existing KAI application tokens: navy `#171650` for structure, orange `#ea580c` for primary actions, slate neutrals for dense data, and status colors only for meaning. The signature element is a compact four-metric operations strip above the asset register; gradients and decorative blobs from the prototype are removed.

- [ ] **Step 1: Write failing index interaction tests**

```js
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import MasterAsset from '@/pages/master-data/assets/MasterAsset.vue'

const state = vi.hoisted(() => ({ get: vi.fn(), delete: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  router: { get: state.get, delete: state.delete },
}))

const props = {
  assets: {
    data: [{
      id: 1,
      nama_aset: 'Track Circuit',
      aset_prasarana_sintel: 'Peralatan Luar Sinyal Elektrik',
      system: 'Peraga Sinyal Elektrik',
      subsystem: 'Track Circuit',
      lokasi: null,
      jumlah_unit: 12,
      status: 'aktif',
      unit_kerja: { code: 'DAOP-1', name: 'Daerah Operasi 1' },
    }],
    links: [], from: 1, to: 1, total: 1,
  },
  stats: { total_assets: 1, total_units: 12, active_assets: 1, unique_subsystems: 1 },
  filters: { search: '', status: '', unit_kerja_id: '' },
  units: [{ id: 1, code: 'DAOP-1', name: 'Daerah Operasi 1' }],
  statusOptions: [{ value: 'aktif', label: 'Aktif' }],
  can: { choose_unit: true },
}

describe('MasterAsset', () => {
  beforeEach(() => {
    state.get.mockReset()
    state.delete.mockReset()
  })

  it('renders backend assets and a useful empty location label', () => {
    const wrapper = mount(MasterAsset, { props, global: { stubs: { MainLayout: { template: '<main><slot /></main>' } } } })
    expect(wrapper.text()).toContain('Track Circuit')
    expect(wrapper.text()).toContain('Belum dilengkapi')
    expect(wrapper.text()).toContain('12')
  })

  it('submits server-side filters', async () => {
    const wrapper = mount(MasterAsset, { props, global: { stubs: { MainLayout: { template: '<main><slot /></main>' } } } })
    await wrapper.get('#asset-search').setValue('Gambir')
    await wrapper.get('form').trigger('submit')
    expect(state.get).toHaveBeenCalledWith('/master-asset', expect.objectContaining({ search: 'Gambir' }), expect.objectContaining({ preserveState: true }))
  })

  it('requires confirmation before deleting', async () => {
    const wrapper = mount(MasterAsset, { props, attachTo: document.body, global: { stubs: { MainLayout: { template: '<main><slot /></main>' } } } })
    await wrapper.get('[aria-label="Hapus Track Circuit"]').trigger('click')
    expect(wrapper.get('[role="dialog"]').text()).toContain('Track Circuit')
    await wrapper.get('[data-test="confirm-delete"]').trigger('click')
    expect(state.delete).toHaveBeenCalledWith('/master-asset/1', expect.any(Object))
    wrapper.unmount()
  })
})
```

- [ ] **Step 2: Verify RED**

```powershell
npm run test:js -- tests/js/MasterAsset.test.js
```

Expected: FAIL because the prototype reads `MockAssetRepository` and has no Inertia props or working delete flow.

- [ ] **Step 3: Create the accessible delete dialog**

```vue
<script setup>
import { nextTick, ref, watch } from 'vue'

const props = defineProps({
  asset: { type: Object, default: null },
  processing: Boolean,
})

const emit = defineEmits(['cancel', 'confirm'])
const confirmButton = ref(null)

watch(() => props.asset, async (asset) => {
  if (asset) {
    await nextTick()
    confirmButton.value?.focus()
  }
})
</script>

<template>
  <div v-if="asset" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 p-4" @keydown.esc="emit('cancel')">
    <section role="dialog" aria-modal="true" aria-labelledby="delete-asset-title" class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-2xl">
      <h2 id="delete-asset-title" class="text-lg font-semibold text-slate-950">Hapus aset?</h2>
      <p class="mt-2 text-sm leading-6 text-slate-600">{{ asset.nama_aset }} akan dihapus dari daftar aktif. Riwayat audit tetap tersimpan.</p>
      <div class="mt-6 flex justify-end gap-3">
        <button type="button" class="h-11 rounded-lg border border-slate-300 px-4 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="processing" @click="emit('cancel')">Batal</button>
        <button ref="confirmButton" data-test="confirm-delete" type="button" class="h-11 rounded-lg bg-red-600 px-4 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50" :disabled="processing" @click="emit('confirm')">Hapus aset</button>
      </div>
    </section>
  </div>
</template>
```

- [ ] **Step 4: Replace `MasterAsset.vue` with server-driven presentation**

```vue
<script setup>
import { reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Boxes, CheckCircle2, Database, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next'
import MainLayout from '@/layouts/MainLayout.vue'
import DeleteAssetDialog from './Partials/DeleteAssetDialog.vue'

const props = defineProps({
  assets: { type: Object, required: true },
  stats: { type: Object, required: true },
  filters: { type: Object, required: true },
  units: { type: Array, required: true },
  statusOptions: { type: Array, required: true },
  can: { type: Object, required: true },
})

const filters = reactive({
  search: props.filters.search ?? '',
  status: props.filters.status ?? '',
  unit_kerja_id: props.filters.unit_kerja_id ?? '',
})
const pendingDelete = ref(null)
const deleting = ref(false)

const applyFilters = () => router.get('/master-asset', filters, { preserveState: true, replace: true })
const clearFilters = () => {
  filters.search = ''
  filters.status = ''
  filters.unit_kerja_id = ''
  applyFilters()
}
const confirmDelete = () => {
  deleting.value = true
  router.delete(`/master-asset/${pendingDelete.value.id}`, {
    preserveScroll: true,
    onFinish: () => {
      deleting.value = false
      pendingDelete.value = null
    },
  })
}
const statusLabel = (value) => props.statusOptions.find((option) => option.value === value)?.label ?? value
const statusClass = (value) => ({
  aktif: 'bg-emerald-50 text-emerald-700',
  nonaktif: 'bg-slate-100 text-slate-600',
  dalam_perbaikan: 'bg-amber-50 text-amber-700',
}[value] ?? 'bg-slate-100 text-slate-600')
const paginationLabel = (label) => label.replace('&laquo; Previous', 'Sebelumnya').replace('Next &raquo;', 'Berikutnya')
const metrics = [
  ['Data aset', 'total_assets', Database],
  ['Jumlah unit', 'total_units', Boxes],
  ['Aset aktif', 'active_assets', CheckCircle2],
  ['Subsystem', 'unique_subsystems', Boxes],
]
</script>

<template>
  <Head title="Master Aset" />
  <MainLayout>
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
      <div>
        <p class="text-sm font-medium text-orange-600">Data dasar RAMS</p>
        <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Master Aset</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Kelola identitas, jumlah, lokasi, dan status aset Sintel per wilayah kerja.</p>
      </div>
      <Link href="/master-asset/create" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-[#ea580c] px-4 text-sm font-semibold text-white hover:bg-[#c2410c]">
        <Plus :size="18" aria-hidden="true" /> Tambah aset
      </Link>
    </div>

    <section class="mb-5 grid gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan aset">
      <article v-for="[label, key, icon] in metrics" :key="key" class="flex items-center gap-4 bg-white p-5">
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-[#2d2a70]"><component :is="icon" :size="20" aria-hidden="true" /></span>
        <div><p class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ label }}</p><p class="mt-1 text-2xl font-semibold text-slate-950">{{ stats[key] }}</p></div>
      </article>
    </section>

    <section class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <form class="grid gap-3 lg:grid-cols-[minmax(15rem,1fr)_13rem_13rem_auto]" @submit.prevent="applyFilters">
        <label class="relative">
          <span class="sr-only">Cari aset</span>
          <Search :size="17" class="pointer-events-none absolute left-3 top-3 text-slate-400" aria-hidden="true" />
          <input id="asset-search" v-model="filters.search" type="search" class="h-11 w-full rounded-lg border border-slate-300 pl-10 pr-3 text-sm outline-none focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10" placeholder="Nama, system, subsystem, lokasi…" />
        </label>
        <select v-if="can.choose_unit" v-model="filters.unit_kerja_id" class="h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm" aria-label="Filter unit kerja">
          <option value="">Semua unit</option>
          <option v-for="unit in units" :key="unit.id" :value="String(unit.id)">{{ unit.code }} — {{ unit.name }}</option>
        </select>
        <select v-model="filters.status" class="h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm" aria-label="Filter status aset">
          <option value="">Semua status</option>
          <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <div class="flex gap-2">
          <button type="submit" class="h-11 flex-1 rounded-lg bg-[#171650] px-4 text-sm font-medium text-white hover:bg-[#24236b]">Terapkan</button>
          <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50" aria-label="Hapus semua filter" @click="clearFilters"><X :size="18" aria-hidden="true" /></button>
        </div>
      </form>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div v-if="assets.data.length" class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-left">
          <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Unit</th><th class="px-5 py-3">Aset</th><th class="px-5 py-3">System / subsystem</th><th class="px-5 py-3 text-right">Jumlah</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="asset in assets.data" :key="asset.id" class="hover:bg-slate-50/70">
              <td class="whitespace-nowrap px-5 py-4"><span class="rounded bg-blue-50 px-2 py-1 font-mono text-xs font-semibold text-[#2d2a70]">{{ asset.unit_kerja.code }}</span></td>
              <td class="px-5 py-4"><p class="text-sm font-semibold text-slate-950">{{ asset.nama_aset }}</p><p class="mt-1 text-xs text-slate-500">{{ asset.lokasi || 'Belum dilengkapi' }}</p></td>
              <td class="px-5 py-4"><p class="text-sm text-slate-800">{{ asset.system }}</p><p class="mt-1 text-xs text-slate-500">{{ asset.subsystem }}</p></td>
              <td class="px-5 py-4 text-right font-mono text-sm font-semibold text-slate-800">{{ asset.jumlah_unit }}</td>
              <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(asset.status)">{{ statusLabel(asset.status) }}</span></td>
              <td class="px-5 py-4 text-right"><div class="flex justify-end gap-1"><Link :href="`/master-asset/${asset.id}/edit`" class="rounded-lg p-2 text-[#2d2a70] hover:bg-blue-50" :aria-label="`Edit ${asset.nama_aset}`"><Pencil :size="17" aria-hidden="true" /></Link><button type="button" class="rounded-lg p-2 text-red-600 hover:bg-red-50" :aria-label="`Hapus ${asset.nama_aset}`" @click="pendingDelete = asset"><Trash2 :size="17" aria-hidden="true" /></button></div></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="px-6 py-16 text-center"><Database :size="28" class="mx-auto text-slate-400" aria-hidden="true" /><h3 class="mt-4 font-semibold text-slate-900">Aset tidak ditemukan</h3><p class="mt-2 text-sm text-slate-500">Ubah filter atau tambahkan aset untuk wilayah kerja Anda.</p></div>
      <div v-if="assets.links.length > 3" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4"><p class="text-xs text-slate-500">Menampilkan {{ assets.from }}–{{ assets.to }} dari {{ assets.total }} aset</p><nav class="flex flex-wrap gap-1" aria-label="Paginasi"><Link v-for="link in assets.links" :key="`${link.label}-${link.url}`" :href="link.url || '#'" preserve-scroll class="min-w-9 rounded-lg border px-3 py-2 text-center text-xs" :class="[link.active ? 'border-[#171650] bg-[#171650] text-white' : 'border-slate-200 text-slate-600', !link.url ? 'pointer-events-none opacity-40' : '']">{{ paginationLabel(link.label) }}</Link></nav></div>
    </section>

    <DeleteAssetDialog :asset="pendingDelete" :processing="deleting" @cancel="pendingDelete = null" @confirm="confirmDelete" />
  </MainLayout>
</template>
```

- [ ] **Step 5: Verify GREEN and commit**

```powershell
npm run test:js -- tests/js/MasterAsset.test.js
git add resources/js/pages/master-data/assets/MasterAsset.vue resources/js/pages/master-data/assets/Partials/DeleteAssetDialog.vue tests/js/MasterAsset.test.js
git commit -m "feat: render live master assets"
```

Expected: all three tests pass before commit.

### Task 7: Build shared create and edit forms

**Files:**
- Create: `tests/js/AssetForm.test.js`
- Create: `resources/js/pages/master-data/assets/Partials/AssetForm.vue`
- Create: `resources/js/pages/master-data/assets/Create.vue`
- Create: `resources/js/pages/master-data/assets/Edit.vue`

- [ ] **Step 1: Write failing form tests**

```js
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AssetForm from '@/pages/master-data/assets/Partials/AssetForm.vue'

const state = vi.hoisted(() => ({ post: vi.fn(), put: vi.fn(), errors: {} }))

vi.mock('@inertiajs/vue3', () => ({
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  useForm: (values) => ({ ...values, errors: state.errors, processing: false, post: state.post, put: state.put }),
}))

const baseProps = {
  units: [{ id: 1, code: 'DAOP-1', name: 'Daerah Operasi 1' }],
  statusOptions: [{ value: 'aktif', label: 'Aktif' }],
  can: { choose_unit: true },
  submitLabel: 'Simpan aset',
}

describe('AssetForm', () => {
  beforeEach(() => {
    state.post.mockReset()
    state.put.mockReset()
    state.errors = {}
  })

  it('posts every core field for a new asset', async () => {
    const wrapper = mount(AssetForm, { props: baseProps })
    await wrapper.get('#unit_kerja_id').setValue('1')
    await wrapper.get('#nama_aset').setValue('Track Circuit Gambir')
    await wrapper.get('#aset_prasarana_sintel').setValue('Peralatan Luar Sinyal Elektrik')
    await wrapper.get('#system').setValue('Peraga Sinyal Elektrik')
    await wrapper.get('#subsystem').setValue('Track Circuit')
    await wrapper.get('#jumlah_unit').setValue('12')
    await wrapper.get('form').trigger('submit')
    expect(state.post).toHaveBeenCalledWith('/master-asset', expect.objectContaining({ preserveScroll: true }))
  })

  it('puts updates to the selected asset', async () => {
    const asset = { id: 7, unit_kerja_id: 1, nama_aset: 'Axle Counter', aset_prasarana_sintel: 'Kelompok', system: 'System', subsystem: 'Subsystem', lokasi: null, jumlah_unit: 4, tanggal_pemasangan: null, status: 'aktif' }
    const wrapper = mount(AssetForm, { props: { ...baseProps, asset } })
    await wrapper.get('form').trigger('submit')
    expect(state.put).toHaveBeenCalledWith('/master-asset/7', expect.objectContaining({ preserveScroll: true }))
  })

  it('hides the unit selector from a regional user', () => {
    const wrapper = mount(AssetForm, { props: { ...baseProps, can: { choose_unit: false }, units: [] } })
    expect(wrapper.find('#unit_kerja_id').exists()).toBe(false)
  })
})
```

- [ ] **Step 2: Verify RED**

```powershell
npm run test:js -- tests/js/AssetForm.test.js
```

Expected: FAIL because `AssetForm.vue` does not exist.

- [ ] **Step 3: Create the shared form**

```vue
<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import { Save } from 'lucide-vue-next'
import BaseButton from '@/components/base/BaseButton.vue'

const props = defineProps({
  asset: { type: Object, default: null },
  units: { type: Array, required: true },
  statusOptions: { type: Array, required: true },
  can: { type: Object, required: true },
  submitLabel: { type: String, required: true },
})

const form = useForm({
  unit_kerja_id: props.asset?.unit_kerja_id ?? '',
  nama_aset: props.asset?.nama_aset ?? '',
  aset_prasarana_sintel: props.asset?.aset_prasarana_sintel ?? '',
  system: props.asset?.system ?? '',
  subsystem: props.asset?.subsystem ?? '',
  lokasi: props.asset?.lokasi ?? '',
  jumlah_unit: props.asset?.jumlah_unit ?? 0,
  tanggal_pemasangan: props.asset?.tanggal_pemasangan ?? '',
  status: props.asset?.status ?? 'aktif',
})

const submit = () => props.asset
  ? form.put(`/master-asset/${props.asset.id}`, { preserveScroll: true })
  : form.post('/master-asset', { preserveScroll: true })
const inputClass = 'h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-950 outline-none transition focus:border-[#2d2a70] focus:ring-4 focus:ring-[#2d2a70]/10'
</script>

<template>
  <form class="space-y-6" @submit.prevent="submit">
    <div v-if="can.choose_unit">
      <label for="unit_kerja_id" class="mb-2 block text-sm font-medium text-slate-800">Unit kerja</label>
      <select id="unit_kerja_id" v-model="form.unit_kerja_id" :class="inputClass" required><option value="" disabled>Pilih unit kerja</option><option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.code }} — {{ unit.name }}</option></select>
      <p v-if="form.errors.unit_kerja_id" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.unit_kerja_id }}</p>
    </div>
    <div class="grid gap-6 md:grid-cols-2">
      <div><label for="nama_aset" class="mb-2 block text-sm font-medium text-slate-800">Nama aset</label><input id="nama_aset" v-model="form.nama_aset" :class="inputClass" maxlength="255" required /><p v-if="form.errors.nama_aset" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.nama_aset }}</p></div>
      <div><label for="status" class="mb-2 block text-sm font-medium text-slate-800">Status</label><select id="status" v-model="form.status" :class="inputClass" required><option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select><p v-if="form.errors.status" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.status }}</p></div>
      <div class="md:col-span-2"><label for="aset_prasarana_sintel" class="mb-2 block text-sm font-medium text-slate-800">Aset prasarana Sintel</label><input id="aset_prasarana_sintel" v-model="form.aset_prasarana_sintel" :class="inputClass" maxlength="255" required /><p v-if="form.errors.aset_prasarana_sintel" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.aset_prasarana_sintel }}</p></div>
      <div><label for="system" class="mb-2 block text-sm font-medium text-slate-800">System</label><input id="system" v-model="form.system" :class="inputClass" maxlength="255" required /><p v-if="form.errors.system" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.system }}</p></div>
      <div><label for="subsystem" class="mb-2 block text-sm font-medium text-slate-800">Subsystem</label><input id="subsystem" v-model="form.subsystem" :class="inputClass" maxlength="255" required /><p v-if="form.errors.subsystem" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.subsystem }}</p></div>
      <div><label for="lokasi" class="mb-2 block text-sm font-medium text-slate-800">Lokasi</label><input id="lokasi" v-model="form.lokasi" :class="inputClass" maxlength="255" placeholder="Opsional" /><p v-if="form.errors.lokasi" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.lokasi }}</p></div>
      <div><label for="jumlah_unit" class="mb-2 block text-sm font-medium text-slate-800">Jumlah unit</label><input id="jumlah_unit" v-model.number="form.jumlah_unit" type="number" min="0" :class="inputClass" required /><p v-if="form.errors.jumlah_unit" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.jumlah_unit }}</p></div>
      <div><label for="tanggal_pemasangan" class="mb-2 block text-sm font-medium text-slate-800">Tanggal pemasangan</label><input id="tanggal_pemasangan" v-model="form.tanggal_pemasangan" type="date" :class="inputClass" /><p v-if="form.errors.tanggal_pemasangan" class="mt-2 text-sm text-red-600" role="alert">{{ form.errors.tanggal_pemasangan }}</p></div>
    </div>
    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end"><Link href="/master-asset" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-300 px-5 text-sm font-medium text-slate-700 hover:bg-slate-50">Batal</Link><BaseButton type="submit" class="h-11 rounded-lg px-5" :loading="form.processing"><Save :size="17" class="mr-2" aria-hidden="true" />{{ submitLabel }}</BaseButton></div>
  </form>
</template>
```

- [ ] **Step 4: Create the page wrappers**

`Create.vue`:

```vue
<script setup>
import { Head } from '@inertiajs/vue3'
import MainLayout from '@/layouts/MainLayout.vue'
import AssetForm from './Partials/AssetForm.vue'
defineProps({ units: { type: Array, required: true }, statusOptions: { type: Array, required: true }, can: { type: Object, required: true } })
</script>
<template><Head title="Tambah Aset" /><MainLayout><div class="mb-6"><p class="text-sm font-medium text-orange-600">Master Aset</p><h2 class="mt-1 text-2xl font-semibold text-slate-950">Tambah aset</h2><p class="mt-2 text-sm text-slate-600">Isi identitas dan kepemilikan aset.</p></div><section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><AssetForm :units="units" :status-options="statusOptions" :can="can" submit-label="Simpan aset" /></section></MainLayout></template>
```

`Edit.vue`:

```vue
<script setup>
import { Head } from '@inertiajs/vue3'
import MainLayout from '@/layouts/MainLayout.vue'
import AssetForm from './Partials/AssetForm.vue'
defineProps({ asset: { type: Object, required: true }, units: { type: Array, required: true }, statusOptions: { type: Array, required: true }, can: { type: Object, required: true } })
</script>
<template><Head title="Edit Aset" /><MainLayout><div class="mb-6"><p class="text-sm font-medium text-orange-600">Master Aset</p><h2 class="mt-1 text-2xl font-semibold text-slate-950">Edit aset</h2><p class="mt-2 text-sm text-slate-600">Perbarui data tanpa mengubah riwayat audit.</p></div><section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><AssetForm :asset="asset" :units="units" :status-options="statusOptions" :can="can" submit-label="Simpan perubahan" /></section></MainLayout></template>
```

- [ ] **Step 5: Verify GREEN and commit**

```powershell
npm run test:js -- tests/js/AssetForm.test.js tests/js/MasterAsset.test.js
git add resources/js/pages/master-data/assets/Create.vue resources/js/pages/master-data/assets/Edit.vue resources/js/pages/master-data/assets/Partials/AssetForm.vue tests/js/AssetForm.test.js
git commit -m "feat: add master asset forms"
```

Expected: all focused Vue tests pass before commit.

### Task 8: Remove dummy wiring, import real workbooks, and verify the module

**Files:**
- Modify: `resources/js/layouts/MainLayout.vue`
- Delete when unreferenced: `resources/js/infrastructure/dummy-data/assets.json`
- Delete when unreferenced: `resources/js/infrastructure/repositories/mock/mock-asset.repository.js`
- Delete when unreferenced: `resources/js/domain/models/asset.model.js`
- Delete when unreferenced: `resources/js/domain/repositories/i-asset.repository.js`
- Delete when unreferenced: `resources/js/application/use-cases/get-assets.use-case.js`
- Modify: `README.md`

- [ ] **Step 1: Prove the dummy asset files have no consumers**

```powershell
rg -n "MockAssetRepository|GetAssetsUseCase|assets.json|AssetModel|IAssetRepository" resources tests
```

Expected: only the five obsolete files reference one another; no page imports them.

- [ ] **Step 2: Delete only the now-unreferenced dummy files**

Delete the five files listed above. Do not delete unrelated inventory or dashboard dummy sources.

- [ ] **Step 3: Keep the sidebar URL explicit**

Confirm the Master Aset item in `MainLayout.vue` remains:

```js
{ name: 'master-asset', label: 'Master Aset', to: '/master-asset', icon: Database },
```

- [ ] **Step 4: Document the import command**

Add to `README.md`:

````markdown
## Import Master Aset

File Excel sumber tetap berada di luar repository. Jalankan satu perintah untuk setiap workbook dan tentukan kode unit secara eksplisit:

```powershell
php artisan rams:import-master-assets "D:\KAI RAMS\Risk Analysis And Management System RAMS Daop 1.xlsm" --unit=DAOP-1
```

Importer membaca sheet `Predictive Data Asset`, menggunakan `subsystem` sebagai nilai awal `nama_aset`, dan dapat dijalankan ulang tanpa membuat duplikat. Perubahan manual pada nama aset, lokasi, dan status dipertahankan.
````

- [ ] **Step 5: Run migrations and import the five approved workbooks**

```powershell
php artisan migrate
php artisan rams:import-master-assets "D:\KAI RAMS\Risk Analysis And Management System RAMS Daop 1.xlsm" --unit=DAOP-1
php artisan rams:import-master-assets "D:\KAI RAMS\Risk Analysis And Management System RAMS Daop 4.xlsm" --unit=DAOP-4
php artisan rams:import-master-assets "D:\KAI RAMS\Risk Analysis And Management System RAMS Daop 8.xlsm" --unit=DAOP-8
php artisan rams:import-master-assets "D:\KAI RAMS\Risk Analysis And Management System RAMS Divre III.xlsm" --unit=DIVRE-III
php artisan rams:import-master-assets "D:\KAI RAMS\Risk Analysis And Management System RAMS Divre IV.xlsm" --unit=DIVRE-IV
```

Expected: every command reports created/updated/skipped counts and exits successfully.

- [ ] **Step 6: Verify backend, frontend, format, and build**

```powershell
php artisan test
npm run test:js
npm run build
php vendor/bin/pint --test
```

Expected: all commands exit 0 with no failing tests or format errors.

- [ ] **Step 7: Verify browser behavior**

Use the local browser to check:

1. `admin.pusat / admin1234` sees imported assets across five units and can filter DAOP-1.
2. `daop1 / daop1234` sees only DAOP-1 assets.
3. A DAOP user can create, edit, and soft-delete an asset in its own unit.
4. A direct edit URL for another unit returns 404.
5. Keyboard focus, validation feedback, empty location text, pagination, and delete confirmation are usable.

- [ ] **Step 8: Commit cleanup and documentation**

```powershell
git add README.md resources/js/layouts/MainLayout.vue resources/js/application/use-cases/get-assets.use-case.js resources/js/domain/models/asset.model.js resources/js/domain/repositories/i-asset.repository.js resources/js/infrastructure/dummy-data/assets.json resources/js/infrastructure/repositories/mock/mock-asset.repository.js
git commit -m "docs: finish master asset integration"
```

Run `git status -sb` and confirm only intentional plan-progress edits remain.
