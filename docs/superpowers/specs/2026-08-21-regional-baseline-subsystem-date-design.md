# Baseline Wilayah, Tanggal Subsystem, dan Lokasi Kejadian

## Tujuan

Menyesuaikan web dengan struktur workbook dan laporan akhir KAI tanpa mengubah formula Reliability dan Availability secara diam-diam.

## Sumber Acuan

- Sheet `Predictive Data Asset` menyimpan Aset Prasarana Sintel, System, Subsystem, jumlah unit, dan Tanggal Pemasangan tanpa lokasi aset.
- Sheet `Risk Matrix` tidak memiliki lokasi aset.
- Sheet setiap subsystem menyimpan Lokasi dan Resor pada tabel kejadian.
- `Total Operating Hour` pada workbook tetap memakai `Operating Days` global dari sheet Dashboard.

## Keputusan Data

### Lokasi

- Hapus `assets.lokasi` dari schema, model, request, pencarian, payload, form, tabel, dan kartu master aset.
- Hapus ketergantungan Risk Register terhadap `assets.lokasi`.
- Pertahankan `failure_logs.location` dan `failure_logs.resort` sebagai lokasi dan resor kejadian.
- Import dan export Report Kejadian tetap membaca serta menulis Lokasi dan Resor sesuai workbook.
- Sebelum migration menghapus kolom, periksa data non-null. Jika ada, buat salinan terkontrol sebelum penghapusan agar data lama tidak hilang tanpa jejak.

### Tanggal Pemasangan Subsystem

- `assets.tanggal_pemasangan` mewakili tanggal pemasangan subsystem pada satu Daop/Divre.
- Import mengambil tanggal dari `Predictive Data Asset` dan sumber subsystem yang sudah didukung importer.
- Tanggal tampil pada bagian identitas/ringkasan Report Kejadian, bukan pada dashboard dan bukan pada setiap baris kejadian.
- Jika tidak ada tanggal, tampilkan `Belum tercatat`.
- Jika satu subsystem pada wilayah yang sama mempunyai beberapa tanggal unik, tampilkan semua tanggal secara ringkas dan tandai data perlu dirapikan; jangan memilih satu tanggal secara diam-diam.
- Tanggal pemasangan subsystem bersifat informasi dan tidak menjadi baseline formula pada revisi ini.

## Baseline Global Wilayah

- Formula tetap mengikuti workbook:

  `Operating Hours = Operating Days global × 24 × Jumlah Unit`.

- Baseline otomatis dibaca dari tanggal awal equipment pada sheet Dashboard saat import.
- Baseline tidak ditampilkan pada dashboard atau Report Kejadian pengguna.
- Admin Pusat dapat mengoreksi baseline melalui `Unit & Akun -> Edit Unit -> Pengaturan Perhitungan`.
- Nama field UI menjadi `Baseline Operating Days sesuai Excel`.
- Form menjelaskan bahwa nilai ini memengaruhi Operating Hours, MTTF, MTBF, Failure Rate, Reliability, dan Availability.
- Perubahan baseline meminta konfirmasi dan alasan.
- Audit menyimpan unit, nilai lama, nilai baru, alasan, pengguna, dan waktu perubahan.

### Urutan Sumber Baseline

1. Override aktif dari Admin Pusat.
2. Baseline hasil import Excel terbaru untuk wilayah.
3. Jika keduanya tidak ada, status perhitungan menjadi `Belum dapat dihitung`.

Tanggal pemasangan subsystem tidak digunakan sebagai fallback baseline.

## Perhitungan Ulang

- Perubahan override baseline memicu perhitungan ulang seluruh Reliability Summary pada wilayah tersebut.
- Hasil export menggunakan baseline efektif yang sama dengan hasil web.
- Snapshot import asli tetap disimpan agar nilai Excel dan koreksi manual dapat diaudit.
- Jika override berbeda dari workbook terakhir, UI admin menampilkan status `Koreksi manual aktif`.

## Hak Akses

- Hanya Admin Pusat dapat melihat dan mengubah pengaturan baseline.
- Akun Daop/Divre hanya melihat hasil perhitungan dan tanggal pemasangan subsystem pada report wilayahnya.
- Data kejadian, termasuk Lokasi dan Resor, tetap dibatasi berdasarkan Daop/Divre.

## Penanganan Kesalahan

- Baseline kosong atau tanggal tidak valid tidak boleh diganti otomatis dengan tanggal subsystem.
- Perhitungan gagal dengan status yang dapat dibaca pengguna, tanpa menghasilkan angka palsu.
- Override tidak dapat disimpan tanpa alasan perubahan.
- Kegagalan perhitungan ulang tidak menghapus snapshot atau hasil sebelumnya.

## Verifikasi

- Master Aset dan Kategori Aset tidak lagi menampilkan atau menerima lokasi aset.
- Risk Matrix tidak bergantung pada lokasi master aset.
- Report Kejadian tetap menampilkan Lokasi dan Resor pada setiap kejadian.
- Report Kejadian menampilkan tanggal pemasangan subsystem sekali pada bagian ringkasan.
- Dashboard dan Report Kejadian tidak menampilkan baseline global.
- Admin Pusat dapat mengubah baseline dengan alasan dan audit.
- Akun wilayah tidak dapat mengakses pengaturan baseline.
- Import tanpa override menghasilkan angka yang sama dengan workbook.
- Override mengubah hasil web dan export secara konsisten, lalu dapat dikembalikan ke baseline import.
- Tanggal subsystem tidak mengubah hasil Reliability atau Availability.

## Di Luar Scope

- Mengubah Operating Hours agar memakai tanggal pemasangan subsystem.
- Menyimpan lokasi pemasangan per stasiun.
- Menyimpan tanggal pemasangan berbeda untuk setiap unit fisik.
- Menampilkan dua versi formula sekaligus kepada pengguna.

## Hubungan dengan Spesifikasi Sebelumnya

Dokumen ini menggantikan keputusan tampilan baseline pada `2026-08-18-subsystem-installation-date-design.md`: baseline tetap tidak terlihat pada dashboard/report, tetapi tersedia sebagai pengaturan terbatas untuk Admin Pusat.
