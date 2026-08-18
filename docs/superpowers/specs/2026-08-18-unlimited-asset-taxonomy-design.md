# Unlimited Asset Taxonomy Design

## Tujuan

Mengubah taksonomi aset yang saat ini berhenti pada tiga level tetap menjadi hierarki global yang dapat dikembangkan tanpa batas, sambil mempertahankan aset operasional per DAOP/DIVRE dan seluruh riwayat RAMS.

## Keputusan Produk

- Level 1 bernama `Aset Prasarana Sintel`.
- Level 2 bernama `System`.
- Level 3 bernama `Subsystem`.
- Admin Pusat dapat menambahkan Level 4 dan seterusnya tanpa batas serta menentukan nama levelnya.
- Setiap level ditampilkan sebagai satu kolom hierarki.
- Aset dapat ditempatkan pada node di level mana pun.
- Taksonomi bersifat global, sedangkan aset tetap dimiliki satu DAOP/DIVRE.
- Admin Pusat dapat memilih wilayah; akun wilayah otomatis terkunci ke unit kerjanya.
- Akun wilayah dapat mengelola aset miliknya, tetapi tidak dapat mengubah level atau node global.
- Penghapusan massal dari node hanya berlaku pada wilayah aktif.
- Penghapusan massal mengarsipkan aset aktif, tetapi tidak menghapus laporan gangguan, risiko, reliability, atau riwayat audit.

## Pilihan Arsitektur

Dipilih satu model pohon generik. Struktur tidak membuat tabel baru untuk setiap level.

### Definisi level

Tabel `asset_category_levels` menyimpan:

- `id`;
- `name`;
- `position` yang unik dan dimulai dari 1;
- status aktif;
- timestamps.

Level baru hanya dapat ditambahkan pada posisi terakhir. Nama level dapat diperbarui. Level hanya dapat dihapus jika merupakan level terakhir dan belum mempunyai node atau aset. Aturan ini mencegah perubahan posisi yang merusak jalur existing.

### Node hierarki

Tabel `asset_category_nodes` menyimpan:

- `id`;
- `asset_category_level_id`;
- `parent_id` yang nullable untuk Level 1;
- nama dan nama ternormalisasi;
- urutan;
- warna dashboard beserta sumber warna;
- status aktif;
- soft delete dan timestamps.

Node akar wajib berada di Level 1. Anak node wajib berada tepat satu level setelah parent. Nama node unik di bawah parent yang sama. Index disediakan untuk level, parent, status, dan pencarian nama.

## Migrasi Data Existing

Migrasi tidak menghapus tabel atau data lama pada rilis pertama.

1. Membuat tiga definisi level bawaan.
2. Menyalin seluruh `asset_groups` menjadi node Level 1.
3. Menyalin seluruh `asset_systems` menjadi node Level 2 dengan parent Level 1.
4. Menyalin seluruh `asset_subsystems` menjadi node Level 3 dengan parent Level 2.
5. Menambahkan `asset_category_node_id` pada aset dan memetakannya dari `asset_subsystem_id`.
6. Memindahkan referensi alias sumber dan importer ke node generik.
7. Menjaga kolom dan tabel lama sebagai compatibility fallback selama validasi parity.

Backfill bersifat idempotent dan mencatat jumlah level, node, aset yang berhasil dipetakan, serta data yang belum dapat dipetakan. Cutover hanya dilakukan jika seluruh referensi wajib berhasil dipetakan.

## Integrasi Import Excel

- Struktur workbook existing tetap dipetakan ke tiga level awal.
- Importer menggunakan resolver hierarki generik sebagai sumber utama.
- Level tambahan tidak mengubah hasil import Level 1–3.
- Import tidak membuat definisi level baru otomatis.
- Nama asing dari workbook tetap disimpan sebagai alias sumber pada node terkait.
- Parity import existing wajib tetap lulus sebelum compatibility fallback dapat dilepas.

## Akses dan Otorisasi

### Admin Pusat

- melihat dan memilih seluruh DAOP/DIVRE;
- menambah, mengganti nama, menonaktifkan, serta menghapus level yang aman;
- menambah dan mengelola node global;
- menambah, mengubah, dan mengarsipkan aset pada wilayah terpilih.

### Akun DAOP/DIVRE

- melihat hierarki global dalam mode baca;
- tidak dapat menambah, mengubah, menonaktifkan, atau menghapus level dan node;
- melihat, menambah, mengubah, dan mengarsipkan aset pada unit kerjanya sendiri;
- tidak dapat mengirim `unit_kerja_id` wilayah lain melalui request manual.

Semua endpoint melakukan pemeriksaan policy di backend. Pembatasan tombol pada frontend bukan kontrol keamanan utama.

## Perilaku Antarmuka

### Pemilih wilayah

- Admin Pusat melihat pemilih DAOP/DIVRE di atas halaman.
- Akun wilayah melihat indikator unit kerja terkunci.
- Perubahan wilayah memperbarui aset dan hitungan tanpa mengubah taksonomi global.

### Kolom level dinamis

- Kolom dibuat dari daftar definisi level aktif.
- Memilih node membuka node anak pada kolom berikutnya.
- Kolom yang belum mempunyai parent terpilih menampilkan petunjuk singkat.
- Pada desktop, kolom memiliki lebar minimum yang konsisten dan dapat digulir horizontal.
- Pada mobile, pengguna bergerak satu level pada satu waktu dengan breadcrumb kembali.
- Admin Pusat melihat tombol `Tambah level` di bagian atas serta `Tambah` pada setiap kolom yang parent-nya valid.
- Nama kolom Level 1–3 memakai istilah yang telah disepakati, bukan `Kategori` generik.

### Panel aset wilayah

- Panel aset berada di bawah hierarki dan bukan bagian dari level.
- Panel mengikuti node aktif dan wilayah aktif.
- Memilih node parent menampilkan aset yang ditempatkan langsung pada node tersebut dan seluruh turunannya.
- Setiap baris menunjukkan nama aset, lokasi, jumlah unit, node penempatan, status, dan wilayah.
- Form tambah aset mengunci wilayah serta node yang sedang dipilih, tetapi pengguna dapat memilih node lain dari jalur yang diizinkan.
- Validasi dan field mengikuti Master Asset existing agar tidak ada dua aturan aset yang berbeda.

## Penghapusan Aset Per Wilayah

Pada setiap node tersedia tindakan eksplisit `Hapus aset wilayah`. Tindakan ini berbeda dari penghapusan node global.

Sebelum konfirmasi, backend menghitung untuk wilayah aktif:

- aset aktif langsung pada node;
- aset aktif pada seluruh node turunan;
- jumlah laporan gangguan, data risiko, reliability, dan data historis lain yang terkait.

Dialog menyebut nama wilayah, nama node, jumlah aset yang akan hilang dari data aktif, serta menegaskan bahwa riwayat tetap disimpan. Konfirmasi menjalankan satu transaksi yang:

1. mengunci scope wilayah dan node;
2. memastikan pengguna masih memiliki akses;
3. melakukan soft delete seluruh aset aktif pada subtree dan wilayah tersebut;
4. tidak menghapus node, level, aset wilayah lain, atau tabel laporan;
5. mencatat satu audit batch dengan unit, node, jumlah aset, dan ID aset.

Relasi pembaca laporan historis memakai aset yang termasuk soft-deleted agar nama aset dan konteks kategori tetap dapat ditampilkan.

Jika jumlah aset nol, tombol penghapusan dinonaktifkan dan menampilkan keterangan `Tidak ada aset aktif di wilayah ini`.

## Penghapusan Struktur Global

- Node global hanya dapat dihapus jika tidak mempunyai anak, alias, atau aset aktif/arsip pada wilayah mana pun.
- Level global hanya dapat dihapus jika merupakan level terakhir dan tidak mempunyai node.
- Untuk struktur yang masih digunakan, UI menyarankan nonaktifkan, bukan memaksa hard delete.
- Foreign key tetap menggunakan pembatasan delete untuk mencegah kehilangan data akibat request langsung atau race condition.

## Audit dan Pemulihan

- Penambahan, perubahan nama, status, dan penghapusan aman level/node dicatat.
- Tambah, ubah, dan bulk archive aset mencatat pengguna serta unit kerja.
- Audit tidak menyimpan password, session, token, atau file import.
- Soft-deleted assets tidak muncul pada data operasional aktif, tetapi tetap tersedia untuk laporan historis dan pemulihan administratif.

## Pengujian

### Backend

- migrasi dan backfill tiga level existing tanpa kehilangan data;
- backfill idempotent;
- level keempat, kelima, dan kedalaman lebih tinggi dapat dibuat;
- parent wajib berasal dari level tepat sebelumnya;
- nama unik dalam parent yang sama;
- akun wilayah ditolak ketika mengubah taksonomi atau menargetkan unit lain;
- aset dapat ditautkan pada level mana pun;
- query parent mencakup aset seluruh subtree;
- bulk archive hanya mengubah aset wilayah aktif;
- aset wilayah lain, node global, dan laporan historis tetap ada;
- race condition bulk archive tidak melewati policy atau scope;
- import Excel dan alias resolver Level 1–3 tetap parity.

### Frontend

- kolom mengikuti jumlah level tanpa hard-code tiga panel;
- pemilihan node memperbarui kolom berikut dan panel aset;
- breadcrumb mobile bekerja untuk kedalaman lebih dari tiga;
- kontrol global tersembunyi bagi akun wilayah;
- wilayah akun regional terkunci;
- dialog bulk archive menampilkan hitungan dan pesan yang benar;
- loading, empty, error, keyboard focus, serta overflow horizontal dapat digunakan.

### Regresi dan Dogfood

- menjalankan seluruh PHPUnit dan Vitest;
- menjalankan build produksi serta pemeriksa format PHP;
- menguji alur Admin Pusat dan minimal satu akun DAOP serta satu DIVRE di browser;
- mencoba tambah level, tambah node, tambah aset, edit aset, bulk archive, dan membaca laporan historis;
- mencatat setiap bug yang ditemukan dan memperbaikinya sebelum fitur dinyatakan selesai.

## Kriteria Penerimaan

- Level baru dapat ditambahkan tanpa migration baru dan langsung muncul sebagai kolom.
- Data Level 1–3 existing tetap identik setelah backfill.
- Import Excel existing tetap menghasilkan kategori serta aset yang sama.
- Aset dapat berada pada level mana pun tanpa rusak saat level baru ditambahkan.
- Akun wilayah tidak dapat melihat atau mengubah aset wilayah lain.
- Satu konfirmasi dapat mengarsipkan seluruh aset subtree hanya pada wilayah aktif.
- Laporan historis tetap dapat menampilkan identitas aset yang telah diarsipkan.
- Tidak ada hard delete massal terhadap aset operasional atau laporan RAMS.
