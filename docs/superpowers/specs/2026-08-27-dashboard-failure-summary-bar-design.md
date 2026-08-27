# Dashboard Failure Summary Bar

## Tujuan

Memisahkan ringkasan gangguan dari toolbar wilayah agar jumlah gangguan mudah ditemukan tanpa membuat toolbar sticky terasa padat.

## Tata letak

- Toolbar sticky hanya menampilkan judul `Dashboard Persinyalan`, wilayah aktif, dan pemilih wilayah untuk pengguna pusat.
- Ringkasan gangguan tampil sebagai bar biasa tepat setelah toolbar sticky.
- Bar ringkasan mengikuti alur dokumen dan menghilang saat pengguna menggulir halaman.
- Sisi kiri menampilkan label `Gangguan tercatat` dengan keterangan singkat.
- Sisi kanan menampilkan jumlah, misalnya `13 kejadian`, dengan ukuran lebih besar dan warna merah tua.

## Gaya visual

- Gunakan latar putih dan bentuk persegi panjang sederhana.
- Hindari badge, fill dekoratif, gradien, dan bayangan besar.
- Gunakan garis batas netral tipis untuk memisahkan bar dari latar halaman.
- Pertahankan hierarki tipografi: label sedang, keterangan kecil, dan angka paling menonjol.
- Pada layar kecil, susun label dan jumlah secara vertikal agar teks tidak terpotong.

## Perilaku dan data

- Gunakan nilai `failureCount` yang sudah diterima halaman dashboard.
- Jangan mengubah controller, query, route, model, perhitungan, atau struktur data backend.
- Perubahan wilayah tetap menggunakan navigasi Inertia yang ada.

## Pengujian

- Pastikan toolbar sticky tidak lagi memuat jumlah gangguan.
- Pastikan bar biasa menampilkan label dan jumlah gangguan.
- Pastikan pemilih wilayah dan navigasinya tetap bekerja.
- Jalankan seluruh pengujian JavaScript dan build produksi.
