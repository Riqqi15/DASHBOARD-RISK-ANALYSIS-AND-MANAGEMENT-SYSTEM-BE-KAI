import { expect, test } from '@playwright/test'

const adminUsername = process.env.UAT_ADMIN_USERNAME
const adminPassword = process.env.UAT_ADMIN_PASSWORD
const daopUsername = process.env.UAT_DAOP_USERNAME
const daopPassword = process.env.UAT_DAOP_PASSWORD

async function login(page, username, password) {
  await page.goto('/login')
  await page.getByLabel('Username').fill(username)
  await page.getByLabel('Kata sandi').fill(password)
  await page.getByRole('button', { name: 'Masuk ke sistem' }).click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

test.describe('UAT account access', () => {
  test.skip(!adminUsername || !adminPassword || !daopUsername || !daopPassword, 'UAT credentials are required')

  test('admin can sign in and open central administration', async ({ page }) => {
    await login(page, adminUsername, adminPassword)

    await expect(page.getByRole('heading', { name: 'Ringkasan kinerja persinyalan' })).toBeVisible()
    const response = await page.goto('/admin/units')
    expect(response?.status()).toBe(200)
    await expect(page.getByRole('heading', { name: 'Unit & Akun' })).toBeVisible()
  })

  test('DAOP account can sign in but cannot open central administration', async ({ page }) => {
    await login(page, daopUsername, daopPassword)

    await expect(page.getByText('DAOP-1', { exact: true }).first()).toBeVisible()
    const response = await page.goto('/admin/units')
    expect(response?.status()).toBe(403)
  })
})
