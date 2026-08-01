# Executive Overview Area Header Redesign

## Tujuan

Merapikan blok pemilih Area Lintas dan judul pada Executive Overview agar memiliki identitas visual KAI yang lebih modern, tetap mudah dipindai, dan responsif tanpa mengubah data maupun perilaku filter wilayah.

## Ruang Lingkup

- Mengubah tampilan `AreaSelectorBanner.vue` saja.
- Mempertahankan judul `Area Lintas` dan `Dashboard Risk Analysis and Management System`.
- Mempertahankan pilihan Nasional, DAOP, dan DIVRE beserta request Inertia yang sudah berjalan.
- Mempertahankan aturan bahwa pemilih area hanya tampil untuk pengguna Pusat.
- Tidak mengubah kartu ringkasan, grafik, tabel, endpoint backend, atau struktur data.

## Arah Visual

- Gaya korporat modern dengan warna navy KAI sebagai warna utama dan oranye sebagai aksen status aktif.
- Latar panel memakai biru sangat muda dengan detail visual yang halus, bukan border hitam tebal seperti referensi Excel.
- Header area memiliki label kontekstual dan judul yang kuat tetapi tetap proporsional dengan dashboard.
- Tombol wilayah berbentuk chip konsisten. Area aktif memakai warna navy, teks putih, dan aksen/ring oranye; area tidak aktif memakai permukaan putih dengan border lembut.
- Judul dashboard ditempatkan pada bagian bawah panel dengan pemisah visual yang ringan.

## Responsivitas dan Aksesibilitas

- Chip wilayah membungkus otomatis pada layar sempit dan tidak menyebabkan overflow horizontal halaman.
- Tombol memiliki tinggi sentuh yang memadai, state hover, focus-visible, dan atribut `aria-pressed`.
- Area aktif tidak dibedakan oleh warna saja; tersedia ikon atau indikator teks yang terlihat.
- Animasi dibatasi pada transisi warna, border, dan elevasi singkat.

## Perilaku Data

- Klik Nasional menghapus parameter area.
- Klik DAOP atau DIVRE mengirim kode area yang sama seperti implementasi saat ini.
- Navigasi tetap memakai `preserveScroll`, mengganti state halaman sesuai respons server, dan tidak memakai data dummy.

## Verifikasi

- Tes komponen memastikan label dan seluruh unit dirender.
- Tes memastikan area aktif memiliki `aria-pressed=true` dan klik mengirim parameter area yang benar.
- Menjalankan tes JavaScript terkait Overview dan production build.
- Memeriksa tampilan desktop dan mobile jika browser pengujian tersedia.

