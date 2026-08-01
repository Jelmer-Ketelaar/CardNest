import { expect, test } from '@playwright/test'

test('a visitor can confirm an email address with a single-use link', async ({ page }) => {
  const token = 'a'.repeat(64)
  const user = {
    id: crypto.randomUUID(),
    email: 'collector@example.com',
    roles: ['ROLE_USER'],
    emailVerifiedAt: new Date().toISOString(),
    createdAt: new Date().toISOString(),
  }
  let verificationPayload: Record<string, unknown> | null = null

  await page.route(/\/api\/v1\/auth\/email-verification\/verify$/, async (route) => {
    verificationPayload = route.request().postDataJSON() as Record<string, unknown>
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ user }),
    })
  })

  await page.goto(`/verify-email?token=${token}`)

  await expect(page.getByRole('heading', { name: 'E-mailadres bevestigen' })).toBeVisible()
  await expect(page.getByText('Je e-mailadres is bevestigd.')).toBeVisible()
  await expect(page.getByRole('link', { name: 'Inloggen' })).toBeVisible()
  expect(verificationPayload).toEqual({ token })
})

test('an unverified user can request a replacement confirmation link', async ({ page }) => {
  const user = {
    id: crypto.randomUUID(),
    email: 'collector@example.com',
    roles: ['ROLE_USER'],
    emailVerifiedAt: null,
    createdAt: new Date().toISOString(),
  }
  let authorizationHeader: string | null = null

  await page.addInitScript((storedUser) => {
    localStorage.setItem('cardnest.authToken', 'test-token')
    localStorage.setItem('cardnest.authUser', JSON.stringify(storedUser))
  }, user)
  await page.route(/\/api\/v1\/auth\/email-verification\/resend$/, async (route) => {
    authorizationHeader = route.request().headers().authorization ?? null
    await route.fulfill({ status: 202 })
  })

  await page.goto('/dashboard')
  await expect(page.getByRole('heading', { name: 'Bevestig je e-mailadres' })).toBeVisible()
  await page.getByRole('button', { name: 'Nieuwe link aanvragen' }).click()

  await expect(page.getByText('Er is een nieuwe bevestigingslink aangevraagd.')).toBeVisible()
  expect(authorizationHeader).toBe('Bearer test-token')
})
