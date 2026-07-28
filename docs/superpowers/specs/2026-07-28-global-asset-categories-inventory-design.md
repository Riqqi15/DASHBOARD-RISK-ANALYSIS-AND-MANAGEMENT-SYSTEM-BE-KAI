# Desain Kategori Aset Global dan Inventory Sparepart

Tanggal: 28 Juli 2026  
Status: Disetujui untuk perencanaan implementasi

## 1. Latar belakang

Modul Master Aset saat ini menyimpan `aset_prasarana_sintel`, `system`, dan `subsystem` sebagai teks bebas pada setiap aset. Pendekatan ini telah menghubungkan 85 aset hasil import ke backend, tetapi belum menyediakan CRUD kategori global. Halaman Predictive Inventory juga masih memakai data dummy.

Workbook RAMS menetapkan dua struktur yang harus dipertahankan:

1. sheet `Predictive Data Asset` memakai kolom `ASET PRASARANA SINTEL`, `System`, `Subsystem`, `TOTAL`, `Sparepart IN`, dan `Sparepart OUT`;
2. sheet `Reorder Stock` merinci `System`, `Sub-System`, `Equipment`, `Detail Equipment`, data kegagalan, lead time, safety stock, lead time demand, reorder point, dan severity.

Desain ini menormalisasi kedua struktur tersebut dalam MySQL 8.4. Excel tetap menjadi sumber import awal. MySQL menjadi sumber data aplikasi setelah import.

## 2. Keputusan yang disetujui

- Hierarki `Aset Prasarana Sintel → System → Subsystem` berlaku sama untuk seluruh Daop dan Divre.
- Admin Pusat mengelola hierarki global dan master sparepart.
- Admin Pusat dapat mengganti nama kategori. Perubahan nama langsung berlaku di seluruh wilayah tanpa mengubah identitas kategori.
- Akun wilayah memilih kategori global dan hanya mengelola data wilayahnya.
- `TOTAL`, `Sparepart IN`, dan `Sparepart OUT` bersifat per unit kerja.
- Pergerakan sparepart baru dicatat sebagai transaksi IN atau OUT yang tidak dapat diedit secara diam-diam.
- Transaksi yang salah diperbaiki dengan transaksi koreksi yang terhubung ke transaksi asal.
- Nilai Excel lama menjadi data awal. Nilai nol tidak menghasilkan transaksi kosong.
- UI mengikuti struktur Excel, tetapi menggunakan pola interaksi web yang lebih aman, jelas, dan responsif.

## 3. Tujuan

1. Menyediakan CRUD tiga tingkat kategori aset global.
2. Mengubah form aset dari teks bebas menjadi pilihan kategori bertingkat.
3. Mempertahankan seluruh aset dan data import yang sudah ada.
4. Menyediakan CRUD master sparepart berdasarkan sheet `Reorder Stock`.
5. Menyediakan saldo stok per unit kerja dan riwayat transaksi IN/OUT.
6. Menampilkan ringkasan `TOTAL`, `Sparepart IN`, dan `Sparepart OUT` per subsystem seperti Excel.
7. Menerapkan pembatasan akses pusat/wilayah dan audit log pada semua perubahan.
8. Mengganti halaman Inventory dummy dengan data backend.

## 4. Di luar cakupan

- Implementasi seluruh formula Criticality, Likelihood, Consequences, Rating, dan forecast dari 37 kolom `Predictive Data Asset`.
- Purchase order dan persetujuan pengadaan.
- Integrasi ERP atau SAP.
- Pengubahan file Excel sumber.
- Penghapusan permanen transaksi stok yang telah diposting.

Data yang dibangun dalam modul ini menjadi fondasi untuk formula predictive dan pengadaan pada tahap berikutnya.

## 5. Model domain

### 5.1 Hierarki kategori global

#### `asset_groups`

Mewakili `ASET PRASARANA SINTEL`.

| Field | Ketentuan |
| --- | --- |
| `id` | Primary key |
| `name` | Nama yang tampil dan dapat diubah |
| `normalized_name` | Nama terstandardisasi untuk validasi duplikat |
| `is_active` | Status pemakaian |
| timestamps | Waktu pembuatan dan perubahan |
| soft delete | Penghapusan aman |

#### `asset_systems`

| Field | Ketentuan |
| --- | --- |
| `id` | Primary key |
| `asset_group_id` | Parent wajib |
| `name` | Nama System yang dapat diubah |
| `normalized_name` | Unik di dalam parent |
| `is_active` | Status pemakaian |
| timestamps dan soft delete | Riwayat dasar |

#### `asset_subsystems`

| Field | Ketentuan |
| --- | --- |
| `id` | Primary key |
| `asset_system_id` | Parent wajib |
| `name` | Nama Subsystem yang dapat diubah |
| `normalized_name` | Unik di dalam parent |
| `is_active` | Status pemakaian |
| timestamps dan soft delete | Riwayat dasar |

Nama kategori bukan identifier. Semua relasi memakai ID. Perubahan nama karena koreksi ejaan atau penyesuaian organisasi tidak memutus aset, sparepart, transaksi, maupun laporan lama.

### 5.2 Alias sumber import

`asset_category_source_aliases` memetakan path teks Excel yang sudah dinormalisasi ke ID kategori global. Importer memakai alias ini setelah kategori diganti nama. Dengan demikian, import ulang tidak membuat kategori lama kembali dan tidak menimpa nama hasil edit Admin Pusat.

Alias menyimpan:

- tipe level;
- path sumber yang dinormalisasi;
- ID kategori tujuan;
- nama workbook/sheet sumber;
- waktu import pertama dan terakhir.

### 5.3 Aset per wilayah

Tabel `assets` tetap menjadi data aset per unit kerja. Tabel ini memperoleh `asset_subsystem_id` sebagai foreign key wajib setelah migrasi data selesai.

Field utama yang tetap dipakai:

- `unit_kerja_id`;
- `asset_subsystem_id`;
- `nama_aset`;
- `lokasi`;
- `jumlah_unit` sebagai `TOTAL`;
- `tanggal_pemasangan`;
- `status`;
- `source_key`;
- timestamps dan soft delete.

UI membaca nama kelompok, system, dan subsystem melalui relasi. Kolom teks lama tidak lagi menjadi sumber tampilan setelah backfill berhasil.

### 5.4 Ringkasan awal per subsystem

Tabel `unit_subsystem_openings` menyimpan nilai historis Excel yang belum memiliki rincian transaksi:

| Field | Ketentuan |
| --- | --- |
| `unit_kerja_id` | Unit pemilik data |
| `asset_subsystem_id` | Subsystem global |
| `sparepart_in` | Nilai awal kolom Excel |
| `sparepart_out` | Nilai awal kolom Excel |
| `source_key` | Mencegah duplikasi import |

Pasangan unit dan subsystem harus unik. Nilai ini hanya dapat diubah melalui import ulang yang sah atau koreksi Admin Pusat yang tercatat. Nilai nol tetap tersimpan sebagai baseline tanpa membuat baris transaksi palsu.

### 5.5 Master sparepart global

Tabel `spare_parts` mengikuti sheet `Reorder Stock`.

| Field | Ketentuan |
| --- | --- |
| `id` | Primary key |
| `asset_subsystem_id` | Subsystem terkait |
| `code` | Kode global unik |
| `source_key` | Identitas import yang tetap saat kode atau nama diubah |
| `equipment` | Nama Equipment |
| `detail_equipment` | Nama Detail Equipment/sparepart |
| `max_yearly_failure` | Nullable, angka nonnegatif |
| `average_yearly_failure` | Nullable, angka nonnegatif |
| `max_lead_time_months` | Nullable, angka nonnegatif |
| `average_lead_time_months` | Nullable, angka nonnegatif |
| `safety_stock` | Nullable, angka nonnegatif |
| `lead_time_demand` | Nullable, angka nonnegatif |
| `reorder_point` | Nullable, angka nonnegatif |
| `severity` | Nullable |
| `unit_of_measure` | Satuan, default `unit` |
| `is_active` | Status pemakaian |
| timestamps dan soft delete | Riwayat dasar |

`detail_equipment` menjadi nama barang yang tampil. `equipment` mempertahankan pengelompokan dari Excel. Admin Pusat dapat melengkapi kode atau satuan yang tidak tersedia di workbook.

### 5.6 Saldo dan transaksi stok

#### `inventory_stocks`

Menyimpan saldo terkini untuk setiap pasangan unit kerja dan sparepart.

| Field | Ketentuan |
| --- | --- |
| `unit_kerja_id` | Unit pemilik stok |
| `spare_part_id` | Sparepart global |
| `quantity` | Saldo nonnegatif |
| timestamps | Waktu perubahan |

Pasangan `unit_kerja_id` dan `spare_part_id` harus unik.

#### `stock_movements`

| Field | Ketentuan |
| --- | --- |
| `unit_kerja_id` | Unit transaksi |
| `spare_part_id` | Barang yang bergerak |
| `type` | `in`, `out`, `opening`, atau `correction` |
| `direction` | `in` atau `out`; wajib untuk opening dan correction |
| `quantity` | Bilangan positif |
| `stock_before` | Saldo sebelum transaksi |
| `stock_after` | Saldo setelah transaksi |
| `movement_date` | Tanggal operasional |
| `reference_number` | Nomor dokumen, nullable |
| `notes` | Catatan, nullable |
| `reverses_movement_id` | Transaksi asal untuk koreksi, nullable |
| `actor_id` | Pengguna yang memposting |
| timestamps | Waktu posting |

Service stok mengunci baris `inventory_stocks` dengan `lockForUpdate`, memvalidasi saldo, membuat movement, memperbarui saldo, dan mencatat audit log dalam satu transaksi MySQL.

## 6. Perhitungan tampilan Excel

Untuk setiap unit dan subsystem:

```text
TOTAL = jumlah seluruh assets.jumlah_unit pada subsystem
Sparepart IN = nilai awal IN + jumlah movement berarah IN
Sparepart OUT = nilai awal OUT + jumlah movement berarah OUT
Stok sparepart = seluruh movement berarah IN - seluruh movement berarah OUT
```

Tabel utama menampilkan enam kolom Excel. Angka IN/OUT pada tabel merupakan ringkasan subsystem. Nilai awal agregat dari kolom A–F tidak menambah saldo barang tertentu karena Excel tidak menyebut nama sparepart. Saldo tiap barang dimulai melalui movement `opening` yang memilih sparepart. Pengguna memposting transaksi berikutnya melalui form terpisah agar setiap perubahan dapat ditelusuri.

## 7. Hak akses

| Tindakan | Admin Pusat | Akun Daop/Divre |
| --- | --- | --- |
| Melihat hierarki global | Ya | Ya, read-only |
| Menambah/mengubah kategori | Ya | Tidak |
| Menonaktifkan kategori | Ya | Tidak |
| Menghapus kategori belum terpakai | Ya | Tidak |
| Mengelola master sparepart | Ya | Tidak |
| Melihat stok | Semua unit | Unit sendiri |
| Membuat transaksi IN/OUT | Semua unit dengan pilihan unit | Unit sendiri |
| Mengoreksi transaksi | Semua unit | Unit sendiri |
| Mengelola aset | Semua unit | Unit sendiri |

Akses objek lintas wilayah mengembalikan 404. Backend menentukan unit akun wilayah; request tidak boleh memilih unit lain.

## 8. Aturan CRUD kategori

- Nama wajib, maksimal 255 karakter, dan dinormalisasi dari spasi berulang.
- Nama kelompok unik secara global.
- Nama System unik di dalam kelompok.
- Nama Subsystem unik di dalam System.
- Kategori aktif dapat dipilih pada form baru.
- Kategori nonaktif tetap tampil pada data lama dan tidak dapat dipilih untuk relasi baru.
- Parent yang memiliki child tidak dapat dihapus.
- Kategori yang dipakai aset, sparepart, alias import, opening, atau transaksi tidak dapat dihapus.
- Sistem menawarkan nonaktifkan ketika penghapusan ditolak.
- Rename mencatat nilai sebelum dan sesudah pada audit log.

## 9. Import Excel

### 9.1 `Predictive Data Asset`

Importer membaca kolom A–F dan melakukan forward-fill pada sel kelompok dan System yang digabungkan.

Untuk setiap baris valid, importer:

1. menormalisasi path kategori sumber;
2. mencari alias sumber;
3. membuat kategori dan alias bila belum ada;
4. membuat atau memperbarui aset wilayah berdasarkan `source_key`;
5. mengimpor `TOTAL` ke `jumlah_unit`;
6. mengimpor `Sparepart IN/OUT` ke `unit_subsystem_openings`;
7. mempertahankan `nama_aset`, `lokasi`, dan `status` yang pernah disunting pengguna;
8. tidak menimpa nama kategori global yang pernah diubah Admin Pusat.

### 9.2 `Reorder Stock`

Importer melakukan forward-fill untuk System, Sub-System, dan Equipment. Baris Detail Equipment menghasilkan master sparepart. Nilai numerik yang kosong tetap `null`; importer tidak menciptakan angka perkiraan.

Kode sparepart yang tidak tersedia di Excel dibuat deterministik untuk import awal dan dapat diubah Admin Pusat selama tetap unik.
Importer memakai `source_key`, bukan kode yang dapat diedit, untuk menemukan sparepart pada import ulang.

### 9.3 Konsistensi import

- Seluruh import berjalan dalam transaksi database.
- Header atau struktur salah membatalkan seluruh import workbook tersebut.
- Import ulang tidak membuat aset, kategori, opening, atau sparepart ganda.
- Soft-deleted record tidak dipulihkan otomatis.
- Workbook tetap read-only dan berada di luar Git.

## 10. Desain UI

### 10.1 Master Aset

Halaman daftar mempertahankan ringkasan dan filter yang ada, lalu menambahkan mode tabel hierarkis enam kolom:

1. Aset Prasarana Sintel;
2. System;
3. Subsystem;
4. TOTAL;
5. Sparepart IN;
6. Sparepart OUT.

Baris kelompok dan System dapat dilipat. Setiap subsystem menyediakan aksi edit aset, lihat sparepart, transaksi IN, dan transaksi OUT. Tampilan mobile mengubah baris menjadi kartu berurutan tanpa menghilangkan konteks parent.

Form aset memakai tiga dropdown bertingkat. Pemilihan kelompok memfilter System; pemilihan System memfilter Subsystem. Akun wilayah tidak melihat kontrol unit kerja.

### 10.2 Administrasi Kategori Aset

Route pusat `/admin/asset-categories` memakai tiga panel:

- panel Aset Prasarana Sintel;
- panel System dari kelompok terpilih;
- panel Subsystem dari System terpilih.

Setiap panel menyediakan pencarian, jumlah turunan, status, tambah, rename, nonaktifkan, dan hapus aman. Dialog menjelaskan dampak sebelum tindakan.

### 10.3 Predictive Inventory

Halaman `/inventory` mengganti data dummy dengan empat bagian:

- ringkasan stok dan kebutuhan reorder;
- daftar stok per sparepart;
- master sparepart untuk Admin Pusat;
- riwayat transaksi.

Filter mencakup unit, kategori, subsystem, status stok, tipe transaksi, dan rentang tanggal. Status stok membandingkan saldo dengan reorder point dan safety stock.

Form transaksi menampilkan tipe IN/OUT dengan warna dan teks yang berbeda, sparepart, jumlah, tanggal, referensi, catatan, saldo sebelum, dan proyeksi saldo setelah. Tombol OUT menolak submit ketika proyeksi saldo negatif.

### 10.4 Standar pengalaman pengguna

- Mengikuti warna KAI yang sudah dipakai layout utama.
- Mengutamakan tipografi, ruang kosong, dan hierarchy informasi.
- Menyediakan loading state, empty state, error state, serta success message.
- Memakai label eksplisit dan pesan validasi dekat field.
- Mendukung keyboard, focus state, dialog ber-ARIA, dan kontras teks yang layak.
- Mempertahankan filter setelah pagination dan navigasi kembali.
- Menyediakan konfirmasi untuk delete, deactivate, koreksi, dan transaksi OUT.

## 11. Error handling dan konkurensi

- OUT melebihi saldo mengembalikan validation error dan tidak membuat movement.
- Permintaan ganda dengan idempotency token tidak membuat transaksi ganda.
- Konflik rename mengembalikan pesan nama sudah digunakan pada parent yang sama.
- Delete kategori terpakai mengembalikan pesan yang menyebut jenis relasi penghalang.
- Semua operasi multi-tabel memakai transaksi MySQL.
- Posting stok memakai row lock untuk mencegah overselling saat dua pengguna melakukan OUT bersamaan.
- Exception import mencatat konteks workbook, sheet, dan baris tanpa menyimpan perubahan parsial.

## 12. Audit log

Audit log mencatat:

- pembuatan, rename, perubahan status, dan penghapusan kategori;
- pembuatan dan perubahan master sparepart;
- pembuatan aset dan perubahan relasi subsystem;
- import opening;
- setiap stock movement dan koreksi;
- actor, unit kerja, IP, user agent, nilai lama, dan nilai baru yang aman.

Audit log tidak menyimpan password, session, token, atau data sensitif autentikasi.

## 13. Migrasi data lama

Migrasi harus mempertahankan 85 aset yang sudah ada.

Urutannya:

1. membuat tabel kategori dan alias;
2. menambah `asset_subsystem_id` nullable;
3. membentuk hierarki global dari kombinasi teks aset lama;
4. membuat alias sumber;
5. mengisi foreign key seluruh aset;
6. memverifikasi tidak ada aset tanpa subsystem;
7. menjadikan foreign key wajib;
8. mengalihkan query dan UI ke relasi;
9. mempertahankan kolom teks lama sebagai snapshot sumber selama satu tahap migrasi;
10. menghapus snapshot hanya setelah importer dan rollback path terverifikasi.

Migration dan command backfill harus dapat dijalankan ulang dengan aman. Sistem melaporkan duplikat atau path yang ambigu untuk ditinjau Admin Pusat, bukan menggabungkannya secara diam-diam.

## 14. Pengujian

### Backend

- schema, relasi, unique constraint, foreign key, dan soft delete;
- policy pusat/wilayah dan 404 lintas wilayah;
- CRUD tiga tingkat kategori;
- rename global tanpa memutus aset;
- larangan delete kategori terpakai;
- backfill 85 aset tanpa kehilangan data;
- import A–F dan Reorder Stock secara idempoten;
- preservasi edit pengguna pada import ulang;
- saldo IN/OUT, opening, correction, dan pencegahan saldo negatif;
- konkurensi transaksi OUT;
- audit log setiap mutation.

### Frontend

- dropdown kategori bertingkat;
- tabel hierarkis dan ringkasan enam kolom;
- filter dan pagination server-side;
- CRUD kategori pusat;
- form sparepart;
- form transaksi dan proyeksi saldo;
- dialog delete/deactivate/correction;
- aksesibilitas pesan error dan empty state.

### Verifikasi akhir

- seluruh PHPUnit dan Vitest lulus;
- Pint dan build produksi lulus;
- browser check dengan `admin.pusat` dan `daop1`;
- admin dapat mengelola kategori global;
- `daop1` hanya melihat dan mengubah data DAOP-1;
- total aset tetap 85 setelah backfill dan import ulang;
- stok dan ringkasan IN/OUT cocok dengan ledger.

## 15. Tahapan implementasi

Implementasi dipecah menjadi checkpoint berikut:

1. schema kategori, alias, dan backfill aset;
2. policy dan CRUD kategori pusat;
3. integrasi dropdown kategori pada CRUD aset;
4. perluasan importer `Predictive Data Asset` A–F;
5. schema dan importer master sparepart `Reorder Stock`;
6. saldo, movement, dan service transaksi;
7. UI tabel Master Aset hierarkis;
8. UI Inventory dan transaksi;
9. penghapusan data dummy yang tidak lagi dipakai;
10. verifikasi data, test suite, build, dan browser.

Setiap checkpoint memakai TDD dan commit lokal terpisah. Push tetap dilakukan oleh pengguna.
