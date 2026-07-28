# Global Asset Categories Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun hierarki kategori aset global `Aset Prasarana Sintel → System → Subsystem`, pengelolaan khusus Admin Pusat, dan menghubungkannya ke CRUD serta impor Master Aset tanpa kehilangan 85 data aset yang sudah ada.

**Architecture:** Kategori memakai ID stabil dan soft delete sehingga perubahan nama berlaku ke semua DAOP/Divre tanpa memutus relasi. Master Aset menyimpan `asset_subsystem_id` sebagai sumber kebenaran, sedangkan tiga kolom teks lama dipertahankan sementara sebagai snapshot kompatibilitas dan diisi ulang saat penyimpanan. Resolver kategori menangani impor Excel secara idempotent, termasuk alias sumber ketika nama kategori sudah pernah diubah.

**Tech Stack:** Laravel 13, PHP 8.3, Eloquent, MySQL 8.4 LTS, Inertia.js 3, Vue 3, Tailwind CSS 4, PHPUnit 12, Vitest 4, PhpSpreadsheet 5.

---

## Batas Lingkup dan Urutan Dependensi

Plan ini menghasilkan perangkat lunak yang dapat dipakai dan diuji sendiri sebelum modul inventori dibuat. Plan inventori yang terpisah bergantung pada `AssetSubsystem` dan resolver kategori dari plan ini.

Urutan wajib:

1. Schema dan model kategori.
2. Backfill data aset yang sudah ada.
3. Otorisasi serta CRUD kategori global.
4. Integrasi kategori ke CRUD Master Aset.
5. Integrasi kolom A–F Excel dan saldo agregat awal.
6. UI kategori serta UI Master Aset hierarkis.
7. Verifikasi MySQL, browser desktop/mobile, dan regression suite.

## Peta File

### File baru

- `database/migrations/2026_07_28_000000_create_asset_category_tables.php` — tabel group, system, subsystem, dan alias sumber.
- `database/migrations/2026_07_28_000001_add_asset_subsystem_id_to_assets_table.php` — foreign key nullable untuk migrasi bertahap.
- `database/migrations/2026_07_28_000002_create_unit_subsystem_openings_table.php` — nilai awal agregat Sparepart IN/OUT per unit dan subsystem dari Excel.
- `database/migrations/2026_07_28_000003_make_asset_subsystem_id_required.php` — menjadikan relasi wajib setelah pemeriksaan backfill.
- `app/Models/AssetGroup.php`, `AssetSystem.php`, `AssetSubsystem.php`, `AssetCategorySourceAlias.php`, `UnitSubsystemOpening.php` — model dan relasi.
- `database/factories/AssetGroupFactory.php`, `AssetSystemFactory.php`, `AssetSubsystemFactory.php`, `UnitSubsystemOpeningFactory.php` — fixture test.
- `app/Services/AssetCategoryResolver.php` — pencarian/pembuatan kategori dan alias secara idempotent.
- `app/Services/AssetCategoryBackfill.php` — menghubungkan aset lama ke kategori global.
- `app/Console/Commands/BackfillAssetCategories.php` — command backfill eksplisit dan aman dijalankan ulang.
- `app/Policies/AssetGroupPolicy.php`, `AssetSystemPolicy.php`, `AssetSubsystemPolicy.php` — hanya Pusat dapat mengubah kategori.
- `app/Http/Controllers/Admin/AssetCategoryController.php` — halaman drill-down kategori.
- `app/Http/Controllers/Admin/AssetGroupController.php`, `AssetSystemController.php`, `AssetSubsystemController.php` — mutasi tiap tingkat.
- `app/Http/Controllers/Admin/UnitSubsystemOpeningController.php` — koreksi baseline IN/OUT khusus Pusat dengan audit.
- `app/Http/Requests/Admin/StoreAssetGroupRequest.php`, `UpdateAssetGroupRequest.php`, `StoreAssetSystemRequest.php`, `UpdateAssetSystemRequest.php`, `StoreAssetSubsystemRequest.php`, `UpdateAssetSubsystemRequest.php`, `UpdateAssetCategoryStatusRequest.php` — validasi nama, induk, keunikan, dan status.
- `resources/js/pages/Admin/AssetCategories/Index.vue` — halaman pengelolaan hierarki.
- `resources/js/pages/Admin/AssetCategories/Partials/CategoryPanel.vue` — panel daftar reusable.
- `resources/js/pages/Admin/AssetCategories/Partials/CategoryDialog.vue` — modal tambah/ubah.
- `resources/js/pages/Admin/AssetCategories/Partials/DeactivateCategoryDialog.vue` — konfirmasi nonaktif.
- `resources/js/pages/Admin/AssetCategories/Partials/DeleteCategoryDialog.vue` — delete aman hanya untuk kategori yang belum dipakai.
- `resources/js/pages/master-data/assets/Partials/CategorySelectFields.vue` — pilihan berjenjang group/system/subsystem.
- `resources/js/pages/master-data/assets/Partials/AssetHierarchyTable.vue` — tabel hierarkis desktop.
- `resources/js/pages/master-data/assets/Partials/AssetHierarchyCard.vue` — kartu mobile.
- `app/Queries/AssetHierarchyQuery.php` — agregasi enam kolom per unit dan subsystem tanpa logika query di controller.
- `tests/Feature/AssetCategorySchemaTest.php`, `AssetCategoryBackfillTest.php`, `Admin/AssetCategoryManagementTest.php`, `AssetCategoryImportTest.php` — test backend.
- `tests/js/AssetCategories.test.js`, `CategorySelectFields.test.js`, `AssetHierarchyTable.test.js` — test frontend.

### File yang diubah

- `app/Models/Asset.php` — relasi subsystem, pencarian melalui relasi, dan snapshot kompatibilitas.
- `database/factories/AssetFactory.php` — fixture menggunakan subsystem valid.
- `app/Http/Requests/AssetDataRequest.php` — menerima `asset_subsystem_id`, tidak menerima teks kategori dari browser.
- `app/Http/Controllers/MasterAssetController.php` — props kategori, eager loading, statistik, dan payload hierarkis.
- `app/Services/MasterAssetWorkbookImporter.php` — resolver kategori serta pembacaan Sparepart IN/OUT.
- `app/Console/Commands/ImportMasterAssets.php` — ringkasan kategori dan opening balance.
- `routes/web.php` — route admin kategori.
- `resources/js/layouts/MainLayout.vue` — menu “Kategori Aset” khusus Admin Pusat.
- `resources/js/pages/master-data/assets/Partials/AssetForm.vue` — mengganti tiga input teks dengan dropdown berjenjang.
- `resources/js/pages/master-data/assets/MasterAsset.vue` — tabel hierarki profesional dan filter kategori.
- `resources/js/pages/master-data/assets/Create.vue`, `Edit.vue` — meneruskan props kategori.
- `tests/Feature/MasterAssetSchemaTest.php`, `MasterAssetManagementTest.php`, `MasterAssetAuthorizationTest.php`, `ImportMasterAssetsTest.php` — regression test.
- `tests/js/AssetForm.test.js`, `MasterAsset.test.js` — regression UI.

## Kontrak Data

Gunakan nama kolom dan tipe berikut secara konsisten:

```text
asset_groups: id, name, normalized_name, sort_order, is_active, timestamps, deleted_at
asset_systems: id, asset_group_id, name, normalized_name, sort_order, is_active, timestamps, deleted_at
asset_subsystems: id, asset_system_id, name, normalized_name, sort_order, is_active, timestamps, deleted_at
asset_category_source_aliases: id, category_type, category_id, source_path, normalized_source_path, workbook_name, sheet_name, first_imported_at, last_imported_at, timestamps
assets: asset_subsystem_id nullable selama backfill, lalu wajib foreign key → asset_subsystems.id
unit_subsystem_openings: id, unit_kerja_id, asset_subsystem_id, source_key, sparepart_in, sparepart_out, timestamps
```

Keunikan MySQL:

```text
asset_groups(normalized_name)
asset_systems(asset_group_id, normalized_name)
asset_subsystems(asset_system_id, normalized_name)
asset_category_source_aliases(category_type, normalized_source_path)
unit_subsystem_openings(unit_kerja_id, asset_subsystem_id)
unit_subsystem_openings(source_key)
```

Normalisasi nama dilakukan dengan `trim`, merapatkan whitespace, lalu `mb_strtolower`. Nama tampil tetap mempertahankan kapitalisasi yang dimasukkan Admin Pusat.

### Task 1: Schema, Model, Relasi, dan Factory Kategori

**Files:**
- Create: `database/migrations/2026_07_28_000000_create_asset_category_tables.php`
- Create: `database/migrations/2026_07_28_000001_add_asset_subsystem_id_to_assets_table.php`
- Create: `app/Models/AssetGroup.php`
- Create: `app/Models/AssetSystem.php`
- Create: `app/Models/AssetSubsystem.php`
- Create: `app/Models/AssetCategorySourceAlias.php`
- Create: `database/factories/AssetGroupFactory.php`
- Create: `database/factories/AssetSystemFactory.php`
- Create: `database/factories/AssetSubsystemFactory.php`
- Modify: `app/Models/Asset.php`
- Modify: `database/factories/AssetFactory.php`
- Test: `tests/Feature/AssetCategorySchemaTest.php`

- [ ] **Step 1: Tulis test schema dan relasi yang gagal**

```php
public function test_category_hierarchy_and_asset_relation_are_persisted(): void
{
    $group = AssetGroup::factory()->create(['name' => 'Peralatan Dalam Sinyal Elektrik']);
    $system = AssetSystem::factory()->for($group)->create(['name' => 'Interlocking Elektrik']);
    $subsystem = AssetSubsystem::factory()->for($system)->create(['name' => 'Interlocking Elektrik']);
    $asset = Asset::factory()->for($subsystem)->create();

    $this->assertDatabaseHas('asset_groups', ['normalized_name' => 'peralatan dalam sinyal elektrik']);
    $this->assertDatabaseHas('asset_systems', ['asset_group_id' => $group->id]);
    $this->assertDatabaseHas('asset_subsystems', ['asset_system_id' => $system->id]);
    $this->assertTrue($asset->assetSubsystem->is($subsystem));
    $this->assertTrue($subsystem->assetSystem->assetGroup->is($group));
}
```

- [ ] **Step 2: Jalankan test dan pastikan gagal karena tabel/model belum ada**

Run: `php artisan test tests/Feature/AssetCategorySchemaTest.php`

Expected: FAIL dengan class `AssetGroup` atau tabel `asset_groups` belum ditemukan.

- [ ] **Step 3: Buat migration dengan foreign key, indeks unik, soft delete, dan aturan restrict**

```php
Schema::create('asset_groups', function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->string('normalized_name')->unique();
    $table->unsignedInteger('sort_order')->default(0);
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
    $table->softDeletes();
});

Schema::create('asset_systems', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('asset_group_id')->constrained()->restrictOnDelete();
    $table->string('name');
    $table->string('normalized_name');
    $table->unsignedInteger('sort_order')->default(0);
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
    $table->softDeletes();
    $table->unique(['asset_group_id', 'normalized_name']);
});

Schema::create('asset_subsystems', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('asset_system_id')->constrained()->restrictOnDelete();
    $table->string('name');
    $table->string('normalized_name');
    $table->unsignedInteger('sort_order')->default(0);
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
    $table->softDeletes();
    $table->unique(['asset_system_id', 'normalized_name']);
});

Schema::create('asset_category_source_aliases', function (Blueprint $table): void {
    $table->id();
    $table->string('category_type', 16);
    $table->unsignedBigInteger('category_id');
    $table->string('source_path');
    $table->string('normalized_source_path');
    $table->string('workbook_name');
    $table->string('sheet_name');
    $table->dateTime('first_imported_at');
    $table->dateTime('last_imported_at');
    $table->timestamps();
    $table->unique(['category_type', 'normalized_source_path']);
    $table->index(['category_type', 'category_id']);
});
```

Migration berikutnya menambah foreign key tanpa langsung `NOT NULL`:

```php
Schema::table('assets', function (Blueprint $table): void {
    $table->foreignId('asset_subsystem_id')
        ->nullable()
        ->after('unit_kerja_id')
        ->constrained('asset_subsystems')
        ->restrictOnDelete();
    $table->index(['unit_kerja_id', 'asset_subsystem_id']);
});
```

- [ ] **Step 4: Implementasikan model dengan normalisasi otomatis dan relasi eksplisit**

```php
protected static function booted(): void
{
    static::saving(function (self $category): void {
        $category->name = preg_replace('/\s+/u', ' ', trim($category->name));
        $category->normalized_name = mb_strtolower($category->name);
    });
}

public function systems(): HasMany
{
    return $this->hasMany(AssetSystem::class)->orderBy('sort_order')->orderBy('name');
}
```

Gunakan pola yang sama pada system dan subsystem; `AssetSubsystem::assets()` adalah `HasMany`, dan `Asset::assetSubsystem()` adalah `BelongsTo`. Tambahkan `asset_subsystem_id` ke `#[Fillable]` Asset serta factory state yang membuat satu rantai kategori valid.

- [ ] **Step 5: Jalankan test schema**

Run: `php artisan test tests/Feature/AssetCategorySchemaTest.php`

Expected: PASS.

- [ ] **Step 6: Format dan commit**

```bash
vendor/bin/pint --dirty
git add app/Models database/migrations database/factories tests/Feature/AssetCategorySchemaTest.php
git commit -m "feat: add global asset category schema"
```

### Task 2: Resolver dan Backfill Aset Lama

**Files:**
- Create: `app/Services/AssetCategoryResolver.php`
- Create: `app/Services/AssetCategoryBackfill.php`
- Create: `app/Console/Commands/BackfillAssetCategories.php`
- Test: `tests/Feature/AssetCategoryBackfillTest.php`

- [ ] **Step 1: Tulis test idempotensi, forward-fill, dan preservasi data**

```php
public function test_backfill_creates_one_global_hierarchy_and_preserves_assets(): void
{
    $first = Asset::factory()->create([
        'asset_subsystem_id' => null,
        'aset_prasarana_sintel' => '2. PERALATAN LUAR SINYAL ELEKTRIK',
        'system' => 'PERAGA SINYAL ELEKTRIK',
        'subsystem' => 'Track Circuit',
    ]);
    $second = Asset::factory()->create([
        'asset_subsystem_id' => null,
        'aset_prasarana_sintel' => '2.  PERALATAN LUAR SINYAL ELEKTRIK',
        'system' => 'PERAGA SINYAL ELEKTRIK',
        'subsystem' => 'Track Circuit',
    ]);

    $this->artisan('rams:backfill-asset-categories')->assertSuccessful();
    $this->artisan('rams:backfill-asset-categories')->assertSuccessful();

    $this->assertSame(1, AssetGroup::query()->count());
    $this->assertSame(1, AssetSystem::query()->count());
    $this->assertSame(1, AssetSubsystem::query()->count());
    $this->assertNotNull($first->refresh()->asset_subsystem_id);
    $this->assertSame($first->asset_subsystem_id, $second->refresh()->asset_subsystem_id);
}
```

- [ ] **Step 2: Jalankan test dan pastikan gagal**

Run: `php artisan test tests/Feature/AssetCategoryBackfillTest.php`

Expected: FAIL karena command dan resolver belum ada.

- [ ] **Step 3: Implementasikan kontrak resolver**

```php
/** @return array{group: AssetGroup, system: AssetSystem, subsystem: AssetSubsystem} */
public function resolve(string $groupName, string $systemName, string $subsystemName): array
{
    return DB::transaction(function () use ($groupName, $systemName, $subsystemName): array {
        $group = $this->resolveGroup($groupName);
        $system = $this->resolveSystem($group, $systemName);
        $subsystem = $this->resolveSubsystem($system, $subsystemName);

        return compact('group', 'system', 'subsystem');
    });
}
```

Setiap `resolve*` menerima konteks `workbookName` dan `sheetName`, lalu mencari alias path lengkap lebih dahulu (`group`, `group|system`, `group|system|subsystem`), kemudian `normalized_name`. Setelah resolve, service melakukan `updateOrCreate` alias dan memperbarui `last_imported_at`; `first_imported_at` tidak berubah. Jika satu path bertabrakan dengan dua ID atau parent path ambigu, throw `RuntimeException` berisi workbook, sheet, row, dan path agar tidak digabung diam-diam. Alias memastikan file lama tetap menunjuk ID yang sama sesudah Admin Pusat mengganti nama tampilan.

- [ ] **Step 4: Implementasikan service dan command backfill dalam chunk**

```php
public function run(): array
{
    $result = ['linked' => 0, 'skipped' => 0];

    Asset::query()->whereNull('asset_subsystem_id')->orderBy('id')->chunkById(100, function ($assets) use (&$result): void {
        foreach ($assets as $asset) {
            if (! $asset->aset_prasarana_sintel || ! $asset->system || ! $asset->subsystem) {
                $result['skipped']++;
                continue;
            }

            $resolved = $this->resolver->resolve($asset->aset_prasarana_sintel, $asset->system, $asset->subsystem);
            $asset->forceFill(['asset_subsystem_id' => $resolved['subsystem']->id])->save();
            $result['linked']++;
        }
    });

    return $result;
}
```

Command bernama `rams:backfill-asset-categories`, mencetak `linked` dan `skipped`, serta exit code sukses bila transaksi selesai.

- [ ] **Step 5: Jalankan test backfill dua kali**

Run: `php artisan test tests/Feature/AssetCategoryBackfillTest.php`

Expected: PASS dan jumlah kategori tidak bertambah pada eksekusi kedua.

- [ ] **Step 6: Commit**

```bash
git add app/Services/AssetCategoryResolver.php app/Services/AssetCategoryBackfill.php app/Console/Commands/BackfillAssetCategories.php tests/Feature/AssetCategoryBackfillTest.php
git commit -m "feat: backfill assets into global categories"
```

### Task 3: Otorisasi dan CRUD Kategori Khusus Admin Pusat

**Files:**
- Create: `app/Policies/AssetGroupPolicy.php`
- Create: `app/Policies/AssetSystemPolicy.php`
- Create: `app/Policies/AssetSubsystemPolicy.php`
- Create: `app/Http/Controllers/Admin/AssetCategoryController.php`
- Create: `app/Http/Controllers/Admin/AssetGroupController.php`
- Create: `app/Http/Controllers/Admin/AssetSystemController.php`
- Create: `app/Http/Controllers/Admin/AssetSubsystemController.php`
- Create: enam request di `app/Http/Requests/Admin/`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/AssetCategoryManagementTest.php`
- Test: `tests/Feature/Admin/PusatAuthorizationTest.php`

- [ ] **Step 1: Tulis test bahwa Pusat dapat CRUD dan wilayah mendapat 403**

```php
public function test_only_pusat_can_create_rename_and_deactivate_categories(): void
{
    $pusat = User::factory()->pusat()->create();
    $unit = User::factory()->unit()->create();

    $this->actingAs($unit)->post('/admin/asset-groups', ['name' => 'Catu Daya Sintel'])->assertForbidden();

    $this->actingAs($pusat)->post('/admin/asset-groups', ['name' => 'Catu Daya Sintel'])
        ->assertRedirect('/admin/asset-categories');

    $group = AssetGroup::query()->firstOrFail();
    $this->actingAs($pusat)->put("/admin/asset-groups/{$group->id}", ['name' => 'Catu Daya Persinyalan'])
        ->assertRedirect('/admin/asset-categories');
    $this->actingAs($pusat)->patch("/admin/asset-groups/{$group->id}/status", ['is_active' => false])
        ->assertRedirect('/admin/asset-categories');

    $this->assertDatabaseHas('asset_groups', ['id' => $group->id, 'name' => 'Catu Daya Persinyalan', 'is_active' => false]);

    $unused = AssetGroup::factory()->create();
    $this->actingAs($pusat)->delete("/admin/asset-groups/{$unused->id}")
        ->assertRedirect('/admin/asset-categories');
    $this->assertSoftDeleted('asset_groups', ['id' => $unused->id]);
}
```

- [ ] **Step 2: Jalankan test dan pastikan route belum ditemukan**

Run: `php artisan test tests/Feature/Admin/AssetCategoryManagementTest.php tests/Feature/Admin/PusatAuthorizationTest.php`

Expected: FAIL dengan 404 untuk route kategori.

- [ ] **Step 3: Implementasikan policy dan validasi**

```php
public function before(User $user): ?bool
{
    return $user->isPusat() ? true : null;
}

public function viewAny(User $user): bool
{
    return false;
}
```

Request update group memakai aturan konkret:

```php
return [
    'name' => ['required', 'string', 'max:255'],
    'normalized_name' => [
        'required', 'string', 'max:255',
        Rule::unique('asset_groups', 'normalized_name')->ignore($this->route('asset_group')),
    ],
    'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
];
```

Sebelum validasi, merge nama yang telah dirapatkan dan `normalized_name`; controller hanya mengambil `name` dan `sort_order` dari validated data. Untuk system/subsystem, keunikan `normalized_name` dibatasi pada induk. `asset_group_id` dan `asset_system_id` wajib menunjuk record aktif.

- [ ] **Step 4: Implementasikan controller, audit log, dan route**

```php
Route::get('asset-categories', AssetCategoryController::class)->name('asset-categories.index');
Route::resource('asset-groups', AssetGroupController::class)->only(['store', 'update', 'destroy']);
Route::resource('asset-systems', AssetSystemController::class)->only(['store', 'update', 'destroy']);
Route::resource('asset-subsystems', AssetSubsystemController::class)->only(['store', 'update', 'destroy']);
Route::patch('asset-groups/{asset_group}/status', [AssetGroupController::class, 'status'])->name('asset-groups.status');
Route::patch('asset-systems/{asset_system}/status', [AssetSystemController::class, 'status'])->name('asset-systems.status');
Route::patch('asset-subsystems/{asset_subsystem}/status', [AssetSubsystemController::class, 'status'])->name('asset-subsystems.status');
```

`destroy` menghitung child, asset, sparepart, alias, dan opening yang masih memakai kategori. Tambahkan test delete kategori terpakai yang mengharapkan validation error berisi nama relasi penghalang dan memastikan record tetap ada. Bila ada relasi, request ditolak dan menawarkan nonaktifkan; bila belum pernah dipakai, controller melakukan soft delete dan audit `asset_category.deleted`. Endpoint status terpisah memvalidasi boolean `is_active`, melakukan aktif/nonaktif, dan audit `asset_category.status_changed`. `update` menyimpan nilai lama/baru melalui `AuditLogger`; ID dan seluruh relasi tetap sama. Akses object lintas unit pada bagian aplikasi lain selalu dicari melalui scope `visibleTo` agar hasilnya 404, bukan membocorkan keberadaan data lewat 403.

- [ ] **Step 5: Jalankan test otorisasi dan CRUD**

Run: `php artisan test tests/Feature/Admin/AssetCategoryManagementTest.php tests/Feature/Admin/PusatAuthorizationTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Policies app/Http/Controllers/Admin app/Http/Requests/Admin routes/web.php tests/Feature/Admin
git commit -m "feat: manage global asset categories"
```

### Task 4: Integrasi Kategori ke CRUD Master Aset

**Files:**
- Modify: `app/Models/Asset.php`
- Modify: `app/Http/Requests/AssetDataRequest.php`
- Modify: `app/Http/Controllers/MasterAssetController.php`
- Modify: `tests/Feature/MasterAssetManagementTest.php`
- Modify: `tests/Feature/MasterAssetAuthorizationTest.php`

- [ ] **Step 1: Tulis test request hanya menerima subsystem aktif dan menulis snapshot**

```php
public function test_regional_user_stores_asset_using_global_subsystem_id(): void
{
    $user = User::factory()->unit()->create();
    $subsystem = AssetSubsystem::factory()->create(['name' => 'Track Circuit']);

    $this->actingAs($user)->post(route('master-assets.store'), [
        'asset_subsystem_id' => $subsystem->id,
        'nama_aset' => 'Relay Track',
        'lokasi' => 'Stasiun A',
        'jumlah_unit' => 12,
        'tanggal_pemasangan' => '2026-07-28',
        'status' => 'aktif',
    ])->assertRedirect(route('master-assets.index'));

    $asset = Asset::query()->firstOrFail();
    $this->assertSame($subsystem->id, $asset->asset_subsystem_id);
    $this->assertSame('Track Circuit', $asset->subsystem);
    $this->assertSame($subsystem->assetSystem->name, $asset->system);
    $this->assertSame($subsystem->assetSystem->assetGroup->name, $asset->aset_prasarana_sintel);
}
```

- [ ] **Step 2: Jalankan test dan pastikan gagal validasi**

Run: `php artisan test tests/Feature/MasterAssetManagementTest.php`

Expected: FAIL karena request lama masih mewajibkan tiga field teks.

- [ ] **Step 3: Ubah kontrak request dan snapshot server-side**

```php
'asset_subsystem_id' => [
    'required',
    Rule::exists('asset_subsystems', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
],
```

Hapus tiga input kategori dari rules browser. Di `assetData()`:

```php
$subsystem = AssetSubsystem::query()
    ->with('assetSystem.assetGroup')
    ->findOrFail($data['asset_subsystem_id']);

$data['subsystem'] = $subsystem->name;
$data['system'] = $subsystem->assetSystem->name;
$data['aset_prasarana_sintel'] = $subsystem->assetSystem->assetGroup->name;
```

- [ ] **Step 4: Ubah query, statistik, form props, dan payload controller**

```php
$categories = AssetGroup::query()
    ->where('is_active', true)
    ->with(['systems' => fn ($query) => $query->where('is_active', true)->with([
        'subsystems' => fn ($query) => $query->where('is_active', true),
    ])])
    ->orderBy('sort_order')->orderBy('name')->get();
```

Eager-load `assetSubsystem.assetSystem.assetGroup`. `unique_subsystems` memakai `distinct('asset_subsystem_id')`. Pencarian memakai `whereHas` untuk nama group/system/subsystem dan tetap mencari `nama_aset`/`lokasi`. Payload asset berisi `asset_subsystem_id` serta object `category` dengan `group`, `system`, dan `subsystem` masing-masing `{id,name}`.

- [ ] **Step 5: Jalankan seluruh test Master Aset**

Run: `php artisan test tests/Feature/MasterAssetManagementTest.php tests/Feature/MasterAssetAuthorizationTest.php tests/Feature/MasterAssetSchemaTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Models/Asset.php app/Http/Requests/AssetDataRequest.php app/Http/Controllers/MasterAssetController.php tests/Feature/MasterAssetManagementTest.php tests/Feature/MasterAssetAuthorizationTest.php tests/Feature/MasterAssetSchemaTest.php
git commit -m "feat: link master assets to global categories"
```

### Task 5: Impor Excel A–F dan Opening Balance Agregat

**Files:**
- Create: `database/migrations/2026_07_28_000002_create_unit_subsystem_openings_table.php`
- Create: `app/Models/UnitSubsystemOpening.php`
- Create: `database/factories/UnitSubsystemOpeningFactory.php`
- Create: `app/Queries/AssetHierarchyQuery.php`
- Create: `app/Http/Controllers/Admin/UnitSubsystemOpeningController.php`
- Modify: `app/Services/MasterAssetWorkbookImporter.php`
- Modify: `app/Console/Commands/ImportMasterAssets.php`
- Test: `tests/Feature/AssetCategoryImportTest.php`
- Modify: `tests/Feature/ImportMasterAssetsTest.php`

- [ ] **Step 1: Tulis test impor kategori dan nilai IN/OUT yang idempotent**

```php
public function test_import_resolves_global_category_and_upserts_aggregate_opening(): void
{
    $unit = UnitKerja::factory()->create(['code' => 'DAOP-1']);
    $path = $this->makeWorkbookRow([
        'group' => 'Peralatan Luar Sinyal Elektrik',
        'system' => 'Peraga Sinyal Elektrik',
        'subsystem' => 'Track Circuit',
        'total' => 81,
        'sparepart_in' => 7,
        'sparepart_out' => 2,
        'installed_at' => '2026-01-01',
    ]);

    $this->artisan('rams:import-master-assets', ['workbook' => $path, '--unit' => $unit->code])->assertSuccessful();
    $this->artisan('rams:import-master-assets', ['workbook' => $path, '--unit' => $unit->code])->assertSuccessful();

    $asset = Asset::query()->firstOrFail();
    $this->assertNotNull($asset->asset_subsystem_id);
    $this->assertDatabaseCount('unit_subsystem_openings', 1);
    $this->assertDatabaseHas('unit_subsystem_openings', [
        'unit_kerja_id' => $unit->id,
        'asset_subsystem_id' => $asset->asset_subsystem_id,
        'sparepart_in' => 7,
        'sparepart_out' => 2,
    ]);
}
```

- [ ] **Step 2: Jalankan test dan pastikan gagal pada header/opening**

Run: `php artisan test tests/Feature/AssetCategoryImportTest.php tests/Feature/ImportMasterAssetsTest.php`

Expected: FAIL karena importer belum membaca `Sparepart IN` dan `Sparepart OUT`.

- [ ] **Step 3: Buat schema opening balance**

```php
Schema::create('unit_subsystem_openings', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('unit_kerja_id')->constrained()->restrictOnDelete();
    $table->foreignId('asset_subsystem_id')->constrained()->restrictOnDelete();
    $table->string('source_key', 64)->unique();
    $table->unsignedInteger('sparepart_in')->default(0);
    $table->unsignedInteger('sparepart_out')->default(0);
    $table->timestamps();
    $table->unique(['unit_kerja_id', 'asset_subsystem_id']);
});
```

- [ ] **Step 4: Tambah header, resolver, dan upsert opening pada importer**

```php
'sparepart in' => 'sparepart_in',
'sparepart out' => 'sparepart_out',
```

Setelah forward-fill group dan system, panggil resolver. Gunakan `asset_subsystem_id` dalam asset dan source key stabil berbasis unit, sheet, dan subsystem ID. Upsert opening:

```php
UnitSubsystemOpening::query()->updateOrCreate(
    ['unit_kerja_id' => $unit->id, 'asset_subsystem_id' => $resolved['subsystem']->id],
    [
        'source_key' => hash('sha256', implode('|', [$unit->code, self::SHEET, $resolved['subsystem']->id, 'opening'])),
        'sparepart_in' => $this->quantity($sheet->getCell([$columns['sparepart_in'], $row])->getCalculatedValue()),
        'sparepart_out' => $this->quantity($sheet->getCell([$columns['sparepart_out'], $row])->getCalculatedValue()),
    ],
);
```

Opening ini hanya agregat sesuai kolom A–F. Nilainya tidak pernah ditambahkan ke stok suku cadang bernama pada plan inventori. Saat update asset impor, field `nama_aset`, `lokasi`, dan `status` yang telah diedit user tidak masuk update payload; hanya relasi kategori, `jumlah_unit`, tanggal sumber, snapshot kategori, dan opening yang diperbarui. Soft-deleted asset/category tidak dipulihkan otomatis.

`UnitSubsystemOpening` mempunyai scope berikut agar baseline tidak bocor lintas wilayah:

```php
public function scopeVisibleTo(Builder $query, User $user): Builder
{
    return $query->when($user->isUnit(), fn (Builder $visible): Builder =>
        $visible->where('unit_kerja_id', $user->unit_kerja_id));
}
```

- [ ] **Step 5: Implementasikan query enam kolom dan koreksi opening khusus Pusat**

```php
public function forUser(User $user, ?int $unitId = null): Collection
{
    return AssetSubsystem::query()
        ->with('assetSystem.assetGroup')
        ->withSum(['assets as total' => fn (Builder $query) => $query
            ->visibleTo($user)
            ->when($user->isPusat() && $unitId, fn (Builder $assets) => $assets->where('unit_kerja_id', $unitId))], 'jumlah_unit')
        ->withSum(['unitSubsystemOpenings as sparepart_in' => fn (Builder $query) => $query
            ->visibleTo($user)
            ->when($user->isPusat() && $unitId, fn (Builder $openings) => $openings->where('unit_kerja_id', $unitId))], 'sparepart_in')
        ->withSum(['unitSubsystemOpenings as sparepart_out' => fn (Builder $query) => $query
            ->visibleTo($user)
            ->when($user->isPusat() && $unitId, fn (Builder $openings) => $openings->where('unit_kerja_id', $unitId))], 'sparepart_out')
        ->get();
}
```

Tambahkan route `PUT /admin/unit-subsystem-openings/{opening}`. Request hanya menerima dua integer nonnegatif. Controller Pusat mencatat old/new melalui `AuditLogger`. Nilai baseline tidak dapat diedit oleh akun wilayah.

- [ ] **Step 6: Jalankan test impor, agregasi, koreksi, dan regression**

Run: `php artisan test tests/Feature/AssetCategoryImportTest.php tests/Feature/ImportMasterAssetsTest.php`

Expected: PASS; impor kedua memperbarui record yang sama.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add database/migrations/2026_07_28_000002_create_unit_subsystem_openings_table.php app/Models/UnitSubsystemOpening.php database/factories/UnitSubsystemOpeningFactory.php app/Queries/AssetHierarchyQuery.php app/Http/Controllers/Admin/UnitSubsystemOpeningController.php app/Services/MasterAssetWorkbookImporter.php app/Console/Commands/ImportMasterAssets.php tests/Feature/AssetCategoryImportTest.php tests/Feature/ImportMasterAssetsTest.php
git commit -m "feat: import asset categories and opening balances"
```

### Task 6: UI Pengelolaan Kategori yang Profesional dan Nyaman

**Files:**
- Create: `resources/js/pages/Admin/AssetCategories/Index.vue`
- Create: `resources/js/pages/Admin/AssetCategories/Partials/CategoryPanel.vue`
- Create: `resources/js/pages/Admin/AssetCategories/Partials/CategoryDialog.vue`
- Create: `resources/js/pages/Admin/AssetCategories/Partials/DeactivateCategoryDialog.vue`
- Create: `resources/js/pages/Admin/AssetCategories/Partials/DeleteCategoryDialog.vue`
- Modify: `resources/js/layouts/MainLayout.vue`
- Test: `tests/js/AssetCategories.test.js`

- [ ] **Step 1: Tulis test interaksi tiga panel dan modal**

```js
it('memilih group dan system lalu membuka form subsystem', async () => {
  const wrapper = mount(Index, {
    props: { groups: hierarchyFixture, selected: { group_id: 1, system_id: 10 } },
    global: inertiaStubs,
  })

  expect(wrapper.get('[data-testid="group-panel"]').text()).toContain('Peralatan Luar Sinyal Elektrik')
  expect(wrapper.get('[data-testid="system-panel"]').text()).toContain('Peraga Sinyal Elektrik')
  await wrapper.get('[data-testid="add-subsystem"]').trigger('click')
  expect(wrapper.get('[role="dialog"]').text()).toContain('Tambah Subsystem')
  expect(wrapper.get('input[name="name"]').attributes('autofocus')).toBeDefined()
})
```

- [ ] **Step 2: Jalankan test dan pastikan komponen belum ada**

Run: `npm run test:js -- tests/js/AssetCategories.test.js`

Expected: FAIL karena import halaman belum ditemukan.

- [ ] **Step 3: Bangun komponen panel reusable**

```vue
<CategoryPanel
  title="Aset Prasarana Sintel"
  description="Kelompok utama yang berlaku untuk seluruh wilayah."
  :items="groups"
  :selected-id="selectedGroupId"
  add-label="Tambah kategori"
  data-testid="group-panel"
  @select="selectGroup"
  @add="openCreate('group')"
  @edit="openEdit('group', $event)"
  @deactivate="openDeactivate('group', $event)"
/>
```

`CategoryPanel` menampilkan pencarian lokal, nama, jumlah child, badge Aktif/Nonaktif, menu aksi Rename/Nonaktifkan/Hapus, empty state, dan skeleton. Setiap tombol memiliki tinggi minimum 44px serta focus ring yang terlihat. Dialog delete menampilkan relasi penghalang dari response server dan mengarahkan user ke aksi Nonaktifkan bila delete aman tidak tersedia.

- [ ] **Step 4: Bangun layout drill-down responsif dan dialog aksesibel**

Desktop `xl:grid-cols-3`; tablet horizontal scroll dengan panel minimum 320px; mobile satu panel aktif dengan breadcrumb dan tombol Kembali. Gunakan permukaan putih, border slate, navy `#171650` untuk struktur, oranye KAI untuk primary action, hijau hanya untuk status sukses. Hindari gradient dekoratif.

Dialog memakai `role="dialog"`, `aria-modal="true"`, label input nyata, error validation di bawah input, tombol Batal dan Simpan, fokus awal ke nama, Escape untuk tutup, dan tombol submit disabled saat `form.processing`.

- [ ] **Step 5: Tambah menu khusus Pusat**

```js
{ name: 'admin-asset-categories', label: 'Kategori Aset', to: '/admin/asset-categories', icon: Network }
```

Letakkan sebelum “Unit Kerja” dan pastikan menu tidak dirender untuk akun wilayah.

- [ ] **Step 6: Jalankan test UI dan build**

Run: `npm run test:js -- tests/js/AssetCategories.test.js`

Expected: PASS.

Run: `npm run build`

Expected: build Vite selesai tanpa error import atau template.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/Admin/AssetCategories resources/js/layouts/MainLayout.vue tests/js/AssetCategories.test.js
git commit -m "feat: add global category management interface"
```

### Task 7: Dropdown Berjenjang dan Tampilan Master Aset Hierarkis

**Files:**
- Create: `resources/js/pages/master-data/assets/Partials/CategorySelectFields.vue`
- Create: `resources/js/pages/master-data/assets/Partials/AssetHierarchyTable.vue`
- Create: `resources/js/pages/master-data/assets/Partials/AssetHierarchyCard.vue`
- Modify: `resources/js/pages/master-data/assets/Partials/AssetForm.vue`
- Modify: `resources/js/pages/master-data/assets/MasterAsset.vue`
- Modify: `resources/js/pages/master-data/assets/Create.vue`
- Modify: `resources/js/pages/master-data/assets/Edit.vue`
- Test: `tests/js/CategorySelectFields.test.js`
- Test: `tests/js/AssetHierarchyTable.test.js`
- Modify: `tests/js/AssetForm.test.js`
- Modify: `tests/js/MasterAsset.test.js`

- [ ] **Step 1: Tulis test reset dropdown dan hierarchy rendering**

```js
it('mereset system dan subsystem ketika group berubah', async () => {
  const wrapper = mount(CategorySelectFields, {
    props: { categories: hierarchyFixture, modelValue: 101, errors: {} },
  })

  await wrapper.get('select[name="asset_group_id"]').setValue('2')
  expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([null])
  expect(wrapper.get('select[name="asset_system_id"]').element.value).toBe('')
  expect(wrapper.get('select[name="asset_subsystem_id"]').attributes('disabled')).toBeDefined()
})

it('menampilkan total, opening in, dan opening out pada baris subsystem', () => {
  const wrapper = mount(AssetHierarchyTable, { props: { rows: hierarchyRows } })
  const trackCircuit = wrapper.get('[data-subsystem-id="101"]')
  expect(trackCircuit.text()).toContain('81')
  expect(trackCircuit.text()).toContain('7')
  expect(trackCircuit.text()).toContain('2')
})
```

- [ ] **Step 2: Jalankan test dan pastikan gagal**

Run: `npm run test:js -- tests/js/CategorySelectFields.test.js tests/js/AssetHierarchyTable.test.js`

Expected: FAIL karena komponen belum ada.

- [ ] **Step 3: Implementasikan dropdown berjenjang**

Komponen menerima kontrak:

```js
const props = defineProps({
  categories: { type: Array, required: true },
  modelValue: { type: [Number, String, null], default: null },
  errors: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:modelValue'])
```

Label urutannya “Aset Prasarana Sintel”, “System”, “Subsystem”. System disabled sampai group dipilih; subsystem disabled sampai system dipilih. Edit form menurunkan group/system awal dari subsystem ID. Pergantian parent menghapus pilihan child sehingga ID tidak mungkin berasal dari cabang lain.

- [ ] **Step 4: Integrasikan ke AssetForm**

```vue
<CategorySelectFields
  v-model="form.asset_subsystem_id"
  :categories="categories"
  :errors="form.errors"
/>
```

Hapus input teks `aset_prasarana_sintel`, `system`, dan `subsystem`. Jangan hapus `nama_aset`; field ini tetap nama aset/suku cadang yang dimiliki wilayah.

- [ ] **Step 5: Bangun tabel hierarki desktop dan kartu mobile**

Kolom desktop tepat: `Aset Prasarana Sintel`, `System`, `Subsystem`, `TOTAL`, `Sparepart IN`, `Sparepart OUT`, `Aksi`. Group/system dapat collapse; baris parent memperlihatkan subtotal; baris subsystem memperlihatkan angka unit-spesifik. Aksi pada tahap ini mencakup edit/lihat aset; plan inventori menambahkan lihat sparepart serta transaksi IN/OUT setelah route tersebut tersedia. Gunakan angka tabular, header sticky, zebra ringan, hover state, dan tidak meniru merge-cell Excel yang sulit dibaca.

Pada viewport `< md`, render kartu bertingkat dengan breadcrumb kategori, tiga angka ringkas, nama aset, lokasi, status, serta menu Edit/Hapus. Empty state menjelaskan apakah data kosong karena filter atau memang belum ada.

- [ ] **Step 6: Jalankan seluruh test JS Master Aset**

Run: `npm run test:js -- tests/js/CategorySelectFields.test.js tests/js/AssetHierarchyTable.test.js tests/js/AssetForm.test.js tests/js/MasterAsset.test.js`

Expected: PASS.

Run: `npm run build`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/master-data/assets tests/js/CategorySelectFields.test.js tests/js/AssetHierarchyTable.test.js tests/js/AssetForm.test.js tests/js/MasterAsset.test.js
git commit -m "feat: present master assets as a category hierarchy"
```

### Task 8: Migrasi Data MySQL dan Verifikasi Akhir

**Files:**
- Modify: `docs/superpowers/specs/2026-07-28-global-asset-categories-inventory-design.md` hanya bila hasil implementasi membuktikan kontrak perlu dikoreksi.
- Verify: seluruh file plan ini.

- [ ] **Step 1: Pastikan container MySQL 8.4 hidup dan konfigurasi test memakai MySQL**

Run: `docker compose ps`

Expected: service MySQL berstatus `Up` dan health `healthy`.

Run: `php artisan about --only=environment`

Expected: environment lokal dan koneksi database aplikasi terbaca tanpa error.

- [ ] **Step 2: Jalankan migration awal dan backfill pada database lokal**

```bash
php artisan migrate
php artisan rams:backfill-asset-categories
```

Expected: migration sukses; output backfill mencantumkan jumlah linked/skipped; `assets.asset_subsystem_id` terisi untuk seluruh 85 aset yang valid.

- [ ] **Step 3: Verifikasi nol orphan lalu jadikan foreign key wajib**

Run: `php artisan tinker --execute="throw_if(App\\Models\\Asset::whereNull('asset_subsystem_id')->exists(), new RuntimeException('Masih ada asset tanpa subsystem.'));"`

Expected: exit code 0 tanpa exception.

Implementasikan migration `2026_07_28_000003_make_asset_subsystem_id_required.php` dengan `foreignId('asset_subsystem_id')->nullable(false)->change()` dan rollback menjadi nullable. Lalu run `php artisan migrate`; expected migration sukses pada MySQL 8.4.

- [ ] **Step 4: Impor ulang workbook untuk setiap unit yang sudah digunakan proyek**

Gunakan command dan opsi unit yang sudah didefinisikan `ImportMasterAssets`. Expected: jumlah asset tetap 85, bukan berlipat; nilai TOTAL dan opening A–F sesuai workbook.

- [ ] **Step 5: Jalankan backend suite, frontend suite, formatter, dan build**

```bash
php artisan test
npm run test:js
vendor/bin/pint --test
npm run build
```

Expected: semua test PASS, Pint tidak melaporkan perubahan, dan Vite build sukses.

- [ ] **Step 6: Verifikasi browser dengan akun Pusat dan wilayah**

Checklist Pusat pada desktop 1440px dan mobile 390px:

- `/admin/asset-categories` menambah, mengganti nama, dan menonaktifkan kategori dengan feedback jelas.
- Rename terlihat di semua daftar aset tanpa mengubah foreign key.
- Form Master Aset memakai dropdown berjenjang dan mempertahankan `nama_aset`.
- Tabel/kartu menampilkan hierarchy dan TOTAL/IN/OUT sesuai unit/filter.
- Tab order logis, focus ring terlihat, modal dapat ditutup Escape, tidak ada horizontal overflow mobile.

Checklist akun DAOP:

- Menu admin kategori tidak terlihat dan URL admin memberi 403.
- Pilihan kategori global tersedia tetapi tidak dapat diedit.
- Hanya data unit sendiri yang dapat dilihat dan diubah.

Expected: tidak ada error console, request 500, teks terpotong, atau data dummy.

- [ ] **Step 7: Cek diff dan commit integrasi akhir**

```bash
git status --short
git diff --check
git add -A
git commit -m "test: verify global asset category workflow"
```

Expected: commit hanya berisi penyesuaian test/dokumentasi/verifikasi yang memang muncul pada task ini; jangan push karena pemilik repo akan melakukan push sendiri.

## Kriteria Selesai

- Seluruh DAOP/Divre menggunakan satu hierarchy kategori global.
- Hanya Admin Pusat dapat menambah, rename, dan menonaktifkan kategori.
- Rename mempertahankan ID dan relasi asset/import lama.
- Semua aset valid memiliki `asset_subsystem_id`; 85 data awal tidak hilang atau berlipat.
- `nama_aset` tetap tersedia sebagai nama item/aset.
- TOTAL berasal dari `SUM(assets.jumlah_unit)` per unit/subsystem.
- IN/OUT Excel A–F tersimpan sebagai opening agregat per unit/subsystem dan tidak mencemari stok sparepart bernama.
- UI desktop/mobile profesional, responsif, keyboard-friendly, dan tanpa data dummy.
- Backend test, frontend test, Pint, dan build seluruhnya lulus pada MySQL 8.4.
