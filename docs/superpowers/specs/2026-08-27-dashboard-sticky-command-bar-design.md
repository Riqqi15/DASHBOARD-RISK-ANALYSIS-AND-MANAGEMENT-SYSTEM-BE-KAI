# Dashboard Sticky Command Bar

## Tujuan

Rapikan area atas dashboard agar tampak seperti antarmuka operasional. Pengguna harus tetap dapat melihat dan mengganti wilayah saat menggulir halaman.

## Ruang Lingkup

Perubahan hanya mencakup tampilan frontend pada area atas dashboard. Data, route, perhitungan jumlah gangguan, hak akses, dan mekanisme pergantian wilayah tetap sama.

## Susunan Tampilan

Ganti banner wilayah, hero dashboard, dan kartu rekap gangguan dengan satu command bar putih yang ringkas. Command bar berisi:

- judul `Dashboard Persinyalan`;
- nama wilayah aktif sebagai teks pendamping;
- jumlah gangguan dengan format `<jumlah> gangguan tercatat`;
- dropdown wilayah yang memakai daftar dan perilaku saat ini.

Command bar tidak memakai badge, ikon dekoratif, label kapsul, latar warna khusus, atau bayangan besar. Gunakan garis bawah tipis untuk memisahkannya dari isi dashboard.

## Perilaku Sticky

Command bar menempel di bawah header aplikasi selama pengguna menggulir halaman. Nilai `top` mengikuti tinggi header utama agar kedua elemen tidak bertumpuk. Gunakan lapisan dan latar putih yang cukup untuk menutup konten di belakangnya.

Pergantian wilayah tetap memakai alur Inertia yang sudah ada. Command bar hanya memindahkan kontrol yang sama ke susunan baru.

## Tampilan Responsif

Pada layar desktop, judul berada di kiri. Jumlah gangguan dan dropdown wilayah berada di kanan dalam satu baris.

Pada layar kecil, command bar membungkus menjadi dua baris. Judul dan wilayah aktif berada pada baris pertama. Jumlah gangguan dan dropdown memenuhi ruang yang tersedia pada baris kedua.

## Aksesibilitas

- Pertahankan label programatis untuk dropdown wilayah.
- Gunakan struktur heading yang berurutan.
- Pastikan command bar tetap dapat digunakan dengan keyboard.
- Jangan menyampaikan wilayah aktif atau jumlah gangguan hanya melalui warna.

## Pengujian

Pengujian frontend harus memastikan:

- command bar memakai posisi sticky;
- judul, wilayah aktif, dan jumlah gangguan tampil;
- dropdown wilayah tetap tersedia bagi pengguna yang berhak memilih wilayah;
- pergantian wilayah tetap mengirim parameter yang sama;
- badge `KAI RAMS` dan kartu rekap gangguan lama tidak lagi dirender.

Build frontend harus selesai tanpa galat.

## Di Luar Ruang Lingkup

- perubahan query atau kalkulasi dashboard;
- perubahan data yang dikirim controller;
- perubahan hak akses wilayah;
- perubahan kartu kelompok aset atau daftar peralatan.
