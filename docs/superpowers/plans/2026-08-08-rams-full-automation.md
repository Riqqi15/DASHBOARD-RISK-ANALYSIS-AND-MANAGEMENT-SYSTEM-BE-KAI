# RAMS Full Automation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan deteksi unit otomatis, detail/progres batch, queue import, rekonsiliasi stok, PDF, dan rollback aman tanpa menyentuh user atau ledger stok melalui import.

**Architecture:** Upload membuat batch dan menyimpan file private sebelum dispatch job database. Service import tetap menjadi orkestrator domain, tetapi progres dan perubahan per baris direkam pada batch. Rekonsiliasi bersifat read-only dan XLSX/PDF memakai dataset laporan yang sama.

**Tech Stack:** PHP 8.3, Laravel 13, MySQL, database queue, Inertia 3, Vue 3, Tailwind CSS, PhpSpreadsheet, Dompdf, PHPUnit, Vitest.

---

### Task 1: Shared unit detector and asynchronous submission

**Files:**
- Create: `app/Services/RamsUnitDetector.php`
- Create: `app/Services/RamsImportSubmissionService.php`
- Create: `app/Jobs/ProcessRamsWorkbookImport.php`
- Modify: `app/Services/RamsWorkbookImportCoordinator.php`
- Modify: `app/Http/Requests/ImportFailureLogsRequest.php`
- Modify: `app/Http/Controllers/FailureLogImportController.php`
- Test: `tests/Unit/RamsUnitDetectorTest.php`
- Test: `tests/Feature/RamsImportQueueTest.php`

- [ ] Tulis test pemetaan DAOP 1/4/8 dan DIVRE III/IV serta nama tidak dikenal.
- [ ] Jalankan `php artisan test tests/Unit/RamsUnitDetectorTest.php` dan pastikan gagal karena service belum ada.
- [ ] Implementasikan `detectCode(string $filename): ?string` dan ganti pemetaan private coordinator.
- [ ] Tulis feature test bahwa pusat mendapat unit otomatis, mismatch ditolak, unit user tidak dapat mengalihkan target, upload disimpan private, dan job didispatch.
- [ ] Implementasikan submission service yang menghitung fingerprint, membuat batch `queued`, menyimpan workbook, dan dispatch job unik ke queue `rams-imports`.
- [ ] Implementasikan job dengan timeout, retry, cleanup file, serta handler gagal yang menutup batch.
- [ ] Jalankan test unit dan feature terarah sampai lulus.

### Task 2: Batch progress, details, and polling

**Files:**
- Create: `database/migrations/2026_08_08_000002_add_import_progress_and_rollback_metadata.php`
- Modify: `app/Models/RamsImportBatch.php`
- Modify: `app/Services/FailureLogImportService.php`
- Modify: `app/Http/Controllers/FailureLogImportController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/pages/input-data/TroubleReportImport.vue`
- Modify: `tests/Feature/RamsImportHistoryTest.php`
- Modify: `tests/js/TroubleReportImport.test.js`

- [ ] Tulis failing test untuk status JSON dengan scoping unit, progress stage/percent, dan detail summary/issues.
- [ ] Tambahkan kolom queue/progress/rollback dan cast/relasi batch.
- [ ] Pecah tahap import agar progress bergerak monoton dari 10 sampai 100 dan status terminal selalu konsisten.
- [ ] Tambahkan endpoint status batch serta payload `can_rollback` dan alasan bila tidak tersedia.
- [ ] Tambahkan deteksi filename di UI, polling batch aktif, progress bar, serta panel detail yang dapat dibuka.
- [ ] Jalankan test PHP dan JS terarah sampai lulus.

### Task 3: Change recording and guarded rollback

**Files:**
- Create: `database/migrations/2026_08_08_000003_create_rams_import_changes.php`
- Create: `app/Models/RamsImportChange.php`
- Create: `app/Services/RamsImportChangeRecorder.php`
- Create: `app/Services/RamsImportRollbackService.php`
- Create: `app/Http/Controllers/RamsImportRollbackController.php`
- Modify: `app/Models/RamsImportBatch.php`
- Modify: `app/Services/FailureLogImportService.php`
- Modify: `routes/web.php`
- Modify: `resources/js/pages/input-data/TroubleReportImport.vue`
- Test: `tests/Feature/RamsImportRollbackTest.php`

- [ ] Tulis failing test bahwa recorder menyimpan created/updated rows tetapi mengecualikan `users`, `inventory_stocks`, dan `stock_movements`.
- [ ] Tambahkan tabel change dengan batch, table name, row ID, operation, before/after JSON, dan after hash.
- [ ] Implementasikan snapshot, diff, hash stabil, daftar tabel eksplisit, serta urutan parent/child.
- [ ] Hubungkan snapshot before/after ke transaksi import sukses non-dry-run.
- [ ] Tulis failing test rollback pusat terbaru berhasil, user unit ditolak, batch lama ditolak, perubahan manual ditolak, dan user/ledger identik.
- [ ] Implementasikan preflight seluruh change lalu rollback atomik dan audit status batch.
- [ ] Tambahkan tombol rollback dengan dialog konfirmasi serta alasan nonaktif.
- [ ] Jalankan test rollback dan import terarah sampai lulus.

### Task 4: Read-only stock reconciliation

**Files:**
- Create: `app/Services/InventoryReconciliationService.php`
- Modify: `app/Http/Controllers/InventoryController.php`
- Modify: `resources/js/pages/master-data/inventory/Inventory.vue`
- Modify: `tests/Feature/InventoryIndexTest.php`
- Modify: `tests/js/Inventory.test.js`

- [ ] Tulis failing test untuk status matched, difference, missing ledger, missing Excel, ambiguous, dan scoping unit.
- [ ] Implementasikan normalisasi nama, pencocokan unit/subsystem, agregasi stok, status, statistik, filter, dan pagination read-only.
- [ ] Tambahkan tab `Rekonsiliasi Excel` dengan kartu statistik, tabel selisih, badge status, dan penjelasan bahwa koreksi melalui transaksi.
- [ ] Pastikan tidak ada write ke ledger dan jalankan test terarah sampai lulus.

### Task 5: PDF reports using shared datasets

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `app/Services/RamsReportExportService.php`
- Modify: `app/Http/Controllers/RamsReportController.php`
- Create: `resources/views/reports/rams-pdf.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/js/pages/reports/Index.vue`
- Modify: `tests/Feature/RamsReportExportTest.php`
- Modify: `tests/js/Reports.test.js`

- [ ] Tambahkan `dompdf/dompdf` melalui Composer dan verifikasi package discovery/autoload.
- [ ] Refactor service menjadi dataset publik yang dipakai workbook dan PDF tanpa menduplikasi query.
- [ ] Tulis failing test untuk MIME, signature `%PDF`, nama file, empty state, dan scoping unit.
- [ ] Implementasikan Blade landscape dengan metadata area/waktu, header berulang, dan nomor halaman.
- [ ] Tambahkan route PDF serta tombol Excel/PDF pada tiap kartu laporan.
- [ ] Jalankan test laporan PHP dan JS sampai lulus.

### Task 6: Migrations and complete automated verification

**Files:**
- Verify all modified files.

- [ ] Jalankan migrasi normal dan `php artisan migrate:status` tanpa `migrate:fresh`.
- [ ] Jalankan test feature/unit baru lalu seluruh `php artisan test` termasuk suite concurrency terpisah.
- [ ] Jalankan seluruh Vitest, production build, targeted Pint, dan `git diff --check`.
- [ ] Jalankan audit lima workbook KAI dan pastikan data user tidak berubah.
- [ ] Periksa route list, queue table, dan status branch.

### Task 7: Run and browser smoke-test the application

**Files:**
- No source file expected unless smoke test reveals a defect.

- [ ] Jalankan server Laravel, Vite, dan database queue worker melalui proses development proyek.
- [ ] Login menggunakan akun development yang tersedia tanpa mengubah credential.
- [ ] Uji halaman import, deteksi unit, progress/detail, tab rekonsiliasi, laporan PDF, dan guard rollback menggunakan browser.
- [ ] Perbaiki defect yang ditemukan dengan test regresi lalu ulangi smoke test.
- [ ] Biarkan web dan queue worker berjalan, lalu laporkan URL serta proses aktif kepada pengguna.
