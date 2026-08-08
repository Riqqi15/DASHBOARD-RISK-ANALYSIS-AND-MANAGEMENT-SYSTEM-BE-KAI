# RAMS Full Automation Design

## Goal

Melengkapi digitalisasi dashboard Excel KAI dengan deteksi unit otomatis, import antrean dan progres, detail batch, rekonsiliasi stok referensi Excel dengan ledger web, laporan PDF, serta rollback terbatas yang aman tanpa pernah membuat, mengubah, atau menghapus akun pengguna.

## Approved scope

Enam fitur diterapkan sebagai satu paket, tetapi dipisahkan menjadi tiga subsistem yang dapat diuji sendiri:

1. Keamanan dan audit import: deteksi unit, detail batch, progres, snapshot perubahan, dan rollback.
2. Rekonsiliasi stok: pembandingan read-only antara stok Excel dan stok transaksi web.
3. Pelaporan: XLSX yang sudah ada ditambah PDF dengan pembatasan unit identik.

## Import submission and unit detection

- `RamsUnitDetector` menjadi satu-satunya pemetaan nama workbook ke kode DAOP/DIVRE dan dipakai web maupun CLI.
- Nama yang dikenali otomatis menentukan unit. Nilai unit yang dikirim browser harus cocok; perbedaan ditolak agar file tidak masuk ke unit yang salah.
- Nama yang tidak dikenali masih boleh dipilih manual oleh akun pusat. Akun unit tidak dapat mengalihkan target dari unit akunnya.
- Controller menyimpan workbook pada disk private, membuat batch berstatus `queued`, lalu menjalankan `ProcessRamsWorkbookImport` pada queue `rams-imports`.
- Fingerprint mencegah workbook dan unit yang sama diproses ganda. Tidak ada jalur import yang menyentuh tabel `users`.

## Progress and batch details

- Batch memiliki `progress_stage`, `progress_percent`, dan `stored_path`.
- Service memperbarui progres setelah master aset, risk matrix, risk register, spare part, trouble report, reliability snapshot, dan parity.
- Endpoint status mengembalikan batch yang sudah melalui scoping unit pengguna.
- UI melakukan polling hanya selama masih ada batch `queued` atau `processing`, menampilkan progress bar, dan menyediakan panel rincian ringkasan serta masalah.

## Safe rollback

- Sebelum dan sesudah transaksi import, `RamsImportChangeRecorder` mengambil snapshot tabel yang memang dapat berubah: kategori aset, alias sumber, aset, opening unit, predictive snapshot, risk matrix, risk register, spare part, kebijakan spare part unit, failure log, reliability Excel snapshot, dan reliability summary.
- Hanya selisih per baris yang disimpan dalam `rams_import_changes`; tabel user dan tabel ledger stok tidak pernah direkam.
- Rollback hanya tersedia bagi akun pusat, pada batch sukses non-simulasi terbaru secara global, dan hanya bila semua baris masih identik dengan `after_values` yang direkam.
- `RamsImportRollbackService` menghapus baris yang dibuat import dari child ke parent dan mengembalikan baris yang diperbarui ke `before_values` dalam satu transaksi. Pelanggaran foreign key atau perubahan data membuat seluruh rollback batal.
- Batch diberi status `rolled_back`, identitas pelaksana, waktu, dan alasan penolakan bila tidak aman. Rollback tidak mengubah `inventory_stocks`, `stock_movements`, atau `users`.

## Stock reconciliation

- `InventoryReconciliationService` membandingkan `PredictiveAssetSnapshot.current_stock` dengan `InventoryStock.quantity` berdasarkan unit, subsystem, dan normalisasi nama detail peralatan.
- Kandidat satu-satunya dalam subsystem boleh dipakai sebagai fallback; kecocokan ambigu ditandai untuk pemeriksaan manual.
- Status: `matched`, `difference`, `missing_ledger`, `missing_excel`, dan `ambiguous`.
- Fitur bersifat read-only. Koreksi tetap dilakukan melalui transaksi stok yang sudah ada agar ledger tidak rusak.
- Rekonsiliasi tersedia sebagai tab inventori dengan ringkasan, filter status, selisih, dan tautan menuju pencatatan transaksi.

## PDF reports

- `RamsReportExportService` mengekspos dataset bersama untuk XLSX dan PDF sehingga query, urutan kolom, dan scoping unit identik.
- PDF dirender dengan Dompdf menggunakan Blade landscape A4, header KAI, area, waktu cetak, nomor halaman, dan tabel berulang.
- Empat laporan tetap tersedia: inventori/proposal, trouble report, risk register, dan reliability/availability.
- XLSX tetap format pengolahan; PDF menjadi format baca/cetak.

## UI direction

Pertahankan bahasa visual dashboard KAI yang sudah ada: utilitarian, rapat tetapi tenang, putih/slate, navy KAI, dan oranye sebagai tindakan utama. Fitur baru tidak mengubah susunan dashboard Excel; ia menambah lapisan operasional berupa status, audit, dan tindakan aman.

## Failure handling

- Job gagal mengubah batch menjadi `failed`, menyimpan pesan aman, dan menghapus file sementara.
- Polling berhenti pada status terminal.
- PDF tanpa data tetap menghasilkan dokumen dengan header dan pesan kosong.
- Rollback yang tidak aman mengembalikan 422/redirect error tanpa perubahan parsial.

## Verification

- Feature tests: deteksi unit, dispatch queue, job sukses/gagal, progress, scoping detail, rollback aman/ditolak, invariansi user dan ledger, rekonsiliasi, PDF, serta otorisasi.
- Vue tests: auto-detection, progress, detail batch, tombol rollback, tab rekonsiliasi, dan tombol PDF.
- Verifikasi akhir: migration status, route list, PHPUnit, Vitest, production build, Pint, `git diff --check`, audit lima workbook KAI, lalu browser smoke test terhadap server dan queue worker lokal.
