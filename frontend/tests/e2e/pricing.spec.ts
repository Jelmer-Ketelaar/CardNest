import { expect, test } from '@playwright/test'

test('a visitor can view a source-current price and daily history', async ({ page }) => {
  const cardId = crypto.randomUUID()
  const setId = crypto.randomUUID()
  const observedAt = '2026-08-02T10:05:00+00:00'
  const card = {
    id: cardId,
    name: 'Mudkip',
    number: '19/25',
    totalNumberInSet: '25',
    rarity: 'Promo',
    cardType: 'Pokemon',
    primaryCollectible: 'Mudkip',
    illustrator: 'Test Illustrator',
    imageUrl: null,
    thumbnailUrl: null,
    releaseDate: '2021-10-22',
    metadata: {},
    set: {
      id: setId,
      name: "McDonald's Collection 2021",
      code: 'mcd21',
      series: { id: crypto.randomUUID(), name: 'Promos' },
      language: 'en',
      releaseDate: '2021-10-22',
      logoUrl: null,
      symbolUrl: null,
      printedTotal: 25,
      total: 25,
      description: null,
      status: 'active',
      createdAt: observedAt,
      updatedAt: observedAt,
    },
    variants: [{ id: crypto.randomUUID(), code: 'holo' }],
    externalReferences: [],
    createdAt: observedAt,
    updatedAt: observedAt,
  }
  const snapshot = {
    variant: 'foil',
    lowest: { amount: 750, currency: 'EUR' },
    average: { amount: 825, currency: 'EUR' },
    trend: { amount: 800, currency: 'EUR' },
    averageOneDay: { amount: 810, currency: 'EUR' },
    averageSevenDays: { amount: 790, currency: 'EUR' },
    averageThirtyDays: { amount: 775, currency: 'EUR' },
    sourceUpdatedAt: '2026-08-02T08:03:04+00:00',
    observedAt,
    observedOn: '2026-08-02',
  }

  await page.route(new RegExp(`/api/v1/cards/${cardId}$`), async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(card) })
  })
  await page.route(new RegExp(`/api/v1/cards/${cardId}/prices[?]days=30$`), async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        cardId,
        source: 'tcgdex',
        market: 'cardmarket',
        currency: 'EUR',
        refreshStatus: 'refreshed',
        checkedAt: observedAt,
        prices: [{ ...snapshot, change: { amount: 50, currency: 'EUR', percentage: 6.7 } }],
        history: [snapshot],
        disclaimer: 'Externe prijsindicatie; geen gegarandeerde verkoopopbrengst.',
      }),
    })
  })

  await page.goto(`/catalog/cards/${cardId}`)

  await expect(page.getByRole('heading', { name: 'Cardmarket-prijsindicatie' })).toBeVisible()
  await expect(page.getByText('Catalogustaal: Engels · Niet conditie-specifiek')).toBeVisible()
  const currentPrice = page.getByRole('article')
  await expect(currentPrice.getByText(/€\s*7,50/)).toBeVisible()
  await expect(currentPrice.getByText(/\+€\s*0,50 \(\+6,7%\) sinds vorige meting/)).toBeVisible()
  await expect(page.getByRole('region', { name: 'Dagelijkse prijshistorie' })).toContainText('2 aug 2026')
})
