import { expect, test } from '@playwright/test'

function listingFixture(status: 'draft' | 'active' = 'active') {
  const now = new Date().toISOString()
  return {
    id: crypto.randomUUID(),
    status,
    seller: { id: crypto.randomUUID() },
    card: {
      id: crypto.randomUUID(),
      name: 'Mudkip',
      number: '19/25',
      set: { id: crypto.randomUUID(), name: "McDonald's Collection 2021" },
    },
    language: 'en',
    variant: 'holo',
    condition: 'near_mint',
    price: { amount: 750, currency: 'EUR' },
    description: 'Clean copy.',
    images: [
      { id: crypto.randomUUID(), side: 'front', url: 'https://placehold.co/400x560?text=Front' },
      { id: crypto.randomUUID(), side: 'back', url: 'https://placehold.co/400x560?text=Back' },
    ],
    shippingMethods: [
      {
        code: 'letter_mail',
        price: { amount: 150, currency: 'EUR' },
        totalPrice: { amount: 900, currency: 'EUR' },
      },
      {
        code: 'pickup',
        price: { amount: 0, currency: 'EUR' },
        totalPrice: { amount: 750, currency: 'EUR' },
      },
    ],
    priceHistory: [{ previousPrice: null, newPrice: { amount: 750, currency: 'EUR' }, changedAt: now }],
    expiresAt: null,
    publishedAt: status === 'active' ? now : null,
    createdAt: now,
    updatedAt: now,
  }
}

test('a visitor can compare the complete listing price', async ({ page }) => {
  const listing = listingFixture()
  await page.route(/\/api\/v1\/listings\?.*$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: [listing], page: 1, perPage: 24, total: 1, totalPages: 1 }),
    })
  })
  await page.route(new RegExp(`/api/v1/listings/${listing.id}$`), async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(listing) })
  })

  await page.goto('/marketplace')
  await expect(page.getByRole('heading', { name: 'Mudkip 19/25' })).toBeVisible()
  await expect(page.getByText(/vanaf\s+€\s*7,50 totaal/)).toBeVisible()
  const openListingButton = page.getByRole('button', { name: 'Bekijk aanbieding' })
  await openListingButton.click()
  await expect(page).toHaveURL(`/marketplace/listings/${listing.id}`)
  await expect(page.getByRole('heading', { name: 'Volledige prijzen' })).toBeVisible()
  await expect(page.getByText(/€\s*9,00 totaal/)).toBeVisible()
  await expect(page.locator('.shipping-totals').getByText(/^€\s*7,50 totaal$/)).toBeVisible()
  await expect(page.getByRole('link', { name: 'Bekijk reputatie van deze verkoper' })).toBeFocused()

  await page.keyboard.press('Escape')

  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(page).toHaveURL('/marketplace')
  await expect(openListingButton).toBeFocused()
})

test('a collector can publish a physical collection item', async ({ page }) => {
  const collectionItemId = crypto.randomUUID()
  const collectionItem = {
    id: collectionItemId,
    card: {
      id: crypto.randomUUID(),
      name: 'Mudkip',
      number: '19/25',
      imageUrl: null,
      thumbnailUrl: null,
      set: { id: crypto.randomUUID(), name: "McDonald's Collection 2021" },
    },
    language: 'en',
    variant: { id: crypto.randomUUID(), code: 'holo' },
    condition: 'near_mint',
    status: 'in_collection',
    purchase: { price: null, purchasedAt: null, sellerName: null },
    notes: null,
    duplicate: { ownedQuantity: 1, isDuplicate: false },
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  }
  let listing: ReturnType<typeof listingFixture> | null = null
  let createRequestBody: string | null = null

  await page.addInitScript(() => localStorage.setItem('cardnest.authToken', 'test-token'))
  await page.route(/\/api\/v1\/me\/listings$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: listing ? [listing] : [], page: 1, perPage: 1, total: listing ? 1 : 0, totalPages: listing ? 1 : 0 }),
    })
  })
  await page.route(/\/api\/v1\/me\/collection-items\?.*$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: listing ? [] : [collectionItem], page: 1, perPage: 100, total: listing ? 0 : 1, totalPages: listing ? 0 : 1 }),
    })
  })
  await page.route(/\/api\/v1\/listings$/, async (route) => {
    createRequestBody = route.request().postData()
    listing = { ...listingFixture('draft'), collectionItemId }
    await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify(listing) })
  })
  await page.route(/\/api\/v1\/listings\/[^/]+\/publish$/, async (route) => {
    if (listing) listing.status = 'active'
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(listing) })
  })

  await page.goto(`/my-listings?itemId=${collectionItemId}`)
  await page.getByLabel('Vaste vraagprijs (centen)').fill('750')
  await expect(page.getByText(/externe afbeeldingslinks worden niet gevraagd/i)).toBeVisible()
  await expect(page.getByLabel('HTTPS-foto voorkant')).toHaveCount(0)
  await expect(page.getByLabel('HTTPS-foto achterkant')).toHaveCount(0)
  await page.getByRole('button', { name: 'Concept opslaan' }).click()

  expect(createRequestBody).toBe(JSON.stringify({
    collectionItemId,
    price: 750,
    currency: 'EUR',
    pickupAllowed: false,
    letterMailPrice: 150,
  }))
  await expect(page.getByText('Concept opgeslagen. Publiceer de listing wanneer je klaar bent.')).toBeVisible()
  await expect(page.getByText('Concept', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Publiceren', exact: true }).click()

  await expect(page.getByText('De conceptlisting is gepubliceerd.')).toBeVisible()
  await expect(page.getByText('Actief', { exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Mudkip · 19/25' })).toBeVisible()
})
