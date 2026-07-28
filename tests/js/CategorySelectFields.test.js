import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import CategorySelectFields from '@/pages/master-data/assets/Partials/CategorySelectFields.vue'

const categories = [
  {
    id: 1,
    name: 'Peralatan Luar Sinyal Elektrik',
    systems: [{
      id: 11,
      name: 'Peraga Sinyal Elektrik',
      subsystems: [{ id: 101, name: 'Track Circuit' }],
    }],
  },
  {
    id: 2,
    name: 'Peralatan Telekomunikasi',
    systems: [{
      id: 21,
      name: 'Radio',
      subsystems: [{ id: 201, name: 'Base Station' }],
    }],
  },
]

describe('CategorySelectFields', () => {
  it('derives the initial ancestors and exposes the three accessible labels', () => {
    const wrapper = mount(CategorySelectFields, {
      props: { categories, modelValue: 101, errors: {} },
    })

    expect(wrapper.get('label[for="asset-group-id"]').text()).toBe('Aset Prasarana Sintel')
    expect(wrapper.get('label[for="asset-system-id"]').text()).toBe('System')
    expect(wrapper.get('label[for="asset-subsystem-id"]').text()).toBe('Subsystem')
    expect(wrapper.get('select[name="asset_group_id"]').element.value).toBe('1')
    expect(wrapper.get('select[name="asset_system_id"]').element.value).toBe('11')
    expect(wrapper.get('select[name="asset_subsystem_id"]').element.value).toBe('101')
  })

  it('resets system and subsystem when the group changes', async () => {
    const wrapper = mount(CategorySelectFields, {
      props: { categories, modelValue: 101, errors: {} },
    })

    await wrapper.get('select[name="asset_group_id"]').setValue('2')

    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([null])
    expect(wrapper.get('select[name="asset_system_id"]').element.value).toBe('')
    expect(wrapper.get('select[name="asset_subsystem_id"]').attributes('disabled')).toBeDefined()
  })

  it('resets the subsystem when the system changes and emits only a valid leaf', async () => {
    const branch = [{
      ...categories[0],
      systems: [
        categories[0].systems[0],
        { id: 12, name: 'Point Machine', subsystems: [{ id: 102, name: 'Motor Wesel' }] },
      ],
    }]
    const wrapper = mount(CategorySelectFields, {
      props: { categories: branch, modelValue: 101, errors: {} },
    })

    await wrapper.get('select[name="asset_system_id"]').setValue('12')
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([null])
    expect(wrapper.get('select[name="asset_subsystem_id"]').element.value).toBe('')
    await wrapper.get('select[name="asset_subsystem_id"]').setValue('102')
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([102])
  })

  it('clears an invalid model and preserves only the current inactive path', async () => {
    const inactiveCategories = [{
      id: 9,
      name: 'Kategori Lama',
      is_active: false,
      systems: [{
        id: 91,
        name: 'System Lama',
        is_active: true,
        subsystems: [{ id: 901, name: 'Subsystem Lama', is_active: false }],
      }],
    }, ...categories]
    const current = mount(CategorySelectFields, {
      props: { categories: inactiveCategories, modelValue: 901, errors: {} },
    })

    expect(current.text()).toContain('Kategori Lama (nonaktif)')
    expect(current.text()).toContain('Subsystem Lama (nonaktif)')
    expect(current.get('select[name="asset_subsystem_id"]').element.value).toBe('901')

    const invalid = mount(CategorySelectFields, {
      props: { categories, modelValue: 999, errors: {} },
    })
    await invalid.vm.$nextTick()
    expect(invalid.emitted('update:modelValue')).toEqual([[null]])
    expect(invalid.get('select[name="asset_group_id"]').element.value).toBe('')
  })

  it('connects the subsystem error to the invalid control', () => {
    const wrapper = mount(CategorySelectFields, {
      props: {
        categories,
        modelValue: null,
        errors: { asset_subsystem_id: 'Pilih subsystem aset.' },
      },
    })

    const select = wrapper.get('#asset-subsystem-id')
    expect(select.attributes('aria-invalid')).toBe('true')
    expect(select.attributes('aria-describedby')).toBe('asset-subsystem-error')
    expect(wrapper.get('#asset-subsystem-error').attributes('role')).toBe('alert')
  })
})
