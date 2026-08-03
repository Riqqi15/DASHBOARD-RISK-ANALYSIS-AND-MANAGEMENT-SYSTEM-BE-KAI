# Trouble Report Workbook Import Design

## Scope

Tahap ini hanya mengimpor data mentah trouble report dari workbook RAMS `.xlsm` atau `.xlsx` ke tabel `failure_logs`. Tahap ini tidak menghitung atau memperbarui MTTF, MTBF, failure rate, reliability, availability, maupun snapshot hasil formula Excel.

## Access and unit selection

- Pengguna unit dapat membuka halaman impor dan hanya dapat mengimpor ke unit kerjanya sendiri.
- Pengguna pusat dapat memilih satu unit kerja aktif sebagai tujuan impor.
- Workbook tidak menentukan tujuan berdasarkan nama file. Pilihan unit yang sudah diotorisasi adalah sumber kebenaran, sehingga nama file hasil salinan atau recovery Excel tetap dapat dipakai.

## Upload flow

1. Halaman Inertia `input-data/TroubleReportImport` menampilkan pemilih unit untuk pengguna pusat, file picker, progress upload, hasil impor terakhir, dan daftar masalah.
2. Request menerima satu file `.xlsm`/`.xlsx` dengan batas 50 MB.
3. Controller meneruskan temporary upload path, nama asli, ukuran, dan unit tujuan ke layanan khusus impor failure log.
4. Layanan membuat atau memperbarui `rams_import_batches`, menjalankan `FailureLogWorkbookImporter`, menyimpan masalah per sheet/baris ke `rams_import_issues`, lalu mengembalikan ringkasan.
5. Redirect kembali membawa `import_result` sehingga Vue menampilkan jumlah dibuat, diperbarui, tidak berubah, dilewati, sheet terbaca, dan masalah.

## Workbook parsing

- PhpSpreadsheet membaca seluruh nama sheet secara dinamis.
- Hanya sheet yang memiliki header detail trouble report yang diproses. Sheet ringkasan seperti Dashboard, Condition, atau Reorder Stock dilewati tanpa masalah.
- Header dicari pada 30 baris pertama dan maksimal 30 kolom, tanpa asumsi dimulai dari kolom A.
- Pemetaan mendukung kolom terpisah `Tanggal Kejadian` + `Mulai` dan `Tanggal Penanganan` + `Selesai`, serta kolom gabungan `Tanggal Jam Kejadian` dan `Tanggal Jam Penanganan` sebagai fallback.
- Baris tanpa `Failure Event` dianggap kosong dan dilewati diam-diam.
- Error Excel seperti `#VALUE!`, `#DIV/0!`, `#REF!`, `#N/A`, dan `#NAME?` dianggap tidak memiliki nilai. Jika berada pada kolom wajib, baris dicatat sebagai issue dan impor berlanjut.
- Sheet yang tidak dapat dipetakan tepat ke satu aset pada unit tujuan dicatat sebagai issue tingkat sheet; sheet lain tetap diproses.

## Mapping and idempotency

- Nama sheet atau nilai `Subsystem` pada ringkasan dipetakan ke `asset_subsystems.name` melalui normalisasi yang sudah dipakai aplikasi, lalu dibatasi oleh `unit_kerja_id`.
- `asset_id` menyimpan konteks unit dan subsystem. Tidak ada migration baru.
- `source_key` dibuat stabil dari versi importer, unit, nama sheet yang dinormalisasi, dan nomor baris sumber. Mengunggah revisi workbook yang sama memperbarui baris sumber yang sama; mengunggah file identik menghasilkan `unchanged`, bukan duplikat.
- Field yang disimpan: lokasi, resor, QC, failure event, penyebab, tindakan, waktu kejadian, waktu penanganan, downtime menit, indikator penggantian sparepart, dan indikator vandalisme.

## Transactions and issues

- Perubahan failure log untuk satu upload berjalan dalam transaksi database.
- Kesalahan data satu baris ditangani sebagai issue dan tidak melempar keluar transaksi.
- Workbook rusak atau kegagalan infrastruktur menandai batch `failed` dan tidak mengklaim keberhasilan.
- Issue menyimpan `sheet_name`, `source_row`, `source_column` bila tersedia, severity, dan pesan berbahasa Indonesia.

## Explicit non-goals

- Tidak memasang `maatwebsite/excel`.
- Tidak membuat migration.
- Tidak mengimpor tabel ringkasan formula.
- Tidak menjalankan `ReliabilityCalculator` atau menulis `reliability_summaries`.
- Tidak mengurangi stok atau membuat stock movement dari penanda penggantian sparepart hasil Excel.

## Verification

- Feature test membuat workbook multi-sheet dengan PhpSpreadsheet agar memverifikasi header offset, baris kosong, cell error, pemetaan aset, idempotency, issue audit, otorisasi unit, validasi upload, dan ketiadaan recalculation.
- Test Vue memverifikasi penggunaan `useForm`, pemilih unit, progress, error validasi, ringkasan, dan daftar issue.
- Build Vite dan seluruh test terkait dijalankan sebelum hasil diserahkan.
