# Desain Isolasi Data Unit Modul RAMS

## Tujuan

Semua data Modul RAMS harus mengikuti satu unit kerja aktif. Data DAOP atau DIVRE lain tidak boleh muncul, dihitung, diekspor, dipilih dalam formulir, atau dapat diubah melalui manipulasi request.

## Cakupan

Isolasi berlaku pada:

- Master Aset
- Matriks Risiko
- Risk Register
- Inventori Suku Cadang, termasuk stok, transaksi, predictive data, rekonsiliasi, kategori, dan master suku cadang
- Laporan RAMS dan seluruh ekspor
- Trouble Report yang dibuka dari data Modul RAMS

Import tetap memakai unit tujuan batch dan tidak mengubah akun atau unit kerja.

## Konteks Unit Aktif

Backend menjadi sumber kebenaran melalui satu resolver konteks unit.

- Admin Pusat dapat memilih satu unit aktif.
- Pilihan valid disimpan dalam session dan tetap digunakan saat berpindah halaman Modul RAMS.
- Parameter `area` maupun `unit_kerja_id` yang sudah dipakai halaman lama diterima oleh resolver yang sama.
- Jika Admin Pusat belum pernah memilih unit, sistem menggunakan unit aktif pertama dan langsung menampilkannya sebagai konteks aktif.
- Tidak ada mode gabungan nasional pada Modul RAMS.
- Akun DAOP/DIVRE selalu memakai unit yang terikat pada akun. Parameter unit dari request diabaikan atau ditolak.
- Unit nonaktif atau tidak ditemukan tidak boleh menjadi konteks aktif.

Konteks aktif dibagikan ke frontend agar selector dan tautan Modul RAMS konsisten. Pergantian unit membersihkan filter turunan dan kembali ke halaman pertama.

## Pembatasan Query

Setiap query data operasional wajib mendapat ID unit aktif sebelum dijalankan. Tanpa ID unit valid, query mengembalikan data kosong atau request ditolak; query tidak boleh jatuh ke data seluruh unit.

- Master Aset: daftar, statistik, hierarki, serta pilihan kategori dibatasi unit aktif.
- Matriks Risiko: risiko dibatasi melalui aset milik unit aktif.
- Risk Register: daftar register dan pilihan aset dibatasi unit aktif.
- Inventori: stok, histori transaksi, predictive snapshot, rekonsiliasi, kategori, master suku cadang, dan statistik dibatasi unit aktif.
- Laporan: dataset XLSX/PDF dan Reliability Workbook dibatasi unit aktif.
- Trouble Report: aset, gangguan, reliability, dan suku cadang dibatasi unit aktif.

Data referensi yang benar-benar global boleh dipakai hanya jika tidak memiliki kepemilikan unit. Kategori dan suku cadang yang terhubung ke hierarki unit bukan referensi global.

## Pembatasan Perubahan Data

Operasi tambah, ubah, hapus, koreksi stok, dan ekspor memvalidasi kepemilikan unit pada backend.

- Asset atau transaksi dari unit lain menghasilkan 404/403 atau error validasi.
- ID kategori, subsystem, aset, dan suku cadang yang dikirim harus berada dalam unit aktif.
- Akun wilayah tidak dapat mengganti unit melalui payload.
- Redirect setelah perubahan mempertahankan konteks unit aktif.

## Antarmuka

- Selector unit hanya tampil untuk Admin Pusat.
- Unit aktif tetap terlihat pada setiap halaman Modul RAMS.
- Tautan sidebar mempertahankan unit aktif.
- Akun wilayah melihat label unit akunnya tanpa selector.
- Tidak ada pilihan “Semua unit kerja” atau “Nasional” pada Modul RAMS.

## Pengujian

Tes membuat data berbeda untuk DAOP-1, DAOP-2, dan satu DIVRE lalu memeriksa:

1. Setiap halaman hanya mengirim data, statistik, kategori, dan opsi milik unit aktif.
2. Pilihan Admin Pusat bertahan ketika berpindah halaman tanpa parameter unit baru.
3. Mengganti unit mengganti seluruh isi halaman dan tidak menyisakan data unit sebelumnya.
4. Akun wilayah tidak dapat membaca atau mengubah data unit lain.
5. Manipulasi ID aset, kategori, suku cadang, dan transaksi lintas unit ditolak.
6. XLSX/PDF tidak memuat baris dari unit lain.
7. Tes frontend memastikan selector mengirim konteks unit dan filter turunan dibersihkan.

## Di Luar Cakupan

- Mengubah rumus Excel, Reliability, Availability, atau reorder stock.
- Mengubah struktur akun dan unit kerja.
- Membuat mode agregasi nasional.
- Mengubah data import yang sudah tersimpan selain memastikan pembacaannya terisolasi per unit.
