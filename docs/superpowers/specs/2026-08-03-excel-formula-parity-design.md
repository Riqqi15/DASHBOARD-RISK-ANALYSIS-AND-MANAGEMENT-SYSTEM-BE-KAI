# Excel Formula Parity Design

## Goal

Tahap 2 membuat perhitungan backend mengikuti formula workbook RAMS untuk setiap sheet subsystem. Data mentah tetap berasal dari `failure_logs`; hasil rumus backend disimpan di `reliability_summaries`; hasil Excel disimpan terpisah sebagai snapshot pembanding.

## Scope

- Tetap memakai `phpoffice/phpspreadsheet`.
- Tidak memasang `maatwebsite/excel`.
- Tidak mengubah file Excel sumber.
- Import trouble report tetap menyimpan data mentah lebih dulu.
- Setelah import, backend membaca ringkasan Excel per sheet dan menghitung parity formula dari data database.
- Jika hasil Excel berupa `#VALUE!`, `#DIV/0!`, atau error Excel lain, nilai disimpan sebagai `null` dan UI menampilkan `Data belum ada`.

## Formula Parity

Backend mengikuti pola formula workbook:

- `Jumlah Unit`: jumlah unit asset.
- `Total Operating Hour`: `(calculation_date - baseline_date) * 24 * Jumlah Unit`.
- `Total Downtime`: mengikuti formula sheet. Mayoritas sheet menjumlahkan `Konversi ke Menit`. `Peraga Sinyal Mekanik Pelengkap` menjumlahkan `Downtime (jam)`, tetapi formula kolom tersebut menghasilkan pecahan hari Excel, sehingga backend memakai `downtime_minutes / 1440` agar identik.
- `Total Uptime`: `Total Operating Hour - Total Downtime`.
- `Jumlah Failure`: jumlah baris failure event.
- `MTTF`: rata-rata `Interval antar Failure (jam)`. Interval pertama mengikuti cell `P8` masing-masing sheet, bukan selalu baseline Dashboard. Workbook saat ini memakai `2020-01-01` untuk Interlocking Elektrik dan `2017-01-01` pada mayoritas sheet lain.
- `MTBF`: `IFERROR(Total Uptime / Jumlah Failure, 0)`.
- `Failure Rate`: `IFERROR(1 / MTBF, 0)`.
- `Reliability`: `EXP(-Failure Rate)`.
- `Availability`: `Total Uptime / Total Operating Hour`.
- `Jumlah penggantian sparepart` dan `Jumlah Tindak Vandalisme`: mengikuti formula sheet, yaitu `COUNTIF(...,"Ya")` atau `COUNTA(...)` sesuai rumus Excel pada sheet tersebut.

## Data Model

Tambahan data dibutuhkan agar parity bisa diaudit:

- `failure_logs` menyimpan marker mentah dari Excel: `spare_part_marker`, `vandalism_marker`, `workbook_hash`, `workbook_name`, `sheet_name`, dan `source_row`.
- `reliability_excel_snapshots` menyimpan nilai dan formula ringkasan Excel per sheet, termasuk profile rumus seperti `downtime_mode`, `spare_part_count_mode`, dan `vandalism_count_mode`.
- `reliability_summaries` menyimpan hasil backend Excel-style: `unit_count`, `operating_hours`, `downtime_value`, `uptime_hours`, counts sparepart/vandalism, baseline, calculation date, profile, snapshot id, parity status, dan daftar selisih.

Kolom lama `operating_minutes` dan `downtime_minutes` tetap diisi untuk kompatibilitas fitur lain.

## Services

- `ExcelReliabilitySnapshotImporter`: membaca semua sheet subsystem, mendeteksi header ringkasan, mengambil nilai/formula B:N, menangani error Excel, memetakan sheet ke asset, dan menyimpan snapshot.
- `ExcelParityReliabilityCalculator`: menghitung ulang semua kolom ringkasan dari `failure_logs` sesuai profile formula Excel.
- `ReliabilityParityService`: memilih snapshot terbaru per asset, menjalankan kalkulator, membandingkan hasil backend vs Excel dengan toleransi numerik, lalu menyimpan `ReliabilitySummary`.

## UI

Halaman Trouble Report menampilkan angka dari backend. Frontend tidak lagi menghitung sparepart/vandalism sendiri untuk ringkasan. Nilai `null` ditampilkan sebagai `Data belum ada`. Status parity ditampilkan sebagai:

- `Sesuai Excel`
- `Ada selisih`
- `Data Excel belum ada`
- `Belum dibandingkan`

## Testing

Test wajib menutup:

- Kalkulator Interlocking Elektrik dengan 3 failure menghasilkan angka yang sama dengan workbook.
- Error Excel pada snapshot menjadi `null` dan tampil sebagai `Data belum ada`.
- Import workbook menyimpan snapshot dan menjalankan parity.
- Marker sparepart/vandalism mentah dipakai untuk membedakan `COUNTIF` dan `COUNTA`.
- UI tidak mengganti nilai formula kosong menjadi `0`.
