import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FlashMessage from '@/presentation/components/feedback/FlashMessage.vue'

describe('FlashMessage', () => {
  it('uses accessible roles for success and error feedback', () => {
    const wrapper = mount(FlashMessage, { props: { success: 'Tersimpan.', error: 'Gagal.' } })
    expect(wrapper.get('[role="status"]').text()).toBe('Tersimpan.')
    expect(wrapper.get('[role="alert"]').text()).toBe('Gagal.')
  })

  it('renders no feedback node when both messages are empty', () => {
    const wrapper = mount(FlashMessage, { props: { success: null, error: null } })
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })
})
