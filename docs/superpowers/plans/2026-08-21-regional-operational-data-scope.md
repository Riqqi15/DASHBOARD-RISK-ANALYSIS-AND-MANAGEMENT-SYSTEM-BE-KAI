# Regional Operational Data Scope Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memastikan Master Aset, Risk Register, dan Inventori Suku Cadang selalu menampilkan serta memutasi data operasional untuk tepat satu DAOP/DIVRE, sementara Master Suku Cadang tetap global dan hanya dikelola Admin Pusat.

**Architecture:** Pertahankan model dan skema yang ada. Normalisasi unit aktif di batas controller/request, lalu gunakan unit tersebut pada seluruh query turunan, statistik, dialog transaksi, dan redirect. Admin Pusat memilih satu unit; pengguna regional selalu memakai unit akun. Jika tidak ada unit aktif, query operasional dibuat kosong dan aksi yang membutuhkan unit dinonaktifkan.

**Tech Stack:** Laravel 12, Eloquent, Inertia.js, Vue 3, Tailwind CSS, PHPUnit, Vitest.

---

## Task 1: Kunci Master Aset ke satu unit kerja

**Files:**
- Modify: `app/Http/Controllers/MasterAssetController.php`
- Test: `tests/Feature/MasterAssetManagementTest.php`

- [ ] **Step 1: Tulis tes gagal untuk fallback Admin Pusat dan scope regional**

Tambahkan kasus berikut pada `MasterAssetManagementTest`:

```php
public function test_pusat_without_unit_defaults_to_first_active_unit_instead_of_all_units(): void
{
    $pusat = User::factory()->pusat()->create();
    $first = UnitKerja::factory()->create(['code' => 'DAOP-1', 'is_active' => true]);
    $second = UnitKerja::factory()->create(['code' => 'DAOP-2', 'is_active' => true]);
    Asset::factory()->for($first)->create(['jumlah_unit' => 2]);
    Asset::factory()->for($second)->create(['jumlah_unit' => 9]);

    $this->actingAs($pusat)->get('/master-asset')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.unit_kerja_id', (string) $first->id)
            ->where('stats.total_assets', 1)
            ->where('stats.total_units', 2)
            ->has('assets.data', 1));
}
```

Tambahkan juga tes bahwa parameter unit tidak valid kembali ke unit aktif pertama, pengguna regional mengirim ID unit sendiri pada props filter, dan keadaan tanpa unit aktif menghasilkan nol aset/statistik.

- [ ] **Step 2: Jalankan tes dan pastikan gagal**

Run:

```powershell
rtk php artisan test tests/Feature/MasterAssetManagementTest.php --filter='defaults_to_first_active_unit|invalid_unit|regional_filter|without_active_unit'
```

Expected: gagal karena `filters.unit_kerja_id` masih kosong atau data masih teragregasi.

- [ ] **Step 3: Normalisasi unit pada backend**

Ubah `selectedUnitId()` agar regional memakai unit akun dan Admin Pusat memakai unit valid atau unit aktif pertama:

```php
private function selectedUnitId(Request $request): ?int
{
    if ($request->user()->isUnit()) {
        return UnitKerja::query()
            ->whereKey($request->user()->unit_kerja_id)
            ->where('is_active', true)
            ->value('id');
    }

    $requested = filter_var($request->input('unit_kerja_id'), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return UnitKerja::query()
        ->where('is_active', true)
        ->when($requested !== false, fn (Builder $query): Builder => $query->whereKey($requested))
        ->value('id')
        ?? UnitKerja::query()->where('is_active', true)->orderBy('code')->value('id');
}
```

Ubah `filteredQuery()` agar scope diterapkan untuk kedua role dan query kosong saat tidak ada unit:

```php
->when(
    $unitId,
    fn (Builder $query): Builder => $query->where('unit_kerja_id', $unitId),
    fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
)
```

- [ ] **Step 4: Jalankan seluruh tes Master Aset**

Run:

```powershell
rtk php artisan test tests/Feature/MasterAssetManagementTest.php tests/Feature/MasterAssetAuthorizationTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit perubahan backend Master Aset**

```powershell
rtk git add app/Http/Controllers/MasterAssetController.php tests/Feature/MasterAssetManagementTest.php
rtk git commit -m "fix: scope master assets to one operational unit"
```

## Task 2: Hilangkan tampilan lintas wilayah pada UI Master Aset

**Files:**
- Modify: `resources/js/pages/master-data/assets/MasterAsset.vue`
- Modify: `tests/js/MasterAsset.test.js`

- [ ] **Step 1: Tulis tes UI yang gagal**

Tambahkan assertion bahwa selector Admin Pusat tidak memiliki opsi `Semua unit kerja`, reset mempertahankan `unit_kerja_id`, dan pohon yang ditampilkan hanya berasal dari `hierarchy` terpilih, bukan kategori global kosong.

```js
expect(wrapper.get('#asset-unit').text()).not.toContain('Semua unit kerja')

await wrapper.get('[aria-label="Hapus semua filter"]').trigger('click')
expect(inertia.get).toHaveBeenCalledWith('/master-asset', {
  search: '', status: '', unit_kerja_id: '1',
}, expect.objectContaining({ preserveState: true, replace: true }))
```

- [ ] **Step 2: Jalankan tes dan pastikan gagal**

```powershell
rtk npm run test:js -- tests/js/MasterAsset.test.js
```

Expected: gagal pada opsi semua unit, reset unit, atau kategori global kosong.

- [ ] **Step 3: Terapkan UI satu wilayah**

Ubah indikator filter agar unit wajib tidak dianggap filter tambahan:

```js
const hasActiveFilters = computed(() => Boolean(filters.search || filters.status))
const displayCategoryTree = computed(() => [])
```

Pertahankan unit pada reset:

```js
const clearFilters = () => {
  filters.search = ''
  filters.status = ''
  applyFilters()
}
```

Hapus `<option value="">Semua unit kerja</option>` dari selector. Gunakan `hierarchy` sebagai satu-satunya sumber struktur operasional; `assetCategories` tetap tersedia untuk alur formulir yang memerlukannya, tetapi tidak ditampilkan sebagai data wilayah.

- [ ] **Step 4: Jalankan tes UI Master Aset**

```powershell
rtk npm run test:js -- tests/js/MasterAsset.test.js
```

Expected: PASS.

- [ ] **Step 5: Commit UI Master Aset**

```powershell
rtk git add resources/js/pages/master-data/assets/MasterAsset.vue tests/js/MasterAsset.test.js
rtk git commit -m "fix: keep master asset view regional"
```

## Task 3: Pertahankan scope wilayah pada Risk Register

**Files:**
- Modify: `app/Http/Controllers/RiskRegisterController.php`
- Modify: `app/Http/Requests/StoreRiskRegisterRequest.php`
- Modify: `app/Http/Requests/UpdateRiskRegisterRequest.php`
- Modify: `app/Services/RiskRegisterService.php`
- Modify: `resources/js/pages/risk-register/Index.vue`
- Modify: `tests/Feature/RiskRegisterManagementTest.php`
- Modify: `tests/js/RiskRegister.test.js`

- [ ] **Step 1: Tulis tes backend gagal untuk satu area**

Tambahkan tes berikut:

- Admin Pusat tanpa `area` melihat unit aktif pertama saja.
- Admin Pusat dengan `area=DIVRE-III` hanya melihat aset dan risiko DIVRE-III.
- Ketika tidak ada unit aktif, daftar aset dan risiko kosong.
- Create/update/delete mempertahankan query `area` pada redirect.
- Asset dari wilayah lain ditolak walaupun aktor adalah Admin Pusat dan mengetahui ID asetnya.

Contoh mutation test:

```php
$this->actingAs($pusat)->post('/risk-register?area=DAOP-1', [
    ...$this->payload($assetDaopDua),
    'unit_kerja_id' => $daopSatu->id,
])->assertForbidden();
```

- [ ] **Step 2: Jalankan tes dan pastikan gagal**

```powershell
rtk php artisan test tests/Feature/RiskRegisterManagementTest.php
```

Expected: gagal karena Admin Pusat masih boleh mengirim aset lintas area atau redirect kehilangan area.

- [ ] **Step 3: Validasi unit mutation dan scope service**

Tambahkan `unit_kerja_id` pada request create/update:

```php
'unit_kerja_id' => [
    Rule::requiredIf(fn (): bool => $this->user()?->isPusat() === true),
    Rule::prohibitedIf(fn (): bool => $this->user()?->isUnit() === true),
    'nullable',
    'integer',
    Rule::exists('unit_kerjas', 'id')->where('is_active', true),
],
```

Controller menentukan unit efektif: unit akun untuk regional, `unit_kerja_id` tervalidasi untuk Pusat. Kirim ID unit efektif ke `RiskRegisterService`, lalu pastikan `authorizedAsset()` dan `authorizeRegister()` membandingkan `asset.unit_kerja_id` dengan unit efektif untuk semua role.

Jangan teruskan `unit_kerja_id` ke `RiskRegister::create()` atau `fill()`:

```php
$data = $request->safe()->except('unit_kerja_id');
$service->create($data, $request->user(), $unitId);
```

Untuk index tanpa unit aktif, tambahkan cabang query kosong (`whereRaw('1 = 0')`) pada aset dan register agar tidak pernah menjadi agregat seluruh wilayah.

- [ ] **Step 4: Pertahankan area pada UI dan redirect**

Tambahkan unit aktif ke form Vue:

```js
const selectedUnit = computed(() => props.units.find(unit => unit.code === props.selected_area))
const emptyForm = () => ({
  unit_kerja_id: props.can_choose_unit ? selectedUnit.value?.id ?? '' : '',
  asset_id: '', part_number: '', sub: '', risk_event: '', risk_cause: '', impact: '',
  part_name: '', recommendation: '', likelihood: 1, consequence: 1, status: 'open',
})
const scopedUrl = path => props.selected_area ? `${path}?area=${encodeURIComponent(props.selected_area)}` : path
```

Gunakan `scopedUrl()` pada POST, PUT, dan DELETE. Controller mengembalikan redirect ke `risk-register.index` dengan parameter `area` dari unit efektif.

- [ ] **Step 5: Jalankan tes Risk Register**

```powershell
rtk php artisan test tests/Feature/RiskRegisterManagementTest.php tests/Feature/RiskRegisterWorkbookImporterTest.php
rtk npm run test:js -- tests/js/RiskRegister.test.js
```

Expected: PASS.

- [ ] **Step 6: Commit Risk Register**

```powershell
rtk git add app/Http/Controllers/RiskRegisterController.php app/Http/Requests/StoreRiskRegisterRequest.php app/Http/Requests/UpdateRiskRegisterRequest.php app/Services/RiskRegisterService.php resources/js/pages/risk-register/Index.vue tests/Feature/RiskRegisterManagementTest.php tests/js/RiskRegister.test.js
rtk git commit -m "fix: preserve risk register regional scope"
```

## Task 4: Kunci seluruh data operasional Inventori ke satu unit

**Files:**
- Modify: `app/Http/Controllers/InventoryController.php`
- Modify: `app/Services/InventoryReconciliationService.php`
- Modify: `tests/Feature/InventoryIndexTest.php`
- Modify: `tests/Feature/InventoryReconciliationTest.php`
- Modify: `tests/Feature/InventoryExcelReconciliationTest.php`

- [ ] **Step 1: Ubah tes lama yang mengizinkan agregat seluruh unit**

Ganti skenario `all_units_are_available_without_selection` menjadi fallback unit pertama. Pastikan props berikut semuanya hanya memuat unit tersebut:

- `stocks.data`
- `movements.data`
- `stats`
- `predictiveAssets`
- `reconciliation.rows`
- `filters.unit_kerja_id`

Master `spareParts` tetap global dan tidak difilter unit.

- [ ] **Step 2: Tambahkan tes batas**

Tambahkan `test_pusat_invalid_unit_falls_back_to_first_active_unit_for_every_operational_tab`: buat dua unit aktif, masing-masing satu stok, movement, aset dengan predictive snapshot, dan baris rekonsiliasi; request `unit_kerja_id=999999`; assert `filters.unit_kerja_id` adalah ID unit pertama dan setiap payload operasional hanya membawa ID unit pertama. Tambahkan tes pengguna regional selalu mendapat `filters.unit_kerja_id` milik sendiri dan tes tanpa unit aktif menghasilkan payload operasional kosong tanpa membocorkan unit nonaktif.

- [ ] **Step 3: Jalankan tes inventori dan pastikan gagal**

```powershell
rtk php artisan test tests/Feature/InventoryIndexTest.php tests/Feature/InventoryReconciliationTest.php tests/Feature/InventoryExcelReconciliationTest.php
```

Expected: gagal karena unit kosong masih memperlebar query.

- [ ] **Step 4: Normalisasi unit efektif pada `InventoryController`**

Gunakan aturan yang sama dengan Master Aset:

```php
private function activeUnitId(Request $request): ?int
{
    if ($request->user()->isUnit()) {
        return UnitKerja::query()
            ->whereKey($request->user()->unit_kerja_id)
            ->where('is_active', true)
            ->value('id');
    }

    $requested = $this->scalarString($request->input('unit_kerja_id'));
    $selected = ctype_digit($requested)
        ? UnitKerja::query()->where('is_active', true)->whereKey((int) $requested)->value('id')
        : null;

    return $selected
        ?? UnitKerja::query()->where('is_active', true)->orderBy('code')->value('id');
}
```

Pada `stockQuery()`, `movementQuery()`, dan `predictiveAssets()`, filter unit harus selalu dipakai. Jika `filters.unit_kerja_id` kosong karena tidak ada unit aktif, tambahkan `whereRaw('1 = 0')`.

`InventoryReconciliationService::reconcile()` harus melakukan hal yang sama pada query aset dan stok. Jangan ubah pencocokan Excel-versus-ledger.

Jangan tambahkan filter unit ke `spareParts()` karena Master Suku Cadang adalah referensi global.

- [ ] **Step 5: Jalankan tes inventori backend**

```powershell
rtk php artisan test tests/Feature/InventoryIndexTest.php tests/Feature/InventoryManagementTest.php tests/Feature/InventoryReconciliationTest.php tests/Feature/InventoryExcelReconciliationTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit backend inventori**

```powershell
rtk git add app/Http/Controllers/InventoryController.php app/Services/InventoryReconciliationService.php tests/Feature/InventoryIndexTest.php tests/Feature/InventoryReconciliationTest.php tests/Feature/InventoryExcelReconciliationTest.php
rtk git commit -m "fix: scope inventory operations to one unit"
```

## Task 5: Rapikan selector unit dan istilah stok pada UI Inventori

**Files:**
- Modify: `resources/js/pages/master-data/inventory/Inventory.vue`
- Modify: `resources/js/pages/master-data/inventory/Partials/InventoryFilters.vue`
- Modify: `app/Http/Controllers/Admin/UnitSubsystemOpeningController.php`
- Modify: `tests/js/Inventory.test.js`
- Modify: `tests/js/InventoryFilters.test.js`

- [ ] **Step 1: Tulis tes UI gagal**

Tambahkan assertion:

```js
expect(wrapper.get('#inventory-unit').text()).not.toContain('Semua unit kerja')
expect(wrapper.text()).not.toMatch(/saldo/i)
```

Uji reset mempertahankan unit:

```js
expect(inertia.get).toHaveBeenCalledWith('/inventory', expect.objectContaining({
  unit_kerja_id: '7',
  search: '',
  stock_status: 'all',
}), expect.any(Object))
```

- [ ] **Step 2: Jalankan tes dan pastikan gagal**

```powershell
rtk npm run test:js -- tests/js/Inventory.test.js tests/js/InventoryFilters.test.js
```

Expected: gagal karena opsi semua unit/reset unit/teks lama.

- [ ] **Step 3: Terapkan selector satu unit**

Hapus opsi `Semua unit kerja` dari `InventoryFilters.vue`. Pada `Inventory.vue`:

```js
const unitContext = computed(() => selectedUnit.value
  ? `${selectedUnit.value.code} — ${selectedUnit.value.name}`
  : scopedUnit.value
    ? `${scopedUnit.value.code} — ${scopedUnit.value.name}`
    : 'Belum ada unit kerja aktif')

const hasActiveFilters = computed(() => Boolean(
  filterState.search || filterState.asset_group_id || filterState.asset_subsystem_id
  || (activeTab.value === 'stock' && filterState.stock_status !== 'all')
  || (activeTab.value === 'history' && (filterState.movement_type || filterState.date_from || filterState.date_to))
  || (activeTab.value === 'reconciliation' && filterState.reconciliation_status !== 'all')
))
```

Pada `resetFilters()`, jangan tulis ulang `unit_kerja_id`. Nonaktifkan `Catat IN/OUT` bila tidak ada unit efektif.

Ganti copy pengguna `Saldo pembukaan unit` menjadi `Stok pembukaan unit` di `UnitSubsystemOpeningController`; nama kolom internal tetap dipertahankan agar tidak memerlukan migrasi.

- [ ] **Step 4: Jalankan tes UI inventori**

```powershell
rtk npm run test:js -- tests/js/Inventory.test.js tests/js/InventoryFilters.test.js
```

Expected: PASS.

- [ ] **Step 5: Commit UI dan copy inventori**

```powershell
rtk git add resources/js/pages/master-data/inventory/Inventory.vue resources/js/pages/master-data/inventory/Partials/InventoryFilters.vue app/Http/Controllers/Admin/UnitSubsystemOpeningController.php tests/js/Inventory.test.js tests/js/InventoryFilters.test.js
rtk git commit -m "fix: clarify regional inventory context"
```

## Task 6: Verifikasi aturan transaksi stok dan otorisasi

**Files:**
- Modify if failing: `app/Http/Requests/StoreStockMovementRequest.php`
- Modify if failing: `app/Http/Requests/CorrectStockMovementRequest.php`
- Modify if failing: `app/Http/Controllers/StockMovementController.php`
- Modify if failing: `app/Policies/InventoryStockPolicy.php`
- Modify if failing: `app/Policies/StockMovementPolicy.php`
- Test: `tests/Feature/InventoryAuthorizationTest.php`
- Test: `tests/Feature/StockMovementServiceTest.php`
- Test: `tests/Feature/StockMovementCorrectionInvariantTest.php`
- Test: `tests/Feature/StockMovementDatabaseImmutabilityTest.php`
- Test: `tests/Feature/Admin/SparePartManagementTest.php`

- [ ] **Step 1: Tambahkan regression test dialog/API lintas unit**

Pastikan:

- Regional tidak dapat mencatat atau mengoreksi stok unit lain.
- Admin Pusat dapat mencatat hanya pada unit eksplisit yang aktif.
- Suku cadang nonaktif ditolak untuk transaksi baru.
- OUT tidak boleh membuat stok negatif.
- Koreksi tidak mengubah movement lama dan membuat movement koreksi baru.
- Hanya Admin Pusat dapat membuat/mengubah Master Suku Cadang global.

- [ ] **Step 2: Jalankan suite transaksi stok**

```powershell
rtk php artisan test tests/Feature/InventoryAuthorizationTest.php tests/Feature/StockMovementServiceTest.php tests/Feature/StockMovementCorrectionInvariantTest.php tests/Feature/StockMovementDatabaseImmutabilityTest.php tests/Feature/Admin/SparePartManagementTest.php
```

Expected: PASS. Jika ada tes gagal, lakukan perubahan minimal pada file production yang disebut di atas, lalu ulangi command yang sama.

- [ ] **Step 3: Commit hanya jika ada perbaikan production/test baru**

```powershell
rtk git add app/Http/Requests/StoreStockMovementRequest.php app/Http/Requests/CorrectStockMovementRequest.php app/Http/Controllers/StockMovementController.php app/Policies/InventoryStockPolicy.php app/Policies/StockMovementPolicy.php tests/Feature/InventoryAuthorizationTest.php tests/Feature/StockMovementServiceTest.php tests/Feature/StockMovementCorrectionInvariantTest.php tests/Feature/StockMovementDatabaseImmutabilityTest.php tests/Feature/Admin/SparePartManagementTest.php
rtk git commit -m "test: harden regional stock invariants"
```

## Task 7: Verifikasi menyeluruh dan uji browser

**Files:**
- Verify only unless regression is found.

- [ ] **Step 1: Jalankan formatter dan seluruh backend tests**

```powershell
rtk vendor/bin/pint --dirty
rtk php artisan test
```

Expected: semua tes PASS.

- [ ] **Step 2: Jalankan seluruh frontend tests dan production build**

```powershell
rtk npm run test:js
rtk npm run build
```

Expected: semua Vitest PASS dan Vite build selesai tanpa error.

- [ ] **Step 3: Pastikan tidak ada copy `saldo` yang terlihat pengguna**

```powershell
rtk rg -n -i "saldo" app resources/js tests
```

Expected: tidak ada copy UI/pesan pengguna; kemunculan internal yang memang nama historis harus didokumentasikan dan tidak direname.

- [ ] **Step 4: Uji browser dengan Admin Pusat**

Gunakan browser lokal pada `http://127.0.0.1:8000` dan periksa:

1. `/master-asset`: default DAOP/DIVRE pertama, pindah unit, reset tetap pada unit tersebut.
2. `/risk-register`: area selector membatasi daftar dan pilihan aset; create/edit/delete tetap di area yang sama.
3. `/inventory`: stok, riwayat, predictive, rekonsiliasi, dan dialog transaksi mengikuti unit selector.
4. Tab Master Suku Cadang tetap global dan dapat dikelola Admin Pusat.
5. Tidak ada opsi `Semua unit kerja`, agregat lintas wilayah, atau istilah `saldo`.

- [ ] **Step 5: Uji browser dengan akun regional**

Pastikan selector tidak tampil, data terkunci ke unit akun, Master Suku Cadang tidak dapat dikelola, dan percobaan mutation lintas unit ditolak.

- [ ] **Step 6: Tinjau diff dan commit final regression fix bila ada**

```powershell
rtk git diff --check
rtk git status --short
```

Expected: tidak ada whitespace error dan hanya perubahan yang terkait scope regional.
