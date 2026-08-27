# Trouble Report UI Redesign

## Tujuan

Mengubah tampilan setiap halaman report subsystem menjadi lebih netral, padat, dan menyerupai lembar kerja operasional. Informasi keandalan serta riwayat gangguan harus lebih mudah dipindai tanpa penggunaan warna dekoratif yang berlebihan.

Perubahan ini hanya menyentuh presentasi. Data, formula, import Excel, routing, otorisasi, dan alur input manual tetap seperti sekarang.

## Arah Visual

- Gunakan kanvas abu-abu sangat muda dan permukaan putih.
- Gunakan teks utama biru-kehitaman dan teks pendukung abu-abu.
- Gunakan garis pemisah abu-abu muda hanya untuk membantu membaca struktur tabel.
- Hilangkan gradient, header ungu, header jingga, dan bidang biru dekoratif.
- Pertahankan warna hanya untuk makna status, peringatan, error, serta tombol tindakan.
- Gunakan sudut kecil sekitar 4–6 piksel; hindari kartu dan tombol yang terlalu membulat.
- Hindari bayangan dekoratif. Bayangan ringan hanya boleh dipakai bila diperlukan untuk membedakan lapisan interaktif.

Palet dasar:

- Latar halaman: `#F8FAFC`
- Permukaan: `#FFFFFF`
- Teks utama: `#0F172A`
- Teks pendukung: `#475569`
- Pemisah: `#E2E8F0`
- Aksi utama: mengikuti warna tindakan aplikasi yang sudah ada
- Warna semantik: hanya untuk sukses, peringatan, dan error

## Header Report

- Teks `SUBSYSTEM` tetap ditampilkan sebagai penanda konteks.
- `SUBSYSTEM` menjadi teks kecil biasa tanpa fill biru, border, atau bentuk badge.
- Nama subsystem tetap menjadi judul utama.
- Deskripsi report tetap berada tepat di bawah judul.
- Tombol `Kembali` dan `Input Manual` tetap tersedia, tetapi radius dan bayangannya disederhanakan.
- Tidak menggunakan logo subsystem. Ikon fungsional yang sudah ada boleh dipertahankan.

## Informasi Wilayah dan Equipment

- Informasi wilayah, jumlah unit, dan tanggal pemasangan tetap berada dalam satu permukaan putih.
- Gunakan layout kolom yang sama agar mudah dipindai.
- Gunakan border abu-abu muda sebagai struktur, bukan garis aksen dekoratif.
- Tautan `Ubah tanggal` tetap terlihat sebagai tindakan sekunder.

## Ringkasan Keandalan

- Header bagian menjadi putih dengan ikon kecil dan teks gelap.
- Tidak ada gradient atau bidang biru penuh.
- Header tabel menggunakan abu-abu netral.
- Isi tabel menggunakan latar putih dan pemisah baris tipis.
- Angka menggunakan perataan konsisten dan gaya tabular bila tersedia.
- Reliability dan availability tidak lagi memakai badge hijau penuh; nilai ditampilkan sebagai teks tebal dengan warna semantik yang hemat.
- Ganti sparepart dan vandalisme tidak diberi warna dekoratif bila nilainya normal.

## Log Kejadian Kegagalan

- Header bagian menjadi putih, tanpa bidang ungu.
- Header kolom menggunakan sistem netral yang sama dengan tabel ringkasan.
- Baris menggunakan latar putih dengan hover abu-abu sangat muda.
- Warna merah atau hijau pada tanggal hanya dipakai bila benar-benar menunjukkan status atau anomali, bukan sekadar membedakan dua kolom tanggal.
- Tombol edit dan hapus tetap menggunakan ikon dan warna tindakan yang jelas.
- Horizontal scrolling dan keterbacaan kolom panjang tetap dipertahankan.

## Output Kebutuhan Sparepart

- Header bagian menjadi putih, tanpa bidang jingga atau frame kuning.
- Header tabel mengikuti gaya netral yang sama.
- Empty state menggunakan ikon dan teks abu-abu tanpa ilustrasi atau warna dekoratif yang dominan.
- Data serta seluruh kondisi tampil/tidak tampil tetap mengikuti logic saat ini.

## Responsivitas dan Aksesibilitas

- Semua tabel tetap dapat digulir horizontal pada viewport sempit.
- Fokus keyboard pada tombol dan tautan tetap terlihat.
- Kontras teks harus tetap memenuhi keterbacaan yang baik.
- Informasi tidak boleh disampaikan hanya melalui warna.
- Struktur heading dan label yang sudah bermakna tetap dipertahankan.

## Batas Perubahan

Perubahan utama dilakukan pada:

- `resources/js/pages/input-data/TroubleReport.vue`
- `tests/js/TroubleReport.test.js`

Tidak termasuk dalam pekerjaan ini:

- perubahan backend atau database;
- perubahan formula reliability;
- perubahan import Excel atau Redis queue;
- perubahan route, permission, atau autentikasi;
- penambahan atau penghapusan kolom report;
- perubahan isi data yang ditampilkan.

## Strategi Pengujian

Implementasi dilakukan secara test-first:

1. Tambahkan pengujian presentasi untuk memastikan label `SUBSYSTEM` tetap ada sebagai teks biasa dan elemen report tidak menggunakan gradient berwarna.
2. Jalankan pengujian untuk memastikan perubahan test gagal sebelum implementasi.
3. Terapkan perubahan visual pada komponen report.
4. Jalankan kembali pengujian komponen.
5. Jalankan build frontend untuk memastikan template dan styling valid.

## Kriteria Penerimaan

- `SUBSYSTEM` terlihat sebagai teks biasa tanpa fill biru dan tanpa bentuk badge.
- Tidak ada gradient berwarna pada tiga bagian report.
- Tidak ada header tabel ungu, jingga, atau biru penuh.
- Semua tabel memakai satu gaya netral yang konsisten.
- Radius permukaan utama tidak lebih besar dari gaya `rounded-md`.
- Tidak ada garis aksen dekoratif; garis abu-abu struktural tetap boleh digunakan.
- Ikon fungsional tetap tersedia, tetapi tidak ada logo subsystem.
- Empty state sparepart tampil netral.
- Semua data, tombol, modal, dan alur report tetap bekerja seperti sebelumnya.
- Pengujian komponen report dan build frontend berhasil.
