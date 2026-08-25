# KAI RAMS

KAI RAMS (Risk Analysis and Management System) membantu Kantor Pusat, Daop, dan Divre mencatat kondisi aset, menganalisis risiko, dan menelusuri tindak lanjut. Aplikasi memakai Laravel 13, Inertia.js 3, Vue 3, Tailwind CSS 4, dan MySQL 8.4 LTS dalam satu repository.

Fondasi saat ini menyediakan autentikasi session, dua peran pengguna, pembatasan unit kerja, pengelolaan akun wilayah, dan audit log read-only. Sebagian view RAMS masih memakai repository dummy dan akan dihubungkan ke backend per modul.

## Prasyarat

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
npm ci
php artisan key:generate
docker compose --profile test up -d --wait
php artisan migrate:fresh --seed
```

Sebelum menjalankan seeder, isi variabel berikut di `.env`:

```dotenv
RAMS_ADMIN_NAME="Admin Pusat"
RAMS_ADMIN_USERNAME=admin.pusat
RAMS_ADMIN_EMAIL=
RAMS_ADMIN_PASSWORD=admin1234
```

Simpan kredensial lokal hanya di `.env`. Git mengabaikan file tersebut.

### Akun demo Daop

Seeder lokal membuat akun `daop1` sampai `daop9`. Setiap akun terikat pada Daop dengan nomor yang sama dan memakai password lokal `daop1234`.

```text
daop1 / daop1234
daop2 / daop1234
...
daop9 / daop1234
```

Akun ini hanya dibuat ketika `APP_ENV` adalah `local`, `testing`, atau `uat` dan `RAMS_SEED_DEMO_ACCOUNTS=true`. Jangan aktifkan kredensial demo pada production.

## Menjalankan aplikasi

Nyalakan database dan server development:

```powershell
docker compose up -d --wait
composer run dev
```

Buka `http://127.0.0.1:8000`, lalu masuk memakai akun Pusat dari `.env`. `composer run dev` menjalankan server Laravel, queue worker, log viewer, dan Vite dalam satu terminal.

Jika ingin menjalankan server secara terpisah, buka dua terminal VS Code:

```powershell
# Terminal 1
php artisan serve

# Terminal 2
npm run dev
```

## Database

| Kegunaan | Service | Port host | Database |
| --- | --- | ---: | --- |
| Development | `mysql` | 3306 | `rams` |
| Automated test | `mysql-test` | 3307 | `rams_testing` |

Nyalakan kedua instance MySQL 8.4 LTS dengan:

```powershell
docker compose --profile test up -d --wait
```

File Excel hanya berfungsi sebagai sumber import awal. MySQL tetap menjadi sumber data aplikasi dan pengujian.

## Import Master Aset dari Excel

Pastikan migration sudah dijalankan dan kode unit kerja tujuan tersedia. Command membaca sheet `Predictive Data Asset`; file Excel tetap berada di luar repository dan tidak diubah oleh proses import.

```powershell
php artisan migrate
php artisan rams:import-master-assets "D:\lokasi\workbook.xlsm" --unit=DAOP-1
```

Kode unit mengikuti referensi aplikasi, misalnya `DAOP-1`, `DAOP-4`, `DAOP-8`, `DIVRE-III`, atau `DIVRE-IV`. Import pertama mengambil `nama_aset` dari kolom `Subsystem`. Import ulang memperbarui data sumber seperti jumlah dan tanggal pemasangan tanpa menimpa nama, lokasi, atau status yang sudah disunting pengguna.

Command menampilkan jumlah data yang dibuat, diperbarui, dan dilewati. Seluruh import memakai transaksi database: bila sheet, header, atau tanggal tidak valid, tidak ada perubahan parsial yang disimpan. Password proteksi sheet Excel tidak perlu dimasukkan karena proses hanya membaca nilai workbook.

## Menjalankan pengujian

```powershell
docker compose --profile test up -d --wait
php artisan migrate:fresh --env=testing
php artisan test
npm run test:js
npm run build
php vendor/bin/pint --test
```

PHPUnit memakai MySQL pada port 3307. Vitest menguji interaksi Vue yang kritis.

## Lingkungan UAT lokal

UAT memakai layanan terpisah agar data development tidak tercampur:

| Layanan | Alamat |
| --- | --- |
| Web UAT | `http://127.0.0.1:8100` |
| MySQL UAT | `127.0.0.1:3309` / database `rams_uat` |
| Redis UAT | `127.0.0.1:6380` |
| phpMyAdmin UAT | `http://127.0.0.1:8081` |

Salin `.env.uat.example` menjadi `.env.uat`, lalu ganti seluruh nilai
`change-this-before-setup`. File `.env.uat` diabaikan Git dan tidak boleh
dibagikan. Setup pertama menjalankan build, migrasi, seeder akun, server, dan
queue worker:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/uat.ps1 setup
```

Perintah operasional berikut tidak menghapus database UAT:

```powershell
# Menyalakan kembali layanan UAT
powershell -ExecutionPolicy Bypass -File scripts/uat.ps1 start

# Memeriksa migrasi, Redis, server, worker, dan halaman login
powershell -ExecutionPolicy Bypass -File scripts/uat.ps1 check

# Menghentikan proses dan container UAT
powershell -ExecutionPolicy Bypass -File scripts/uat.ps1 stop
```

Seeder UAT membuat akun Pusat dari `RAMS_ADMIN_*` serta akun `daop1` sampai
`daop9` dari `RAMS_DAOP_PASSWORD`. Password UAT wajib berbeda dari development
dan production. Reset database sengaja tidak dimasukkan ke skrip karena bersifat
destruktif; lakukan backup dan minta persetujuan sebelum memakai
`migrate:fresh`.

## Struktur frontend

```text
resources/js/
├── app.js              # Bootstrap Inertia + Vue
├── pages/              # Halaman Inertia per domain
│   ├── auth/
│   ├── dashboard/
│   ├── input-data/
│   ├── master-data/
│   └── Admin/
├── components/         # Komponen UI yang dipakai ulang
├── layouts/            # Layout halaman bersama
├── assets/             # Logo dan aset frontend
├── application/        # Use case dan composable
├── domain/             # Model dan kontrak domain frontend
└── infrastructure/     # Repository/data dummy sementara
```

Laravel merender nama halaman, misalnya `Inertia::render('master-data/assets/MasterAsset')`, dan Vite menyelesaikannya ke `resources/js/pages/master-data/assets/MasterAsset.vue`.

## Aturan akun dan akses

- Aplikasi hanya memiliki peran `pusat` dan `unit`.
- Akun Pusat tidak terikat pada unit kerja dan dapat membuka halaman administrasi.
- Akun Unit wajib terikat pada satu Daop atau Divre.
- Middleware server menolak akun Unit dari halaman Pusat.
- Akun nonaktif gagal login dan session aktifnya dicabut pada request berikutnya.
- Tidak ada registrasi publik atau pembuatan akun Pusat dari antarmuka.
- Perubahan unit dan akun menghasilkan audit log tanpa password, hash, remember token, atau payload session.

## Dokumen proyek

- [Desain fondasi](docs/superpowers/specs/2026-07-27-rams-application-foundation-design.md)
- [Rencana implementasi fondasi](docs/superpowers/plans/2026-07-27-rams-foundation-implementation-plan.md)
- [Rencana penyederhanaan presentasi Vue](docs/superpowers/plans/2026-07-27-simplify-vue-presentation-layer.md)

Tulis implementation plan modul berikutnya sebelum mengganti repository dummy pada view prototype.
