# RAMS Operational Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyediakan import workbook RAMS lengkap, Risk Register CRUD, export XLSX, riwayat import, dan membersihkan kode Reorder Stock lama tanpa menyentuh data user.

**Architecture:** Laravel mengelola orkestrasi import, otorisasi unit, CRUD, dan export; Inertia/Vue menampilkan halaman operasional dengan pola UI yang sudah ada. Semua import ditelusuri melalui `rams_import_batches` dan seluruh query dibatasi unit pengguna.

**Tech Stack:** PHP 8.3, Laravel 13, MySQL, Inertia 3, Vue 3, Tailwind CSS, PhpSpreadsheet, PHPUnit, Vitest.

---

### Task 1: Full workbook web import

**Files:**
- Modify: `app/Services/FailureLogImportService.php`
- Modify: `app/Http/Controllers/FailureLogImportController.php`
- Modify: `resources/js/pages/input-data/TroubleReportImport.vue`
- Modify: `tests/Feature/FailureLogImportUploadTest.php`
- Modify: `tests/js/TroubleReportImport.test.js`

- [x] Tambah failing test bahwa upload memanggil/menyimpan Risk Register dan Reorder Stock serta user tidak berubah.
- [x] Jalankan test terarah dan pastikan gagal karena dua importer belum terhubung.
- [x] Tambahkan `RiskRegisterWorkbookImporter` dan `SparePartWorkbookImporter` ke transaksi upload serta ringkasan hasil.
- [x] Ubah label UI menjadi Import Data RAMS dan tampilkan counter tiap section.
- [x] Jalankan test PHP dan JS terarah sampai lulus.

### Task 2: Import actor and history

**Files:**
- Create: `database/migrations/2026_08_08_000001_add_uploaded_by_to_rams_import_batches.php`
- Modify: `app/Models/RamsImportBatch.php`
- Modify: `app/Services/FailureLogImportService.php`
- Modify: `app/Http/Controllers/FailureLogImportController.php`
- Modify: `resources/js/pages/input-data/TroubleReportImport.vue`
- Test: `tests/Feature/RamsImportHistoryTest.php`

- [x] Tulis test scoping riwayat berdasarkan role dan unit.
- [x] Tambah foreign key nullable `uploaded_by_user_id` dan relasi `uploadedBy`.
- [x] Simpan actor saat upload web, lalu query riwayat dengan `unitKerja`, `uploadedBy`, dan `issues_count`.
- [x] Render tabel riwayat responsif pada halaman import.
- [x] Jalankan test riwayat sampai lulus.

### Task 3: Risk Register CRUD

**Files:**
- Create: `app/Http/Controllers/RiskRegisterController.php`
- Create: `app/Http/Requests/StoreRiskRegisterRequest.php`
- Create: `app/Http/Requests/UpdateRiskRegisterRequest.php`
- Create: `app/Services/RiskRegisterService.php`
- Create: `resources/js/pages/risk-register/Index.vue`
- Modify: `routes/web.php`
- Modify: `resources/js/layouts/MainLayout.vue`
- Test: `tests/Feature/RiskRegisterManagementTest.php`
- Test: `tests/js/RiskRegister.test.js`

- [x] Tulis feature test list/create/update/delete dan penolakan lintas unit.
- [x] Implementasikan request validation, service scoping, dan controller tipis.
- [x] Tambah resource routes serta halaman tabel/dialog yang mengikuti gaya dashboard.
- [x] Tambah menu Risk Register dan component test.
- [x] Jalankan test terarah sampai lulus.

### Task 4: XLSX reports

**Files:**
- Create: `app/Http/Controllers/RamsReportController.php`
- Create: `app/Services/RamsReportExportService.php`
- Create: `resources/js/pages/reports/Index.vue`
- Modify: `routes/web.php`
- Modify: `resources/js/layouts/MainLayout.vue`
- Test: `tests/Feature/RamsReportExportTest.php`
- Test: `tests/js/Reports.test.js`

- [x] Tulis test empat jenis export, MIME XLSX, nama file, dan scoping unit.
- [x] Implementasikan query dan worksheet inventori, Trouble Report, Risk Register, reliability/availability.
- [x] Tambah halaman laporan dengan pilihan unit dan tombol download.
- [x] Jalankan test export dan UI sampai lulus.

### Task 5: Remove unused reorder page

**Files:**
- Delete: `resources/js/pages/master-data/inventory/ReorderStock.vue`
- Modify: `app/Http/Controllers/RamsDashboardController.php`
- Modify: `app/Services/RamsDashboardQuery.php`
- Modify: `routes/web.php`

- [x] Pastikan tidak ada import atau test yang memakai halaman lama.
- [x] Hapus method controller/query yang tidak terpakai; pertahankan redirect `/reorder-stock` ke tab inventori.
- [x] Jalankan route list dan pencarian referensi mati.

### Task 6: Migration and complete verification

**Files:**
- Verify all modified files.

- [x] Jalankan migration normal tanpa `migrate:fresh`.
- [x] Jalankan seluruh PHPUnit dan Vitest.
- [x] Jalankan production build, Pint, dan `git diff --check`.
- [x] Jalankan audit lima workbook KAI dan pastikan tabel user identik sebelum/sesudah.
- [x] Periksa `migrate:status`, `route:list`, dan status worktree untuk handoff.
