import { expect, test } from '@playwright/test'

test('a collector can create a binder and place a card visually', async ({ page }) => {
  const itemId = crypto.randomUUID()
  const cardId = crypto.randomUUID()
  const collectionItem = {
    id: itemId,
    card: {
      id: cardId,
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
  let binder: Record<string, unknown> | null = null

  function createBinderResponse(): Record<string, unknown> {
    const slots = Array.from({ length: 4 }, (_, index) => ({
      id: crypto.randomUUID(),
      position: index + 1,
      row: Math.floor(index / 2) + 1,
      column: (index % 2) + 1,
      collectionItem: null,
      placeholder: null,
    }))
    return {
      id: crypto.randomUUID(),
      name: 'Water cards',
      rows: 2,
      columns: 2,
      pageCount: 1,
      surfaceCount: 1,
      doubleSided: false,
      layoutStrategy: 'custom',
      slotCount: 4,
      occupiedSlots: 0,
      parentLocation: null,
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
      pages: [{ id: crypto.randomUUID(), number: 1, side: 'front', slots }],
    }
  }

  await page.addInitScript(() => localStorage.setItem('cardnest.authToken', 'test-token'))
  await page.route(/\/api\/v1\/me\/binders$/, async (route) => {
    if (route.request().method() === 'POST') {
      binder = createBinderResponse()
      await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify(binder) })
      return
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: binder ? [binder] : [], total: binder ? 1 : 0 }),
    })
  })
  await page.route(/\/api\/v1\/me\/binders\/[^/]+$/, async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(binder) })
  })
  await page.route(/\/api\/v1\/me\/binders\/[^/]+\/slots\/[^/]+\/assign$/, async (route) => {
    const current = binder as {
      occupiedSlots: number
      pages: Array<{ slots: Array<Record<string, unknown>> }>
    }
    const slotId = route.request().url().split('/').at(-2)
    const slot = current.pages[0].slots.find((candidate) => candidate.id === slotId)
    if (slot) {
      slot.collectionItem = {
        id: itemId,
        card: { id: cardId, name: 'Mudkip', number: '19/25', thumbnailUrl: null },
        language: 'en',
        variant: 'holo',
        condition: 'near_mint',
      }
      current.occupiedSlots = 1
    }
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(current) })
  })
  await page.route(/\/api\/v1\/me\/collection-items\?.*$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: [collectionItem], page: 1, perPage: 100, total: 1, totalPages: 1 }),
    })
  })
  await page.route(/\/api\/v1\/me\/storage-locations$/, async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ items: [], total: 0 }) })
  })

  await page.goto('/binders')
  await page.getByLabel('Naam').fill('Water cards')
  await page.getByLabel('Rijen').fill('2')
  await page.getByLabel('Kolommen').fill('2')
  await page.getByLabel('Fysieke pagina’s').fill('1')
  await page.getByRole('button', { name: 'Binder aanmaken' }).click()

  await expect(page.locator('.binder-slot')).toHaveCount(4)
  const paletteCard = page.locator('.palette-items button').filter({ hasText: 'Mudkip' })
  const targetSlot = page.locator('.binder-slot').first()
  const dataTransfer = await page.evaluateHandle(() => new DataTransfer())
  await paletteCard.dispatchEvent('dragstart', { dataTransfer })
  await targetSlot.dispatchEvent('dragover', { dataTransfer })
  await targetSlot.dispatchEvent('drop', { dataTransfer })

  await expect(targetSlot.getByText('Mudkip')).toBeVisible()
  await expect(page.getByText('1 van 4 vakken gevuld')).toBeVisible()
})
