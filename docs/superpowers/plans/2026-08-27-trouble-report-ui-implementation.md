# Trouble Report UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengubah halaman Trouble Report menjadi tampilan laporan operasional yang netral tanpa mengubah logic backend, kontrak data, formula, route, atau perilaku interaktif.

**Architecture:** Seluruh perubahan produksi dibatasi pada template presentasional `TroubleReport.vue`; blok `<script setup>` tidak disentuh. Pengujian komponen Vue mengunci label konteks polos, tiga section report netral, dan ketiadaan gradient supaya perubahan visual tidak menggeser logic aplikasi.

**Tech Stack:** Laravel Inertia, Vue 3, Tailwind CSS, Vitest, Vue Test Utils, Vite

---

## Peta File dan Batas Scope

- Modify: `tests/js/TroubleReport.test.js` — menambahkan kontrak tampilan netral tanpa menguji atau mengubah backend.
- Modify: `resources/js/pages/input-data/TroubleReport.vue` — mengubah class Tailwind dan atribut pengujian pada template saja.
- Do not modify: seluruh file `app/**/*.php`, `routes/**/*.php`, `database/**/*`, `config/**/*`, dan blok `<script setup>` pada komponen report.

### Task 1: Kunci kontrak visual report dengan pengujian gagal

**Files:**
- Modify: `tests/js/TroubleReport.test.js`
- Test: `tests/js/TroubleReport.test.js`

- [ ] **Step 1: Tambahkan pengujian presentasi netral**

Tambahkan test berikut sebagai test pertama di dalam `describe('TroubleReport', () => {`:

```js
  it('renders a neutral operational report without decorative subsystem badges or gradients', () => {
    const wrapper = mountPage()
    const contextLabel = wrapper.get('[data-report-context-label]')
    const reportSections = wrapper.findAll('[data-report-section]')

    expect(contextLabel.text()).toBe('Subsystem')
    expect(contextLabel.classes()).not.toContain('bg-blue-100')
    expect(contextLabel.classes()).not.toContain('rounded-full')
    expect(wrapper.find('[class*="bg-gradient"]').exists()).toBe(false)
    expect(reportSections).toHaveLength(3)

    reportSections.forEach((section) => {
      expect(section.classes()).toContain('rounded-md')
      expect(section.classes()).not.toContain('rounded-xl')
      expect(section.classes()).not.toContain('shadow-sm')
    })
  })
```

- [ ] **Step 2: Jalankan test dan pastikan gagal karena markup lama**

Run:

```powershell
rtk npm run test:js -- tests/js/TroubleReport.test.js
```

Expected: FAIL karena `[data-report-context-label]` dan `[data-report-section]` belum ada atau karena gradient lama masih ditemukan.

### Task 2: Terapkan visual laporan operasional yang netral

**Files:**
- Modify: `resources/js/pages/input-data/TroubleReport.vue:4-284`
- Test: `tests/js/TroubleReport.test.js`

- [ ] **Step 1: Ubah label konteks menjadi teks polos**

Ganti blok label konteks di header dengan markup berikut:

```vue
          <p
            data-report-context-label
            class="mb-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500"
          >
            Subsystem
          </p>
```

Pertahankan `subsystemName`, deskripsi report, event tombol, dan props tombol tanpa perubahan. Pada tombol `Input Manual`, hapus class bayangan dekoratif dan gunakan class berikut:

```vue
          <BaseButton
            :disabled="assets.length === 0"
            variant="primary"
            class="flex items-center gap-2"
            @click="isModalOpen = true"
          >
            <PlusIcon class="h-4 w-4" /> Input Manual
          </BaseButton>
```

- [ ] **Step 2: Ratakan panel identitas dan editor tanggal**

Gunakan class panel identitas berikut tanpa mengubah isi, kondisi, atau event-nya:

```vue
      <section
        class="grid gap-3 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-3"
        aria-label="Identitas subsystem"
      >
```

Gunakan class netral berikut pada editor, input, tombol simpan, dan tooltip:

```vue
class="pointer-events-none invisible absolute right-0 top-full z-20 mt-2 w-72 max-w-[calc(100vw-3rem)] rounded-md bg-slate-900 px-3 py-2.5 text-left text-xs font-normal leading-5 text-white opacity-0 shadow-lg transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100"
```

```vue
class="mt-3 space-y-3 rounded-md border border-slate-200 bg-slate-50 p-3"
```

```vue
class="h-10 min-w-44 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-100"
```

```vue
class="h-10 rounded-md bg-[#171650] px-4 text-sm font-semibold text-white hover:bg-[#24236a]"
```

- [ ] **Step 3: Ubah Ringkasan Keandalan menjadi tabel netral**

Gunakan container dan header berikut. Atribut `data-report-section="reliability"` adalah kontrak pengujian; isi tabel dan semua binding tetap sama.

```vue
      <section
        data-report-section="reliability"
        class="overflow-hidden rounded-md border border-slate-200 bg-white"
      >
        <div class="border-b border-slate-200 bg-white px-4 py-3">
          <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900">
            <ActivityIcon class="h-4 w-4 text-slate-500" aria-hidden="true" />
            Ringkasan Keandalan (Reliability Data)
          </h3>
        </div>
```

Gunakan class tabel berikut:

```vue
          <table class="w-full text-left text-xs text-slate-700">
            <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
```

Gunakan class row berikut:

```vue
              <tr v-if="summaryData" class="transition-colors hover:bg-slate-50">
```

Ganti cell berwarna dan badge reliability/availability dengan cell teks netral berikut:

```vue
                <td class="p-3 text-center tabular-nums text-slate-900">{{ formatNumber(summaryData.total_uptime) }}</td>
                <td class="p-3 text-center tabular-nums text-slate-900">{{ formatNumber(summaryData.total_downtime) }}</td>
                <td class="p-3 text-center font-semibold tabular-nums text-slate-900">{{ formatNumber(summaryData.jumlah_failure) }}</td>
                <td class="p-3 text-center tabular-nums">{{ formatNumber(summaryData.mttf) }}</td>
                <td class="p-3 text-center font-medium tabular-nums">{{ formatNumber(summaryData.mtbf) }}</td>
                <td class="p-3 text-center tabular-nums">{{ formatDecimal(summaryData.failure_rate) }}</td>
                <td class="p-3 text-center font-semibold tabular-nums text-slate-900">{{ formatPercent(summaryData.reliability) }}</td>
                <td class="p-3 text-center font-semibold tabular-nums text-slate-900">{{ formatPercent(summaryData.availability) }}</td>
                <td class="p-3 text-center font-semibold tabular-nums text-slate-900">{{ formatNumber(summaryData.spare_part_replacement_count) }}</td>
                <td class="p-3 text-center font-semibold tabular-nums text-slate-900">{{ formatNumber(summaryData.vandalism_count) }}</td>
```

Tutup container dengan `</section>` sebagai pengganti penutup `<div>` paling luar. Jangan mengubah ekspresi `formatNumber`, `formatDecimal`, atau `formatPercent`.

- [ ] **Step 4: Ubah Log Kejadian menjadi tabel netral**

Gunakan container dan header berikut:

```vue
      <section
        data-report-section="failures"
        class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white"
      >
        <div class="border-b border-slate-200 bg-white px-4 py-3">
          <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900">
            <AlertTriangleIcon class="h-4 w-4 text-slate-500" aria-hidden="true" />
            Log Kejadian Kegagalan (Failure Report)
          </h3>
        </div>
```

Gunakan class tabel dan header yang sama dengan tabel ringkasan:

```vue
          <table class="w-full text-left text-xs text-slate-700">
            <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
```

Ganti class dua cell tanggal agar keduanya netral tanpa mengubah nilai atau fallback-nya:

```vue
                <td class="whitespace-nowrap p-3 font-medium tabular-nums text-slate-700">{{ formatReportDateTime(log.tanggal_jam_kejadian || (log.tanggal_kejadian + ' ' + (log.mulai || '00:00'))) }}</td>
                <td class="whitespace-nowrap p-3 font-medium tabular-nums text-slate-700">{{ formatReportDateTime(log.tanggal_jam_penanganan || (log.tanggal_penanganan + ' ' + (log.selesai || '00:00'))) }}</td>
```

Gunakan radius kecil untuk tombol empty state tanpa mengubah handler:

```vue
                      <button
                        v-if="assets.length > 0"
                        type="button"
                        class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                        @click="openCreateModal"
                      >
                        <PlusIcon class="h-4 w-4" /> Input Manual
                      </button>
```

Tutup container dengan `</section>`. Pertahankan seluruh kolom, loop, kondisi `Y/Ya`, serta aksi edit dan hapus.

- [ ] **Step 5: Ubah Output Sparepart menjadi tabel netral**

Gunakan container dan header berikut:

```vue
      <section
        data-report-section="spare-parts"
        class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white"
      >
        <div class="border-b border-slate-200 bg-white px-4 py-3">
          <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900">
            <SettingsIcon class="h-4 w-4 text-slate-500" aria-hidden="true" />
            Output Report: Daftar Kebutuhan & Penggantian Sparepart
          </h3>
        </div>
```

Gunakan class tabel netral berikut:

```vue
          <table class="w-full text-left text-xs text-slate-700">
            <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
```

Gunakan body, empty state, dan row berikut:

```vue
            <tbody class="divide-y divide-slate-100">
              <tr v-if="sparepartLogs.length === 0">
                <td colspan="6" class="p-8 text-center text-slate-500">
                  <div class="flex flex-col items-center justify-center">
                    <SettingsIcon class="mb-2 h-7 w-7 text-slate-300" aria-hidden="true" />
                    <p>Belum ada laporan gangguan yang memerlukan penggantian sparepart di unit ini.</p>
                  </div>
                </td>
              </tr>
              <tr v-for="(log, idx) in sparepartLogs" :key="'sp-'+idx" class="transition-colors hover:bg-slate-50">
```

Ganti dua cell terakhir dengan tampilan netral berikut, tanpa mengubah fallback data:

```vue
                <td class="p-3 text-center font-semibold text-slate-900">{{ log.nama_sparepart || 'Sesuai Tindakan' }}</td>
                <td class="p-3 text-center font-semibold tabular-nums text-slate-900">{{ log.jumlah_sparepart || '1' }}</td>
```

Tutup container dengan `</section>`.

- [ ] **Step 6: Pastikan blok logic Vue tidak berubah**

Jalankan pemeriksaan berikut:

```powershell
rtk git diff -- resources/js/pages/input-data/TroubleReport.vue
```

Expected: perubahan hanya berada sebelum `<script setup>`; tidak ada baris mulai dari `const props = defineProps` sampai `backToDashboard` yang berubah.

- [ ] **Step 7: Jalankan test komponen dan pastikan lulus**

Run:

```powershell
rtk npm run test:js -- tests/js/TroubleReport.test.js
```

Expected: seluruh test `TroubleReport` PASS, termasuk test visual baru dan test event `router.patch` yang sudah ada.

- [ ] **Step 8: Commit perubahan UI dan test secara terbatas**

```powershell
rtk git add -- resources/js/pages/input-data/TroubleReport.vue tests/js/TroubleReport.test.js
rtk git commit -m "refactor: simplify trouble report presentation"
```

Expected: commit hanya memuat dua file frontend tersebut. Migration lokal yang sudah termodifikasi tidak ikut ter-stage.

### Task 3: Verifikasi regresi dan batas backend

**Files:**
- Verify: `resources/js/pages/input-data/TroubleReport.vue`
- Verify: `tests/js/TroubleReport.test.js`

- [ ] **Step 1: Jalankan seluruh test frontend**

```powershell
rtk npm run test:js
```

Expected: seluruh suite Vitest PASS.

- [ ] **Step 2: Jalankan build produksi frontend**

```powershell
rtk npm run build
```

Expected: Vite selesai tanpa error template, import, atau CSS.

- [ ] **Step 3: Verifikasi tidak ada class dekoratif lama pada report**

```powershell
rtk rg -n "bg-gradient|from-\[#4A72B2\]|from-\[#7030A0\]|from-amber-500|rounded-xl|bg-blue-100" resources/js/pages/input-data/TroubleReport.vue
```

Expected: tidak ada hasil.

- [ ] **Step 4: Verifikasi commit tidak mengubah backend**

```powershell
rtk git show --name-only --format=HEAD HEAD
```

Expected: daftar file commit hanya berisi:

```text
resources/js/pages/input-data/TroubleReport.vue
tests/js/TroubleReport.test.js
```

- [ ] **Step 5: Periksa status worktree tanpa memasukkan perubahan milik pengguna**

```powershell
rtk git status --short --branch
```

Expected: perubahan migration yang sudah ada boleh tetap tampil sebagai perubahan lokal, tetapi tidak terdapat perubahan backend baru dari pekerjaan UI ini.
