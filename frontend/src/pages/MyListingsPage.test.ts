import { createApp, nextTick, type App } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Listing } from '../types/marketplace'

const mocks = vi.hoisted(() => {
  class ApiError extends Error {
    public constructor(
      message: string,
      public readonly status: number,
      public readonly code: string,
      public readonly details: Record<string, string[]> = {},
    ) {
      super(message)
    }
  }

  return {
    ApiError,
    apiRequest: vi.fn(),
    route: { query: {} as Record<string, string> },
  }
})

vi.mock('../api/client', () => ({
  ApiError: mocks.ApiError,
  apiRequest: mocks.apiRequest,
}))

vi.mock('vue-router', () => ({
  useRoute: () => mocks.route,
}))

import MyListingsPage from './MyListingsPage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('MyListingsPage', () => {
  beforeEach(() => {
    mocks.apiRequest.mockReset()
    mocks.route.query = {}
  })

  afterEach(() => {
    for (const page of mountedPages.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('lists seller data only and excludes collection items with an existing draft', async () => {
    // Arrange
    const draftItem = collectionItem({ id: 'draft-item', card: { ...catalogCard(), name: 'Pikachu' } })
    const availableItem = collectionItem()
    mockInitialRequests([listingFixture({ collectionItemId: draftItem.id })], [draftItem, availableItem])

    // Act
    const { root } = mountPage()
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/me/listings')
    expect(mocks.apiRequest).toHaveBeenCalledWith('/me/collection-items?status=in_collection&perPage=100')
    const options = Array.from(root.querySelectorAll<HTMLSelectElement>('select option')).map((option) => option.textContent)
    expect(options).toContain('Mudkip · 19/25 · Near Mint')
    expect(options).not.toContain('Pikachu · 19/25 · Near Mint')
  })

  it('saves a valid listing as a draft without publishing it', async () => {
    // Arrange
    const item = collectionItem()
    const draft = listingFixture({ collectionItemId: item.id })
    let listings = [] as ReturnType<typeof listingFixture>[]
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/listings') return Promise.resolve(paginated(listings))
      if (path === '/me/collection-items?status=in_collection&perPage=100') return Promise.resolve(paginated([item]))
      if (path === '/listings' && options?.method === 'POST') {
        listings = [draft]

        return Promise.resolve(draft)
      }

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })
    const { root } = mountPage()
    await flush()
    fillRequiredListingFields(root)

    // Act
    clickButton(root, 'Concept opslaan')
    await flush(5)

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/listings', {
      method: 'POST',
      body: JSON.stringify({
        collectionItemId: item.id,
        price: 750,
        currency: 'EUR',
        pickupAllowed: false,
        letterMailPrice: 150,
      }),
    })
    expect(mocks.apiRequest.mock.calls.some(([path]) => path === `/listings/${draft.id}/publish`)).toBe(false)
    expect(root.textContent).toContain('Concept opgeslagen. Publiceer de listing wanneer je klaar bent.')
    expect(root.textContent).toContain('Concept')
  })

  it('renders accessible API validation feedback for listing fields', async () => {
    // Arrange
    const item = collectionItem()
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/listings') return Promise.resolve(paginated([]))
      if (path === '/me/collection-items?status=in_collection&perPage=100') return Promise.resolve(paginated([item]))
      if (path === '/listings' && options?.method === 'POST') {
        return Promise.reject(new mocks.ApiError('Controleer de listinggegevens.', 422, 'validation_failed', {
          price: ['De vraagprijs is verplicht.'],
        }))
      }

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })
    const { root } = mountPage()
    await flush()
    fillRequiredListingFields(root)

    // Act
    clickButton(root, 'Concept opslaan')
    await flush(5)

    // Assert
    expect(root.querySelector('[role="alert"]')?.textContent).toContain('Controleer de listinggegevens.')
    expect(root.querySelector('#listing-price-error')?.textContent).toContain('De vraagprijs is verplicht.')
    expect(root.querySelector<HTMLInputElement>('#listing-price')?.getAttribute('aria-invalid')).toBe('true')
  })

  it('surfaces a lifecycle conflict and refreshes the seller listing state', async () => {
    // Arrange
    const draft = listingFixture()
    let sellerListingRequests = 0
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/listings') {
        sellerListingRequests += 1

        return Promise.resolve(paginated([draft]))
      }
      if (path === '/me/collection-items?status=in_collection&perPage=100') return Promise.resolve(paginated([]))
      if (path === `/listings/${draft.id}/publish` && options?.method === 'POST') {
        return Promise.reject(new mocks.ApiError('Deze fysieke kaart heeft al een openstaande listing.', 409, 'listing_already_exists'))
      }

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })
    const { root } = mountPage()
    await flush()

    // Act
    clickButton(root, 'Publiceren')
    await flush(5)

    // Assert
    expect(root.querySelector('[role="alert"]')?.textContent).toContain('Deze fysieke kaart heeft al een openstaande listing.')
    expect(sellerListingRequests).toBeGreaterThanOrEqual(2)
    expect(root.textContent).toContain('Concept')
  })

  it('cancels an active seller listing and removes its lifecycle action', async () => {
    // Arrange
    let listing = listingFixture({ status: 'active' })
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/listings') return Promise.resolve(paginated([listing]))
      if (path === '/me/collection-items?status=in_collection&perPage=100') return Promise.resolve(paginated([]))
      if (path === `/listings/${listing.id}/cancel` && options?.method === 'POST') {
        listing = { ...listing, status: 'cancelled' }

        return Promise.resolve(listing)
      }

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })
    const { root } = mountPage()
    await flush()

    // Act
    clickButton(root, 'Annuleren')
    await flush(5)

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith(`/listings/${listing.id}/cancel`, { method: 'POST' })
    expect(root.textContent).toContain('De listing is geannuleerd; de kaart is weer beschikbaar in je collectie.')
    expect(root.textContent).toContain('Geannuleerd')
    expect(Array.from(root.querySelectorAll('button')).some((button) => button.textContent?.trim() === 'Annuleren')).toBe(false)
  })
})

function mockInitialRequests(listings: ReturnType<typeof listingFixture>[], items: ReturnType<typeof collectionItem>[]): void {
  mocks.apiRequest.mockImplementation((path: string) => {
    if (path === '/me/listings') return Promise.resolve(paginated(listings))
    if (path === '/me/collection-items?status=in_collection&perPage=100') return Promise.resolve(paginated(items))

    return Promise.reject(new Error(`Unexpected request: ${path}`))
  })
}

function paginated<T>(items: T[]) {
  return { items, page: 1, perPage: Math.max(1, items.length), total: items.length, totalPages: items.length === 0 ? 0 : 1 }
}

function catalogCard() {
  return {
    id: 'card-id',
    name: 'Mudkip',
    number: '19/25',
    imageUrl: null,
    thumbnailUrl: null,
    set: { id: 'set-id', name: "McDonald's Collection 2021" },
  }
}

function collectionItem(overrides: Partial<{
  id: string
  card: ReturnType<typeof catalogCard>
}> = {}) {
  return {
    id: 'item-id',
    card: catalogCard(),
    language: 'en',
    variant: { id: 'variant-id', code: 'holo' },
    condition: 'near_mint' as const,
    status: 'in_collection' as const,
    purchase: { price: null, purchasedAt: null, sellerName: null },
    notes: null,
    duplicate: { ownedQuantity: 1, isDuplicate: false },
    createdAt: '2026-08-01T10:30:00+00:00',
    updatedAt: '2026-08-01T10:30:00+00:00',
    ...overrides,
  }
}

function listingFixture(overrides: Partial<{
  collectionItemId: string
  status: Listing['status']
}> = {}) {
  return {
    id: 'listing-id',
    status: 'draft' as const,
    seller: { id: 'seller-id' },
    collectionItemId: 'item-id',
    card: {
      id: 'card-id',
      name: 'Mudkip',
      number: '19/25',
      set: { id: 'set-id', name: "McDonald's Collection 2021" },
    },
    language: 'en',
    variant: 'holo',
    condition: 'near_mint',
    price: { amount: 750, currency: 'EUR' },
    description: null,
    images: [
      { id: 'front-image', side: 'front' as const, url: 'https://images.example/front.jpg' },
      { id: 'back-image', side: 'back' as const, url: 'https://images.example/back.jpg' },
    ],
    shippingMethods: [{
      code: 'letter_mail' as const,
      price: { amount: 150, currency: 'EUR' },
      totalPrice: { amount: 900, currency: 'EUR' },
    }],
    priceHistory: [],
    expiresAt: null,
    publishedAt: null,
    createdAt: '2026-08-01T10:30:00+00:00',
    updatedAt: '2026-08-01T10:30:00+00:00',
    ...overrides,
  }
}

function fillRequiredListingFields(root: HTMLDivElement): void {
  fill(root, '#listing-price', '750')
}

function fill(root: HTMLDivElement, selector: string, value: string): void {
  const input = root.querySelector<HTMLInputElement>(selector)
  expect(input).not.toBeNull()
  input!.value = value
  input!.dispatchEvent(new Event('input', { bubbles: true }))
}

function clickButton(root: HTMLDivElement, text: string): void {
  const button = Array.from(root.querySelectorAll<HTMLButtonElement>('button')).find((candidate) => (
    candidate.textContent?.trim() === text
  ))
  expect(button).toBeDefined()
  button?.click()
}

function mountPage(): MountedPage {
  const root = document.createElement('div')
  document.body.append(root)
  const app = createApp(MyListingsPage)
  app.mount(root)
  const page = { app, root }
  mountedPages.push(page)

  return page
}

async function flush(cycles = 2): Promise<void> {
  for (let index = 0; index < cycles; index += 1) {
    await Promise.resolve()
    await nextTick()
  }
}
