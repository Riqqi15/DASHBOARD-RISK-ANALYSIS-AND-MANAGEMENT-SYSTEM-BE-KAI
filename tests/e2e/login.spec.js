import { expect, test } from '@playwright/test'

test('guest can view login page', async ({ page }) => {
  await page.goto('/login')

  await expect(page.getByRole('heading', { name: 'Masuk ke KAI RAMS' })).toBeVisible()
})
