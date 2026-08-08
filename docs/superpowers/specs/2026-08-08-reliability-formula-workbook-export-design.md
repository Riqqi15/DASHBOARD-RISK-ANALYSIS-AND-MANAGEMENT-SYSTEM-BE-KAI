# Reliability & Availability Formula Workbook Export Design

## Tujuan

Mengubah export laporan **Reliability & Availability** menjadi workbook Excel yang tetap menyerupai workbook RAMS KAI, dapat diaudit, dan dapat menghitung ulang nilai turunan menggunakan formula Excel. Export mengambil data terkini dari database web untuk satu DAOP/DIVRE yang sedang dipilih dan tidak menyertakan data user.

## Ruang Lingkup

- Export hanya berlaku untuk jenis laporan `reliability` dalam format Excel.
- Export mengikuti unit kerja yang aktif pada dashboard atau pilihan area akun Pusat.
- Satu workbook memuat satu sheet ringkasan dan satu sheet untuk setiap subsystem milik unit tersebut.
- PDF dan export laporan lain tetap memakai implementasi yang sudah ada.
- Halaman web tidak didesain ulang; tombol export Reliability yang ada tetap menjadi titik masuk.

## Struktur Workbook

### Sheet `Ringkasan Reliability`

Sheet pertama menampilkan satu baris per subsystem dengan kolom:

1. Subsystem
2. Jumlah Unit
3. Total Operating Hour
4. Total Uptime
5. Total Downtime
6. Jumlah Failure
7. MTTF
8. MTBF
9. Failure Rate λ
10. Reliability
11. Availability
12. Jumlah penggantian sparepart
13. Jumlah tindak vandalisme

Nilai pada sheet ringkasan menggunakan formula yang merujuk ke cell ringkasan pada sheet subsystem masing-masing. Nama subsystem menjadi hyperlink internal menuju sheet terkait bila library export mendukungnya dengan stabil.

### Sheet Subsystem

Setiap subsystem mendapatkan satu sheet dengan nama Excel yang aman dan unik. Nama lebih dari 31 karakter dipendekkan secara deterministik tanpa menghilangkan pemetaan ke nama subsystem lengkap.

Bagian atas mengikuti pola workbook KAI:

- judul subsystem;
- tabel ringkasan berwarna biru;
- tanggal awal pemasangan/baseline;
- informasi formula profile dan tanggal kalkulasi secara ringkas.

Bagian bawah berupa tabel failure report berwarna ungu dengan kolom:

- Lokasi;
- Resor;
- QC;
- Failure Event;
- Penyebab;
- Tindakan;
- Penggantian sparepart;
- Tindak Vandalisme;
- Tahun Kejadian;
- Tanggal Kejadian;
- Tanggal Penanganan;
- Mulai;
- Selesai;
- Tanggal Jam Kejadian;
- Tanggal Jam Penanganan;
- Downtime (jam);
- Konversi ke Menit;
- Interval antar Failure (jam).

Kolom input berasal dari database. Kolom tanggal-jam, downtime, konversi menit, interval failure, dan seluruh metrik ringkasan ditulis sebagai formula Excel.

## Formula

Formula dasar mengikuti workbook DAOP 1:

- `Total Operating Hour = jumlah hari operasi × 24 × Jumlah Unit`
- `Total Uptime = Total Operating Hour − Total Downtime`
- `Total Downtime = SUM(kolom downtime sesuai formula profile)`
- `Jumlah Failure = COUNTA(Failure Event)` dengan variasi profile yang diperlukan
- `MTTF = AVERAGE(Interval antar Failure)`
- `MTBF = IFERROR(Total Uptime / Jumlah Failure, 0)`
- `Failure Rate λ = IFERROR(1 / MTBF, 0)`
- `Reliability = EXP(-Failure Rate λ)`
- `Availability = Total Uptime / Total Operating Hour`
- jumlah penggantian sparepart dan vandalisme menggunakan `COUNTIF("Ya")` atau `COUNTA` sesuai formula profile subsystem.

Formula detail mengikuti workbook:

- tanggal-jam kejadian menggabungkan tanggal kejadian dan waktu mulai;
- tanggal-jam penanganan menggabungkan tanggal penanganan dan waktu selesai;
- downtime memperhitungkan penanganan yang melewati tengah malam;
- konversi menit berasal dari nilai downtime;
- interval pertama dihitung dari baseline subsystem;
- interval berikutnya dihitung dari tanggal-jam kejadian sebelumnya.

## Formula Profile dan Parity

Export tidak memaksakan satu rumus untuk seluruh subsystem. Profile yang tersimpan pada snapshot reliability menentukan:

- baseline interval, termasuk perbedaan 1 Januari 2017 dan 1 Januari 2020;
- sumber downtime berupa menit, jam, atau pecahan hari Excel;
- cara menghitung failure, sparepart, dan vandalisme;
- perilaku MTTF ketika belum ada failure.

Jika snapshot Excel tidak tersedia, export memakai profile standar backend dan memberi catatan `Profile standar — snapshot Excel belum tersedia` pada sheet subsystem. Formula version dicantumkan agar hasil dapat diaudit.

## Penanganan Nilai Kosong dan Error

- Cell input kosong tetap kosong dan tidak diubah menjadi data buatan.
- Formula yang secara matematis belum dapat dihitung dibungkus penanganan error yang menampilkan `Data belum cukup` pada cell presentasi.
- Cell sumber dan formula tetap dapat diaudit; export tidak menyimpan angka ringkasan sebagai nilai statis.
- Nilai tanggal dan angka ditulis sebagai tipe Excel, bukan string yang sudah diformat.
- Reliability dan availability memakai format persen; count memakai bilangan bulat; metrik jam dan rate memakai presisi yang sesuai.

## Sumber Data dan Otorisasi

Controller menggunakan cakupan data yang sama dengan laporan web:

- akun DAOP/DIVRE hanya dapat mengekspor unitnya sendiri;
- akun Pusat wajib memiliki area aktif dan export hanya mencakup area tersebut;
- asset, reliability summary, failure log, dan formula profile dibatasi ke unit aktif;
- tabel `users`, credential, audit login, dan data unit lain tidak pernah masuk workbook.

## Arsitektur

`RamsReportController` tetap menangani endpoint download dan meneruskan report type, user, serta unit aktif ke service export. `RamsReportExportService` tetap menjadi sumber dataset laporan umum, sedangkan builder khusus Reliability menyusun workbook multi-sheet dan formula.

Komponen yang direncanakan:

- `ReliabilityWorkbookExportService`: orkestrasi workbook dan scope unit;
- `ReliabilityFormulaProfileResolver`: menerjemahkan profile snapshot menjadi formula workbook;
- `ReliabilitySheetNameResolver`: menghasilkan nama sheet aman dan unik;
- writer ringkasan dan writer subsystem yang berfokus pada layout masing-masing.

Pemisahan ini menjaga logika formula dapat diuji tanpa bergantung pada styling workbook.

## Perilaku UI

- Tombol `Excel` pada kartu Reliability tetap digunakan.
- Label atau deskripsi tombol diperjelas menjadi export workbook berformula.
- Area yang sedang dipilih diteruskan ke URL export.
- Jika akun Pusat belum memilih area, export ditolak dengan pesan yang menjelaskan bahwa DAOP/DIVRE harus dipilih.

## Pengujian

Pengujian backend wajib memverifikasi:

- akun regional hanya mendapat data unitnya;
- akun Pusat hanya mendapat area aktif;
- workbook memiliki sheet ringkasan dan seluruh sheet subsystem;
- formula penting terdapat pada cell yang benar;
- summary merujuk ke sheet subsystem;
- formula profile 2017/2020 dan variasi count/downtime diterapkan;
- nama sheet panjang dan duplikat tetap valid;
- tidak ada data user di workbook;
- workbook dapat dibuka dan tidak memiliki referensi formula rusak.

Pengujian frontend memverifikasi URL export membawa parameter area aktif. Verifikasi akhir mencakup pembukaan hasil export, pemeriksaan formula dan nilai representatif, serta inspeksi visual terhadap sheet ringkasan dan beberapa jenis sheet subsystem.

## Kriteria Penerimaan

Implementasi diterima ketika pengguna dapat memilih DAOP/DIVRE, mengekspor laporan Reliability, membuka satu workbook dengan seluruh subsystem, melihat layout yang tetap dekat dengan Excel KAI, dan menelusuri formula MTTF, MTBF, failure rate, reliability, availability, downtime, serta interval failure tanpa menemukan data dari unit lain atau data user.
