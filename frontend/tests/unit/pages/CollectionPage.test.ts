import { createApp, nextTick, type App } from 'vue'
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
    replace: vi.fn(),
    route: { query: {} as Record<string, string>, params: {} },
  }
})

vi.mock('../../../src/api/client', () => ({
  ApiError: mocks.ApiError,
  apiRequest: mocks.apiRequest,
}))

vi.mock('vue-router', () => ({
  useRoute: () => mocks.route,
  useRouter: () => ({ replace: mocks.replace }),
}))

import CollectionPage from '../../../src/pages/CollectionPage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('CollectionPage', () => {
  beforeEach(() => {
    mocks.apiRequest.mockReset()
    mocks.replace.mockReset()
    mocks.replace.mockResolvedValue(undefined)
    mocks.route.query = {}
    mocks.route.params = {}
  })

  afterEach(() => {
    for (const page of mountedPages.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('renders the signed-in collection and submits filter state to the collection API', async () => {
    // Arrange
    mockOverviewRequests()
    const { root } = mountPage()
    await flush()
    const input = root.querySelector<HTMLInputElement>('#filter-card-name')
    const form = root.querySelector<HTMLFormElement>('form.filter-bar')
    expect(input).not.toBeNull()
    expect(form).not.toBeNull()

    // Act
    input!.value = 'Mudkip'
    input!.dispatchEvent(new Event('input', { bubbles: true }))
    form!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flush()

    // Assert
    expect(root.textContent).toContain('Mudkip')
    expect(root.textContent).toContain('Bekijk details')
    expect(mocks.apiRequest.mock.calls.some(([path]) => (
      typeof path === 'string' && path.includes('cardName=Mudkip')
    ))).toBe(true)
  })

  it('renders API validation errors next to the add-card controls', async () => {
    // Arrange
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path.startsWith('/me/collection-items?')) return Promise.resolve(collectionResponse())
      if (path === '/me/storage-locations') return Promise.resolve({ items: [], total: 0 })
      if (path.startsWith('/cards?')) return Promise.resolve({ items: [catalogCard()], total: 1, page: 1, perPage: 8, totalPages: 1 })
      if (path === '/me/collection-items' && options?.method === 'POST') {
        return Promise.reject(new mocks.ApiError('Ongeldige invoer.', 422, 'validation_failed', {
          quantity: ['Kies een aantal tussen 1 en 100.'],
        }))
      }

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })
    const { root } = mountPage()
    await flush()
    const searchInput = root.querySelector<HTMLInputElement>('#catalog-query')
    const searchForm = root.querySelector<HTMLFormElement>('form.catalog-search')
    expect(searchInput).not.toBeNull()
    expect(searchForm).not.toBeNull()

    // Act
    searchInput!.value = 'Mudkip'
    searchInput!.dispatchEvent(new Event('input', { bubbles: true }))
    searchForm!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flush()
    root.querySelector<HTMLButtonElement>('.catalog-results button')?.click()
    await flush()
    root.querySelector<HTMLFormElement>('form.collection-form')?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flush()

    // Assert
    expect(root.querySelector('#quantity-error')?.textContent).toContain('Kies een aantal tussen 1 en 100.')
    expect(root.querySelector<HTMLInputElement>('#quantity')?.getAttribute('aria-invalid')).toBe('true')
  })

  it('loads the owner-safe item detail before opening the route-driven edit form', async () => {
    // Arrange
    mocks.route.query = { edit: 'item-id' }
    mocks.apiRequest.mockImplementation((path: string) => {
      if (path.startsWith('/me/collection-items?')) return Promise.resolve(collectionResponse())
      if (path === '/me/storage-locations') return Promise.resolve({ items: [], total: 0 })
      if (path === '/me/collection-items/item-id') return Promise.resolve(collectionItem())
      if (path === '/cards/card-id') return Promise.resolve(catalogCard())
      if (path === '/me/collection-items/item-id/location') {
        return Promise.resolve({ collectionItemId: 'item-id', current: null, history: [] })
      }

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })
    const { root } = mountPage()

    // Act
    await flush(5)

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/me/collection-items/item-id')
    expect(root.querySelector('#edit-heading')?.textContent).toContain('Mudkip bewerken')
    expect(root.textContent).toContain('officiële kaartgegevens kunnen hier niet worden gewijzigd')
    expect(root.textContent).toContain('Archiveren')
  })
})

function mockOverviewRequests(): void {
  mocks.apiRequest.mockImplementation((path: string) => {
    if (path.startsWith('/me/collection-items?')) return Promise.resolve(collectionResponse())
    if (path === '/me/storage-locations') return Promise.resolve({ items: [], total: 0 })

    return Promise.reject(new Error(`Unexpected request: ${path}`))
  })
}

function collectionResponse() {
  return { items: [collectionItem()], total: 1, page: 1, perPage: 24, totalPages: 1 }
}

function collectionItem() {
  return {
    id: 'item-id',
    card: {
      id: 'card-id',
      name: 'Mudkip',
      number: '19/25',
      imageUrl: null,
      thumbnailUrl: null,
      set: { id: 'set-id', name: "McDonald's Collection 2021" },
    },
    language: 'en',
    variant: { id: 'variant-id', code: 'holo' },
    condition: 'near_mint',
    status: 'in_collection' as const,
    purchase: { price: null, purchasedAt: null, sellerName: null },
    notes: null,
    duplicate: { ownedQuantity: 1, isDuplicate: false },
    createdAt: '2026-08-01T10:30:00+00:00',
    updatedAt: '2026-08-01T10:30:00+00:00',
  }
}

function catalogCard() {
  return {
    id: 'card-id',
    name: 'Mudkip',
    number: '19/25',
    rarity: 'Promo',
    imageUrl: null,
    thumbnailUrl: null,
    set: { id: 'set-id', name: "McDonald's Collection 2021" },
    variants: [{ id: 'variant-id', code: 'holo' }],
  }
}

function mountPage(): MountedPage {
  const root = document.createElement('div')
  document.body.append(root)
  const app = createApp(CollectionPage)
  app.component('RouterLink', { template: '<a><slot /></a>' })
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
