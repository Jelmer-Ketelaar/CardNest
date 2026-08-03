import { expect, test } from '@playwright/test'

test('a visitor can view a collector reputation without private order data', async ({ page }) => {
  const userId = crypto.randomUUID()
  const reviewerId = crypto.randomUUID()
  const createdAt = new Date().toISOString()

  await page.route(new RegExp(`/api/v1/users/${userId}/reputation\\?.*$`), async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        user: { id: userId, memberSince: '2025-01-15T10:00:00+00:00' },
        summary: {
          reviewCount: 1,
          averageOverall: 5,
          averages: {
            descriptionAccuracy: 5,
            packaging: 5,
            communication: 4,
            shippingSpeed: 5,
          },
        },
        reviews: {
          items: [{
            id: crypto.randomUUID(),
            reviewer: { id: reviewerId },
            reviewedUser: { id: userId },
            scores: {
              overall: 5,
              descriptionAccuracy: 5,
              packaging: 5,
              communication: 4,
              shippingSpeed: 5,
            },
            comment: 'Betrouwbare verkoper en stevig verpakt.',
            createdAt,
          }],
          page: 1,
          perPage: 20,
          total: 1,
          totalPages: 1,
        },
      }),
    })
  })

  await page.goto(`/users/${userId}`)

  await expect(page).toHaveURL(`/users/${userId}`)
  await expect(page.getByRole('heading', { name: 'Reputatie' })).toBeVisible()
  await expect(page.getByRole('heading', { name: '5.00 / 5' })).toBeVisible()
  await expect(page.getByText('Betrouwbare verkoper en stevig verpakt.')).toBeVisible()
  await expect(page.getByText('1 review')).toBeVisible()
  await expect(page.getByText('Order')).toHaveCount(0)
})
