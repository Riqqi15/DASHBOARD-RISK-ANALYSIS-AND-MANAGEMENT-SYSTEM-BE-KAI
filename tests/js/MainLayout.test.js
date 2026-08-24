import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import MainLayout from '@/layouts/MainLayout.vue'

vi.mock('@inertiajs/vue3', () => ({
  Link: {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
  },
  usePage: () => ({
    url: '/risk-register',
    props: {
      auth: { user: { id: 1, name: 'Admin Pusat', username: 'admin', role: 'pusat' } },
      flash: {},
      active_rams_unit: { id: 12, code: 'DIVRE-III', name: 'Divisi Regional III' },
    },
  }),
}))

describe('MainLayout RAMS unit navigation', () => {
  it('carries the active unit to every RAMS module link', () => {
    const wrapper = mount(MainLayout, {
      global: { stubs: { FlashMessage: true } },
    })
    const links = Object.fromEntries(wrapper.findAll('a').map((link) => [link.text().trim(), link.attributes('href')]))

    expect(links.Dashboard).toBe('/dashboard?area=DIVRE-III')
    expect(links['Master Aset']).toBe('/master-asset?unit_kerja_id=12')
    expect(links['Matriks Risiko']).toBe('/risk-matrix?area=DIVRE-III')
    expect(links['Risk Register']).toBe('/risk-register?area=DIVRE-III')
    expect(links['Inventori Suku Cadang']).toBe('/inventory?unit_kerja_id=12')
    expect(links['Laporan RAMS']).toBe('/reports?area=DIVRE-III')
    expect(links['Import Data RAMS']).toBe('/trouble-report/import')
  })
})
