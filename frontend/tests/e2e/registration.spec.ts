import { expect, test } from '@playwright/test'

test('a visitor can register', async ({ page }) => {
  await page.route('**/api/v1/auth/register', async (route) => {
    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({ user: { id: crypto.randomUUID(), email: 'collector@example.com', roles: ['ROLE_USER'], emailVerifiedAt: null, createdAt: new Date().toISOString() } }),
    })
  })

  await page.goto('/register')
  await page.getByLabel('E-mailadres').fill('collector@example.com')
  await page.getByLabel('Wachtwoord', { exact: true }).fill('A-strong-password-123')
  await page.getByLabel('Herhaal wachtwoord').fill('A-strong-password-123')
  await page.getByRole('button', { name: 'Registreren' }).click()
  await expect(page).toHaveURL(/\/login\?registered=1$/)
  await expect(page.getByText(/Account aangemaakt/)).toBeVisible()
})
