import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import TroubleReport from '@/pages/input-data/TroubleReport.vue'

const router = vi.hoisted(() => ({
  get: vi.fn(),
  patch: vi.fn(),
  post: vi.fn(),
}))

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  router,
}))

const mountPage = (overrides = {}) => mount(TroubleReport, {
  props: {
    selected_area: 'DAOP1',
    subsystem: 'INTERLOCKING ELEKTRIK',
    assets: [{ id: 1, nama_aset: 'INTERLOCKING ELEKTRIK', jumlah_unit: 2, tahun_pemasangan: '2020-01-01' }],
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
  it('uses color only for report headers and uptime or downtime values', () => {
    const wrapper = mountPage({
      reliability: [{
        total_operating_hour: 115488,
        total_uptime: 115350,
        total_downtime: 138,
        jumlah_failure: 3,
        mttf: 4626.6222,
        mtbf: 38450,
        failure_rate: 0.000026,
        reliability: 0.99997,
        availability: 0.9988,
        spare_part_replacement_count: 0,
        vandalism_count: 0,
      }],
    })
    const contextLabel = wrapper.get('[data-report-context-label]')
    const reportSections = wrapper.findAll('[data-report-section]')
    const reliabilityHeader = wrapper.get('[data-report-section="reliability"] > div')
    const failureHeader = wrapper.get('[data-report-section="failures"] > div')
    const sparepartHeader = wrapper.get('[data-report-section="spare-parts"] > div')
    const uptime = wrapper.get('[data-report-section="reliability"] tbody td:nth-child(4)')
    const downtime = wrapper.get('[data-report-section="reliability"] tbody td:nth-child(5)')

    expect(contextLabel.text()).toBe('Subsystem')
    expect(contextLabel.classes()).not.toContain('bg-blue-100')
    expect(contextLabel.classes()).not.toContain('rounded-full')
    expect(wrapper.find('[class*="bg-gradient"]').exists()).toBe(false)
    expect(reportSections).toHaveLength(3)
    expect(reliabilityHeader.classes()).toContain('bg-[#365f9c]')
    expect(failureHeader.classes()).toContain('bg-[#6b2a9b]')
    expect(sparepartHeader.classes()).toContain('bg-[#e87516]')
    expect(uptime.classes()).toContain('text-emerald-700')
    expect(downtime.classes()).toContain('text-red-700')

    reportSections.forEach((section) => {
      expect(section.classes()).toContain('rounded-md')
      expect(section.classes()).not.toContain('rounded-xl')
      expect(section.classes()).not.toContain('shadow-sm')
    })
  })

  it('shows the subsystem installation date once in the report identity', () => {
    const wrapper = mountPage()

    expect(wrapper.text()).toContain('Tanggal pemasangan equipment')
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

  it('updates an equipment installation date from the report identity panel', async () => {
    const wrapper = mountPage()

    await wrapper.get('[data-edit-installation-date]').trigger('click')
    await wrapper.get('[data-installation-date-input="1"]').setValue('2021-03-04')
    await wrapper.get('[data-save-installation-date="1"]').trigger('click')

    expect(router.patch).toHaveBeenCalledWith(
      '/master-asset/1/installation-date',
      { tanggal_pemasangan: '2021-03-04' },
      expect.objectContaining({ preserveScroll: true }),
    )
  })

  it('explains the Excel calculation baseline without treating a different equipment date as a conflict', () => {
    const wrapper = mountPage({
      assets: [{ id: 1, nama_aset: 'INTERLOCKING ELEKTRIK', jumlah_unit: 2, tahun_pemasangan: '2012-01-01' }],
      reliability: [{ baseline_date: '2020-01-01' }],
    })

    const trigger = wrapper.get('[data-calculation-baseline-info]')
    const tooltip = wrapper.get('[role="tooltip"]')

    expect(trigger.attributes('aria-describedby')).toBe(tooltip.attributes('id'))
    expect(wrapper.text()).not.toContain('Tanggal pemasangan equipment berbeda dari baseline perhitungan Excel')
    expect(wrapper.text()).toContain('1 Januari 2012')
    expect(wrapper.text()).toContain('1 Januari 2020')
    expect(tooltip.text()).toContain('Tanggal pemasangan equipment hanya informasi aset')
  })

  it('renders backend summary values without exposing technical parity status', () => {
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

    expect(wrapper.text()).not.toContain('Sesuai Excel')
    expect(wrapper.text()).not.toContain('Ada selisih')
    expect(wrapper.text()).toContain('115488')
    expect(wrapper.text()).toContain('138')
    expect(wrapper.text()).toContain('4626.62')
    expect(wrapper.text()).toContain('99.9974%')
    expect(wrapper.text()).toContain('99.8805%')
    expect(wrapper.text()).toContain('09/03/2020 13:15')
    expect(wrapper.text()).toContain('09/03/2020 14:50')
    expect(wrapper.text()).not.toContain('2020-03-09 13:15:00')
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

    expect(wrapper.text()).toContain('Data belum ada')
  })
})
