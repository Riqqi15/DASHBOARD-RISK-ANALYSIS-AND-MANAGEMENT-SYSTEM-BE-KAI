# Integrated Unit Accounts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menggabungkan pengelolaan akun wilayah ke Unit Kerja dan menjadikan audit log sebagai mekanisme backend internal saja.

**Architecture:** `UnitKerjaController@index` mengirim relasi akun wilayah per unit. Route mutasi akun tetap memakai `RegionalAccountController`, tetapi halaman, active navigation, dan redirect berpusat pada Unit Kerja. Route daftar akun dan tampilan audit dihapus tanpa menghapus data atau pencatatan audit.

**Tech Stack:** Laravel 13, Inertia 3, Vue 3, Tailwind CSS 4, PHPUnit 12, Vitest.

**Commit policy:** Jangan membuat commit sampai pengguna meminta.

---

### Task 1: Lock the integrated backend contract

**Files:**
- Modify: `tests/Feature/Admin/UnitKerjaManagementTest.php`
- Modify: `tests/Feature/Admin/RegionalAccountManagementTest.php`

- [ ] Tambahkan test bahwa payload `Admin/Units/Index` memuat akun role wilayah pada unit yang benar dan tidak memuat akun Pusat.
- [ ] Tambahkan test bahwa seluruh mutasi akun redirect ke `/admin/units`.
- [ ] Tambahkan test bahwa `GET /admin/accounts` dan `GET /admin/audit-logs` tidak tersedia.
- [ ] Jalankan dua file feature test dan pastikan assertion baru gagal sebelum implementasi.

### Task 2: Integrate account data and routes

**Files:**
- Modify: `app/Http/Controllers/Admin/UnitKerjaController.php`
- Modify: `app/Http/Controllers/Admin/RegionalAccountController.php`
- Modify: `routes/web.php`

- [ ] Eager-load akun role `unit` pada pagination Unit Kerja dengan kolom publik minimal.
- [ ] Izinkan pencarian Unit Kerja menemukan nama atau username akun terkait.
- [ ] Preselect unit aktif pada form tambah akun melalui query `unit_kerja_id`.
- [ ] Arahkan create/update/status/reset password kembali ke route `admin.units.index`.
- [ ] Hapus route index akun dan route tampilan audit, tetapi pertahankan route mutasi akun serta `AuditLogger`.
- [ ] Jalankan feature test terfokus dan pastikan lulus.

### Task 3: Build the single administration UI

**Files:**
- Modify: `resources/js/layouts/MainLayout.vue`
- Modify: `resources/js/pages/Admin/Units/Index.vue`
- Modify: `resources/js/pages/Admin/Accounts/Create.vue`
- Modify: `resources/js/pages/Admin/Accounts/Edit.vue`
- Modify: `resources/js/pages/Admin/Accounts/ResetPassword.vue`
- Modify: `resources/js/pages/Admin/Accounts/Partials/AccountForm.vue`
- Modify: `tests/js/AssetCategories.test.js`

- [ ] Tambahkan assertion menu Pusat tidak memuat `Akun Wilayah` dan `Audit Log`, lalu konfirmasi gagal.
- [ ] Hapus kedua item dan import ikon yang tidak dipakai dari sidebar.
- [ ] Perlakukan route `/admin/accounts/*` sebagai bagian aktif dari menu Unit Kerja.
- [ ] Tambahkan kolom akun dan aksi akun pada setiap baris Unit Kerja.
- [ ] Preselect unit pada form create dan ubah seluruh breadcrumb/cancel link ke Unit Kerja.
- [ ] Jalankan test JavaScript terfokus dan pastikan lulus.

### Task 4: Verify the complete change

**Files:**
- No production changes unless verification reveals a defect.

- [ ] Jalankan full PHPUnit dengan `PHP_INI_SCAN_DIR=tests` agar proses konkurensi memuat `pdo_mysql`.
- [ ] Jalankan full Vitest.
- [ ] Jalankan Pint check dan Vite build.
- [ ] Buka `/admin/units` sebagai Pusat dan pastikan akun tampil serta kedua menu hilang.
- [ ] Pastikan Git tetap tidak memiliki commit baru.
