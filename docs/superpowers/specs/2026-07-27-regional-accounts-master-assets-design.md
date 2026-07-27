# Desain Akun DAOP dan Master Aset

Tanggal: 27 Juli 2026  
Status: Disetujui

## Tujuan

Menyelesaikan dua fondasi sebelum modul RAMS lain memakai backend sebenarnya:

1. menyediakan akun demo DAOP 1–9 yang terikat pada unit kerja masing-masing; dan
2. mengganti data dummy Master Aset dengan CRUD Laravel, Inertia.js, Vue 3, dan MySQL 8.4.

Implementasi tetap berada dalam satu repository Laravel + Inertia.js + Vue 3. MySQL menjadi sumber data utama aplikasi dan pengujian.

## Sumber Acuan

Desain ini menggabungkan keputusan pengguna dengan hasil pemeriksaan terhadap:

- `Dokumen_Database_RAMS_FINAL_MasterAset.docx`;
- `Dokumen_Perancangan_RAMS_FINAL_MasterAset_Timeline.docx`;
- workbook RAMS Daop 1, Daop 4, Daop 8, Divre III, dan Divre IV;
- prototype `MasterAsset.vue` dan data dummy frontend; serta
- fondasi autentikasi, unit kerja, akun wilayah, dan audit log yang sudah tersedia.

Kelima workbook memiliki 29 sheet dengan struktur utama yang sama. Sheet `Predictive Data Asset` memiliki 38 kolom dan 340 formula. Sheet tersebut menyediakan kelompok aset, system, subsystem, total unit, dan tanggal pemasangan, tetapi tidak menyediakan kolom khusus `nama_aset` atau `lokasi`.

Sheet `Sheet1` memuat klasifikasi berjenjang yang lebih rinci. Pemetaan levelnya masih membutuhkan desain modul Kategori Aset tersendiri dan tidak dimasukkan ke CRUD Master Aset tahap ini.

## Pendekatan

Implementasi memakai pendekatan bertahap dan ter-normalisasi:

- Master Aset hanya menyimpan identitas dan kepemilikan aset;
- formula inventori, risiko, dan reliability tidak disalin ke tabel aset;
- kategori hierarkis dan sparepart tetap menjadi entitas terpisah;
- data Excel diimpor lewat proses lokal yang dapat diulang; dan
- file Excel sumber tidak disalin ke repository atau diubah oleh aplikasi.

Pendekatan ini dipilih dibanding memasukkan seluruh 38 kolom `Predictive Data Asset` ke tabel aset. Tabel tunggal tersebut akan menggandakan data, mencampur tanggung jawab modul, dan menyulitkan pemeliharaan formula.

## Akun Demo DAOP

### Akun yang dibuat

Seeder membuat sembilan akun:

| Unit | Username | Password lokal |
| --- | --- | --- |
| DAOP-1 | `daop1` | `daop1234` |
| DAOP-2 | `daop2` | `daop1234` |
| DAOP-3 | `daop3` | `daop1234` |
| DAOP-4 | `daop4` | `daop1234` |
| DAOP-5 | `daop5` | `daop1234` |
| DAOP-6 | `daop6` | `daop1234` |
| DAOP-7 | `daop7` | `daop1234` |
| DAOP-8 | `daop8` | `daop1234` |
| DAOP-9 | `daop9` | `daop1234` |

Nama pengguna mengikuti pola `Operator Daop 1` sampai `Operator Daop 9`. Semua akun memakai role `unit`, email `null`, status aktif, dan relasi `unit_kerja_id` yang sesuai.

Akun Divre tidak dibuat pada tahap ini.

### Keamanan seeder

Password pendek hanya berlaku untuk demo lokal. `RegionalAccountSeeder` hanya bekerja jika seluruh kondisi berikut terpenuhi:

- environment adalah `local` atau `testing`;
- konfigurasi `RAMS_SEED_DEMO_ACCOUNTS` bernilai `true`; dan
- password demo tersedia melalui `RAMS_DAOP_PASSWORD`.

Seeder tidak membuat akun demo pada production. Seeder bersifat idempotent: menjalankannya kembali mempertahankan satu akun per username dan memastikan unit, role, status, serta password demo sesuai konfigurasi lokal.

Aturan password minimal 12 karakter pada formulir administrasi tetap berlaku. Pengecualian hanya terjadi pada seeder demo yang tidak menerima input pengguna.

## Model Data Master Aset

Tabel `assets` memiliki field berikut:

| Field | Aturan |
| --- | --- |
| `id` | Primary key bigint |
| `unit_kerja_id` | Foreign key wajib ke `unit_kerjas` |
| `nama_aset` | Nama aset wajib, maksimal 255 karakter |
| `aset_prasarana_sintel` | Kelompok besar sesuai Excel, wajib |
| `system` | System sesuai Excel, wajib |
| `subsystem` | Subsystem sesuai Excel, wajib |
| `lokasi` | Nullable, maksimal 255 karakter |
| `jumlah_unit` | Bilangan bulat minimal 0 |
| `tanggal_pemasangan` | Tanggal nullable |
| `status` | `aktif`, `nonaktif`, atau `dalam_perbaikan` |
| `source_key` | Hash sumber nullable dan unik; tidak ditampilkan pada UI |
| `created_at`, `updated_at` | Timestamp Laravel |
| `deleted_at` | Soft delete |

Data manual memiliki `source_key` bernilai `null`. Importer membentuk `source_key` deterministik dari kode unit, nama sheet, system, dan subsystem sumber. Import yang dijalankan kembali memperbarui baris yang sama, bukan membuat duplikat. Pendekatan ini tetap mengizinkan pengguna membuat lebih dari satu aset manual pada subsystem yang sama.

`nama_aset` tetap menjadi bagian model inti. Untuk data hasil import, nilai awalnya diambil dari `subsystem` dan dapat diedit. Saat modul sparepart dibuat, sparepart akan memiliki nama sendiri dan foreign key `aset_id`; nama sparepart tidak menggantikan `nama_aset`.

`lokasi` bersifat nullable karena sheet sumber tidak menyediakannya. UI menampilkan `Belum dilengkapi` untuk lokasi kosong.

## Import Excel

Artisan command menerima path workbook dan kode unit secara eksplisit, misalnya:

```text
php artisan rams:import-master-assets "D:\KAI RAMS\Risk Analysis And Management System RAMS Daop 1.xlsm" --unit=DAOP-1
```

Importer membaca sheet `Predictive Data Asset` mulai dari baris data ketiga. Baris tanpa system atau subsystem dilewati. Pemetaan field:

| Excel | Master Aset |
| --- | --- |
| `ASET PRASARANA SINTEL` | `aset_prasarana_sintel` |
| `System` | `system` |
| `Subsystem` | `subsystem` dan nilai awal `nama_aset` |
| `TOTAL` | `jumlah_unit` |
| `Tanggal Pemasangan` | `tanggal_pemasangan` |

Importer tidak menyalin formula Criticality, inventory, umur peralatan, likelihood, consequence, rating, atau safety stock. Field tersebut menjadi tanggung jawab modul terkait.

Nilai `TOTAL` berupa tanda hubung atau kosong dinormalisasi menjadi nol. Nilai tanggal Excel dikonversi menjadi tanggal MySQL tanpa mengubah workbook.

Pada import ulang, importer memperbarui field sumber, tetapi mempertahankan `nama_aset`, `lokasi`, dan `status` yang sudah disunting pengguna. Aset dengan `source_key` sama yang sudah dihapus tetap berada dalam keadaan soft-deleted dan dilaporkan sebagai baris yang dilewati; import tidak menghidupkannya kembali secara diam-diam.

Importer memakai library PHP yang mendukung pembacaan `.xlsm`, berjalan dalam transaksi database, tidak menyimpan ulang workbook, dan menampilkan ringkasan jumlah baris dibuat, diperbarui, dilewati, atau gagal. Kode unit wajib ditemukan dan aktif. Kegagalan validasi membatalkan import agar database tidak berisi data sebagian.

Proteksi sheet tidak mengenkripsi nilai sel. Password `admin` hanya dipakai untuk pemeriksaan manual di Microsoft Excel jika formula atau validasi perlu ditampilkan. Importer bersifat read-only terhadap workbook.

## Hak Akses dan Batas Wilayah

Akun Pusat:

- melihat aset seluruh Daop dan Divre;
- memfilter berdasarkan unit kerja;
- membuat aset untuk unit aktif;
- mengubah dan menghapus aset dari semua unit; dan
- memilih unit kerja saat membuat atau mengubah aset.

Akun Unit:

- hanya melihat aset dengan `unit_kerja_id` miliknya;
- membuat aset untuk unitnya sendiri;
- mengubah dan menghapus aset miliknya; dan
- tidak dapat mengirim atau mengubah `unit_kerja_id` ke wilayah lain.

Pembatasan diterapkan pada query server, validasi, dan otorisasi model. Filter frontend bukan mekanisme keamanan.

## Alur Backend

Route `/master-asset` yang semula merender prototype diganti dengan resource controller Inertia. Controller menyediakan daftar, formulir tambah, penyimpanan, formulir edit, pembaruan, dan soft delete.

Daftar aset memakai pagination server-side. Query mendukung:

- pencarian `nama_aset`, `system`, `subsystem`, dan `lokasi`;
- filter unit kerja untuk Akun Pusat;
- filter status; dan
- urutan stabil berdasarkan unit, system, subsystem, dan nama aset.

Form Request menormalisasi spasi pada data teks dan memvalidasi relasi unit aktif, jumlah unit, tanggal, serta status. Operasi tulis menggunakan transaksi. Setiap create, update, import, dan soft delete menghasilkan audit log tanpa data sensitif.

## Alur Inertia dan UI

Halaman Vue menerima data dari controller melalui Inertia props. `MockAssetRepository`, `assets.json`, dan use case dummy tidak lagi dipakai oleh halaman Master Aset.

Daftar Master Aset mempertahankan identitas visual KAI yang sudah digunakan aplikasi:

- warna biru tua untuk struktur dan navigasi;
- oranye KAI untuk tindakan utama;
- kartu statistik yang tenang dan mudah dipindai;
- tabel responsif dengan header tetap jelas;
- status memakai teks dan warna, bukan warna saja; dan
- fokus keyboard terlihat pada semua kontrol.

Halaman menyediakan:

- jumlah aset hasil filter;
- jumlah unit fisik dari penjumlahan `jumlah_unit`;
- jumlah aset aktif;
- jumlah subsystem unik;
- pencarian dengan submit yang jelas;
- filter unit dan status;
- pagination nyata;
- formulir tambah dan edit; serta
- dialog konfirmasi sebelum soft delete.

Empty state menjelaskan tindakan yang dapat dilakukan. Pesan validasi ditampilkan dekat field terkait. Tombol tidak aktif selama request diproses untuk mencegah submit ganda.

## Penanganan Kesalahan

- Aset di luar wilayah menghasilkan respons 404 agar keberadaan data wilayah lain tidak bocor.
- Unit tidak aktif tidak dapat dipakai untuk aset baru.
- Data yang gagal validasi tidak ditulis ke database.
- Import dengan sheet atau header yang tidak sesuai berhenti dengan pesan yang menyebutkan masalah dan nama workbook.
- Import berulang tidak menghasilkan baris ganda.
- Penghapusan menggunakan soft delete dan tidak merusak audit log.

## Pengujian

Feature test MySQL membuktikan:

- seeder membuat sembilan akun DAOP dengan password yang diminta;
- seeder dapat dijalankan ulang tanpa duplikat;
- seeder berhenti ketika demo account dinonaktifkan atau environment production;
- Akun Pusat dapat mengelola semua aset;
- Akun Unit hanya dapat mengakses aset unitnya;
- manipulasi `unit_kerja_id` ditolak;
- pencarian, filter, pagination, soft delete, dan audit log bekerja;
- import membuat dan memperbarui data tanpa duplikat; serta
- workbook atau header yang salah ditolak tanpa partial write.

Test Vue membuktikan:

- props aset dirender tanpa repository dummy;
- pencarian dan filter mengirim query yang benar;
- status, lokasi kosong, dan pagination tampil dengan benar;
- form mengirim field yang sesuai; dan
- dialog hapus dapat dibatalkan atau dikonfirmasi dengan keyboard.

Verifikasi akhir menjalankan PHPUnit pada MySQL 8.4 test, Vitest, build Vite, Laravel Pint, migration database utama, import sampel, dan pengujian browser untuk Akun Pusat serta satu Akun DAOP.

## Batas Lingkup

Termasuk:

- akun demo DAOP 1–9;
- tabel, model, enum status, factory, otorisasi, request, controller, route, dan audit Master Aset;
- import Master Aset dari sheet `Predictive Data Asset`;
- UI Inertia/Vue CRUD Master Aset;
- penghapusan dependency dummy dari halaman Master Aset; dan
- pengujian serta dokumentasi penggunaan.

Tidak termasuk:

- akun Divre;
- CRUD Kategori Aset dan import hierarki `Sheet1`;
- CRUD sparepart dan 37 field Predictive Inventory;
- Risk Matrix, LxC, reliability, dan Reorder Stock;
- restore UI untuk aset yang sudah dihapus;
- import otomatis dari folder tanpa pilihan pengguna; dan
- penyimpanan workbook Excel dalam repository.

## Kriteria Penerimaan

Perubahan diterima jika:

1. `daop1` sampai `daop9` dapat login secara lokal dengan `daop1234` dan masing-masing terikat pada unit yang benar;
2. Akun Pusat dapat melihat serta mengelola aset seluruh wilayah;
3. akun DAOP hanya dapat melihat dan mengelola aset wilayahnya;
4. data Master Aset tersimpan di MySQL dan halaman tidak memakai data dummy;
5. import lima workbook dapat dijalankan ulang tanpa duplikasi;
6. `nama_aset` dipertahankan dan nilai awal import berasal dari `subsystem`;
7. pengubahan dan penghapusan tercatat dalam audit log; dan
8. seluruh test, build, format check, dan pemeriksaan browser lulus.
