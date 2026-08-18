# Tanggal Pemasangan Subsystem

## Tujuan

Menjaga hasil Reliability dan Availability tetap sama dengan rumus workbook KAI, sambil menyimpan dan menampilkan tanggal pemasangan setiap subsystem pada masing-masing Daop/Divre.

## Keputusan

- Perhitungan Operating Hours, Reliability, dan Availability tetap memakai tanggal awal operasi wilayah atau baseline global hasil import Excel.
- `assets.tanggal_pemasangan` tetap menjadi tanggal pemasangan subsystem pada wilayah terkait.
- Tanggal pemasangan subsystem tidak mengganti baseline global dan tidak memicu perubahan rumus.
- Data yang sudah diimpor dari `Predictive Data Asset` tetap digunakan; pengguna dapat memperbaruinya melalui Master Aset.

## Tampilan

- Tanggal pemasangan ditampilkan pada rincian subsystem/aset wilayah.
- Jika tanggal belum tersedia, tampilkan `Belum tercatat`, bukan tanggal buatan.
- Beri keterangan singkat bahwa tanggal ini adalah informasi aset dan perhitungan keandalan masih mengikuti baseline wilayah dari Excel.

## Alur Data

1. Import Excel atau input manual menyimpan tanggal ke `assets.tanggal_pemasangan`.
2. Dashboard membaca tanggal tersebut untuk tampilan subsystem.
3. `ReliabilityParityService` tetap memprioritaskan `reliability_excel_snapshots.baseline_date` sebagai baseline perhitungan.
4. Edit tanggal subsystem memperbarui informasi aset tanpa mengubah baseline global.

## Di Luar Scope

- Simulasi perhitungan berdasarkan tanggal subsystem.
- Pilihan mode rumus global/per-subsystem.
- Perubahan formula Excel atau formula Reliability/Availability web.
- Tanggal berbeda untuk setiap unit fisik dalam satu subsystem.

## Verifikasi

- Import dan edit menyimpan tanggal pemasangan subsystem.
- Dashboard menampilkan tanggal sesuai wilayah dan subsystem.
- Subsystem tanpa tanggal menampilkan `Belum tercatat`.
- Nilai Reliability/Availability sebelum dan sesudah edit tanggal subsystem tetap sama.
- Hak akses Daop/Divre tetap membatasi data wilayah masing-masing.
