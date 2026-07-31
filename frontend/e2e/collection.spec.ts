import { expect, test } from '@playwright/test'

test('a collector can register separate physical cards', async ({ page }) => {
  const cardId = crypto.randomUUID()
  const variantId = crypto.randomUUID()
  const setId = crypto.randomUUID()
  const collectionItem = {
    id: crypto.randomUUID(),
    card: {
      id: cardId,
      name: 'Mudkip',
      number: '19/25',
      imageUrl: null,
      thumbnailUrl: null,
      set: { id: setId, name: "McDonald's Collection 2021" },
    },
    language: 'en',
    variant: { id: variantId, code: 'holo' },
    condition: 'near_mint',
    status: 'in_collection',
    purchase: { price: { amount: 350, currency: 'EUR' }, purchasedAt: null, sellerName: null },
    notes: null,
    duplicate: { ownedQuantity: 2, isDuplicate: true },
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  }
  let savedItems: typeof collectionItem[] = []

  await page.addInitScript(() => localStorage.setItem('cardnest.authToken', 'test-token'))
  await page.route(/\/api\/v1\/me\/storage-locations$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: [], total: 0 }),
    })
  })
  await page.route(/\/api\/v1\/me\/collection-items(?:\?.*)?$/, async (route) => {
    if (route.request().method() === 'POST') {
      savedItems = [collectionItem, { ...collectionItem, id: crypto.randomUUID() }]
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ created: 2, items: savedItems }),
      })
      return
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: savedItems, page: 1, perPage: 24, total: savedItems.length, totalPages: savedItems.length ? 1 : 0 }),
    })
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

  await page.goto('/collection')
  await page.locator('#catalog-query').fill('Mudkip')
  await page.getByRole('button', { name: 'Zoek in catalogus' }).click()
  await page.getByRole('button', { name: /Mudkip · 19\/25/ }).click()
  await page.getByLabel('Aantal').fill('2')
  await page.getByLabel('Aankoopprijs per kaart (centen)').fill('350')
  await page.getByRole('button', { name: 'Aan collectie toevoegen' }).click()

  await expect(page.getByText('2 fysieke kaarten zijn afzonderlijk toegevoegd.')).toBeVisible()
  await expect(page.getByText('2 in bezit').first()).toBeVisible()
  await expect(page.getByRole('heading', { name: /Mudkip 19\/25/ }).first()).toBeVisible()
})
