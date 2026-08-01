# Remove Frontend Dummy Data Implementation Plan

**Goal:** Menghubungkan halaman RAMS Vue/Inertia ke data MySQL dari backend yang sudah tersedia, lalu menghapus seluruh sumber data dummy JavaScript.

## Tasks

1. Ubah pemilih area agar memuat ulang halaman melalui Inertia dengan parameter `area` dan daftar unit dari backend.
2. Ubah Dashboard, Overview, Risk Matrix, Inventory, dan Reorder Stock agar memakai props server.
3. Ubah Trouble Report agar membaca data server dan menyimpan log kegagalan melalui endpoint backend; hapus simulasi upload/input lokal.
4. Hapus repository dummy dan kode dummy lain setelah tidak memiliki pemakai.
5. Jalankan pencarian dummy tersisa, test JavaScript/PHP, build Vite, pemeriksaan route, formatting, dan diff.

**Constraint:** Jangan membuat commit.
