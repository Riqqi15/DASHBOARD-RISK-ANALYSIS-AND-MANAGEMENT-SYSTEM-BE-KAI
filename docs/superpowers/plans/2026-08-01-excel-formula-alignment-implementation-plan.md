# Excel Formula Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menjadikan Laravel/MySQL sumber kebenaran RAMS yang mengikuti aturan bisnis lima workbook XLSM KAI, dengan koreksi eksplisit untuk kesalahan satuan dan formula Excel.

**Architecture:** Formula ditempatkan dalam service PHP terpisah dan diuji memakai contoh batas dari workbook. Data sumber, hasil kalkulasi, versi formula, status kecukupan data, serta issue import disimpan untuk audit. Vue hanya menampilkan payload backend.

**Tech Stack:** PHP 8.4, Laravel 13, PhpSpreadsheet 5.9, MySQL 8.4, Inertia 3, Vue 3, PHPUnit.

**Commit policy:** Jangan membuat commit sampai pengguna meminta.

---

### Task 1: Risk Matrix KAI 4x4

**Files:**
- Create: `app/Services/RiskAssessmentCalculator.php`
- Modify: `app/Models/RiskMatrix.php`
- Modify: `database/migrations/2026_08_01_000000_create_risk_matrices_table.php`
- Modify: `database/migrations/2026_08_01_000001_create_risk_registers_table.php`
- Modify: `resources/js/pages/dashboard/RiskMatrix.vue`
- Test: `tests/Unit/RiskAssessmentCalculatorTest.php`

- [ ] Uji seluruh 16 kombinasi L/C sesuai sheet `Condition` dan penolakan nilai di luar 1..4.
- [ ] Pusatkan rating dan level di `RiskAssessmentCalculator`; model dan payload tidak boleh memiliki threshold berbeda.
- [ ] Ubah constraint serta UI menjadi 4x4.

### Task 2: RAMS dengan satuan yang konsisten

**Files:**
- Modify: `database/migrations/2026_08_01_000002_create_reliability_summaries_table.php`
- Modify: `app/Models/ReliabilitySummary.php`
- Modify: `app/Services/ReliabilityCalculator.php`
- Modify: `app/Services/FailureLogService.php`
- Modify: `app/Services/RamsDashboardQuery.php`
- Test: `tests/Unit/ReliabilityCalculatorTest.php`
- Test: `tests/Feature/RamsDashboardBackendTest.php`

- [ ] Simpan semua durasi dasar dalam menit dan keluarkan jam hanya di response.
- [ ] Hitung MTTF sebagai rata-rata interval antarkejadian; MTBF sebagai uptime/failure; MTTR sebagai downtime/failure.
- [ ] Untuk nol denominator, simpan `insufficient_data`, bukan angka buatan atau error pembagian nol.
- [ ] Kirim MTTF ke Vue serta hasil reliability/availability nasional dan per kelompok aset.

### Task 3: Reorder dan predictive inventory

**Files:**
- Create: `app/Services/ReorderStockCalculator.php`
- Create: `app/Services/PredictiveInventoryCalculator.php`
- Create: `database/migrations/2026_08_01_000004_add_excel_calculation_fields_to_spare_parts_table.php`
- Modify: `app/Models/SparePart.php`
- Modify: `app/Services/RamsDashboardQuery.php`
- Test: `tests/Unit/ReorderStockCalculatorTest.php`
- Test: `tests/Unit/PredictiveInventoryCalculatorTest.php`

- [ ] Terapkan `safety_stock=(max_failure*max_lead)-(avg_failure*avg_lead)`, `lead_time_demand=avg_failure*avg_lead`, dan `reorder_point=safety_stock+lead_time_demand`, dengan hasil minimum nol.
- [ ] Terapkan criticality, kategori lead time, inventory policy, needed stock, proposal quantity, kewajaran, tiga komponen safety stock, umur, dan kondisi umur dengan batas lengkap.
- [ ] Hapus persentase stok dummy sebagai sumber hasil kalkulasi.

### Task 4: Import XLSM terlacak

**Files:**
- Create: `database/migrations/2026_08_01_000005_create_rams_import_tables.php`
- Create: `app/Models/RamsImportBatch.php`
- Create: `app/Models/RamsImportIssue.php`
- Create: `app/Services/RamsWorkbookImporter.php`
- Create: `app/Console/Commands/ImportRamsWorkbooks.php`
- Test: `tests/Feature/RamsWorkbookImporterTest.php`

- [ ] Baca lima workbook `Daop 1`, `Daop 4`, `Daop 8`, `Divre III`, dan `Divre IV` memakai PhpSpreadsheet tanpa menjalankan VBA.
- [ ] Sediakan `--dry-run`, fingerprint SHA-256, pemetaan unit dari nama file, sumber sheet/baris, transaksi per workbook, dan issue per baris.
- [ ] Import hanya nilai sumber; kalkulasi authoritative selalu dijalankan oleh service PHP.

### Task 5: Database lokal dan verifikasi

- [ ] Jalankan migration normal dan importer; jangan memakai `migrate:fresh`.
- [ ] Jalankan importer kedua kali dan pastikan fingerprint mencegah duplikasi.
- [ ] Jalankan seluruh test PHP, Pint, route list, build Vite, dan pencarian sisa dummy.
- [ ] Pastikan tidak ada commit baru.
