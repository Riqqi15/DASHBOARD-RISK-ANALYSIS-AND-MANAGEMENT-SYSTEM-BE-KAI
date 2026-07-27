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
    await wrapper.get('#email').setValue('admin@example.test')
    await wrapper.get('#password').setValue('secret-password')
    await wrapper.get('form').trigger('submit')
    expect(state.post).toHaveBeenCalledWith('/login', expect.objectContaining({ onFinish: expect.any(Function) }))
  })

  it('renders a server email error accessibly', () => {
    state.errors = { email: 'Email tidak valid.' }
    const wrapper = mount(Login)
    expect(wrapper.get('[role="alert"]').text()).toContain('Email tidak valid.')
  })
})
