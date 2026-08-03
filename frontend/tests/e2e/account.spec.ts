import { expect, test } from '@playwright/test'

test('an authenticated collector can view safe account details and log out', async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('cardnest.authToken', 'test-token'))
  await page.route('**/api/v1/me', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        user: {
          id: crypto.randomUUID(),
          email: 'collector@example.com',
          roles: ['ROLE_USER'],
          emailVerifiedAt: null,
          createdAt: '2026-08-01T10:30:00+00:00',
        },
      }),
    })
  })
  await page.route('**/api/v1/auth/logout', async (route) => {
    await route.fulfill({ status: 204 })
  })

  await page.goto('/account')

  await expect(page.getByRole('heading', { name: 'Mijn account' })).toBeVisible()
  await expect(page.getByText('collector@example.com')).toBeVisible()
  await expect(page.getByText('Nog niet bevestigd')).toBeVisible()
  await expect(page.getByText('ROLE_USER')).toHaveCount(0)

  await page.getByLabel('Accountgegevens').getByRole('button', { name: 'Uitloggen' }).click()

  await expect(page).toHaveURL(/\/login$/)
})
