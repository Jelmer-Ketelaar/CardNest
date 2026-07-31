import { expect, test } from '@playwright/test'

function actionableOrder(status: 'paid' | 'shipped') {
  const now = new Date().toISOString()
  const buyerId = crypto.randomUUID()
  const sellerId = crypto.randomUUID()
  const history = [
    { id: crypto.randomUUID(), from: null, to: 'pending_payment', actor: { id: buyerId }, occurredAt: now },
    { id: crypto.randomUUID(), from: 'pending_payment', to: 'paid', actor: { id: buyerId }, occurredAt: now },
  ]
  if (status === 'shipped') {
    history.push({ id: crypto.randomUUID(), from: 'paid', to: 'shipped', actor: { id: sellerId }, occurredAt: now })
  }

  return {
    id: crypto.randomUUID(),
    status,
    buyer: { id: buyerId },
    seller: { id: sellerId },
    items: [{
      id: crypto.randomUUID(),
      listingId: crypto.randomUUID(),
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
    payment: { id: crypto.randomUUID(), status: 'succeeded', provider: 'fake', providerReference: 'fake_test' },
    statusHistory: history,
    availableActions: {
      markShipped: status === 'paid',
      confirmReceived: status === 'shipped',
    },
    paidAt: now,
    completedAt: null,
    createdAt: now,
    updatedAt: now,
  }
}

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
    statusHistory: [
      { id: crypto.randomUUID(), from: null, to: 'pending_payment', actor: { id: crypto.randomUUID() }, occurredAt: now },
      { id: crypto.randomUUID(), from: 'pending_payment', to: 'paid', actor: { id: crypto.randomUUID() }, occurredAt: now },
    ],
    availableActions: { markShipped: false, confirmReceived: false },
    paidAt: now,
    completedAt: null,
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

test('a seller can mark a paid order as shipped', async ({ page }) => {
  const order = actionableOrder('paid')
  const shippedOrder = {
    ...order,
    status: 'shipped',
    availableActions: { markShipped: false, confirmReceived: false },
    statusHistory: [
      ...order.statusHistory,
      { id: crypto.randomUUID(), from: 'paid', to: 'shipped', actor: order.seller, occurredAt: new Date().toISOString() },
    ],
  }

  await page.addInitScript(() => localStorage.setItem('cardnest.authToken', 'seller-token'))
  await page.route(/\/api\/v1\/me\/orders$/, async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ items: [order], total: 1 }) })
  })
  await page.route(new RegExp(`/api/v1/me/orders/${order.id}/mark-shipped$`), async (route) => {
    expect(route.request().method()).toBe('POST')
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(shippedOrder) })
  })

  await page.goto('/orders')
  await page.getByRole('button', { name: 'Markeer als verzonden' }).click()

  await expect(page.getByText('De order is als verzonden gemarkeerd.')).toBeVisible()
  await expect(page.getByText('Verzonden', { exact: true }).first()).toBeVisible()
  await expect(page.getByRole('button', { name: 'Markeer als verzonden' })).toHaveCount(0)
})

test('a buyer can confirm a shipped order as received', async ({ page }) => {
  const order = actionableOrder('shipped')
  const completedOrder = {
    ...order,
    status: 'completed',
    completedAt: new Date().toISOString(),
    availableActions: { markShipped: false, confirmReceived: false },
    statusHistory: [
      ...order.statusHistory,
      { id: crypto.randomUUID(), from: 'shipped', to: 'completed', actor: order.buyer, occurredAt: new Date().toISOString() },
    ],
  }

  await page.addInitScript(() => localStorage.setItem('cardnest.authToken', 'buyer-token'))
  await page.route(/\/api\/v1\/me\/orders$/, async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ items: [order], total: 1 }) })
  })
  await page.route(new RegExp(`/api/v1/me/orders/${order.id}/confirm-received$`), async (route) => {
    expect(route.request().method()).toBe('POST')
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(completedOrder) })
  })

  await page.goto('/orders')
  await page.getByRole('button', { name: 'Bevestig ontvangst' }).click()

  await expect(page.getByText('Ontvangst bevestigd. De order is voltooid.')).toBeVisible()
  await expect(page.getByText('Voltooid', { exact: true }).first()).toBeVisible()
  await expect(page.getByRole('button', { name: 'Bevestig ontvangst' })).toHaveCount(0)
})
