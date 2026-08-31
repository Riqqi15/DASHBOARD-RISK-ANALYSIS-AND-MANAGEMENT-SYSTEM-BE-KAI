# Deploy KAI RAMS ke Render

Deployment menggunakan satu Blueprint `render.yaml` dengan empat layanan:

- web Laravel;
- worker antrean impor Excel;
- Redis/Key Value untuk antrean dan cache;
- MySQL 8 dengan persistent disk 10 GB.

Workbook yang menunggu diproses disimpan di object storage S3-compatible. Ini wajib karena web dan worker berjalan pada container terpisah dan tidak berbagi filesystem lokal.

## Nilai rahasia yang diminta Render

Isi nilai berikut pada layar pembuatan Blueprint. Jangan commit nilainya ke repository.

- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `AWS_DEFAULT_REGION`
- `AWS_BUCKET`
- `AWS_ENDPOINT`
- `RAMS_ADMIN_NAME`
- `RAMS_ADMIN_USERNAME`
- `RAMS_ADMIN_EMAIL` (boleh kosong)
- `RAMS_ADMIN_PASSWORD`

Untuk Cloudflare R2, gunakan region `auto`, endpoint bucket S3 dari dashboard R2, dan biarkan path-style `false`. Bucket harus sudah dibuat sebelum deployment pertama.

## Urutan deployment

1. Hubungkan repository GitHub ke Render Blueprint.
2. Pilih branch deployment yang sudah diverifikasi.
3. Periksa estimasi biaya. MySQL dengan disk, Redis, web, dan worker merupakan resource berbayar.
4. Isi seluruh secret yang diminta.
5. Terapkan Blueprint.

Render menjalankan migrasi melalui pre-deploy command sebelum versi web baru aktif. Pada deployment pertama, hook membuat satu akun admin dari nilai `RAMS_ADMIN_*`. Akun demo daerah dinonaktifkan.

## Pemeriksaan setelah deploy

1. Pastikan endpoint `/up` merespons sukses.
2. Masuk dengan akun admin produksi.
3. Jalankan dry run workbook pada DAOP tujuan.
4. Jalankan satu impor normal dan pastikan status berubah dari `Menunggu` menjadi `Selesai`.
5. Periksa dashboard dan angka reliability/availability terhadap workbook sumber.

Jangan menjalankan `migrate:fresh` di produksi karena perintah itu menghapus seluruh data.
