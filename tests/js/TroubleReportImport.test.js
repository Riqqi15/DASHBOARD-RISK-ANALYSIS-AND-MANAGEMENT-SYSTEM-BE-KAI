import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'
import TroubleReportImport from '@/pages/input-data/TroubleReportImport.vue'

const inertia = vi.hoisted(() => ({
  post: vi.fn(),
  routerPost: vi.fn(),
  form: null,
}))

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  router: { post: inertia.routerPost },
  useForm: vi.fn((values) => {
    inertia.form = reactive({
      ...values,
      processing: false,
      progress: null,
      errors: {},
      post: inertia.post,
    })

    return inertia.form
  }),
  usePage: () => ({
    props: {
      auth: {
        user: {
          id: 1,
          name: 'Petugas Daop 1',
          role: 'unit',
          unit_kerja: { id: 10, code: 'DAOP-1', name: 'Daerah Operasi 1 Jakarta' },
        },
      },
    },
  }),
}))

const defaultProps = {
  can_choose_unit: true,
  selected_unit_id: null,
  units: [
    { id: 10, code: 'DAOP-1', name: 'Daerah Operasi 1 Jakarta' },
    { id: 40, code: 'DAOP-4', name: 'Daerah Operasi 4 Semarang' },
  ],
  result: null,
  history: [],
}

const mountPage = (overrides = {}) => mount(TroubleReportImport, {
  props: { ...defaultProps, ...overrides },
  global: {
    stubs: {
      MainLayout: { template: '<main><slot /></main>' },
    },
  },
})

describe('TroubleReportImport', () => {
  beforeEach(() => {
    inertia.post.mockReset()
    inertia.routerPost.mockReset()
    inertia.form = null
  })

  it('submits the selected workbook as multipart data', async () => {
    const wrapper = mountPage()
    const file = new File(['workbook'], 'RAMS Daop 1.xlsm', {
      type: 'application/vnd.ms-excel.sheet.macroEnabled.12',
    })

    await wrapper.get('#import-unit').setValue('10')
    const fileInput = wrapper.get('#import-workbook')
    Object.defineProperty(fileInput.element, 'files', { value: [file] })
    await fileInput.trigger('change')
    await wrapper.get('form').trigger('submit')

    expect(inertia.form.unit_kerja_id).toBe('10')
    expect(inertia.form.workbook).toBe(file)
    expect(inertia.post).toHaveBeenCalledWith('/trouble-report/import', expect.objectContaining({
      forceFormData: true,
      preserveScroll: true,
    }))
  })

  it('auto-selects a recognized unit from the workbook filename', async () => {
    const wrapper = mountPage()
    const file = new File(['workbook'], 'Risk Analysis RAMS Daop 4.xlsm')
    const fileInput = wrapper.get('#import-workbook')
    Object.defineProperty(fileInput.element, 'files', { value: [file] })

    await fileInput.trigger('change')

    expect(inertia.form.unit_kerja_id).toBe('40')
    expect(wrapper.text()).toContain('Terdeteksi otomatis: DAOP-4')
  })

  it('locks a unit user to the assigned unit', () => {
    const wrapper = mountPage({
      can_choose_unit: false,
      selected_unit_id: 10,
      units: [],
    })

    expect(wrapper.find('#import-unit').exists()).toBe(false)
    expect(wrapper.text()).toContain('DAOP-1')
    expect(inertia.form.unit_kerja_id).toBe(10)
  })

  it('shows upload progress and validation errors', async () => {
    const wrapper = mountPage()
    inertia.form.progress = { percentage: 64 }
    inertia.form.errors = {
      unit_kerja_id: 'Pilih unit kerja tujuan impor.',
      workbook: 'File harus berformat .xlsm atau .xlsx.',
    }
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('64%')
    expect(wrapper.text()).toContain('Pilih unit kerja tujuan impor.')
    expect(wrapper.text()).toContain('File harus berformat .xlsm atau .xlsx.')
  })

  it('renders the import counters and row issues', async () => {
    const wrapper = mountPage({
      result: {
        status: 'succeeded',
        workbook: 'RAMS Daop 1.xlsm',
        unit: { id: 10, code: 'DAOP-1', name: 'Daerah Operasi 1 Jakarta' },
        created: 3,
        updated: 2,
        unchanged: 4,
        data_updated: 7,
        data_unchanged: 4,
        duplicates_skipped: 4,
        duplicate_locations: ['Interlocking Elektrik!10', 'LxC!2'],
        skipped: 1,
        sheets: 19,
        snapshots: 18,
        risk_registers_created: 5,
        risk_registers_updated: 2,
        spare_parts_created: 7,
        spare_parts_updated: 3,
        parity: {
          calculated: 18,
          matched: 12,
          mismatch: 4,
          excel_data_missing: 1,
          not_compared: 1,
        },
        issues: [{
          sheet_name: 'Interlocking Elektrik',
          source_row: 12,
          source_column: 'Tanggal/Waktu',
          severity: 'warning',
          message: 'Tanggal/waktu tidak valid.',
        }],
      },
    })

    expect(wrapper.text()).toContain('RAMS Daop 1.xlsm')
    expect(wrapper.text()).toContain('19')
    expect(wrapper.text()).toContain('Dibuat')
    expect(wrapper.text()).toContain('Diperbarui')
    expect(wrapper.text()).toContain('Data diperbarui')
    expect(wrapper.text()).toContain('Tidak berubah')
    expect(wrapper.text()).toContain('Duplikat dilewati')
    expect(wrapper.get('[data-duplicate-locations]').text()).toContain('Interlocking Elektrik!10')
    expect(wrapper.get('[data-duplicate-locations]').text()).toContain('LxC!2')
    expect(wrapper.text()).toContain('Log Tetap')
    expect(wrapper.text()).toContain('Dilewati')
    expect(wrapper.text()).toContain('Snapshot Excel')
    expect(wrapper.text()).toContain('Risk Register Baru')
    expect(wrapper.text()).toContain('Suku Cadang Baru')
    expect(wrapper.text()).toContain('Parity dihitung')
    expect(wrapper.text()).toContain('Sesuai Excel')
    expect(wrapper.text()).toContain('Ada selisih')
    expect(wrapper.text()).toContain('Interlocking Elektrik')
    expect(wrapper.text()).toContain('Baris 12')
    expect(wrapper.text()).toContain('Tanggal/waktu tidak valid.')

    await wrapper.get('[data-counter-help="assets-created"]').trigger('click')
    expect(wrapper.text()).toContain('Jumlah aset baru yang berhasil ditambahkan dari workbook.')
  })

  it('renders scoped import history', () => {
    const wrapper = mountPage({
      history: [{
        id: 9,
        workbook_name: 'RAMS Daop 1.xlsm',
        status: 'succeeded',
        dry_run: false,
        file_size: 1048576,
        unit: { code: 'DAOP-1' },
        uploaded_by: { name: 'Petugas Daop 1' },
        issues_count: 2,
        progress_stage: 'Import selesai',
        progress_percent: 100,
        summary: { risk_registers_created: 3, spare_parts_created: 4 },
        started_at: '2026-08-08T08:00:00+07:00',
      }],
    })

    expect(wrapper.text()).toContain('Riwayat import')
    expect(wrapper.text()).toContain('RAMS Daop 1.xlsm')
    expect(wrapper.text()).toContain('Petugas Daop 1')
    expect(wrapper.get('[data-batch-issues="9"]').text()).toContain('2 masalah')
    expect(wrapper.find('a[href="/trouble-report/import/batch/9/issues/csv"]').exists()).toBe(false)
  })

  it('shows batch issues directly in the web detail row', async () => {
    const batch = {
      id: 18,
      workbook_name: 'RAMS Daop 1.xlsm',
      status: 'succeeded',
      progress_stage: 'Import selesai',
      progress_percent: 100,
      unit: { code: 'DAOP-1' },
      issues_count: 2,
      summary: {
        data_updated: 2,
        data_unchanged: 3,
        duplicates_skipped: 3,
        duplicate_locations: ['Reorder Stock!2'],
      },
      issues: [
        {
          id: 101,
          sheet_name: 'Interlocking Elektrik',
          source_row: 12,
          source_column: 'Tanggal/Waktu',
          severity: 'warning',
          message: 'Tanggal/waktu tidak valid.',
        },
        {
          id: 102,
          sheet_name: 'LxC',
          source_row: 4,
          source_column: 'Likelihood',
          severity: 'error',
          message: 'Nilai likelihood tidak ditemukan.',
        },
      ],
    }
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: batch }),
    }))

    const wrapper = mountPage({ history: [batch] })

    await wrapper.get('[data-batch-detail="18"]').trigger('click')

    const detail = wrapper.get('[data-batch-detail-row="18"]')
    expect(detail.text()).toContain('Masalah yang ditemukan')
    expect(detail.text()).toContain('Tanggal/waktu tidak valid.')
    expect(detail.text()).toContain('Interlocking Elektrik')
    expect(detail.text()).toContain('Baris 12')
    expect(detail.text()).toContain('Nilai likelihood tidak ditemukan.')
    expect(detail.text()).toContain('Peringatan (1)')
    expect(detail.text()).toContain('Error (1)')
    expect(detail.text()).toContain('Data diperbarui')
    expect(detail.text()).toContain('Tidak berubah')
    expect(detail.text()).toContain('Duplikat dilewati')
    expect(detail.get('[data-batch-duplicate-locations="18"]').text()).toContain('Reorder Stock!2')

    await detail.get('[data-metric-help^="detail-colors-updated-"]').trigger('click')
    expect(detail.text()).toContain('Jumlah kategori, system, atau subsystem yang warnanya disesuaikan mengikuti warna dari Excel.')

    vi.unstubAllGlobals()
  })

  it('opens a batch summary and shows processing progress', async () => {
    const batch = {
        id: 12,
        workbook_name: 'RAMS Daop 1.xlsm',
        status: 'processing',
        progress_stage: 'Memproses Risk Register',
        progress_percent: 50,
        unit: { code: 'DAOP-1' },
        issues_count: 0,
        summary: { risk_registers_created: 6, spare_parts_created: 2 },
        started_at: '2026-08-08T08:00:00+07:00',
    }
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: batch }),
    }))
    const wrapper = mountPage({ history: [batch] })

    expect(wrapper.text()).toContain('50%')
    expect(wrapper.text()).toContain('Memproses Risk Register')
    await wrapper.get('[data-batch-detail="12"]').trigger('click')
    expect(wrapper.text()).toContain('Risk Register dibuat')
    expect(wrapper.text()).toContain('6')
    vi.unstubAllGlobals()
  })

  it('starts polling when a newly submitted batch becomes queued', async () => {
    vi.useFakeTimers()
    const completedBatch = {
      id: 31,
      workbook_name: 'RAMS Daop 1.xlsm',
      status: 'failed',
      progress_stage: 'Import gagal',
      progress_percent: 10,
      unit: { code: 'DAOP-1' },
      issues_count: 0,
      summary: null,
      error_message: 'Baris workbook tidak dapat dipetakan ke aset.',
      started_at: '2026-08-10T09:41:00+07:00',
    }
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: completedBatch }),
    }))
    const wrapper = mountPage()

    await wrapper.setProps({
      history: [{ ...completedBatch, status: 'queued', progress_stage: 'Menunggu antrean', progress_percent: 0 }],
    })
    await vi.advanceTimersByTimeAsync(2500)
    await wrapper.vm.$nextTick()

    expect(fetch).toHaveBeenCalledWith('/trouble-report/import/batch/31', expect.objectContaining({
      credentials: 'same-origin',
    }))
    expect(wrapper.text()).toContain('Gagal')
    expect(wrapper.text()).not.toContain('Menunggu antrean')

    wrapper.unmount()
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('offers guarded rollback only when the backend allows it', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const batch = {
        id: 20,
        workbook_name: 'RAMS Daop 1.xlsm',
        status: 'succeeded',
        progress_stage: 'Import selesai',
        progress_percent: 100,
        unit: { code: 'DAOP-1' },
        issues_count: 0,
        summary: {},
        can_rollback: true,
    }
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: batch }),
    }))
    const wrapper = mountPage({ history: [batch] })

    await wrapper.get('[data-batch-detail="20"]').trigger('click')
    await wrapper.get('[data-batch-rollback="20"]').trigger('click')

    expect(inertia.routerPost).toHaveBeenCalledWith('/trouble-report/import/batch/20/rollback', {}, expect.any(Object))
    vi.unstubAllGlobals()
  })
})
