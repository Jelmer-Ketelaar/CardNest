import { expect, test } from '@playwright/test'

test('a collector can add a listing and complete a split-ready test checkout', async ({ page }) => {
  const sellerId = crypto.randomUUID()
  const listingId = crypto.randomUUID()
  const cartItemId = crypto.randomUUID()
  const orderId = crypto.randomUUID()
  const paymentId = crypto.randomUUID()
  const now = new Date().toISOString()
  const listing = {
    id: listingId,
    status: 'active',
    seller: { id: sellerId },
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
    publishedAt: now,
    createdAt: now,
    updatedAt: now,
  }
  const cart = {
    id: crypto.randomUUID(),
    sellerGroups: [{
      seller: { id: sellerId },
      items: [{
        id: cartItemId,
        listing,
        addedPrice: { amount: 750, currency: 'EUR' },
        priceChanged: false,
        available: true,
        addedAt: now,
      }],
      itemSubtotal: { amount: 750, currency: 'EUR' },
      shippingMethods: listing.shippingMethods,
    }],
    itemCount: 1,
    checkoutAvailable: true,
  }
  const order = {
    id: orderId,
    status: 'paid',
    buyer: { id: crypto.randomUUID() },
    seller: { id: sellerId },
    items: [{
      id: crypto.randomUUID(),
      listingId,
      card: listing.card,
      language: listing.language,
      variant: listing.variant,
      condition: listing.condition,
      price: listing.price,
    }],
    shippingMethod: 'letter_mail',
    costs: {
      items: { amount: 750, currency: 'EUR' },
      shipping: { amount: 150, currency: 'EUR' },
      serviceFee: { amount: 0, currency: 'EUR' },
      total: { amount: 900, currency: 'EUR' },
    },
    shippingAddress: {
      fullName: 'Sam Collector',
      addressLine1: 'Dorpsstraat 1',
      addressLine2: null,
      postalCode: '1234 AB',
      city: 'Utrecht',
      countryCode: 'NL',
    },
    payment: { id: paymentId, status: 'succeeded', provider: 'fake', providerReference: 'fake_test' },
    paidAt: now,
    createdAt: now,
    updatedAt: now,
  }
  let checkoutPayload: Record<string, unknown> | null = null

  await page.addInitScript(() => localStorage.setItem('cardnest.authToken', 'test-token'))
  await page.route(/\/api\/v1\/listings\?.*$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: [listing], page: 1, perPage: 24, total: 1, totalPages: 1 }),
    })
  })
  await page.route(new RegExp(`/api/v1/listings/${listingId}$`), async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(listing) })
  })
  await page.route(/\/api\/v1\/me\/cart\/items$/, async (route) => {
    await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify(cart) })
  })
  await page.route(/\/api\/v1\/me\/cart$/, async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(cart) })
  })
  await page.route(/\/api\/v1\/me\/cart\/checkout$/, async (route) => {
    checkoutPayload = route.request().postDataJSON() as Record<string, unknown>
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        payment: {
          id: paymentId,
          status: 'succeeded',
          provider: 'fake',
          providerReference: 'fake_test',
          amount: { amount: 900, currency: 'EUR' },
        },
        orders: [order],
      }),
    })
  })
  await page.route(/\/api\/v1\/me\/orders$/, async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ items: [order], total: 1 }) })
  })

  await page.goto('/marketplace')
  await page.getByRole('button', { name: 'Bekijk aanbieding' }).click()
  await page.getByRole('button', { name: 'In winkelmand' }).click()
  await expect(page.getByText(/Mudkip is toegevoegd/)).toBeVisible()
  await page.getByRole('link', { name: 'Winkelmand' }).first().click()

  await expect(page.getByRole('heading', { name: 'Winkelmand' })).toBeVisible()
  await expect(page.getByLabel('Brievenbuspost')).toBeChecked()
  await page.getByLabel('Volledige naam').fill('Sam Collector')
  await page.getByLabel('Straat en huisnummer').fill('Dorpsstraat 1')
  await page.getByLabel('Postcode').fill('1234 AB')
  await page.getByLabel('Plaats').fill('Utrecht')
  await page.getByRole('button', { name: 'Testbetaling afronden' }).click()

  await expect(page).toHaveURL(/\/orders\?checkedOut=1$/)
  await expect(page.getByText(/Testbetaling geslaagd/)).toBeVisible()
  await expect(page.getByRole('heading', { name: `Order ${orderId.slice(0, 8)}` })).toBeVisible()
  expect(checkoutPayload).toMatchObject({
    shippingMethods: { [sellerId]: 'letter_mail' },
    fullName: 'Sam Collector',
    postalCode: '1234 AB',
    countryCode: 'NL',
  })
})
