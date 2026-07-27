import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Login from '@/pages/auth/Login.vue'

const state = vi.hoisted(() => ({ errors: {}, post: vi.fn(), reset: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  useForm: (values) => ({ ...values, errors: state.errors, processing: false, post: state.post, reset: state.reset }),
}))

describe('Login', () => {
  beforeEach(() => {
    state.errors = {}
    state.post.mockReset()
    state.reset.mockReset()
  })

  it('submits credentials to the session endpoint', async () => {
    const wrapper = mount(Login)
    await wrapper.get('#username').setValue('admin.pusat')
    await wrapper.get('#password').setValue('admin1234')
    await wrapper.get('form').trigger('submit')
    expect(state.post).toHaveBeenCalledWith('/login', expect.objectContaining({ onFinish: expect.any(Function) }))
  })

  it('renders a username-only field', () => {
    const wrapper = mount(Login)

    expect(wrapper.get('label[for="username"]').text()).toBe('Username')
    expect(wrapper.get('#username').attributes('type')).toBe('text')
    expect(wrapper.find('#email').exists()).toBe(false)
  })

  it('renders a server username error accessibly', () => {
    state.errors = { username: 'Username tidak valid.' }
    const wrapper = mount(Login)
    expect(wrapper.get('[role="alert"]').text()).toContain('Username tidak valid.')
  })
})
