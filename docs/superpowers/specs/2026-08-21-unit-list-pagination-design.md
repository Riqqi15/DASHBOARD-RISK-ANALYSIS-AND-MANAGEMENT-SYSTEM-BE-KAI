# Unit & Akun Pagination Design

## Tujuan

Daftar Unit & Akun menampilkan maksimal 20 unit per halaman agar seluruh Daop dan Divre yang umum digunakan tetap terlihat dalam satu halaman.

## Perilaku

- Data 1–20 tampil pada halaman pertama tanpa kontrol pagination tambahan.
- Halaman kedua baru tersedia mulai data ke-21.
- Pencarian, filter, urutan, dan isi data tidak berubah.

## Implementasi

Ubah ukuran pagination `UnitKerjaController` dari 15 menjadi 20 dan tambahkan regresi test yang memastikan 20 data tetap satu halaman serta data ke-21 masuk halaman kedua.
