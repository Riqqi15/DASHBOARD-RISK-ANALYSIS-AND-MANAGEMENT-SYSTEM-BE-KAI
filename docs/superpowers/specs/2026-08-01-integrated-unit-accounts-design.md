# Integrated Unit Accounts Design

## Goal

Menyederhanakan administrasi RAMS dengan menjadikan Unit Kerja sebagai satu-satunya pintu pengelolaan unit dan akun wilayah, serta menyembunyikan Audit Log dari seluruh UI pengguna KAI.

## Scope

- Sidebar Pusat hanya menampilkan `Kategori Aset` dan `Unit Kerja` pada kelompok Administrasi.
- Halaman Unit Kerja memuat akun wilayah untuk setiap unit dan menyediakan aksi tambah, edit, aktif/nonaktif, serta reset password.
- Form akun tetap memakai request, controller, validasi, otorisasi, dan tabel `users` yang ada.
- Semua aksi akun kembali ke halaman Unit Kerja.
- Daftar `/admin/accounts` dan halaman `/admin/audit-logs` tidak lagi memiliki route yang dapat dibuka.
- `AuditLogger`, model, tabel, dan catatan audit tetap aktif sebagai kontrol internal dan tidak menampilkan `old_values`/`new_values` kepada pengguna aplikasi.
- Tidak ada data unit, akun, atau audit yang dihapus.
- Tidak ada commit sampai pengguna meminta.

## Data and authorization

`unit_kerjas` tetap menjadi referensi organisasi dan `users` tetap menjadi identitas login. Relasi satu unit ke banyak akun dipertahankan agar kredensial tidak dibagi antarpegawai. Seluruh endpoint pengelolaan tetap berada di middleware `auth`, `active`, dan `pusat`.

## UI flow

Halaman Unit Kerja menampilkan identitas unit, status unit, akun akses, dan aksi. Unit aktif dapat membuat akun baru. Setiap akun menampilkan nama, username, status, serta aksi edit, reset password, dan ubah status. Form akun dapat tetap berada pada halaman terpisah, tetapi breadcrumb, active menu, dan redirect selalu mengarah ke Unit Kerja sehingga tidak ada modul administrasi akun yang berdiri sendiri.

## Verification

- Feature test memastikan payload Unit Kerja hanya memuat akun role wilayah.
- Feature test memastikan aksi akun kembali ke `/admin/units` dan daftar akun/audit tidak memiliki route.
- JavaScript test memastikan menu Akun Wilayah dan Audit Log tidak tampil.
- Full PHP test, full JavaScript test, Vite build, dan browser smoke test harus lulus.
