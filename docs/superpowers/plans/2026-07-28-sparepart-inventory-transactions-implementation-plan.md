# Sparepart Inventory Transactions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengganti prototype Predictive Inventory dengan master suku cadang global, stok per DAOP/Divre, transaksi IN/OUT yang dapat diaudit, koreksi terhubung, dan parameter reorder yang diimpor dari Excel.

**Architecture:** `SparePart` adalah master global yang dikelola Admin Pusat dan terhubung ke `AssetSubsystem`; `InventoryStock` menyimpan saldo materialized per unit untuk baca cepat; `StockMovement` adalah ledger immutable yang menjadi jejak audit. Semua mutasi stok masuk melalui satu service transactional dengan row lock, idempotency key, dan larangan saldo negatif. UI memakai data Inertia server-side, filter URL, dialog transaksi, dan riwayat yang responsif tanpa metrik dummy.

**Tech Stack:** Laravel 13, PHP 8.3, Eloquent, MySQL 8.4 LTS, Inertia.js 3, Vue 3, Tailwind CSS 4, PHPUnit 12, Vitest 4, PhpSpreadsheet 5.

---

## Prasyarat

Selesaikan `docs/superpowers/plans/2026-07-28-global-asset-categories-implementation-plan.md` sampai `AssetSubsystem`, `AssetCategoryResolver`, dan relasi kategori Master Aset tersedia. Plan ini tidak mengubah makna `unit_subsystem_openings`: angka agregat A–F tetap ditampilkan pada hierarchy aset tetapi tidak dihitung sebagai stok suku cadang bernama.

## Peta File

### File baru

- `app/Enums/StockMovementType.php` — `in`, `out`, `opening`, `correction`.
- `app/Enums/StockDirection.php` — `in`, `out` untuk perhitungan saldo yang eksplisit.
- `database/migrations/2026_07_28_000004_create_spare_parts_table.php` — master suku cadang global.
- `database/migrations/2026_07_28_000005_create_inventory_stocks_table.php` — saldo per unit dan sparepart.
- `database/migrations/2026_07_28_000006_create_stock_movements_table.php` — ledger transaksi dan koreksi.
- `app/Models/SparePart.php`, `InventoryStock.php`, `StockMovement.php` — model dan scope akses.
- `database/factories/SparePartFactory.php`, `InventoryStockFactory.php`, `StockMovementFactory.php` — fixture test.
- `app/Policies/SparePartPolicy.php`, `InventoryStockPolicy.php`, `StockMovementPolicy.php` — aturan global vs unit.
- `app/Services/SparePartWorkbookImporter.php` — impor sheet `Reorder Stock`.
- `app/Services/StockMovementService.php` — satu pintu mutasi saldo.
- `app/Console/Commands/ImportSpareParts.php` — command impor idempotent.
- `app/Http/Controllers/InventoryController.php` — overview, filter, stok, dan riwayat.
- `app/Http/Controllers/Admin/SparePartController.php` — CRUD master sparepart khusus Pusat.
- `app/Http/Controllers/StockMovementController.php` — transaksi dan koreksi.
- `app/Http/Requests/Admin/StoreSparePartRequest.php`, `UpdateSparePartRequest.php` — validasi master.
- `app/Http/Requests/StoreStockMovementRequest.php`, `CorrectStockMovementRequest.php` — validasi transaksi.
- `resources/js/pages/master-data/inventory/Partials/InventoryStats.vue`, `InventoryFilters.vue`, `InventoryTable.vue`, `InventoryCard.vue`, `MovementDialog.vue`, `MovementHistory.vue`, `SparePartDialog.vue` — komponen UI terfokus.
- `tests/Feature/InventorySchemaTest.php`, `SparePartImportTest.php`, `InventoryAuthorizationTest.php`, `StockMovementServiceTest.php`, `InventoryManagementTest.php` — test backend.
- `tests/js/Inventory.test.js`, `MovementDialog.test.js`, `MovementHistory.test.js`, `SparePartDialog.test.js` — test frontend.

### File yang diubah

- `app/Models/UnitKerja.php` — relasi stocks dan movements.
- `app/Models/User.php` — relasi stock movement sebagai actor.
- `app/Models/AssetSubsystem.php` — relasi spareparts.
- `app/Queries/AssetHierarchyQuery.php` — menambahkan movement IN/OUT ke ringkasan enam kolom.
- `routes/web.php` — mengganti closure `/inventory` dengan controller dan route transaksi/admin sparepart.
- `resources/js/pages/master-data/inventory/Inventory.vue` — menghapus semua array/metrik dummy.
- `resources/js/layouts/MainLayout.vue` — label menu menjadi “Inventori Suku Cadang”; route reorder lama diarahkan ke tab/filter yang nyata.
- `tests/Feature/Admin/PusatAuthorizationTest.php`, `SharedInertiaDataTest.php` — regression otorisasi/navigation.

## Kontrak Data

```text
spare_parts:
  id, asset_subsystem_id, code, source_key, equipment, detail_equipment,
  max_yearly_failure, average_yearly_failure, max_lead_time_months,
  average_lead_time_months, safety_stock, lead_time_demand, reorder_point,
  severity, unit_of_measure, is_active, timestamps, deleted_at

inventory_stocks:
  id, unit_kerja_id, spare_part_id, quantity, timestamps
  unique(unit_kerja_id, spare_part_id)

stock_movements:
  id, unit_kerja_id, spare_part_id, actor_id, type, direction, quantity,
  stock_before, stock_after, movement_date, reference_number, notes,
  reverses_movement_id nullable, idempotency_key, timestamps
  unique(idempotency_key)
```

Rumus yang menjadi sumber kebenaran:

```text
movement signed quantity = direction IN ? quantity : -quantity
current stock             = sum(signed quantity) per unit + sparepart
materialized stock        = inventory_stocks.quantity
below reorder             = current stock <= spare_parts.reorder_point
correction                = movement baru yang menunjuk reverses_movement_id
```

`InventoryStock.quantity` selalu harus sama dengan hasil ledger. Nilai `quantity`, `stock_before`, dan `stock_after` adalah integer non-negatif. `StockMovement` tidak memiliki update/delete route.

### Task 1: Enum, Schema, Model, dan Factory Inventori

**Files:**
- Create: `app/Enums/StockMovementType.php`
- Create: `app/Enums/StockDirection.php`
- Create: tiga migration inventori
- Create: `app/Models/SparePart.php`
- Create: `app/Models/InventoryStock.php`
- Create: `app/Models/StockMovement.php`
- Create: tiga factory inventori
- Modify: `app/Models/UnitKerja.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/AssetSubsystem.php`
- Test: `tests/Feature/InventorySchemaTest.php`

- [ ] **Step 1: Tulis test schema, enum cast, dan relasi**

```php
public function test_inventory_relations_and_movement_casts_are_available(): void
{
    $unit = UnitKerja::factory()->create();
    $part = SparePart::factory()->create();
    $actor = User::factory()->for($unit)->unit()->create();
    $stock = InventoryStock::factory()->for($unit)->for($part)->create(['quantity' => 10]);
    $movement = StockMovement::factory()->for($unit)->for($part)->for($actor, 'actor')->create([
        'type' => StockMovementType::In,
        'direction' => StockDirection::In,
        'quantity' => 10,
        'stock_before' => 0,
        'stock_after' => 10,
    ]);

    $this->assertTrue($stock->sparePart->is($part));
    $this->assertSame(StockMovementType::In, $movement->type);
    $this->assertSame(StockDirection::In, $movement->direction);
    $this->assertTrue($movement->actor->is($actor));
}
```

- [ ] **Step 2: Jalankan test dan pastikan gagal**

Run: `php artisan test tests/Feature/InventorySchemaTest.php`

Expected: FAIL karena enum/model/tabel belum ada.

- [ ] **Step 3: Implementasikan enum**

```php
enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Opening = 'opening';
    case Correction = 'correction';
}

enum StockDirection: string
{
    case In = 'in';
    case Out = 'out';

    public function apply(int $stock, int $quantity): int
    {
        return $this === self::In ? $stock + $quantity : $stock - $quantity;
    }
}
```

- [ ] **Step 4: Buat migration dengan constraint dan indeks query**

```php
Schema::create('inventory_stocks', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('unit_kerja_id')->constrained()->restrictOnDelete();
    $table->foreignId('spare_part_id')->constrained()->restrictOnDelete();
    $table->unsignedInteger('quantity')->default(0);
    $table->timestamps();
    $table->unique(['unit_kerja_id', 'spare_part_id']);
});

Schema::create('stock_movements', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('unit_kerja_id')->constrained()->restrictOnDelete();
    $table->foreignId('spare_part_id')->constrained()->restrictOnDelete();
    $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
    $table->string('type', 16);
    $table->string('direction', 8);
    $table->unsignedInteger('quantity');
    $table->unsignedInteger('stock_before');
    $table->unsignedInteger('stock_after');
    $table->date('movement_date');
    $table->string('reference_number')->nullable();
    $table->text('notes')->nullable();
    $table->foreignId('reverses_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
    $table->uuid('idempotency_key')->unique();
    $table->timestamps();
    $table->index(['unit_kerja_id', 'movement_date']);
    $table->index(['spare_part_id', 'movement_date']);
});
```

`spare_parts.code` dan `spare_parts.source_key` unik. Kolom failure/lead time memakai `decimal(10,2)->nullable()`, stock/reorder memakai `unsignedInteger()->nullable()`, `severity` memakai string nullable, dan `unit_of_measure` adalah string default `unit`.

- [ ] **Step 5: Implementasikan fillable, cast, scope, dan relasi**

```php
protected function casts(): array
{
    return [
        'type' => StockMovementType::class,
        'direction' => StockDirection::class,
        'quantity' => 'integer',
        'stock_before' => 'integer',
        'stock_after' => 'integer',
        'movement_date' => 'date',
    ];
}

public function scopeVisibleTo(Builder $query, User $user): Builder
{
    return $query->when($user->isUnit(), fn (Builder $visible): Builder =>
        $visible->where('unit_kerja_id', $user->unit_kerja_id));
}
```

- [ ] **Step 6: Jalankan test schema lalu commit**

Run: `php artisan test tests/Feature/InventorySchemaTest.php`

Expected: PASS.

```bash
vendor/bin/pint --dirty
git add app/Enums app/Models database/migrations database/factories tests/Feature/InventorySchemaTest.php
git commit -m "feat: add sparepart inventory schema"
```

### Task 2: Policy dan Isolasi Data per Unit

**Files:**
- Create: `app/Policies/SparePartPolicy.php`
- Create: `app/Policies/InventoryStockPolicy.php`
- Create: `app/Policies/StockMovementPolicy.php`
- Test: `tests/Feature/InventoryAuthorizationTest.php`

- [ ] **Step 1: Tulis matriks test akses Pusat dan wilayah**

```php
public function test_unit_user_only_sees_and_moves_own_unit_stock(): void
{
    $ownUser = User::factory()->unit()->create();
    $otherUnit = UnitKerja::factory()->create();
    $ownStock = InventoryStock::factory()->for($ownUser->unitKerja)->create();
    $otherStock = InventoryStock::factory()->for($otherUnit)->create();

    $visibleIds = InventoryStock::query()->visibleTo($ownUser)->pluck('id');

    $this->assertTrue($visibleIds->contains($ownStock->id));
    $this->assertFalse($visibleIds->contains($otherStock->id));
    $this->assertTrue($ownUser->can('createMovement', $ownStock));
    $this->assertFalse($ownUser->can('createMovement', $otherStock));
    $this->assertFalse($ownUser->can('create', SparePart::class));
}
```

- [ ] **Step 2: Jalankan test dan pastikan policy belum ada**

Run: `php artisan test tests/Feature/InventoryAuthorizationTest.php`

Expected: FAIL pada kemampuan `createMovement` atau `create`.

- [ ] **Step 3: Implementasikan policy eksplisit**

```php
public function createMovement(User $user, InventoryStock $stock): bool
{
    return $user->isPusat() || $user->unit_kerja_id === $stock->unit_kerja_id;
}

public function correct(User $user, StockMovement $movement): bool
{
    return $user->isPusat() || $user->unit_kerja_id === $movement->unit_kerja_id;
}
```

`SparePartPolicy`: semua user aktif boleh `viewAny/view`, hanya Pusat boleh `create/update/delete`. `InventoryStockPolicy`: view dan movement mengikuti unit; Pusat dapat memilih unit. `StockMovementPolicy`: tidak memiliki update/delete, correction mengikuti unit asal. Controller tidak melakukan route-model binding terbuka untuk stock/movement; object selalu diambil melalui `visibleTo($request->user())->findOrFail(...)` sehingga percobaan lintas wilayah menghasilkan 404.

- [ ] **Step 4: Jalankan test otorisasi lalu commit**

Run: `php artisan test tests/Feature/InventoryAuthorizationTest.php tests/Feature/Admin/PusatAuthorizationTest.php`

Expected: PASS.

```bash
vendor/bin/pint --dirty
git add app/Policies tests/Feature/InventoryAuthorizationTest.php tests/Feature/Admin/PusatAuthorizationTest.php
git commit -m "feat: enforce regional inventory access"
```

### Task 3: Impor Master Sparepart dari Sheet Reorder Stock

**Files:**
- Create: `app/Services/SparePartWorkbookImporter.php`
- Create: `app/Console/Commands/ImportSpareParts.php`
- Test: `tests/Feature/SparePartImportTest.php`

- [ ] **Step 1: Tulis test mapping 12 kolom dan idempotensi source key**

```php
public function test_reorder_stock_sheet_is_imported_idempotently(): void
{
    $path = $this->makeReorderWorkbook([
        'System' => 'PERAGA SINYAL ELEKTRIK',
        'Sub-System' => 'Track Circuit',
        'Equipment' => 'Track Circuit',
        'Detail Equipment' => 'Relay Track',
        'Max yearly Failure' => 4,
        'Average Yearly Failure' => 2.5,
        'Max Lead Time (Month)' => 3,
        'Average Lead Time (Month)' => 2,
        'Safety Stock' => 8,
        'Lead Time Demand' => 5,
        'Reorder Point' => 13,
        'Severity Equipment' => 'Critical',
    ]);

    $this->artisan('rams:import-spare-parts', ['workbook' => $path])->assertSuccessful();
    $this->artisan('rams:import-spare-parts', ['workbook' => $path])->assertSuccessful();

    $this->assertDatabaseCount('spare_parts', 1);
    $this->assertDatabaseHas('spare_parts', [
        'detail_equipment' => 'Relay Track',
        'safety_stock' => 8,
        'lead_time_demand' => 5,
        'reorder_point' => 13,
        'severity' => 'Critical',
    ]);
}
```

- [ ] **Step 2: Jalankan test dan pastikan command belum ada**

Run: `php artisan test tests/Feature/SparePartImportTest.php`

Expected: FAIL karena command `rams:import-spare-parts` belum terdaftar.

- [ ] **Step 3: Implementasikan parser dengan header map konkret**

```php
private const HEADERS = [
    'system' => 'system',
    'sub-system' => 'subsystem',
    'equipment' => 'equipment',
    'detail equipment' => 'detail_equipment',
    'max yearly failure' => 'max_yearly_failure',
    'average yearly failure' => 'average_yearly_failure',
    'max lead time (month)' => 'max_lead_time_months',
    'average lead time (month)' => 'average_lead_time_months',
    'safety stock' => 'safety_stock',
    'lead time demand' => 'lead_time_demand',
    'reorder point' => 'reorder_point',
    'severity equipment' => 'severity',
];
```

Forward-fill System, Sub-System, dan Equipment untuk sel merge/kosong. Resolver mencari subsystem berdasarkan alias/nama system+subsystem. `source_key = sha256('Reorder Stock|system|subsystem|equipment|detail equipment')`; `code` stabil dibentuk `SP-` + 10 karakter uppercase dari hash source key bila Excel tidak memiliki kode.

- [ ] **Step 4: Upsert seluruh field tanpa membuat opening stock**

```php
$part = SparePart::withTrashed()->where('source_key', $sourceKey)->first();
if ($part?->trashed()) {
    $result['skipped']++;
    continue;
}

$sourceValues = [
    'asset_subsystem_id' => $resolved['subsystem']->id,
    'equipment' => $currentEquipment,
    'detail_equipment' => $detailEquipment,
    'max_yearly_failure' => $this->nullableDecimal($row['max_yearly_failure']),
    'average_yearly_failure' => $this->nullableDecimal($row['average_yearly_failure']),
    'max_lead_time_months' => $this->nullableDecimal($row['max_lead_time_months']),
    'average_lead_time_months' => $this->nullableDecimal($row['average_lead_time_months']),
    'safety_stock' => $this->nullableQuantity($row['safety_stock']),
    'lead_time_demand' => $this->nullableQuantity($row['lead_time_demand']),
    'reorder_point' => $this->nullableQuantity($row['reorder_point']),
    'severity' => $this->nullableText($row['severity']),
];

if ($part) {
    $part->update($sourceValues);
} else {
    SparePart::query()->create([
        ...$sourceValues,
        'source_key' => $sourceKey,
        'code' => 'SP-'.strtoupper(substr($sourceKey, 0, 10)),
        'unit_of_measure' => 'unit',
        'is_active' => true,
    ]);
}
```

Seluruh workbook diproses di dalam satu `DB::transaction`. Header/struktur salah melempar `RuntimeException`; error baris menyertakan workbook, sheet `Reorder Stock`, nomor baris, dan header terkait. Import ulang mempertahankan kode, satuan, dan status yang telah diubah Admin Pusat serta tidak memulihkan soft-deleted record.

- [ ] **Step 5: Jalankan test impor lalu commit**

Run: `php artisan test tests/Feature/SparePartImportTest.php`

Expected: PASS dan eksekusi kedua tidak menambah baris.

```bash
vendor/bin/pint --dirty
git add app/Services/SparePartWorkbookImporter.php app/Console/Commands/ImportSpareParts.php tests/Feature/SparePartImportTest.php
git commit -m "feat: import spareparts from reorder workbook"
```

### Task 4: Service Transaksi Stok yang Atomic dan Immutable

**Files:**
- Create: `app/Services/StockMovementService.php`
- Test: `tests/Feature/StockMovementServiceTest.php`

- [ ] **Step 1: Tulis test IN, OUT, saldo negatif, idempotensi, dan koreksi**

```php
public function test_out_rejects_insufficient_stock_without_partial_write(): void
{
    $stock = InventoryStock::factory()->create(['quantity' => 3]);
    $actor = User::factory()->pusat()->create();

    try {
        app(StockMovementService::class)->record(
            unit: $stock->unitKerja,
            part: $stock->sparePart,
            actor: $actor,
            type: StockMovementType::Out,
            direction: StockDirection::Out,
            quantity: 4,
            movementDate: CarbonImmutable::parse('2026-07-28'),
            referenceNumber: 'OUT-001',
            notes: null,
            idempotencyKey: '6a887dcf-7ff6-4f70-a9a5-34c641322159',
        );
        $this->fail('Expected ValidationException.');
    } catch (ValidationException $exception) {
        $this->assertArrayHasKey('quantity', $exception->errors());
    }

    $this->assertSame(3, $stock->refresh()->quantity);
    $this->assertDatabaseCount('stock_movements', 0);
}
```

Tambahkan test kedua bahwa request dengan `idempotency_key` sama mengembalikan movement pertama dan tidak menggandakan saldo; test ketiga bahwa correction membuat movement baru dengan `reverses_movement_id` dan tidak mengubah movement asli. Tambahkan test MySQL-only dengan dua proses OUT pada saldo yang sama: total sukses tidak boleh melebihi saldo, satu proses mendapat validation error, dan saldo/ledger akhir tetap konsisten.

- [ ] **Step 2: Jalankan test dan pastikan service belum ada**

Run: `php artisan test tests/Feature/StockMovementServiceTest.php`

Expected: FAIL karena `StockMovementService` belum ditemukan.

- [ ] **Step 3: Implementasikan signature dan transaksi dengan row lock**

```php
public function record(
    UnitKerja $unit,
    SparePart $part,
    User $actor,
    StockMovementType $type,
    StockDirection $direction,
    int $quantity,
    CarbonInterface $movementDate,
    ?string $referenceNumber,
    ?string $notes,
    string $idempotencyKey,
    ?StockMovement $reverses = null,
): StockMovement
```

Isi method dalam `DB::transaction`:

```php
$existing = StockMovement::query()->where('idempotency_key', $idempotencyKey)->first();
if ($existing) {
    return $existing;
}

$stock = InventoryStock::query()->firstOrCreate(
    ['unit_kerja_id' => $unit->id, 'spare_part_id' => $part->id],
    ['quantity' => 0],
);
$stock = InventoryStock::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
$existingAfterLock = StockMovement::query()->where('idempotency_key', $idempotencyKey)->first();
if ($existingAfterLock) {
    return $existingAfterLock;
}
$after = $direction->apply($stock->quantity, $quantity);

throw_if($quantity < 1, ValidationException::withMessages(['quantity' => 'Jumlah minimal 1.']));
throw_if($after < 0, ValidationException::withMessages(['quantity' => 'Stok keluar melebihi stok tersedia.']));
```

Kemudian create movement dengan before/after, `movement_date`, update stock, audit `stock.movement_created`, dan return movement. Correction harus memakai type `Correction`, part/unit sama dengan movement asal, direction eksplisit yang dipilih user, serta `reverses_movement_id` terisi. Simpan actor, unit, IP, user agent, nilai sebelum/sesudah melalui `AuditLogger`; jangan masukkan idempotency key ke audit payload.

- [ ] **Step 4: Tambah rekonsiliasi invariant dalam test**

```php
$ledger = StockMovement::query()->whereBelongsTo($stock->unitKerja)->whereBelongsTo($stock->sparePart)
    ->get()->sum(fn (StockMovement $movement): int =>
        $movement->direction === StockDirection::In ? $movement->quantity : -$movement->quantity);
$this->assertSame($ledger, $stock->refresh()->quantity);
```

- [ ] **Step 5: Jalankan test service lalu commit**

Run: `php artisan test tests/Feature/StockMovementServiceTest.php`

Expected: PASS seluruh skenario transaksi.

```bash
vendor/bin/pint --dirty
git add app/Services/StockMovementService.php tests/Feature/StockMovementServiceTest.php
git commit -m "feat: record auditable stock movements"
```

### Task 5: Endpoint Transaksi dan Koreksi

**Files:**
- Create: `app/Http/Requests/StoreStockMovementRequest.php`
- Create: `app/Http/Requests/CorrectStockMovementRequest.php`
- Create: `app/Http/Controllers/StockMovementController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/InventoryManagementTest.php`

- [ ] **Step 1: Tulis feature test endpoint, unit spoofing, dan correction**

```php
public function test_unit_user_records_out_for_own_unit_and_cannot_spoof_unit(): void
{
    $user = User::factory()->unit()->create();
    $part = SparePart::factory()->create();
    InventoryStock::factory()->for($user->unitKerja)->for($part)->create(['quantity' => 10]);

    $this->actingAs($user)->post(route('stock-movements.store'), [
        'unit_kerja_id' => UnitKerja::factory()->create()->id,
        'spare_part_id' => $part->id,
        'type' => 'out',
        'direction' => 'out',
        'quantity' => 3,
        'movement_date' => '2026-07-28',
        'reference_number' => 'WO-001',
        'notes' => 'Penggantian relay',
        'idempotency_key' => '98d4bb31-49f7-4e04-af74-e1b884de0b63',
    ])->assertRedirect(route('inventory.index'));

    $this->assertDatabaseHas('inventory_stocks', [
        'unit_kerja_id' => $user->unit_kerja_id,
        'spare_part_id' => $part->id,
        'quantity' => 7,
    ]);
}
```

- [ ] **Step 2: Jalankan test dan pastikan route belum tersedia**

Run: `php artisan test tests/Feature/InventoryManagementTest.php`

Expected: FAIL dengan route `stock-movements.store` tidak ditemukan.

- [ ] **Step 3: Implementasikan validasi request**

```php
return [
    'unit_kerja_id' => $this->user()->isPusat()
        ? ['required', Rule::exists('unit_kerjas', 'id')->where('is_active', true)]
        : ['prohibited'],
    'spare_part_id' => ['required', Rule::exists('spare_parts', 'id')->where('is_active', true)],
    'type' => ['required', Rule::enum(StockMovementType::class), Rule::notIn(['correction'])],
    'direction' => ['required', Rule::enum(StockDirection::class)],
    'quantity' => ['required', 'integer', 'min:1'],
    'movement_date' => ['required', 'date', 'before_or_equal:today'],
    'reference_number' => ['nullable', 'string', 'max:100'],
    'notes' => ['nullable', 'string', 'max:1000'],
    'idempotency_key' => ['required', 'uuid'],
];
```

Server memilih unit login untuk akun wilayah. Setelah validasi, request memastikan pasangan type-direction sah: `in → in`, `out → out`, dan `opening → in`; `correction` hanya melalui endpoint correction. Request correction menerima `direction`, `quantity`, `movement_date`, `notes`, `idempotency_key`; unit dan part selalu disalin dari movement asal.

- [ ] **Step 4: Implementasikan controller dan route tanpa update/delete**

```php
Route::post('/inventory/movements', [StockMovementController::class, 'store'])->name('stock-movements.store');
Route::post('/inventory/movements/{movement}/corrections', [StockMovementController::class, 'correct'])->name('stock-movements.correct');
```

Controller memanggil Gate lalu service. Setelah sukses, redirect ke inventory dengan flash “Transaksi stok berhasil dicatat.” atau “Koreksi stok berhasil dicatat.”

- [ ] **Step 5: Jalankan test endpoint dan commit**

Run: `php artisan test tests/Feature/InventoryManagementTest.php tests/Feature/InventoryAuthorizationTest.php`

Expected: PASS.

```bash
vendor/bin/pint --dirty
git add app/Http/Requests/StoreStockMovementRequest.php app/Http/Requests/CorrectStockMovementRequest.php app/Http/Controllers/StockMovementController.php routes/web.php tests/Feature/InventoryManagementTest.php
git commit -m "feat: expose regional stock transaction endpoints"
```

### Task 6: CRUD Master Sparepart Khusus Admin Pusat

**Files:**
- Create: `app/Http/Requests/Admin/StoreSparePartRequest.php`
- Create: `app/Http/Requests/Admin/UpdateSparePartRequest.php`
- Create: `app/Http/Controllers/Admin/SparePartController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/SparePartManagementTest.php`

- [ ] **Step 1: Tulis test Pusat dapat CRUD dan deaktif tidak menghapus ledger**

```php
public function test_pusat_deactivates_sparepart_without_deleting_stock_history(): void
{
    $pusat = User::factory()->pusat()->create();
    $part = SparePart::factory()->create();
    $movement = StockMovement::factory()->for($part)->create();

    $this->actingAs($pusat)->delete(route('admin.spare-parts.destroy', $part))
        ->assertRedirect(route('inventory.index', ['tab' => 'master']));

    $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'is_active' => false]);
    $this->assertDatabaseHas('stock_movements', ['id' => $movement->id]);
}
```

- [ ] **Step 2: Jalankan test dan pastikan route belum ada**

Run: `php artisan test tests/Feature/Admin/SparePartManagementTest.php`

Expected: FAIL dengan route admin sparepart belum ditemukan.

- [ ] **Step 3: Implementasikan validasi field master**

```php
return [
    'asset_subsystem_id' => ['required', Rule::exists('asset_subsystems', 'id')->where('is_active', true)],
    'code' => ['required', 'string', 'max:50', Rule::unique('spare_parts', 'code')->ignore($this->route('spare_part'))],
        'equipment' => ['nullable', 'string', 'max:255'],
        'detail_equipment' => ['required', 'string', 'max:255'],
    'max_yearly_failure' => ['nullable', 'numeric', 'min:0'],
    'average_yearly_failure' => ['nullable', 'numeric', 'min:0'],
    'max_lead_time_months' => ['nullable', 'numeric', 'min:0'],
    'average_lead_time_months' => ['nullable', 'numeric', 'min:0'],
    'safety_stock' => ['nullable', 'integer', 'min:0'],
    'lead_time_demand' => ['nullable', 'integer', 'min:0'],
    'reorder_point' => ['nullable', 'integer', 'min:0'],
    'severity' => ['nullable', 'string', 'max:100'],
    'unit_of_measure' => ['required', 'string', 'max:30'],
];
```

- [ ] **Step 4: Implementasikan controller, audit, dan route admin**

```php
Route::resource('spare-parts', SparePartController::class)->only(['store', 'update', 'destroy']);
```

Store membuat `source_key = sha256('manual|'.code)`; update tidak mengubah source key; destroy mengubah `is_active=false`. Semua aksi mencatat old/new value melalui `AuditLogger`.

- [ ] **Step 5: Jalankan test lalu commit**

Run: `php artisan test tests/Feature/Admin/SparePartManagementTest.php tests/Feature/Admin/PusatAuthorizationTest.php`

Expected: PASS.

```bash
vendor/bin/pint --dirty
git add app/Http/Requests/Admin app/Http/Controllers/Admin/SparePartController.php routes/web.php tests/Feature/Admin/SparePartManagementTest.php
git commit -m "feat: manage global sparepart master data"
```

### Task 7: InventoryController, Statistik Nyata, Filter, dan Pagination

**Files:**
- Create: `app/Http/Controllers/InventoryController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/InventoryIndexTest.php`

- [ ] **Step 1: Tulis test props Inertia dan scope unit**

```php
public function test_inventory_index_returns_real_scoped_stats_and_rows(): void
{
    $user = User::factory()->unit()->create();
    InventoryStock::factory()->for($user->unitKerja)->create(['quantity' => 4]);
    InventoryStock::factory()->for(UnitKerja::factory()->create())->create(['quantity' => 99]);

    $this->actingAs($user)->get(route('inventory.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('master-data/inventory/Inventory')
            ->where('stats.total_quantity', 4)
            ->has('stocks.data', 1)
            ->where('can.choose_unit', false)
            ->where('can.manage_master', false));
}
```

- [ ] **Step 2: Jalankan test dan pastikan closure lama tidak memberi props**

Run: `php artisan test tests/Feature/InventoryIndexTest.php`

Expected: FAIL karena `stats`/`stocks` tidak tersedia.

- [ ] **Step 3: Ganti closure dengan controller**

```php
Route::get('/inventory', InventoryController::class)->name('inventory.index');
Route::redirect('/reorder-stock', '/inventory?tab=master')->name('reorder-stock');
```

- [ ] **Step 4: Implementasikan filter dan props server-side**

Filter URL stok: `search`, `asset_group_id`, `asset_subsystem_id`, `stock_status` (`all|available|below_reorder|critical|empty`), `unit_kerja_id` khusus Pusat, `tab` (`stock|history|master`), dan `page`. Filter history menambah `movement_type`, `date_from`, dan `date_to`. Query eager-load `sparePart.assetSubsystem.assetSystem.assetGroup` dan unit, paginate 20; history memakai paginator berbeda bernama `movement_page` agar kedua daftar tidak saling mengubah halaman.

Statistik konkret:

```php
$stats = [
    'total_parts' => (clone $query)->count(),
    'total_quantity' => (int) (clone $query)->sum('quantity'),
    'below_reorder' => (clone $query)->whereColumn('inventory_stocks.quantity', '<=', 'spare_parts.reorder_point')->count(),
    'movements_this_month' => StockMovement::query()->visibleTo($user)->whereBetween('movement_date', [now()->startOfMonth(), now()->endOfMonth()])->count(),
];
```

Gunakan join atau subquery yang eksplisit agar `whereColumn` memakai `spare_parts.reorder_point`. Status `critical` berarti stok kosong atau `quantity <= safety_stock`; `below_reorder` berarti di atas safety stock tetapi `quantity <= reorder_point`; selebihnya `available`. Jangan tampilkan “prediksi 30 hari”, “dalam pengiriman”, atau PO karena belum ada tabel/model pendukung.

- [ ] **Step 5: Tambahkan movement ke ringkasan enam kolom Master Aset**

Ubah `AssetHierarchyQuery` agar `Sparepart IN/OUT` merupakan baseline Excel ditambah ledger unit-spesifik yang dikelompokkan melalui `spare_parts.asset_subsystem_id`:

```php
$movementTotals = StockMovement::query()
    ->visibleTo($user)
    ->when($user->isPusat() && $unitId, fn (Builder $query) => $query->where('unit_kerja_id', $unitId))
    ->join('spare_parts', 'spare_parts.id', '=', 'stock_movements.spare_part_id')
    ->selectRaw('spare_parts.asset_subsystem_id, direction, SUM(quantity) AS total')
    ->groupBy('spare_parts.asset_subsystem_id', 'direction')
    ->get()
    ->groupBy('asset_subsystem_id');
```

Merge nilai ini ke hasil hierarchy: `sparepart_in = baseline_in + movement in`, `sparepart_out = baseline_out + movement out`. Tambahkan route/filter `inventory?asset_subsystem_id={id}` dan tombol Lihat Sparepart, Transaksi IN, Transaksi OUT pada baris subsystem.

- [ ] **Step 6: Jalankan test index dan hierarchy lalu commit**

Run: `php artisan test tests/Feature/InventoryIndexTest.php`

Expected: PASS.

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/InventoryController.php app/Queries/AssetHierarchyQuery.php routes/web.php tests/Feature/InventoryIndexTest.php
git commit -m "feat: serve scoped inventory data"
```

### Task 8: UI Inventori Profesional, Responsif, dan Tanpa Dummy

**Files:**
- Modify: `resources/js/pages/master-data/inventory/Inventory.vue`
- Create: tujuh partial UI inventori yang tercantum di peta file
- Modify: `resources/js/layouts/MainLayout.vue`
- Test: `tests/js/Inventory.test.js`
- Test: `tests/js/MovementDialog.test.js`
- Test: `tests/js/MovementHistory.test.js`
- Test: `tests/js/SparePartDialog.test.js`

- [ ] **Step 1: Tulis test bahwa data berasal dari props dan tidak ada metrik dummy**

```js
it('menampilkan statistik server dan membuka transaksi untuk stok yang dipilih', async () => {
  const wrapper = mount(Inventory, {
    props: inventoryProps,
    global: inertiaStubs,
  })

  expect(wrapper.get('[data-testid="total-quantity"]').text()).toContain('137')
  expect(wrapper.text()).not.toContain('Prediksi Defisit (30 Hari)')
  expect(wrapper.text()).not.toContain('Dalam Pengiriman')
  await wrapper.get('[data-testid="record-movement-4"]').trigger('click')
  expect(wrapper.get('[role="dialog"]').text()).toContain('Catat transaksi stok')
})
```

- [ ] **Step 2: Tulis test dialog mencegah OUT melebihi stok di client**

```js
it('menampilkan error ketika OUT melebihi stok tersedia', async () => {
  const wrapper = mount(MovementDialog, {
    props: { open: true, stock: stockFixture, units: [], canChooseUnit: false },
    global: inertiaStubs,
  })

  await wrapper.get('select[name="type"]').setValue('out')
  await wrapper.get('input[name="quantity"]').setValue(stockFixture.quantity + 1)
  await wrapper.get('form').trigger('submit')
  expect(wrapper.text()).toContain('melebihi stok tersedia')
})
```

- [ ] **Step 3: Jalankan test dan pastikan halaman dummy gagal kontrak**

Run: `npm run test:js -- tests/js/Inventory.test.js tests/js/MovementDialog.test.js tests/js/MovementHistory.test.js tests/js/SparePartDialog.test.js`

Expected: FAIL karena komponen dan props belum ada.

- [ ] **Step 4: Bangun struktur halaman berbasis tabs**

Header ringkas berisi judul, keterangan unit aktif, tombol `Catat IN/OUT`, dan tombol `Tambah Suku Cadang` hanya untuk Pusat. Di bawahnya empat kartu statistik nyata: Total Jenis, Total Unit Tersedia, Di Bawah Reorder Point, Transaksi Bulan Ini.

Tabs:

```text
Stok Saat Ini | Riwayat Transaksi | Master Suku Cadang (Pusat saja)
```

Filter memakai Inertia GET dengan `preserveState`, `replace`, dan debounce 300ms. Tombol reset muncul hanya ketika filter aktif. Semua loading state memakai skeleton dengan ukuran stabil untuk menghindari layout shift.

- [ ] **Step 5: Bangun tabel desktop dan kartu mobile**

Kolom stok desktop: `Kode/Nama`, `Kategori`, `Unit Kerja` (Pusat), `Stok`, `Reorder Point`, `Status`, `Aksi`. Status: `Kosong/Kritis` merah ketika kosong atau mencapai safety stock, `Perlu Reorder` amber ketika mencapai reorder point, `Tersedia` hijau. Jangan memakai progress bar bila max capacity tidak tersedia dari data.

Mobile menampilkan kode/nama, breadcrumb subsystem, stok besar dengan angka tabular, badge status, unit, dan tombol transaksi 44px. Pagination mempertahankan query filter.

- [ ] **Step 6: Bangun dialog transaksi dan riwayat immutable**

Dialog fields: Jenis IN/OUT, Unit (Pusat), Suku Cadang, Jumlah, Tanggal, Nomor Referensi, Catatan, Saldo Sebelum, dan Proyeksi Saldo Setelah. Generate UUID melalui `crypto.randomUUID()` saat dialog dibuka; pertahankan UUID selama retry; buat UUID baru setelah sukses/tutup. OUT meminta konfirmasi kedua yang menyebut item, jumlah, dan saldo setelah; submit diblok bila proyeksi negatif.

History menampilkan tanggal operasional, waktu posting (`created_at`), unit, item, arah, jumlah, before → after, actor, referensi, dan badge “Koreksi”. Aksi “Buat koreksi” membuka dialog konfirmasi baru yang menautkan transaksi asal; tidak ada Edit/Hapus.

- [ ] **Step 7: Bangun dialog master sparepart untuk Pusat**

Gunakan dropdown kategori berjenjang yang sama dari plan kategori. Kelompokkan field menjadi Identitas, Parameter Kegagalan, Lead Time, dan Reorder. Field opsional diberi label “Opsional”, bantuan satuan bulan/unit, serta validasi inline server.

- [ ] **Step 8: Terapkan arahan visual dan aksesibilitas**

Gunakan slate/white sebagai permukaan utama, navy KAI untuk heading/navigasi, oranye KAI untuk primary action, dan warna status hanya untuk makna. Tidak memakai gradient hero besar. Maksimum lebar mengikuti `MainLayout`; ruang vertikal 16/24/32px; body text minimal 14px; target interaksi minimal 44px; focus ring 2px; label nyata; icon selalu memiliki teks atau `aria-label`; dialog focus trap dan Escape; empty/error/loading state spesifik.

- [ ] **Step 9: Jalankan test JS dan build lalu commit**

Run: `npm run test:js -- tests/js/Inventory.test.js tests/js/MovementDialog.test.js tests/js/MovementHistory.test.js tests/js/SparePartDialog.test.js`

Expected: PASS.

Run: `npm run build`

Expected: PASS.

```bash
git add resources/js/pages/master-data/inventory resources/js/layouts/MainLayout.vue tests/js/Inventory.test.js tests/js/MovementDialog.test.js tests/js/MovementHistory.test.js tests/js/SparePartDialog.test.js
git commit -m "feat: replace inventory prototype with real workflow"
```

### Task 9: Import Lokal, Rekonsiliasi, dan Verifikasi Browser

**Files:**
- Verify: seluruh file plan ini.

- [ ] **Step 1: Jalankan migration dan impor master sparepart**

```bash
docker compose ps
php artisan migrate
php artisan rams:import-spare-parts "D:\KAI RAMS\Risk Analysis And Management System RAMS Daop 1.xlsm"
```

Expected: MySQL healthy; migration sukses; output impor memberi created/updated/skipped; rerun tidak menambah duplicate.

- [ ] **Step 2: Buat opening stock per item melalui workflow transaksi**

Gunakan dialog/endpoint movement type `opening` dengan memilih sparepart dan unit. Jangan mengonversi otomatis `unit_subsystem_openings.sparepart_in/sparepart_out` ke stok item karena Excel A–F tidak menunjukkan nama sparepart.

Expected: setiap opening item menghasilkan satu ledger row dan satu inventory stock yang sama saldonya.

- [ ] **Step 3: Jalankan query rekonsiliasi melalui test**

Tambahkan test invariant untuk setiap inventory stock:

```php
InventoryStock::query()->each(function (InventoryStock $stock): void {
    $ledger = StockMovement::query()
        ->where('unit_kerja_id', $stock->unit_kerja_id)
        ->where('spare_part_id', $stock->spare_part_id)
        ->get()
        ->sum(fn (StockMovement $movement): int =>
            $movement->direction === StockDirection::In ? $movement->quantity : -$movement->quantity);

    $this->assertSame($ledger, $stock->quantity, "Stock mismatch for inventory_stocks.id={$stock->id}");
});
```

- [ ] **Step 4: Jalankan seluruh quality gate**

```bash
php artisan test
npm run test:js
vendor/bin/pint --test
npm run build
```

Expected: seluruh test PASS, Pint bersih, Vite build sukses.

- [ ] **Step 5: Verifikasi browser Pusat dan wilayah pada desktop/mobile**

Pusat:

- Dapat melihat stok lintas unit dan filter unit.
- Dapat CRUD/deaktif master sparepart tanpa menghapus history.
- Dapat transaksi untuk unit pilihan dan membuat koreksi.
- Impor Reorder Stock terlihat pada master dengan kategori/parameter yang benar.

Wilayah:

- Hanya melihat stok/history unit sendiri.
- Dapat IN/OUT dan koreksi transaksi unit sendiri.
- Tidak melihat tab/tombol mutasi master sparepart.
- Tidak dapat spoof `unit_kerja_id` melalui request.

Kedua viewport:

- Tidak ada data dummy, error console, request 500, horizontal overflow, dialog tanpa label, atau tombol terlalu kecil.
- Empty/filter/loading/server-validation states dapat dipahami tanpa pengetahuan teknis.

- [ ] **Step 6: Cek diff dan commit akhir tanpa push**

```bash
git status --short
git diff --check
git add -A
git commit -m "test: verify sparepart inventory workflow"
```

Expected: working tree bersih setelah commit; remote tidak berubah karena push dilakukan sendiri oleh pemilik repo.

## Kriteria Selesai

- Master sparepart global mengikuti sheet `Reorder Stock` dan dapat dikelola hanya oleh Pusat.
- Stock dan seluruh transaksi terisolasi per DAOP/Divre.
- OUT tidak pernah membuat saldo negatif, termasuk pada request bersamaan.
- Retry request tidak menggandakan transaksi karena idempotency key.
- Movement immutable; koreksi selalu berupa movement baru yang menunjuk transaksi asal.
- Materialized stock dan jumlah ledger selalu sama.
- Parameter reorder Excel tampil tanpa mengarang prediksi/PO yang belum memiliki data.
- UI profesional, nyaman, responsif, aksesibel, dan memakai data backend nyata.
- MySQL migration, impor ulang, backend test, frontend test, Pint, serta build seluruhnya lulus.
