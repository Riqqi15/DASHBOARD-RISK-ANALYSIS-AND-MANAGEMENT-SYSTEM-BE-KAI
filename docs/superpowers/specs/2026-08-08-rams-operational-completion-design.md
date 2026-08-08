# RAMS Operational Completion Design

## Goal

Menutup gap operasional yang masih tersisa setelah audit Excel/PDF tanpa mengubah identitas dashboard KAI dan tanpa pernah membuat, mengubah, atau menghapus akun pengguna melalui import workbook.

## Chosen approach

Gunakan satu alur import terintegrasi pada halaman yang ada. Jalur ini tetap mempertahankan URL lama untuk kompatibilitas, tetapi UI diberi nama **Import Data RAMS**. Import memproses Predictive Data Asset, Risk Matrix dan warna, Risk Register, Reorder Stock/suku cadang, Trouble Report, snapshot reliability, serta parity backend dalam satu transaksi dan satu ringkasan batch.

Alternatif yang ditolak:

- Halaman import terpisah per sheet: lebih mudah secara teknis, tetapi menyulitkan operator dan berisiko menghasilkan data antar-sheet yang tidak sinkron.
- Mempertahankan import lengkap hanya lewat Artisan: aman bagi pengembang, tetapi tidak dapat digunakan operator KAI melalui web.

## Architecture

- `FailureLogImportService` tetap menjadi orkestrator upload untuk menjaga kompatibilitas, tetapi diperluas dengan importer Risk Register dan Reorder Stock.
- Hasil setiap importer disimpan sebagai ringkasan bernama sehingga UI dapat menunjukkan data apa yang berubah.
- Tabel `rams_import_batches` menyimpan pengguna pengunggah bila import berasal dari web; import CLI tetap boleh bernilai null.
- Risk Register mendapat halaman CRUD terpisah dengan scoping unit yang sama seperti modul lain. Data hasil import boleh diedit, tetapi import berikutnya tetap menjadi sumber utama untuk baris yang mempunyai `source_key`.
- Export menggunakan PhpSpreadsheet yang sudah tersedia, menghasilkan `.xlsx` untuk inventori, Trouble Report, Risk Register, dan reliability/availability.
- Riwayat import ditampilkan pada halaman import yang sama, lengkap dengan status, unit, pengunggah, waktu, jumlah masalah, dan ringkasan perubahan.

## Authorization and data safety

- Akun pusat dapat memilih unit aktif; akun unit hanya dapat memakai unit yang ditetapkan pada akunnya.
- Seluruh CRUD, export, dan riwayat difilter melalui unit pengguna.
- Importer tidak memiliki dependensi terhadap model `User`; controller hanya meneruskan ID pengunggah ke metadata batch.
- Pengujian mengambil snapshot tabel user sebelum import dan membandingkannya setelah import.
- Dry run menjalankan seluruh importer tetapi membatalkan transaksi data operasional.

## UI

- Gaya visual mengikuti layout KAI yang sudah ada: slate/putih, aksen oranye, kartu padat, tabel responsif, dan warna kategori aset dari Excel.
- Menu menambahkan `Risk Register` dan `Laporan`; label import menjadi `Import Data RAMS`.
- Halaman import menampilkan cakupan sheet, kartu hasil per bagian, serta tabel riwayat batch.
- Halaman Risk Register menyediakan filter unit/status, tabel, dan dialog tambah/edit; hapus memakai konfirmasi.
- Halaman laporan menyediakan empat kartu export dengan penjelasan isi dan unit aktif.

## Error handling

- Sheet operasional yang tidak tersedia menghasilkan warning dan tidak menggagalkan sheet lain yang valid.
- Kesalahan struktur/header yang membuat data ambigu menggagalkan transaksi dan dicatat pada batch.
- Export tanpa data tetap menghasilkan workbook dengan header.
- Akses record atau batch dari unit lain menghasilkan 404/403 sesuai pola aplikasi.

## Verification

- Feature test untuk import lengkap, user invariance, otorisasi Risk Register, export XLSX, dan riwayat import.
- JS component test untuk label/counter import, Risk Register, laporan, dan menu.
- Seluruh PHPUnit, Vitest, production build, Pint, migration status, route list, serta audit lima workbook KAI dijalankan sebelum selesai.
