# KAI RAMS

KAI RAMS (Risk Analysis and Management System) membantu Kantor Pusat, Daop, dan Divre mencatat kondisi aset, menganalisis risiko, dan menelusuri tindak lanjut. Aplikasi memakai Laravel 13, Inertia.js 3, Vue 3, Tailwind CSS 4, dan MySQL 8.4 LTS dalam satu repository.

Fondasi saat ini menyediakan autentikasi session, dua peran pengguna, pembatasan unit kerja, pengelolaan akun wilayah, dan audit log read-only. View RAMS yang berasal dari prototype tetap tersedia dan akan dihubungkan ke backend per modul.

## Prasyarat

Pasang perangkat berikut:

- PHP sesuai versi pada `composer.json`, beserta ekstensi MySQL;
- Composer;
- Node.js dan NPM;
- Docker Desktop;
- Visual Studio Code.

Pastikan Docker Desktop sudah berjalan sebelum menyalakan database.

## Setup pertama

Jalankan perintah berikut dari terminal PowerShell VS Code:

```powershell
Copy-Item .env.example .env
composer install
npm install
php artisan key:generate
docker compose --profile test up -d --wait
php artisan migrate:fresh --seed
```

Sebelum menjalankan seeder, isi tiga variabel berikut di `.env`:

```dotenv
RAMS_ADMIN_NAME="Admin Pusat"
RAMS_ADMIN_EMAIL=admin@example.test
RAMS_ADMIN_PASSWORD=ganti-dengan-password-yang-kuat
```

Simpan kredensial lokal hanya di `.env`. Git mengabaikan file tersebut.

## Menjalankan aplikasi

Nyalakan database lalu jalankan server development:

```powershell
docker compose up -d --wait
composer run dev
```

Buka URL yang ditampilkan Laravel, lalu masuk memakai akun Pusat dari `.env`. `composer run dev` menjalankan server Laravel, queue worker, log viewer, dan Vite dalam satu terminal.

## Database

Compose menyediakan dua instance MySQL 8.4 LTS:

| Kegunaan | Service | Port host | Database |
| --- | --- | ---: | --- |
| Development | `mysql` | 3306 | `rams` |
| Automated test | `mysql-test` | 3307 | `rams_testing` |

Nyalakan keduanya dengan:

```powershell
docker compose --profile test up -d --wait
```

File Excel hanya berfungsi sebagai sumber import awal. MySQL tetap menjadi sumber data aplikasi dan pengujian.

## Menjalankan pengujian

Pastikan profile test aktif, lalu jalankan seluruh pemeriksaan:

```powershell
docker compose --profile test up -d --wait
php artisan migrate:fresh --env=testing
php artisan test
npm run test:js
npm run build
php vendor/bin/pint --test
```

PHPUnit memakai MySQL pada port 3307. Vitest menguji interaksi Vue yang kritis, termasuk login dan flash message.

## Aturan akun dan akses

- Aplikasi hanya memiliki peran `pusat` dan `unit`.
- Akun Pusat tidak terikat pada unit kerja dan dapat membuka halaman administrasi.
- Akun Unit wajib terikat pada satu Daop atau Divre.
- Middleware server menolak akun Unit dari halaman Pusat. Penyembunyian menu di Vue hanya membantu tampilan.
- Akun nonaktif gagal login. Session yang menjadi nonaktif akan dicabut pada request berikutnya.
- Tidak ada registrasi publik atau pembuatan akun Pusat dari antarmuka.
- Perubahan unit dan akun menghasilkan audit log. Audit tidak menyimpan kata sandi, hash, remember token, atau payload session.

## Dokumen proyek

- [Desain fondasi](docs/superpowers/specs/2026-07-27-rams-application-foundation-design.md)
- [Rencana implementasi fondasi](docs/superpowers/plans/2026-07-27-rams-foundation-implementation-plan.md)

Tulis implementation plan modul berikutnya sebelum mengganti repository dummy pada view prototype.
