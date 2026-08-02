# Desain Hierarki Kategori Aset pada Dashboard

Tanggal: 2 Agustus 2026
Status: Disetujui untuk perencanaan implementasi

## 1. Latar belakang

Dashboard saat ini membentuk kartu `Kategori Aset → System → Subsystem` dari prop `assets`. Cara ini hanya menampilkan cabang yang sudah memiliki aset. Kategori aktif yang baru dibuat melalui halaman Administrasi Kategori Aset tidak muncul sampai sebuah aset terhubung ke subsystem di bawahnya.

Database lokal menunjukkan tiga contoh kondisi yang hilang dari dashboard:

- kategori `1234` belum memiliki system;
- system `123` belum memiliki subsystem;
- subsystem `12` belum memiliki aset.

Dashboard harus mencerminkan hierarki global yang dikelola Admin Pusat. Hitungan aset dan unit tetap mengikuti area aktif serta hak akses pengguna.

## 2. Tujuan

1. Menampilkan seluruh hierarki kategori aktif pada dashboard.
2. Menampilkan kategori, system, dan subsystem yang masih memiliki nol turunan atau nol aset.
3. Menghitung aset dan unit berdasarkan area aktif dan hak akses pengguna.
4. Mempertahankan desain kartu dashboard yang sekarang.
5. Mempertahankan navigasi dari subsystem ke Trouble Report.

## 3. Di luar cakupan

- Menampilkan daftar nama aset langsung pada dashboard.
- Mendesain ulang kartu, warna, tipografi, atau layout dashboard.
- Mengubah CRUD kategori aset.
- Menampilkan kategori nonaktif atau yang sudah dihapus.
- Mengubah perhitungan ringkasan risiko, reliability, atau inventory.

## 4. Keputusan arsitektur

Laravel menjadi pemilik struktur hierarki dashboard. `RamsDashboardQuery::dashboard()` mengirim prop baru bernama `asset_hierarchy`. Vue merender prop tersebut tanpa membentuk ulang hierarki dari prop `assets`.

Pendekatan ini dipilih karena backend sudah menentukan hak akses dan area aktif. Backend juga dapat menghitung agregat dengan aturan yang sama untuk setiap tingkat. Frontend hanya menangani presentasi.

Prop `assets` tetap dipertahankan dalam perubahan ini untuk menjaga kontrak halaman yang sudah ada. Dashboard berhenti memakai prop tersebut sebagai sumber hierarki.

## 5. Kontrak data

Setiap node memakai ID database sebagai identitas. Nama hanya dipakai sebagai label.

```json
[
  {
    "id": 1,
    "name": "1. PERALATAN DALAM SINYAL ELEKTRIK",
    "assetCount": 5,
    "unitCount": 59,
    "systems": [
      {
        "id": 1,
        "name": "INTERLOCKING ELEKTRIK",
        "assetCount": 5,
        "unitCount": 59,
        "subsystems": [
          {
            "id": 1,
            "name": "INTERLOCKING ELEKTRIK",
            "assetCount": 5,
            "unitCount": 59
          },
          {
            "id": 30,
            "name": "12",
            "assetCount": 0,
            "unitCount": 0
          }
        ]
      }
    ]
  }
]
```

Kategori tanpa system memiliki array `systems` kosong. System tanpa subsystem memiliki array `subsystems` kosong. Semua hitungan memakai bilangan bulat.

## 6. Query backend

Query memuat `AssetGroup`, `AssetSystem`, dan `AssetSubsystem` aktif. Global scope Eloquent mengabaikan data yang sudah dihapus secara lunak. Setiap tingkat diurutkan berdasarkan `sort_order`, lalu `name`.

Backend menghitung `assetCount` dan `unitCount` pada tingkat subsystem. Query aset menerapkan aturan berikut:

- akun Pusat tanpa area menghitung seluruh aset;
- akun Pusat dengan area menghitung aset pada area tersebut;
- akun Unit selalu menghitung aset pada unit miliknya;
- aset yang dihapus secara lunak tidak dihitung.

Backend menjumlahkan hitungan subsystem untuk menghasilkan hitungan system dan kategori. Query memakai eager loading dan agregasi agar jumlah query tidak bertambah untuk setiap node.

## 7. Tampilan dashboard

Dashboard mempertahankan susunan dua kolom, header berwarna, kartu system, dan tombol subsystem yang sekarang. Perubahan hanya mengganti sumber data dan menambahkan kondisi kosong pada tiap tingkat.

- Kategori tanpa system menampilkan kartu kategori, label `0 system`, dan pesan `Belum ada system aktif`.
- System tanpa subsystem menampilkan kartu system, label `0 subsystem`, dan pesan `Belum ada subsystem aktif`.
- Subsystem tanpa aset tetap tampil sebagai tombol dengan label `0 aset - 0 unit`.
- Jika database tidak memiliki kategori aktif, halaman menampilkan empty state tingkat halaman.

Dashboard memakai ID untuk `key` Vue. Nama yang sama pada parent berbeda tidak menyebabkan node tercampur.

## 8. Navigasi Trouble Report

Klik subsystem tetap membuka `/trouble-report` dan mempertahankan parameter area aktif. Dashboard menyertakan ID subsystem agar backend memilih relasi yang tepat meskipun dua system memiliki nama subsystem yang sama. Backend dapat mempertahankan parameter nama selama masa transisi jika halaman lain masih memakainya.

Trouble Report menampilkan empty state ketika subsystem belum memiliki aset. Kondisi ini sah dan tidak menghasilkan error database.

## 9. Error dan kondisi kosong

- Hierarki kosong menghasilkan empty state, bukan exception.
- Node tanpa turunan tetap menghasilkan array kosong.
- Hitungan kosong menghasilkan `0`, bukan `null`.
- Area tidak valid tetap ditolak oleh `RamsAreaRequest`.
- Hak akses unit tetap diterapkan pada query backend; parameter browser tidak dapat membuka data unit lain.
- Kategori nonaktif tidak muncul walaupun masih mempunyai data lama.

## 10. Pengujian

### Backend

- Dashboard mengirim seluruh kategori, system, dan subsystem aktif.
- Kategori tanpa system, system tanpa subsystem, dan subsystem tanpa aset tetap muncul.
- Kategori nonaktif dan data soft-deleted tidak muncul.
- Hitungan aset dan unit benar untuk tampilan nasional, area pilihan Pusat, dan akun Unit.
- Struktur menggunakan ID dan urutan `sort_order`, lalu nama.
- Query dashboard tidak menimbulkan pola N+1.

### Frontend

- Dashboard merender `asset_hierarchy` dari backend.
- Kartu kosong dan tombol subsystem nol aset tampil dengan teks yang benar.
- Dashboard tidak membentuk hierarki dari prop `assets`.
- Klik subsystem mengirim identitas subsystem dan area aktif ke Trouble Report.
- Layout serta kelas visual utama tetap sama.

### Verifikasi akhir

- PHPUnit terkait dashboard lulus.
- Vitest dashboard lulus.
- Build Vite lulus.
- Pemeriksaan browser memastikan kategori `1234`, system `123`, dan subsystem `12` muncul sesuai parent masing-masing.
- Pergantian area memperbarui hitungan tanpa menghilangkan struktur kategori aktif.

## 11. Kriteria selesai

Pekerjaan selesai ketika dashboard menampilkan seluruh hierarki kategori aktif seperti halaman Administrasi Kategori Aset, tetap memakai tampilan kartu yang ada, menghitung aset serta unit sesuai area, dan menangani node kosong tanpa error.
