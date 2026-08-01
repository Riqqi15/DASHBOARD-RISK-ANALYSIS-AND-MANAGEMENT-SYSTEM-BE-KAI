# Desain Pemindahan Data Dummy RAMS ke MySQL Lokal

Tanggal: 1 Agustus 2026  
Status: Disetujui untuk perencanaan implementasi

## 1. Latar belakang

Beberapa halaman RAMS masih membaca data statis dari JavaScript. Sumber utamanya adalah `dummy-rams.repository.js`, tetapi data statis juga tersebar di halaman Risk Matrix, Inventory, Overview, Reorder Stock, Area Selector, dan formulir Trouble Report. Akibatnya perubahan yang terlihat di UI tidak selalu tersimpan, data tidak dapat dikelola lewat phpMyAdmin, dan halaman berbeda dapat menampilkan angka yang tidak konsisten.

Database lokal `rams` memakai MySQL 8.4 melalui Docker pada `127.0.0.1:3308`. Migrasi autentikasi dan unit kerja sudah dijalankan, sedangkan migrasi aset, kategori aset, sparepart, inventory, dan stock movement masih pending. Belum ada schema untuk risk matrix, risk register, ringkasan reliability, dan failure log.

## 2. Keputusan

- MySQL lokal menjadi satu-satunya sumber data aplikasi.
- Seluruh data statis yang saat ini dipakai UI dipindahkan melalui seeder idempoten.
- Migrasi dijalankan secara bertahap tanpa `migrate:fresh`, sehingga akun, session, dan data lokal yang sudah ada tetap dipertahankan.
- Frontend tetap memakai Laravel, Inertia, dan Vue sebagai satu aplikasi. Data halaman dikirim sebagai Inertia props; mutasi memakai route Laravel.
- Hak akses pusat dan wilayah tetap ditegakkan pada query backend. Filter unit dari browser tidak dipercaya sebagai otorisasi.
- File `dummy-rams.repository.js` dihapus setelah tidak memiliki konsumen.
- Konfigurasi UI murni seperti label likelihood, label consequence, ikon, dan menu tidak diperlakukan sebagai data dummy database.

## 3. Ruang lingkup data

### Dipindahkan ke database

1. unit kerja;
2. aset;
3. risk matrix per aset;
4. risk register;
5. ringkasan reliability per aset dan periode;
6. failure log atau Trouble Report;
7. master sparepart dan saldo inventory;
8. sumber tren kegagalan bulanan;
9. daftar area Daop/Divre yang aktif;
10. data reorder yang dapat dihitung dari saldo dan reorder point.

### Dihapus dari frontend

- seluruh array data dalam `dummy-rams.repository.js`;
- `riskAssets` statis pada halaman Risk Matrix;
- `inventoryData` statis;
- `sparepartDictionary` beserta stok simulasi;
- `failureTrend` statis;
- kartu reorder yang ditulis langsung di template;
- simulasi upload dan penyimpanan Trouble Report ke array lokal.

### Di luar cakupan

- sinkronisasi database lokal ke cloud;
- purchase order atau integrasi SAP/ERP;
- perubahan desain visual besar;
- penghapusan factory yang hanya dipakai oleh automated test;
- pembuatan chart baru selain mengganti sumber angka chart yang sudah ada.

## 4. Model data baru

### `risk_matrices`

- `id`;
- `asset_id` foreign key;
- `likelihood` dan `consequence` dengan batas 1 sampai 5;
- `assessed_at`;
- timestamps;
- satu penilaian aktif per aset untuk versi awal.

Rating dihitung sebagai `likelihood * consequence` pada response, bukan disimpan sebagai nilai yang dapat berbeda dari input.

### `risk_registers`

- `id`;
- `asset_id` foreign key;
- `part_number`, `sub`, `risk_event`, `risk_cause`, `impact`, `part_name`, dan `recommendation`;
- `likelihood` dan `consequence` nullable;
- status `open`, `in_progress`, atau `closed`;
- timestamps.

Unit kerja diperoleh melalui relasi aset agar tidak ada duplikasi kepemilikan yang dapat bertentangan.

### `reliability_summaries`

- `id`;
- `asset_id` foreign key;
- `period` berupa tanggal awal bulan;
- `operating_hours`;
- `downtime_minutes`;
- jumlah failure dan metrik turunan;
- timestamps;
- pasangan aset dan periode unik.

Nilai uptime, MTBF, MTTR, failure rate, reliability, dan availability dihitung oleh service backend dari periode dan failure log. Seed awal boleh menyimpan hasil sumber lama, lalu perhitungan berikutnya memakai formula service yang sama.

### `failure_logs`

- `id`;
- `asset_id` foreign key;
- lokasi, resor, QC, failure event, penyebab, dan tindakan;
- waktu kejadian dan waktu selesai;
- flag penggantian sparepart dan vandalisme;
- `spare_part_id` nullable dan jumlah sparepart;
- `created_by` foreign key;
- timestamps.

Downtime dihitung dari waktu kejadian sampai waktu selesai. Waktu selesai tidak boleh lebih awal dari waktu kejadian. Jika penggantian sparepart dipilih, sparepart dan jumlah wajib serta saldo harus cukup.

## 5. Data lama dan seeding

Seeder migrasi data memakai natural key, bukan ID JavaScript lama:

- unit kerja dicocokkan dengan kode yang dinormalisasi, misalnya `DAOP1` menjadi `DAOP-1` dan `DIVRE4` menjadi `DIVRE-IV`;
- hierarki aset dicocokkan dengan nama group, system, dan subsystem yang dinormalisasi;
- aset memakai source key deterministik dari unit, hierarchy, lokasi, dan nama aset;
- risiko, reliability, dan failure log ditautkan melalui source key aset;
- sparepart memakai kode sebagai identifier stabil;
- inventory memakai pasangan unit kerja dan sparepart.

Seeder menggunakan `updateOrCreate` atau `upsert` sehingga dapat dijalankan ulang tanpa menggandakan baris. Seeder tidak menghapus data yang ditambahkan pengguna dan tidak menurunkan saldo yang sudah berubah setelah transaksi nyata.

## 6. Alur Laravel dan Inertia

### Halaman baca

- `/dashboard`: controller mengirim ringkasan dan daftar aset sesuai unit aktif;
- `/overview`: controller mengirim statistik, risk register, aset, dan tren failure bulanan;
- `/risk-matrix`: controller mengirim distribusi matriks dan daftar penilaian;
- `/inventory`: controller memakai `spare_parts` dan `inventory_stocks`;
- `/reorder-stock`: controller menghitung kebutuhan dari stok, safety stock, dan reorder point;
- `/trouble-report`: controller mengirim aset/subsystem, reliability, failure logs, dan sparepart yang tersedia.

Admin Pusat boleh memilih unit aktif melalui query parameter yang tervalidasi. Akun wilayah selalu dipaksa memakai `unit_kerja_id` miliknya.

### Mutasi

- Trouble Report manual dikirim ke route `POST` Laravel dengan Form Request;
- upload Excel memakai endpoint import nyata dan validasi ekstensi/ukuran;
- pencatatan sparepart pada failure log memakai transaksi database dan service stock movement yang sudah ada;
- seluruh mutasi penting menulis audit log.

## 7. Error handling

- Halaman menampilkan empty state ketika query sah tetapi belum memiliki data.
- Error database atau import ditampilkan melalui flash error yang aman.
- Input lintas unit ditolak di backend.
- Foreign key yang tidak ditemukan menghasilkan validation error, bukan insert parsial.
- Pembuatan failure log dan pengurangan stok berjalan dalam satu transaksi MySQL.
- Kegagalan seeding membatalkan batch terkait dan tidak menghapus data lama.

## 8. Strategi penghapusan dummy

Urutan aman:

1. buat schema dan model baru;
2. jalankan migrasi pending;
3. jalankan seeder idempoten;
4. verifikasi jumlah serta relasi data;
5. ubah controller dan route agar mengirim data database;
6. ubah Vue agar memakai Inertia props dan form Laravel;
7. verifikasi seluruh halaman;
8. hapus `dummy-rams.repository.js` dan array statis yang tidak lagi dipakai;
9. cari ulang kata `dummy`, `simulasi`, serta import repository lama untuk memastikan tidak ada runtime dummy tersisa.

Penghapusan dilakukan terakhir agar UI tidak kehilangan data di tengah implementasi.

## 9. Pengujian dan verifikasi

### Backend

- seluruh migration berstatus `Ran`;
- seeder dapat dijalankan dua kali tanpa duplikasi;
- query pusat dapat melihat semua unit;
- query wilayah hanya melihat unit sendiri;
- risk rating dan metrik reliability dihitung konsisten;
- Trouble Report tersimpan permanen;
- penggantian sparepart mengurangi saldo satu kali;
- input tidak valid atau saldo kurang tidak membuat data parsial.

### Frontend

- Dashboard, Overview, Risk Matrix, Inventory, Reorder Stock, dan Trouble Report tidak mengimpor repository dummy;
- pergantian area Admin Pusat memuat data unit yang benar;
- loading, empty, success, dan error state berfungsi;
- refresh browser mempertahankan data Trouble Report yang baru disimpan;
- build Vite dan test JavaScript lulus.

### Database lokal

- akun admin yang ada tetap tersedia;
- data seed terlihat melalui phpMyAdmin;
- jumlah unit, aset, risiko, reliability, failure log, sparepart, dan stok dicatat setelah seeding;
- tidak ada penggunaan `migrate:fresh` atau penyalinan Docker volume.

## 10. Kriteria selesai

Pekerjaan selesai ketika tidak ada halaman runtime yang memakai data dummy, seluruh data awal berada di MySQL lokal, mutasi Trouble Report tersimpan permanen, hak akses unit diterapkan backend, dan verifikasi migration, test, build, serta data database berhasil.
