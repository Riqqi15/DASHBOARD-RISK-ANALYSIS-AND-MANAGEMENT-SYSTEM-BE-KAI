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
- Baseline global wilayah tidak ditampilkan pada UI.
- Beri keterangan singkat bahwa tanggal pemasangan adalah informasi aset dan tidak mengubah rumus keandalan Excel, tanpa menampilkan nilai baseline global.

## Alur Data

1. Import Excel atau input manual menyimpan tanggal ke `assets.tanggal_pemasangan`.
2. Dashboard membaca tanggal tersebut untuk tampilan subsystem.
3. `ReliabilityParityService` tetap memprioritaskan `reliability_excel_snapshots.baseline_date` sebagai baseline perhitungan.
4. Edit tanggal subsystem memperbarui informasi aset tanpa mengubah baseline global.
5. Baseline global hanya digunakan di backend dan tidak dikirim sebagai informasi tanggal pada kartu subsystem.

## Di Luar Scope

- Simulasi perhitungan berdasarkan tanggal subsystem.
- Pilihan mode rumus global/per-subsystem.
- Perubahan formula Excel atau formula Reliability/Availability web.
- Tanggal berbeda untuk setiap unit fisik dalam satu subsystem.

## Verifikasi

- Import dan edit menyimpan tanggal pemasangan subsystem.
- Dashboard menampilkan tanggal sesuai wilayah dan subsystem.
- Subsystem tanpa tanggal menampilkan `Belum tercatat`.
- Dashboard tidak menampilkan nilai baseline global wilayah.
- Nilai Reliability/Availability sebelum dan sesudah edit tanggal subsystem tetap sama.
- Hak akses Daop/Divre tetap membatasi data wilayah masing-masing.
