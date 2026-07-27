# RAMS Dashboard

RAMS (**Risk Analysis and Management System**) Dashboard adalah aplikasi web untuk pemantauan aset, laporan gangguan, matriks risiko, keandalan, dan inventori Prasarana Sintel & LAA PT Kereta Api Indonesia (Persero).

Project menggunakan arsitektur **Laravel + Inertia.js + Vue 3 dalam satu repository**. Laravel menangani route, autentikasi, validasi, aturan bisnis, dan akses data. Vue menangani tampilan dan interaksi halaman melalui Inertia, sehingga dashboard internal tidak memerlukan REST API atau project frontend terpisah.

## Tech Stack

| Bagian | Teknologi |
|---|---|
| Backend | PHP 8.3+, Laravel 13 |
| Server-driven SPA | Inertia.js 3 |
| Frontend | Vue 3 |
| Styling | Tailwind CSS 4 |
| Build tool | Vite 8 |
| State management | Pinia |
| Chart | ApexCharts |
| Icon | Lucide Vue Next |
| Database lokal default | SQLite |

## Prasyarat

Pastikan perangkat sudah memiliki:

- Git
- PHP `>= 8.3`
- Composer 2
- Node.js `^20.19.0` atau `>= 22.12.0`
- npm
- ekstensi PHP PDO SQLite jika menggunakan database default

Periksa instalasi:

```bash
git --version
php --version
composer --version
node --version
npm --version
```

## Instalasi dari Clone

### 1. Clone repository

```bash
git clone https://github.com/Riqqi15/DASHBOARD-RISK-ANALYSIS-AND-MANAGEMENT-SYSTEM-BE-KAI.git rams_be
cd rams_be
```

### 2. Pasang dependency backend

```bash
composer install
```

### 3. Siapkan environment

Command berikut dapat digunakan di PowerShell, Command Prompt, Git Bash, Linux, dan macOS:

```bash
php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate
```

Konfigurasi default di `.env.example` menggunakan SQLite.

### 4. Siapkan database SQLite

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
```

### 5. Pasang dependency frontend

Gunakan `npm ci` agar versi dependency mengikuti `package-lock.json`:

```bash
npm ci
```

### 6. Jalankan project

```bash
composer run dev
```

Command tersebut menjalankan Laravel development server, queue listener, log viewer, dan Vite secara bersamaan.

Buka:

```text
http://127.0.0.1:8000
```

Root aplikasi akan mengarahkan pengguna ke `/login`.

## Menjalankan Server Secara Terpisah

Jika `composer run dev` tidak dapat digunakan, jalankan dua terminal dari folder project.

Terminal 1 — Laravel:

```bash
php artisan serve
```

Terminal 2 — Vite:

```bash
npm run dev
```

Queue worker dapat dijalankan pada terminal tambahan ketika fitur queue sudah digunakan:

```bash
php artisan queue:listen --tries=1
```

## Menggunakan MySQL atau MariaDB

SQLite adalah pilihan default untuk setup lokal yang cepat. Untuk menggunakan MySQL/MariaDB:

1. Buat database, misalnya `rams`.
2. Ubah konfigurasi database di `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rams
DB_USERNAME=root
DB_PASSWORD=
```

3. Jalankan migration:

```bash
php artisan migrate
```

Sesuaikan username dan password dengan instalasi database lokal.

## Build Production

Bangun aset frontend:

```bash
npm run build
```

Untuk deployment production, pastikan:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` sudah terisi
- konfigurasi database dan permission storage sudah benar
- web server mengarah ke folder `public`

Optimalkan cache Laravel setelah konfigurasi production siap:

```bash
php artisan optimize
```

## Command yang Sering Digunakan

| Command | Fungsi |
|---|---|
| `composer run dev` | Menjalankan Laravel, queue, log, dan Vite |
| `php artisan serve` | Menjalankan Laravel development server |
| `npm run dev` | Menjalankan Vite development server |
| `npm run build` | Membuat aset frontend production |
| `php artisan migrate` | Menjalankan migration database |
| `php artisan route:list` | Melihat seluruh route Laravel |
| `php artisan optimize:clear` | Membersihkan cache konfigurasi, route, dan view |

## Struktur Project

```text
rams_be/
├── app/
│   ├── Http/Controllers/       # Controller Laravel
│   └── Models/                 # Model Eloquent
├── database/
│   └── migrations/             # Struktur database
├── resources/
│   ├── css/app.css             # Tailwind CSS dan token tema
│   ├── js/
│   │   ├── app.js              # Bootstrap Inertia + Vue
│   │   ├── pages/              # Halaman Inertia dan UI per domain
│   │   │   ├── auth/
│   │   │   ├── dashboard/
│   │   │   ├── input-data/
│   │   │   └── master-data/
│   │   ├── components/         # Komponen UI yang dipakai ulang
│   │   ├── layouts/            # Layout halaman bersama
│   │   ├── assets/             # Aset frontend
│   │   ├── application/        # Use case dan composable
│   │   ├── domain/             # Model dan aturan domain frontend
│   │   └── infrastructure/     # Repository/data dummy sementara
│   └── views/app.blade.php     # Root template Inertia
├── routes/web.php              # Route halaman dan aksi internal
├── composer.json
├── package.json
└── vite.config.js
```

## Alur Laravel–Inertia–Vue

```text
Browser
  -> routes/web.php
  -> middleware/controller Laravel
  -> Inertia::render('domain/NamaPage', props)
  -> resources/js/pages/domain/NamaPage.vue
  -> komponen dan layout Vue yang digunakan halaman
```

Navigasi menggunakan `Link` atau `router` dari `@inertiajs/vue3`. Form nantinya dikirim melalui Inertia ke web route Laravel, kemudian divalidasi dan diproses di sisi server.

## Halaman yang Tersedia

| URL | Inertia Page | Halaman |
|---|---|---|
| `/login` | `auth/Login` | Login |
| `/dashboard` | `dashboard/Dashboard` | Pemilihan aset/subsistem |
| `/overview` | `dashboard/Overview` | Executive overview |
| `/trouble-report` | `input-data/TroubleReport` | Laporan gangguan |
| `/master-asset` | `master-data/assets/MasterAsset` | Master aset |
| `/risk-matrix` | `dashboard/RiskMatrix` | Matriks risiko |
| `/inventory` | `master-data/inventory/Inventory` | Inventori |
| `/reorder-stock` | `master-data/inventory/ReorderStock` | Rekomendasi reorder stock |

> [!NOTE]
> Kerangka Laravel–Inertia–Vue dan halaman presentasi sudah tersedia. Sebagian data masih menggunakan repository dummy. Controller, autentikasi nyata, policy, model, dan integrasi database RAMS akan ditambahkan secara bertahap.

## Troubleshooting

### `php` atau `composer` tidak dikenali

Pastikan lokasi PHP dan Composer sudah ditambahkan ke `PATH`, lalu tutup dan buka kembali terminal.

### Database SQLite tidak ditemukan

Jalankan:

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
```

### PowerShell memblokir `npm.ps1`

Gunakan executable `.cmd`:

```powershell
npm.cmd ci
npm.cmd run dev
```

### `No application encryption key has been specified`

Jalankan:

```bash
php artisan key:generate
```

### Perubahan `.env` belum terbaca

Jalankan:

```bash
php artisan optimize:clear
```

## Keamanan

- Jangan commit file `.env`.
- Jangan menyimpan password atau credential produksi di repository.
- Otorisasi role/wilayah harus diterapkan di Laravel, bukan hanya dengan menyembunyikan menu Vue.
- Laporkan kerentanan keamanan langsung kepada pengelola repository dan jangan membukanya sebagai issue publik.
