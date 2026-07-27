# Desain Autentikasi Berbasis Username

Tanggal: 27 Juli 2026  
Status: Disetujui

## Tujuan

Mengganti identitas login aplikasi RAMS dari alamat email menjadi username. Pengguna hanya dapat masuk dengan pasangan `username` dan `password`.

Kredensial lokal Akun Pusat:

```text
Username: admin.pusat
Password: admin1234
```

`admin.pusat@example.test` tidak dapat digunakan untuk login setelah perubahan diterapkan.

## Keputusan Desain

### Identitas akun

Tabel `users` memiliki kolom `username` yang wajib dan unik. Username dinormalisasi menjadi huruf kecil sebelum validasi dan penyimpanan.

Format username:

- panjang 3–50 karakter;
- hanya huruf kecil, angka, titik, garis bawah, dan tanda hubung;
- tidak mengandung spasi;
- unik tanpa membedakan huruf besar dan kecil.

Kolom `email` tetap tersedia sebagai informasi kontak opsional. Email tidak menjadi kredensial, tidak ditampilkan pada formulir login, dan tidak pernah dicoba sebagai alternatif autentikasi.

### Alur login

Form Inertia mengirim:

```text
username
password
remember
```

Backend menormalisasi username ke huruf kecil, menerapkan rate limit berdasarkan kombinasi username dan alamat IP, lalu menjalankan autentikasi hanya dengan:

```php
[
    'username' => $username,
    'password' => $password,
    'is_active' => true,
]
```

Jika autentikasi gagal, aplikasi menampilkan pesan umum:

```text
Username, kata sandi, atau status akun tidak valid.
```

Pesan tidak membocorkan apakah username terdaftar, password salah, atau akun sedang nonaktif.

### Formulir login

Kolom pertama pada halaman login berubah menjadi:

- label: `Username`;
- tipe input: `text`;
- nama field dan ID: `username`;
- placeholder: `Masukkan username`;
- autocomplete: `username`;
- tanpa validasi format email.

Kolom password dan opsi "Ingat saya di perangkat ini" tetap dipertahankan.

### Seeder dan konfigurasi

Konfigurasi Akun Pusat memakai:

```text
RAMS_ADMIN_USERNAME=admin.pusat
RAMS_ADMIN_PASSWORD=admin1234
```

Seeder mencari atau membuat Akun Pusat berdasarkan `username`, bukan email. Password sederhana tersebut hanya digunakan untuk pengembangan dan demo lokal. Aturan pembuatan atau reset password melalui antarmuka tetap minimal 12 karakter.

Perubahan ini tidak membuat akun Daop atau Divre. Pembuatan akun wilayah dibahas dan diimplementasikan terpisah.

### Manajemen akun dan shared data

Modul manajemen akun wilayah menjadikan username sebagai identitas wajib dan unik. Pencarian akun mencakup nama dan username. Email tetap tersedia pada formulir akun sebagai kontak opsional dan tidak digunakan untuk login.

Data pengguna yang dibagikan ke Inertia menyertakan `username`. Header aplikasi menampilkan username sebagai pengenal akun dan tidak bergantung pada email.

## Migrasi Data

Migrasi harus aman untuk database yang sudah berisi pengguna:

1. Tambahkan kolom `username` dalam keadaan nullable.
2. Isi username akun yang sudah ada secara deterministik.
3. Tetapkan Akun Pusat yang ada menjadi `admin.pusat`.
4. Tangani kemungkinan benturan dengan akhiran ID pengguna.
5. Tambahkan indeks unik dan jadikan kolom wajib.
6. Ubah email menjadi nullable tanpa menjadikannya alternatif login.

Migrasi tidak menghapus pengguna, password hash, role, unit kerja, status aktif, atau audit log yang sudah ada.

## Keamanan

- Login email ditolak karena backend hanya membaca field `username`.
- Username dinormalisasi sebelum autentikasi dan pembuatan throttle key.
- Pesan kesalahan login tetap generik.
- Regenerasi session ID setelah login tetap dipertahankan.
- Logout tetap menghapus session dan membuat ulang token CSRF.
- Password tidak ditulis ke audit log atau shared Inertia data.
- Password lokal `admin1234` tidak boleh dipakai pada deployment produksi.

## Pengujian

Feature test harus membuktikan:

- `admin.pusat` dan `admin1234` berhasil login;
- email lama tidak dapat dipakai login;
- password salah ditolak;
- akun nonaktif ditolak;
- rate limit memakai username;
- username tersimpan unik dan dalam huruf kecil;
- seeder dapat dijalankan ulang tanpa membuat akun Pusat ganda;
- pengelolaan akun wilayah memvalidasi username unik.

Test Vue harus membuktikan:

- label `Username` tampil;
- input tidak lagi bertipe email;
- form mengirim `username`;
- pesan kesalahan username memiliki relasi aksesibilitas yang benar.

Verifikasi akhir menjalankan PHPUnit, Vitest, build Vite, Laravel Pint, migration pada MySQL 8.4 utama, dan login browser lokal.

## Batas Lingkup

Termasuk:

- migration username;
- model, seeder, konfigurasi, autentikasi, dan rate limit;
- form login dan tampilan identitas pengguna;
- penyesuaian manajemen akun;
- test backend dan frontend;
- dokumentasi kredensial lokal.

Tidak termasuk:

- pembuatan akun Daop/Divre;
- lupa password melalui email;
- single sign-on;
- perubahan modul Master Aset;
- push ke remote Git.

## Kriteria Penerimaan

Perubahan diterima jika pengguna dapat membuka halaman login, memasukkan `admin.pusat` dan `admin1234`, lalu masuk ke Dashboard. Form tidak menampilkan label atau validasi alamat email. Percobaan login menggunakan `admin.pusat@example.test` ditolak.
