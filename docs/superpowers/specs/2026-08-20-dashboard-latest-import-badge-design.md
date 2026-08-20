# Dashboard Latest Import Badge Design

## Tujuan

Menandai kelompok aset pada dashboard yang benar-benar berubah pada impor Excel sukses terakhir untuk wilayah terpilih. Penanda menampilkan tanggal impor dan tidak boleh muncul akibat edit manual atau impor ulang yang tidak mengubah data.

## Perilaku

- Backend memilih satu `rams_import_batches` terbaru dengan status `succeeded`, bukan dry run, dan `unit_kerja_id` sesuai wilayah dashboard.
- Backend membaca `rams_import_changes` milik batch tersebut dan memetakan record yang dibuat atau diperbarui ke kelompok asetnya.
- Perubahan yang dapat dipetakan melalui aset, Trouble Report, ringkasan reliability, matriks risiko, risk register, dan data prediktif menandai kelompok aset terkait.
- Kelompok yang tidak tersentuh oleh batch terakhir tidak mendapat penanda.
- Jika batch sukses terakhir tidak menghasilkan perubahan yang dapat dipetakan, tidak ada kartu yang mendapat penanda.
- Tanggal berasal dari `finished_at` batch, dengan fallback ke `created_at`.

## Kontrak Data Dashboard

`summary.latestImport` dikirim ke Vue dalam bentuk:

```json
{
  "date": "2026-08-20",
  "groupCodes": ["PDSE", "PLSE"]
}
```

Nilainya `null` bila belum ada impor sukses. Kode kelompok menggunakan pemetaan yang sama dengan ringkasan reliability: PDSM, PLSM, PDSE, PLSE, dan CDS.

## Tampilan

- Pada header setiap kartu kelompok aset yang terdapat dalam `groupCodes`, tampil badge `Data Terbaru · 20 Agu 2026`.
- Badge ditempatkan di samping kode kelompok agar langsung terlihat tetapi tidak menutupi nama kelompok.
- Padding vertikal header dan area nilai dipangkas untuk mengurangi ruang kosong pada lima kartu, tanpa mengubah ukuran minimum teks atau kontras warna Excel.
- Badge tetap terbaca pada latar terang maupun gelap dengan gaya kapsul netral berkontras tinggi.

## Pengujian

- Feature test membuktikan dashboard hanya mengirim kode kelompok yang berubah pada batch sukses terbaru untuk wilayah terpilih.
- Feature test membuktikan batch wilayah lain dan batch dry run tidak memengaruhi penanda.
- Vue test membuktikan badge dan tanggal hanya muncul pada kartu yang ditandai.
- Vue test memeriksa kelas tata letak ringkas pada kartu.
- Seluruh test dashboard terkait dan build frontend dijalankan sebelum penyelesaian.

