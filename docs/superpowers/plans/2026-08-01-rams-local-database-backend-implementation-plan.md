# RAMS Local Database Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membentuk backend RAMS lengkap dan mengisi MySQL lokal dengan data awal yang saat ini masih berada di JavaScript, tanpa menghapus akun atau memakai `migrate:fresh`.

**Architecture:** Laravel tetap menjadi boundary otorisasi dan sumber data untuk aplikasi Inertia. Schema baru menormalkan risk matrix, risk register, reliability summary, dan failure log di atas tabel aset/unit/inventory yang sudah dirancang. Seeder idempoten memindahkan data awal dengan natural key sehingga aman dijalankan ulang.

**Tech Stack:** PHP 8.4, Laravel 13, Eloquent, Form Request, MySQL 8.4 Docker, PHPUnit.

**Commit policy:** Jangan membuat commit sampai pengguna meminta setelah seluruh backend dan database lokal selesai.

---

### Task 1: Schema risiko dan reliability

**Files:**
- Create: `database/migrations/2026_08_01_000000_create_risk_matrices_table.php`
- Create: `database/migrations/2026_08_01_000001_create_risk_registers_table.php`
- Create: `database/migrations/2026_08_01_000002_create_reliability_summaries_table.php`
- Create: `database/migrations/2026_08_01_000003_create_failure_logs_table.php`
- Test: `tests/Feature/RamsOperationalSchemaTest.php`

- [ ] **Step 1: Tulis test schema yang gagal**

Test memastikan foreign key, index, unique constraint, nullable field, dan tipe kolom tersedia. `risk_matrices.asset_id` dan pasangan `reliability_summaries(asset_id, period)` harus unik. `failure_logs` memiliki FK aset, sparepart nullable, creator, waktu kejadian/selesai, serta jumlah sparepart.

- [ ] **Step 2: Jalankan test schema dan konfirmasi gagal**

Run:

```powershell
& 'C:\Program Files\PHP\php.exe' -d extension=pdo_mysql -d extension=intl artisan test tests/Feature/RamsOperationalSchemaTest.php
```

Expected: gagal karena tabel baru belum ada.

- [ ] **Step 3: Buat migration**

Gunakan `foreignId()->constrained()->restrictOnDelete()` untuk aset dan sparepart, `nullOnDelete()` untuk creator, check constraint MySQL untuk likelihood/consequence 1..5, enum string untuk status risk register, decimal yang cukup untuk metrik reliability, dan timestamps.

- [ ] **Step 4: Jalankan test schema**

Expected: seluruh assertion schema lulus.

### Task 2: Enum, model, relasi, dan scope wilayah

**Files:**
- Create: `app/Enums/RiskRegisterStatus.php`
- Create: `app/Models/RiskMatrix.php`
- Create: `app/Models/RiskRegister.php`
- Create: `app/Models/ReliabilitySummary.php`
- Create: `app/Models/FailureLog.php`
- Modify: `app/Models/Asset.php`
- Modify: `app/Models/SparePart.php`
- Modify: `app/Models/User.php`
- Create: `database/factories/RiskMatrixFactory.php`
- Create: `database/factories/RiskRegisterFactory.php`
- Create: `database/factories/ReliabilitySummaryFactory.php`
- Create: `database/factories/FailureLogFactory.php`
- Test: `tests/Feature/RamsOperationalModelTest.php`

- [ ] **Step 1: Tulis test model yang gagal**

Uji cast enum/bool/datetime/decimal, rating risk matrix, relasi aset, relasi failure log, dan scope `visibleTo(User $user)` untuk pusat serta unit.

- [ ] **Step 2: Jalankan test dan konfirmasi gagal**

- [ ] **Step 3: Implementasikan model**

Semua model memakai `#[Fillable([...])]`, `HasFactory`, relasi Eloquent bertipe, dan cast melalui `casts(): array`. Scope wilayah memfilter melalui `asset.unit_kerja_id` agar kepemilikan hanya bersumber dari aset.

- [ ] **Step 4: Jalankan test model**

Expected: test model dan relasi lulus tanpa query lintas unit.

### Task 3: Service kalkulasi reliability

**Files:**
- Create: `app/Services/ReliabilityCalculator.php`
- Test: `tests/Unit/ReliabilityCalculatorTest.php`

- [ ] **Step 1: Tulis test formula**

Kasus wajib: tanpa failure, downtime lintas tanggal, beberapa failure, operating hours nol, MTBF, MTTR, failure rate, reliability `exp(-failureRate)`, dan availability.

- [ ] **Step 2: Jalankan test dan konfirmasi gagal**

- [ ] **Step 3: Implementasikan value calculation**

Service menerima jumlah unit, awal/akhir periode, dan koleksi interval failure. Hasilnya berupa array bernama yang cocok dengan kolom `reliability_summaries`. Semua durasi disimpan dalam menit dan dikonversi ke jam hanya pada response.

- [ ] **Step 4: Jalankan test kalkulator**

Expected: seluruh edge case lulus dan tidak ada pembagian nol.

### Task 4: Seeder data awal yang idempoten

**Files:**
- Create: `database/seeders/RamsOperationalDataSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/RamsOperationalDataSeederTest.php`

- [ ] **Step 1: Tulis test idempotensi yang gagal**

Jalankan seeder dua kali lalu pastikan jumlah unit, aset, risk matrix, risk register, reliability summary, failure log, sparepart, dan inventory tidak berubah pada run kedua. Pastikan akun admin yang sudah ada tetap ada.

- [ ] **Step 2: Jalankan test dan konfirmasi gagal**

- [ ] **Step 3: Implementasikan seeder**

Seeder harus:

1. memanggil `UnitKerjaSeeder`;
2. menormalisasi `DAOP1` menjadi `DAOP-1` dan `DIVRE4` menjadi `DIVRE-IV`;
3. memakai `AssetCategoryResolver` untuk group/system/subsystem;
4. membuat aset dengan `source_key = hash('sha256', implode('|', [...natural key...]))`;
5. menautkan risiko/reliability/failure melalui `source_key`, bukan ID array lama;
6. membuat master sparepart berdasarkan kode statis yang sekarang dipakai Inventory dan Trouble Report;
7. membuat opening stock melalui `StockMovementService` bila belum memiliki movement opening, agar ledger dan saldo konsisten;
8. memakai transaction dan `updateOrCreate`/`firstOrCreate`;
9. tidak menghapus atau menimpa data operasional yang dibuat pengguna.

- [ ] **Step 4: Jalankan test seeder dua kali**

Expected: jumlah baris run pertama dan kedua identik; admin tetap ada.

### Task 5: Query service untuk dashboard dan halaman RAMS

**Files:**
- Create: `app/Services/RamsDashboardQuery.php`
- Create: `app/Http/Controllers/RamsDashboardController.php`
- Create: `app/Http/Requests/RamsAreaRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/RamsDashboardBackendTest.php`

- [ ] **Step 1: Tulis feature test otorisasi dan payload**

Uji pusat melihat nasional/unit pilihan, akun wilayah selalu dibatasi unit sendiri, area tidak valid ditolak, dan payload dashboard/overview/risk matrix/inventory/reorder/trouble report berasal dari database.

- [ ] **Step 2: Jalankan test dan konfirmasi gagal**

- [ ] **Step 3: Implementasikan request, query service, dan controller**

Query service menyediakan method terpisah untuk summary, assets, risks, risk registers, reliability, failure trend, inventory, reorder candidates, dan trouble report. Gunakan eager loading, aggregate SQL, serta pagination bila daftar dapat tumbuh. Controller hanya menentukan konteks unit dan membentuk Inertia response.

- [ ] **Step 4: Jalankan feature test dashboard**

Expected: seluruh payload dan pembatasan unit lulus.

### Task 6: Penyimpanan Trouble Report

**Files:**
- Create: `app/Http/Requests/StoreFailureLogRequest.php`
- Create: `app/Services/FailureLogService.php`
- Create: `app/Http/Controllers/FailureLogController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/FailureLogManagementTest.php`

- [ ] **Step 1: Tulis test gagal**

Uji validasi aset, waktu selesai, akses lintas unit, penyimpanan permanen, pengurangan stok, rollback saat stok tidak cukup, idempotensi request, dan audit log.

- [ ] **Step 2: Jalankan test dan konfirmasi gagal**

- [ ] **Step 3: Implementasikan transaction service**

Service memuat ulang model authoritative, memverifikasi akses aktor, membuat failure log, mencatat stock movement OUT bila diperlukan, menghitung ulang reliability summary, dan mencatat audit log dalam satu transaksi.

- [ ] **Step 4: Jalankan feature test**

Expected: data bertahan setelah request baru dan rollback bekerja tanpa baris parsial.

### Task 7: Migrasikan dan seed MySQL lokal

**Files:**
- Modify database lokal `rams` melalui migration dan seeder saja.

- [ ] **Step 1: Ambil baseline**

Run `artisan migrate:status` dan `artisan db:show --counts`; simpan jumlah users sebelum migrasi.

- [ ] **Step 2: Jalankan migration normal**

```powershell
& 'C:\Program Files\PHP\php.exe' -d extension=pdo_mysql -d extension=intl artisan migrate --force
```

Expected: seluruh migration pending menjadi `Ran` tanpa reset data.

- [ ] **Step 3: Jalankan seeder data RAMS**

```powershell
& 'C:\Program Files\PHP\php.exe' -d extension=pdo_mysql -d extension=intl artisan db:seed --class=RamsOperationalDataSeeder --force
```

- [ ] **Step 4: Jalankan seeder kedua kali**

Expected: sukses tanpa unique violation dan tanpa kenaikan jumlah baris.

- [ ] **Step 5: Verifikasi database**

Run `artisan migrate:status`, `artisan db:show --counts`, dan query count per tabel. Pastikan user awal tetap ada serta semua tabel RAMS terisi.

### Task 8: Verifikasi backend lengkap

**Files:**
- No production changes unless verification reveals a defect.

- [ ] **Step 1: Jalankan test backend relevan**

Run seluruh test schema, model, seeder, dashboard, failure log, asset, inventory, dan stock movement.

- [ ] **Step 2: Jalankan seluruh test Laravel**

```powershell
& 'C:\Program Files\PHP\php.exe' -d extension=pdo_mysql -d extension=intl artisan test
```

- [ ] **Step 3: Jalankan Pint check dan route listing**

```powershell
& 'C:\Program Files\PHP\php.exe' vendor/bin/pint --test
& 'C:\Program Files\PHP\php.exe' -d extension=pdo_mysql -d extension=intl artisan route:list
```

- [ ] **Step 4: Audit status Git**

Pastikan semua perubahan masih lokal dan tidak ada commit baru.
