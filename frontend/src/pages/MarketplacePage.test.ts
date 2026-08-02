import { createApp, nextTick, type App } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

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
    push: vi.fn(),
    replace: vi.fn(),
    route: {
      fullPath: '/marketplace',
      query: {} as Record<string, string>,
      params: {} as Record<string, string>,
    },
  }
})

vi.mock('../api/client', () => ({
  ApiError: mocks.ApiError,
  apiRequest: mocks.apiRequest,
}))

vi.mock('vue-router', () => ({
  useRoute: () => mocks.route,
  useRouter: () => ({ push: mocks.push, replace: mocks.replace }),
}))

import MarketplacePage from './MarketplacePage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('MarketplacePage', () => {
  beforeEach(() => {
    localStorage.clear()
    mocks.apiRequest.mockReset()
    mocks.push.mockReset()
    mocks.push.mockResolvedValue(undefined)
    mocks.replace.mockReset()
    mocks.replace.mockResolvedValue(undefined)
    mocks.route.fullPath = '/marketplace'
    mocks.route.query = {}
    mocks.route.params = {}
  })

  afterEach(() => {
    for (const page of mountedPages.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('loads public listings with route-bound filters and pagination', async () => {
    // Arrange
    mocks.route.fullPath = '/marketplace?cardName=Mudkip&language=en&condition=near_mint&maximumPrice=750&pickupAllowed=1&page=2'
    mocks.route.query = {
      cardName: 'Mudkip',
      language: 'en',
      condition: 'near_mint',
      maximumPrice: '750',
      pickupAllowed: '1',
      page: '2',
    }
    mocks.apiRequest.mockResolvedValue(listingResponse())

    // Act
    const { root } = mountPage()
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith(
      '/listings?page=2&perPage=24&cardName=Mudkip&language=en&condition=near_mint&maximumPrice=750&pickupAllowed=1',
    )
    expect(root.textContent).toContain('Mudkip')
    expect(root.textContent).toContain('Pagina 2 van 2')
  })

  it('writes submitted filters to the public marketplace route', async () => {
    // Arrange
    mocks.apiRequest.mockResolvedValue(listingResponse())
    const { root } = mountPage()
    await flush()
    const input = root.querySelector<HTMLInputElement>('#marketplace-name')
    expect(input).not.toBeNull()
    input!.value = 'Pikachu'
    input!.dispatchEvent(new Event('input', { bubbles: true }))

    // Act
    root.querySelector<HTMLFormElement>('form.marketplace-filters')
      ?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flush()

    // Assert
    expect(mocks.push).toHaveBeenCalledWith({ name: 'marketplace', query: { cardName: 'Pikachu' } })
  })

  it('opens a directly addressed public listing and preserves browse filters on close', async () => {
    // Arrange
    const listing = listingFixture()
    mocks.route.fullPath = '/marketplace/listings/listing-id?language=en'
    mocks.route.query = { language: 'en' }
    mocks.route.params = { id: listing.id }
    mocks.apiRequest.mockImplementation((path: string) => {
      if (path === '/listings?page=1&perPage=24&language=en') return Promise.resolve(listingResponse(listing))
      if (path === `/listings/${listing.id}`) return Promise.resolve(listing)

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })

    // Act
    const { root } = mountPage()
    await flush(5)

    // Assert
    expect(root.querySelector('#listing-detail-heading')?.textContent).toContain('Mudkip · 19/25')
    expect(root.textContent).toContain('Volledige prijzen')

    // Act
    clickButton(root, 'Sluiten')
    await flush()

    // Assert
    expect(mocks.replace).toHaveBeenCalledWith({ name: 'marketplace', query: { language: 'en' } })
  })

  it('does not render unavailable listing details', async () => {
    // Arrange
    mocks.route.fullPath = '/marketplace/listings/listing-id'
    mocks.route.params = { id: 'listing-id' }
    mocks.apiRequest.mockImplementation((path: string) => {
      if (path === '/listings?page=1&perPage=24') return Promise.resolve({ ...listingResponse(), items: [], total: 0, totalPages: 0 })
      if (path === '/listings/listing-id') return Promise.reject(new mocks.ApiError('Niet gevonden.', 404, 'not_found'))

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })

    // Act
    const { root } = mountPage()
    await flush(5)

    // Assert
    expect(root.querySelector('#listing-detail-heading')).toBeNull()
    expect(root.querySelector('[role="alert"]')?.textContent).toContain('Niet gevonden.')
  })
})

function listingResponse(listing = listingFixture()) {
  return {
    items: [listing],
    page: 2,
    perPage: 24,
    total: 25,
    totalPages: 2,
  }
}

function listingFixture() {
  return {
    id: 'listing-id',
    status: 'active' as const,
    seller: { id: 'seller-id' },
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
    description: 'Clean copy.',
    images: [
      { id: 'front-image', side: 'front' as const, url: 'https://images.example/front.jpg' },
      { id: 'back-image', side: 'back' as const, url: 'https://images.example/back.jpg' },
    ],
    shippingMethods: [
      {
        code: 'letter_mail' as const,
        price: { amount: 150, currency: 'EUR' },
        totalPrice: { amount: 900, currency: 'EUR' },
      },
    ],
    priceHistory: [],
    expiresAt: null,
    publishedAt: '2026-08-01T10:30:00+00:00',
    createdAt: '2026-08-01T10:30:00+00:00',
    updatedAt: '2026-08-01T10:30:00+00:00',
  }
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
  const pinia = createPinia()
  setActivePinia(pinia)
  const app = createApp(MarketplacePage)
  app.use(pinia)
  app.component('RouterLink', {
    props: { to: { type: [String, Object], required: true } },
    template: '<a :data-route="JSON.stringify(to)"><slot /></a>',
  })
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
