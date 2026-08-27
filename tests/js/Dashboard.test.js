import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
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
      AreaSelectorBanner: {
        props: ['failureCount'],
        template: '<div data-dashboard-command-bar>{{ failureCount }} gangguan tercatat</div>',
      },
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
    expect(wrapper.get('[data-family-code="PDSM"] .family-metric__code').attributes('style')).toContain('background-color: rgb(146, 208, 80)')
    expect(wrapper.get('[data-family-code="PLSM"] .family-metric__code').attributes('style')).toContain('background-color: rgb(75, 172, 198)')
    expect(wrapper.get('[data-family-code="PDSE"] .family-metric__code').attributes('style')).toContain('background-color: rgb(255, 255, 0)')
    expect(wrapper.get('[data-family-code="PLSE"] .family-metric__code').attributes('style')).toContain('background-color: rgb(255, 192, 0)')
    expect(wrapper.get('[data-family-code="CDS"] .family-metric__code').attributes('style')).toContain('background-color: rgb(255, 0, 0)')
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
    expect(wrapper.get('[data-family-code="DS"] .family-metric__code').attributes('style')).toContain('background-color: rgb(18, 58, 188)')
  })

  it('does not invent asset-family cards when the selected area has no assets', () => {
    const wrapper = mountPage({
      assets: [],
      summary: { reliabilityGroups: [] },
    })

    expect(wrapper.findAll('[data-family-code]')).toHaveLength(0)
    expect(wrapper.text()).toContain('Belum ada data aset untuk wilayah terpilih')
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

  it('shows the latest import date once beside the section title and uses the horizontal family track', () => {
    const wrapper = mountPage({
      summary: {
        latestImport: {
          date: '2026-08-20',
          groupCodes: ['PDSE', 'PLSE'],
        },
        reliabilityGroups: [
          { code: 'PDSE', reliability: 0.99974, availability: 0.9988 },
          { code: 'PLSE', reliability: 1, availability: 1 },
        ],
      },
    })

    expect(wrapper.get('.family-metrics__heading [data-latest-import-badge]').text()).toBe('Data Terbaru · 20 Agu 2026')
    expect(wrapper.findAll('[data-family-code] [data-latest-import-badge]')).toHaveLength(0)
    expect(wrapper.get('[data-family-track]').classes()).toContain('family-metrics__track')
    expect(wrapper.get('[data-family-code="PDSE"] .family-metric__code').text()).toBe('PDSE')
    expect(wrapper.get('[data-family-code="PDSE"] .family-metric__values').classes()).toContain('family-metric__values--emphasized')
  })

  it('fills the available width when there are no more than seven family cards', () => {
    for (const count of [5, 7]) {
      const wrapper = mountPage({
        summary: {
          reliabilityGroups: Array.from({ length: count }, (_, index) => ({
            id: index + 1,
            code: `K${index + 1}`,
            name: `Kelompok ${index + 1}`,
            reliability: 1,
            availability: 1,
          })),
        },
      })

      expect(wrapper.get('[data-family-track]').classes()).toContain('family-metrics__track--fit')
      expect(wrapper.find('[aria-label="Navigasi kelompok aset"]').exists()).toBe(false)
      wrapper.unmount()
    }
  })

  it('keeps more than seven family cards on one scrollable row with boundary-aware arrows', async () => {
    const wrapper = mountPage({
      summary: {
        reliabilityGroups: Array.from({ length: 8 }, (_, index) => ({
          id: index + 1,
          code: `K${index + 1}`,
          name: `Kelompok ${index + 1}`,
          reliability: 1,
          availability: 1,
        })),
      },
    })

    const track = wrapper.get('[data-family-track]').element
    Object.defineProperties(track, {
      clientWidth: { configurable: true, value: 700 },
      scrollWidth: { configurable: true, value: 1000 },
      scrollLeft: { configurable: true, writable: true, value: 0 },
    })
    track.scrollBy = vi.fn()

    window.dispatchEvent(new Event('resize'))
    await nextTick()

    const previous = wrapper.get('[aria-label="Geser kelompok aset ke kiri"]')
    const next = wrapper.get('[aria-label="Geser kelompok aset ke kanan"]')

    expect(wrapper.findAll('[data-family-code]')).toHaveLength(8)
    expect(wrapper.get('[data-family-track]').classes()).toContain('family-metrics__track--scroll')
    expect(wrapper.get('[data-family-track]').attributes('tabindex')).toBe('0')
    expect(previous.attributes('disabled')).toBeDefined()
    expect(next.attributes('disabled')).toBeUndefined()

    await next.trigger('click')
    expect(track.scrollBy).toHaveBeenCalledWith(expect.objectContaining({ left: expect.any(Number) }))
    expect(track.scrollBy.mock.calls[0][0].left).toBeGreaterThan(0)

    track.scrollLeft = 300
    await wrapper.get('[data-family-track]').trigger('scroll')

    expect(previous.attributes('disabled')).toBeUndefined()
    expect(next.attributes('disabled')).toBeDefined()
  })

  it('renders recorded failure count for the selected daop or divre', () => {
    const wrapper = mountPage({
      summary: {
        totalFailure: 9,
      },
    })

    expect(wrapper.get('[data-dashboard-command-bar]').text()).toContain('9 gangguan tercatat')
    expect(wrapper.find('.dashboard-hero').exists()).toBe(false)
    expect(wrapper.find('.failure-stat-card').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('KAI RAMS')
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
