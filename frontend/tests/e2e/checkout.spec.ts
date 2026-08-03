import { expect, test, type Page } from '@playwright/test'

async function mockAuthoredReviews(page: Page, items: object[] = []): Promise<void> {
  await page.route(/\/api\/v1\/me\/reviews$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items, total: items.length }),
    })
  })
}

function actionableOrder(status: 'paid' | 'shipped' | 'completed') {
  const now = new Date().toISOString()
  const buyerId = crypto.randomUUID()
  const sellerId = crypto.randomUUID()
  const history = [
    { id: crypto.randomUUID(), from: null, to: 'pending_payment', actor: { id: buyerId }, occurredAt: now },
    { id: crypto.randomUUID(), from: 'pending_payment', to: 'paid', actor: { id: buyerId }, occurredAt: now },
  ]
  if (status === 'shipped' || status === 'completed') {
    history.push({ id: crypto.randomUUID(), from: 'paid', to: 'shipped', actor: { id: sellerId }, occurredAt: now })
  }
  if (status === 'completed') {
    history.push({ id: crypto.randomUUID(), from: 'shipped', to: 'completed', actor: { id: buyerId }, occurredAt: now })
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
      collectionImport: {
        available: status === 'completed',
        imported: false,
        collectionItemId: null,
        importedAt: null,
      },
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
    cancellation: null,
    refund: null,
    statusHistory: history,
    availableActions: {
      cancel: status === 'paid',
      markShipped: status === 'paid',
      confirmReceived: status === 'shipped',
    },
    paidAt: now,
    completedAt: status === 'completed' ? now : null,
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
      collectionImport: { available: false, imported: false, collectionItemId: null, importedAt: null },
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
    cancellation: null,
    refund: null,
    statusHistory: [
      { id: crypto.randomUUID(), from: null, to: 'pending_payment', actor: { id: crypto.randomUUID() }, occurredAt: now },
      { id: crypto.randomUUID(), from: 'pending_payment', to: 'paid', actor: { id: crypto.randomUUID() }, occurredAt: now },
    ],
    availableActions: { cancel: true, markShipped: false, confirmReceived: false },
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
  await mockAuthoredReviews(page)

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
    availableActions: { cancel: false, markShipped: false, confirmReceived: false },
    statusHistory: [
      ...order.statusHistory,
      { id: crypto.randomUUID(), from: 'paid', to: 'shipped', actor: order.seller, occurredAt: new Date().toISOString() },
    ],
  }

  await page.addInitScript(() => localStorage.setItem('cardnest.authToken', 'seller-token'))
  await page.route(/\/api\/v1\/me\/orders$/, async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ items: [order], total: 1 }) })
  })
  await mockAuthoredReviews(page)
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

test('a buyer can cancel a paid order and sees the refund state', async ({ page }) => {
  const order = {
    ...actionableOrder('paid'),
    availableActions: { cancel: true, markShipped: false, confirmReceived: false },
  }
  const cancellationReason = 'The seller cannot fulfil the order.'
  const refundedOrder = {
    ...order,
    status: 'refunded',
    cancellation: {
      reason: cancellationReason,
      cancelledBy: order.buyer,
      cancelledAt: new Date().toISOString(),
    },
    refund: { status: 'succeeded', amount: order.costs.total },
    availableActions: { cancel: false, markShipped: false, confirmReceived: false },
    statusHistory: [
      ...order.statusHistory,
      { id: crypto.randomUUID(), from: 'paid', to: 'cancelled', actor: order.buyer, occurredAt: new Date().toISOString() },
      { id: crypto.randomUUID(), from: 'cancelled', to: 'refunded', actor: order.buyer, occurredAt: new Date().toISOString() },
    ],
  }
  let cancellationPayload: Record<string, unknown> | null = null

  await page.addInitScript(() => localStorage.setItem('cardnest.authToken', 'buyer-token'))
  await page.route(/\/api\/v1\/me\/orders$/, async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ items: [order], total: 1 }) })
  })
  await mockAuthoredReviews(page)
  await page.route(new RegExp(`/api/v1/me/orders/${order.id}/cancel$`), async (route) => {
    expect(route.request().method()).toBe('POST')
    cancellationPayload = route.request().postDataJSON() as Record<string, unknown>
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(refundedOrder) })
  })

  await page.goto('/orders')
  await page.getByRole('button', { name: 'Bestelling annuleren' }).click()
  await expect(page.getByRole('dialog', { name: 'Bestelling annuleren' })).toBeVisible()
  await page.getByLabel('Annuleringsreden').fill(cancellationReason)
  await page.getByRole('button', { name: 'Annuleren en terugbetalen' }).click()

  await expect(page.getByText('Order geannuleerd en € 9,00 terugbetaald.')).toBeVisible()
  await expect(page.getByText('Terugbetaald', { exact: true }).first()).toBeVisible()
  await expect(page.getByRole('button', { name: 'Bestelling annuleren' })).toHaveCount(0)
  expect(cancellationPayload).toEqual({ reason: cancellationReason })
})

test('a buyer can confirm a shipped order as received', async ({ page }) => {
  const order = actionableOrder('shipped')
  const completedOrder = {
    ...order,
    status: 'completed',
    completedAt: new Date().toISOString(),
    availableActions: { cancel: false, markShipped: false, confirmReceived: false },
    statusHistory: [
      ...order.statusHistory,
      { id: crypto.randomUUID(), from: 'shipped', to: 'completed', actor: order.buyer, occurredAt: new Date().toISOString() },
    ],
  }

  await page.addInitScript(() => localStorage.setItem('cardnest.authToken', 'buyer-token'))
  await page.route(/\/api\/v1\/me\/orders$/, async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ items: [order], total: 1 }) })
  })
  await mockAuthoredReviews(page)
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

test('a buyer confirms condition and storage before importing a purchase', async ({ page }) => {
  const order = actionableOrder('completed')
  const locationId = crypto.randomUUID()
  const collectionItemId = crypto.randomUUID()
  const importedOrder = {
    ...order,
    items: order.items.map((item) => ({
      ...item,
      collectionImport: {
        available: false,
        imported: true,
        collectionItemId,
        importedAt: new Date().toISOString(),
      },
    })),
  }
  let importPayload: Record<string, unknown> | null = null

  await page.addInitScript(() => localStorage.setItem('cardnest.authToken', 'buyer-token'))
  await page.route(/\/api\/v1\/me\/orders$/, async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ items: [order], total: 1 }) })
  })
  await mockAuthoredReviews(page)
  await page.route(/\/api\/v1\/me\/storage-locations$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        items: [{
          id: locationId,
          name: 'Nieuwe aanwinsten',
          type: 'box',
          parentId: null,
          path: [{ id: locationId, name: 'Nieuwe aanwinsten', type: 'box' }],
          currentItemCount: 0,
          createdAt: new Date().toISOString(),
          updatedAt: new Date().toISOString(),
        }],
        total: 1,
      }),
    })
  })
  await page.route(new RegExp(`/api/v1/me/orders/${order.id}/items/${order.items[0].id}/import-to-collection$`), async (route) => {
    importPayload = route.request().postDataJSON() as Record<string, unknown>
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(importedOrder) })
  })

  await page.goto('/orders')
  await page.getByRole('button', { name: 'Voeg toe aan collectie' }).click()
  await expect(page.getByRole('dialog', { name: 'Mudkip toevoegen' })).toBeVisible()
  await page.getByLabel('Taal').selectOption('fr')
  await page.getByLabel('Conditie na ontvangst').selectOption('excellent')
  await page.getByLabel('Fysieke opslaglocatie').selectOption(locationId)
  await page.getByLabel('Notities').fill('Gecontroleerd na ontvangst.')
  await page.getByRole('button', { name: 'Bevestig en voeg toe' }).click()

  await expect(page.getByText('Mudkip is aan je collectie toegevoegd.')).toBeVisible()
  await expect(page.getByText('Aan collectie toegevoegd')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Voeg toe aan collectie' })).toHaveCount(0)
  expect(importPayload).toEqual({
    language: 'fr',
    condition: 'excellent',
    storageLocationId: locationId,
    notes: 'Gecontroleerd na ontvangst.',
  })
})

test('an order participant can review a completed transaction once', async ({ page }) => {
  const order = actionableOrder('completed')
  let reviewPayload: Record<string, unknown> | null = null

  await page.addInitScript(() => localStorage.setItem('cardnest.authToken', 'buyer-token'))
  await page.route(/\/api\/v1\/me\/orders$/, async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ items: [order], total: 1 }) })
  })
  await mockAuthoredReviews(page)
  await page.route(new RegExp(`/api/v1/me/orders/${order.id}/reviews$`), async (route) => {
    reviewPayload = route.request().postDataJSON() as Record<string, unknown>
    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({
        id: crypto.randomUUID(),
        orderId: order.id,
        reviewer: order.buyer,
        reviewedUser: order.seller,
        scores: {
          overall: 5,
          descriptionAccuracy: 4,
          packaging: 5,
          communication: 4,
          shippingSpeed: 3,
        },
        comment: 'Netjes verpakt en duidelijk gecommuniceerd.',
        createdAt: new Date().toISOString(),
      }),
    })
  })

  await page.goto('/orders')
  await page.getByRole('button', { name: 'Schrijf review' }).click()
  await expect(page.getByRole('dialog', { name: 'Review plaatsen' })).toBeVisible()
  await page.getByLabel('Beschrijving correct').selectOption('4')
  await page.getByLabel('Communicatie').selectOption('4')
  await page.getByLabel('Verzendsnelheid').selectOption('3')
  await page.getByLabel('Toelichting (optioneel)').fill('Netjes verpakt en duidelijk gecommuniceerd.')
  await page.getByRole('button', { name: 'Plaats review' }).click()

  await expect(page.getByText('Je review is geplaatst.')).toBeVisible()
  await expect(page.getByText('Review geplaatst')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Schrijf review' })).toHaveCount(0)
  expect(reviewPayload).toEqual({
    overallScore: 5,
    descriptionAccuracyScore: 4,
    packagingScore: 5,
    communicationScore: 4,
    shippingSpeedScore: 3,
    comment: 'Netjes verpakt en duidelijk gecommuniceerd.',
  })
})
