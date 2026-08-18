# Collapsible Sticky Area Selector

## Tujuan

Menjaga pemilih wilayah tetap terlihat saat pengguna menggulir halaman tanpa menutupi informasi utama dashboard.

## Ruang Lingkup

- Mengubah komponen bersama `AreaSelectorBanner.vue`.
- Perilaku berlaku konsisten pada Dashboard, Overview, dan Matriks Risiko.
- Mempertahankan pilihan wilayah, parameter `area`, hak akses pengguna Pusat, serta navigasi Inertia yang ada.
- Tidak mengubah backend, data wilayah, perhitungan RAMS, atau struktur menu.

## Perilaku

- Di bagian atas halaman, panel tampil lengkap seperti sekarang agar konteks dan petunjuk tetap mudah dipahami.
- Ketika bagian atas panel mencapai header aplikasi, panel beralih menjadi bar ringkas yang menempel tepat di bawah header.
- Bar ringkas berisi ikon kecil, label `Wilayah`, wilayah aktif, dan kontrol pilihan wilayah.
- Saat pengguna kembali ke atas, panel kembali ke bentuk lengkap.
- Perubahan bentuk memakai transisi singkat dan tidak memindahkan fokus keyboard.

## Implementasi Teknis

- Menggunakan posisi `sticky` pada komponen dan status ringkas berdasarkan posisi komponen terhadap viewport.
- Status scroll dikelola dengan API browser bawaan tanpa dependensi baru.
- Listener scroll bersifat pasif dan dibersihkan saat komponen dilepas.
- Batas atas mengikuti tinggi header aplikasi, yaitu `76px` pada desktop.
- `z-index` pemilih wilayah berada di bawah header utama dan di atas konten dashboard.

## Arah Visual

- Bentuk lengkap mempertahankan hierarki informasi yang ada.
- Bentuk ringkas memiliki tinggi sekitar `52px`, latar putih, garis tepi tipis, dan bayangan lembut.
- Teks panjang dipotong secara aman pada layar sempit.
- Tidak ada kartu `Sedang melihat` terpisah pada bentuk ringkas agar ruang tetap hemat.
- Animasi dibatasi pada ukuran, jarak, dan opasitas selama sekitar 180–220 ms.

## Responsivitas dan Aksesibilitas

- Pada desktop, label dan pilihan wilayah berada dalam satu baris.
- Pada layar kecil, kontrol tetap satu baris dan wilayah aktif dapat terpotong dengan elipsis.
- Label aksesibel untuk elemen `select` tetap tersedia.
- Fokus keyboard terlihat jelas dan preferensi `prefers-reduced-motion` dihormati.

## Verifikasi

- Memastikan panel lengkap tampil saat halaman berada di atas.
- Memastikan panel mengecil dan tetap terlihat setelah scroll.
- Memastikan panel kembali terbuka ketika pengguna kembali ke atas.
- Memastikan pilihan DAOP/DIVRE tetap mengganti isi halaman.
- Memeriksa Dashboard, Overview, dan Matriks Risiko pada desktop serta viewport kecil.
