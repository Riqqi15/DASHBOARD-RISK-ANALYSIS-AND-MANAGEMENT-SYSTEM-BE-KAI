import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
  router: { get: routerGet },
}))

import Dashboard from '@/pages/dashboard/Dashboard.vue'

const mountPage = (overrides = {}) => mount(Dashboard, {
  props: {
    selected_area: 'DAOP-1',
    units: [],
    summary: {
      operatingDays: 2409,
      operatingStartDate: '2020-01-01',
    },
    asset_categories: [],
    assets: [
      {
        id: 1,
        unit_kerja_id: 'DAOP1',
        aset_prasarana_sintel: '1234',
        system: '1',
        subsystem: '1',
        jumlah_unit: 1,
        status: 'Aktif',
      },
    ],
    ...overrides,
  },
  global: {
    stubs: {
      MainLayout: { template: '<main><slot /></main>' },
      AreaSelectorBanner: true,
    },
  },
})

describe('Dashboard', () => {
  beforeEach(() => {
    routerGet.mockReset()
  })

  it('renders subsystem groups from master assets', () => {
    const wrapper = mountPage()

    expect(wrapper.text()).toContain('1234')
    expect(wrapper.text()).toContain('1 system')
    expect(wrapper.text()).toContain('1 aset')
    expect(wrapper.get('[data-subsystem-name="1"]').text()).toContain('1')
  })

  it('uses imported Excel colors for the matching asset hierarchy', () => {
    const wrapper = mountPage({
      asset_categories: [{
        id: 1,
        name: 'Catu Daya Sintel',
        dashboard_color: '#FF0000',
        systems: [{
          id: 2,
          name: 'Catu Daya Sinyal',
          dashboard_color: '#FF0000',
          subsystems: [{ id: 3, name: 'Catu Daya Sinyal', dashboard_color: '#FF0000' }],
        }],
      }],
      assets: [{
        id: 1,
        aset_prasarana_sintel: 'Catu Daya Sintel',
        system: 'Catu Daya Sinyal',
        subsystem: 'Catu Daya Sinyal',
        jumlah_unit: 1,
      }],
    })

    expect(wrapper.get('[data-asset-group="Catu Daya Sintel"] summary span[style]').attributes('style')).toContain('rgb(255, 0, 0)')
    expect(wrapper.get('[data-subsystem-name="Catu Daya Sinyal"]').attributes('style')).toContain('rgb(255, 0, 0)')
  })

  it('keeps the Excel asset-family abbreviations and colors visible', () => {
    const wrapper = mountPage({
      summary: {
        reliabilityGroups: [
          { code: 'PDSM', reliability: 1, availability: 1 },
          { code: 'PLSM', reliability: 1, availability: 1 },
          { code: 'PDSE', reliability: 0.99974, availability: 0.9988 },
          { code: 'PLSE', reliability: 1, availability: 1 },
          { code: 'CDS', reliability: 1, availability: 1 },
        ],
      },
    })

    expect(wrapper.findAll('[data-family-code]')).toHaveLength(5)
    expect(wrapper.get('[data-family-code="PDSM"]').text()).toContain('PDSM')
    expect(wrapper.get('[data-family-code="PLSM"]').text()).toContain('PLSM')
    expect(wrapper.get('[data-family-code="PDSE"]').text()).toContain('PDSE')
    expect(wrapper.get('[data-family-code="PLSE"]').text()).toContain('PLSE')
    expect(wrapper.get('[data-family-code="CDS"]').text()).toContain('CDS')
    expect(wrapper.get('[data-family-code="PDSM"] header').attributes('style')).toContain('rgb(146, 208, 80)')
    expect(wrapper.get('[data-family-code="PLSM"] header').attributes('style')).toContain('rgb(75, 172, 198)')
    expect(wrapper.get('[data-family-code="PDSE"] header').attributes('style')).toContain('rgb(255, 255, 0)')
    expect(wrapper.get('[data-family-code="PLSE"] header').attributes('style')).toContain('rgb(255, 192, 0)')
    expect(wrapper.get('[data-family-code="CDS"] header').attributes('style')).toContain('rgb(255, 0, 0)')
  })

  it('renders a dynamic family card supplied by the backend', () => {
    const wrapper = mountPage({
      summary: {
        reliabilityGroups: [
          {
            id: 6,
            code: 'DS',
            name: 'DAYA SATU',
            color: '#123ABC',
            asset_count: 0,
            reliability: null,
            availability: null,
          },
        ],
      },
    })

    expect(wrapper.findAll('[data-family-code]')).toHaveLength(1)
    expect(wrapper.get('[data-family-code="DS"]').text()).toContain('DAYA SATU')
    expect(wrapper.get('[data-family-code="DS"]').text()).toContain('Belum ada data')
    expect(wrapper.get('[data-family-code="DS"] header').attributes('style')).toContain('rgb(18, 58, 188)')
  })

  it('shows an automatically assigned order before a clean root-category name', () => {
    const wrapper = mountPage({
      assets: [],
      asset_categories: [{
        id: 6,
        name: 'DAYA SATU',
        sort_order: 6,
        systems: [],
      }],
    })

    expect(wrapper.text()).toContain('6. DAYA SATU')
    expect(wrapper.text()).not.toContain('6. 6. DAYA SATU')
  })

  it('shows the latest import date once beside the section title and uses a fluid card grid', () => {
    const wrapper = mountPage({
      summary: {
        latestImport: {
          date: '2026-08-20',
          groupCodes: ['PDSE', 'PLSE'],
        },
      },
    })

    expect(wrapper.get('.family-metrics__heading [data-latest-import-badge]').text()).toBe('Data Terbaru · 20 Agu 2026')
    expect(wrapper.findAll('[data-family-code] [data-latest-import-badge]')).toHaveLength(0)
    expect(wrapper.get('.family-metrics__grid').classes()).toContain('family-metrics__grid--fluid')
    expect(wrapper.get('[data-family-code="PDSE"] .family-metric__values').classes()).toContain('family-metric__values--emphasized')
  })

  it('renders recorded failure count for the selected daop or divre', () => {
    const wrapper = mountPage({
      summary: {
        totalFailure: 9,
      },
    })

    expect(wrapper.text()).toContain('Ringkasan kinerja persinyalan')
    expect(wrapper.text()).toContain('Rekap gangguan tercatat')
    expect(wrapper.text()).toContain('9 kejadian')
    expect(wrapper.get('.failure-stat-card').classes()).toContain('failure-stat-card--plain')
    expect(wrapper.get('.asset-group__summary').classes()).toContain('asset-group__summary--plain')
    expect(wrapper.get('.asset-group__summary').classes()).toContain('asset-group__summary--white')
  })

  it('renders empty asset categories from the master taxonomy', () => {
    const wrapper = mountPage({
      assets: [],
      asset_categories: [
        {
          id: 6,
          name: '1234',
          systems: [],
        },
      ],
    })

    expect(wrapper.text()).toContain('1234')
    expect(wrapper.text()).toContain('0 aset · 0 unit · 0 system')
    expect(wrapper.text()).toContain('Belum ada system aktif.')
  })

  it('renders empty systems and subsystems supplied by the backend taxonomy', () => {
    const wrapper = mountPage({
      assets: [],
      asset_categories: [
        {
          id: 6,
          name: 'KATEGORI BARU',
          systems: [{
            id: 7,
            name: 'SYSTEM BARU',
            subsystems: [{ id: 8, name: 'SUBSYSTEM BARU' }],
          }],
        },
      ],
    })

    expect(wrapper.text()).toContain('KATEGORI BARU')
    expect(wrapper.text()).toContain('SYSTEM BARU')
    expect(wrapper.get('[data-subsystem-name="SUBSYSTEM BARU"]').text()).toContain('0 aset · 0 unit')
  })

  it('opens trouble report for the selected subsystem and area', async () => {
    const wrapper = mountPage()

    await wrapper.get('[data-subsystem-name="1"]').trigger('click')

    expect(routerGet).toHaveBeenCalledWith('/trouble-report', {
      subsystem: '1',
      area: 'DAOP-1',
    })
  })
})
