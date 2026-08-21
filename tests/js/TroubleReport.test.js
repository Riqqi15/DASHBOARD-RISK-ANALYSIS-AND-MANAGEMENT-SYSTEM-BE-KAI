import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import TroubleReport from '@/pages/input-data/TroubleReport.vue'

const router = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
}))

vi.mock('@inertiajs/vue3', () => ({
  router,
}))

const mountPage = (overrides = {}) => mount(TroubleReport, {
  props: {
    selected_area: 'DAOP1',
    subsystem: 'INTERLOCKING ELEKTRIK',
    assets: [{ id: 1, jumlah_unit: 2, tahun_pemasangan: '2020-01-01' }],
    reliability: [],
    failure_logs: [],
    spare_parts: [],
    ...overrides,
  },
  global: {
    stubs: {
      MainLayout: { template: '<main><slot /></main>' },
      BaseButton: { template: '<button type="button" @click="$emit(`click`)"><slot /></button>' },
      TroubleReportModal: { template: '<div />' },
    },
  },
})

describe('TroubleReport', () => {
  it('shows the subsystem installation date once in the report identity', () => {
    const wrapper = mountPage()

    expect(wrapper.text()).toContain('Tanggal pemasangan subsystem')
    expect(wrapper.text()).toContain('1 Januari 2020')
    expect(wrapper.text()).toContain('DAOP1')
  })

  it('shows missing and inconsistent subsystem installation dates clearly', () => {
    const missing = mountPage({ assets: [{ id: 1, jumlah_unit: 2, tahun_pemasangan: null }] })
    const inconsistent = mountPage({
      assets: [
        { id: 1, jumlah_unit: 1, tahun_pemasangan: '2020-01-01' },
        { id: 2, jumlah_unit: 1, tahun_pemasangan: '2021-02-03' },
      ],
    })

    expect(missing.text()).toContain('Belum tercatat')
    expect(inconsistent.text()).toContain('1 Januari 2020')
    expect(inconsistent.text()).toContain('3 Februari 2021')
    expect(inconsistent.text()).toContain('lebih dari satu tanggal')
  })

  it('renders backend summary values and parity status without recalculating counts in the frontend', () => {
    const wrapper = mountPage({
      reliability: [{
        total_operating_hour: 115488,
        total_uptime: 115350,
        total_downtime: 138,
        jumlah_failure: 3,
        mttf: 4626.6222,
        mtbf: 38450,
        failure_rate: 0.000026007802340702212,
        reliability: 0.9999739925358593,
        availability: 0.9988050706566916,
        spare_part_replacement_count: 0,
        vandalism_count: 0,
        parity_status: 'matched',
      }],
      failure_logs: [{
        lokasi: 'Jakk',
        resor: '1.10 JAKK',
        qc: '1.C MRI',
        failure_event: 'Gangguan',
        penyebab: 'Modul rusak',
        tindakan: 'Diganti',
        penggantian_sparepart: 'Y',
        tindak_vandalisme: 'N',
        tanggal_jam_kejadian: '2020-03-09 13:15:00',
        tanggal_jam_penanganan: '2020-03-09 14:50:00',
        downtime_jam: 1.58,
        downtime_menit: 95,
      }],
    })

    expect(wrapper.text()).toContain('Sesuai Excel')
    expect(wrapper.text()).toContain('115488')
    expect(wrapper.text()).toContain('138')
    expect(wrapper.text()).toContain('4626.62')
    expect(wrapper.text()).toContain('99.9974%')
    expect(wrapper.text()).toContain('99.8805%')
  })

  it('shows Data belum ada for null formula outputs', () => {
    const wrapper = mountPage({
      reliability: [{
        total_operating_hour: 0,
        total_uptime: null,
        total_downtime: null,
        jumlah_failure: 0,
        mttf: null,
        mtbf: 0,
        failure_rate: 0,
        reliability: null,
        availability: null,
        spare_part_replacement_count: 0,
        vandalism_count: 0,
        parity_status: 'excel_data_missing',
      }],
    })

    expect(wrapper.text()).toContain('Data Excel belum ada')
    expect(wrapper.text()).toContain('Data belum ada')
  })
})
