import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'
import TroubleReportImport from '@/pages/input-data/TroubleReportImport.vue'

const inertia = vi.hoisted(() => ({
  post: vi.fn(),
  form: null,
}))

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
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

  it('renders the import counters and row issues', () => {
    const wrapper = mountPage({
      result: {
        status: 'succeeded',
        workbook: 'RAMS Daop 1.xlsm',
        unit: { id: 10, code: 'DAOP-1', name: 'Daerah Operasi 1 Jakarta' },
        created: 3,
        updated: 2,
        unchanged: 4,
        skipped: 1,
        sheets: 19,
        snapshots: 18,
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
    expect(wrapper.text()).toContain('Tidak berubah')
    expect(wrapper.text()).toContain('Dilewati')
    expect(wrapper.text()).toContain('Snapshot Excel')
    expect(wrapper.text()).toContain('Parity dihitung')
    expect(wrapper.text()).toContain('Sesuai Excel')
    expect(wrapper.text()).toContain('Ada selisih')
    expect(wrapper.text()).toContain('Interlocking Elektrik')
    expect(wrapper.text()).toContain('Baris 12')
    expect(wrapper.text()).toContain('Tanggal/waktu tidak valid.')
  })
})
