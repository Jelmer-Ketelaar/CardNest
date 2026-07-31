import { expect, test } from '@playwright/test'

test('a collector can create a wishlist and add a missing card', async ({ page }) => {
  const wishlistId = crypto.randomUUID()
  const cardId = crypto.randomUUID()
  const variantId = crypto.randomUUID()
  const setId = crypto.randomUUID()
  const now = new Date().toISOString()
  let wishlist: Record<string, unknown> | null = null

  function emptyWishlist(): Record<string, unknown> {
    return {
      id: wishlistId,
      name: 'Mudkip master set',
      itemCount: 0,
      completedItemCount: 0,
      progress: { desiredQuantity: 0, fulfilledQuantity: 0, remainingQuantity: 0, percentage: 0 },
      setProgress: [],
      createdAt: now,
      updatedAt: now,
      items: [],
    }
  }

  await page.addInitScript(() => localStorage.setItem('cardnest.authToken', 'test-token'))
  await page.route(/\/api\/v1\/me\/wishlists$/, async (route) => {
    if (route.request().method() === 'POST') {
      wishlist = emptyWishlist()
      await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify(wishlist) })
      return
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: wishlist ? [wishlist] : [], total: wishlist ? 1 : 0 }),
    })
  })
  await page.route(/\/api\/v1\/me\/wishlists\/[^/]+$/, async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(wishlist) })
  })
  await page.route(/\/api\/v1\/cards\?.*$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        items: [{
          id: cardId,
          name: 'Mudkip',
          number: '19/25',
          rarity: 'Promo',
          imageUrl: null,
          thumbnailUrl: null,
          set: { id: setId, name: "McDonald's Collection 2021" },
          variants: [{ id: variantId, code: 'holo' }],
        }],
        page: 1,
        perPage: 8,
        total: 1,
        totalPages: 1,
      }),
    })
  })
  await page.route(/\/api\/v1\/me\/wishlists\/[^/]+\/items$/, async (route) => {
    const current = emptyWishlist()
    current.itemCount = 1
    current.progress = { desiredQuantity: 1, fulfilledQuantity: 0, remainingQuantity: 1, percentage: 0 }
    current.setProgress = [{
      id: setId,
      name: "McDonald's Collection 2021",
      desiredQuantity: 1,
      fulfilledQuantity: 0,
      progress: { desiredQuantity: 1, fulfilledQuantity: 0, remainingQuantity: 1, percentage: 0 },
    }]
    current.items = [{
      id: crypto.randomUUID(),
      card: {
        id: cardId,
        name: 'Mudkip',
        number: '19/25',
        thumbnailUrl: null,
        set: { id: setId, name: "McDonald's Collection 2021" },
      },
      language: 'en',
      variant: { id: variantId, code: 'holo' },
      minimumCondition: 'excellent',
      maxPrice: { amount: 800, currency: 'EUR' },
      desiredQuantity: 1,
      fulfilledQuantity: 0,
      remainingQuantity: 1,
      completed: false,
      completedAt: null,
      priority: 'wanted',
      notes: null,
      createdAt: now,
      updatedAt: now,
    }]
    wishlist = current
    await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify(current) })
  })

  await page.goto('/wishlists')
  await page.getByLabel('Naam').fill('Mudkip master set')
  await page.getByRole('button', { name: 'Wishlist maken' }).click()
  await expect(page.getByRole('heading', { name: 'Mudkip master set' })).toBeVisible()

  await page.getByLabel('Zoek op kaartnaam').fill('Mudkip')
  await page.getByRole('button', { name: 'Zoeken' }).click()
  await page.getByRole('button', { name: /Mudkip · 19\/25/ }).click()
  await page.getByLabel('Gewenste taal').selectOption('en')
  await page.getByLabel('Gewenste variant').selectOption(variantId)
  await page.getByLabel('Minimale conditie').selectOption('excellent')
  await page.getByLabel('Maximumprijs (centen)').fill('800')
  await page.getByLabel('Prioriteit').selectOption('wanted')
  await page.getByRole('button', { name: 'Kaart toevoegen' }).click()

  await expect(page.locator('.wishlist-card').getByRole('heading', { name: 'Mudkip · 19/25' })).toBeVisible()
  await expect(page.getByText('1 van 1 kaarten ontbreken nog.')).toBeVisible()
  await expect(page.getByText(/€\s*8,00/)).toBeVisible()
})
