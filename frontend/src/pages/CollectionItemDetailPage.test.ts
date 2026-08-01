import { createApp, nextTick, type App } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => {
  class ApiError extends Error {
    public constructor(
      message: string,
      public readonly status: number,
      public readonly code: string,
    ) {
      super(message)
    }
  }

  return {
    ApiError,
    apiRequest: vi.fn(),
    route: { params: { id: 'item-id' } },
  }
})

vi.mock('../api/client', () => ({
  ApiError: mocks.ApiError,
  apiRequest: mocks.apiRequest,
}))

vi.mock('vue-router', () => ({
  useRoute: () => mocks.route,
}))

import CollectionItemDetailPage from './CollectionItemDetailPage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('CollectionItemDetailPage', () => {
  beforeEach(() => {
    mocks.apiRequest.mockReset()
    mocks.route.params = { id: 'item-id' }
  })

  afterEach(() => {
    for (const page of mountedPages.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('loads a physical item and exposes the owner edit flow', async () => {
    // Arrange
    mocks.apiRequest.mockResolvedValue(collectionItem())
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/me/collection-items/item-id')
    expect(root.querySelector('h1')?.textContent).toContain('Mudkip')
    expect(root.textContent).toContain('Je bezit 2 exemplaren')
    expect(root.textContent).toContain('Bewerken')
    expect(root.textContent).not.toContain('Te koop zetten')
  })

  it('renders a safe not-found state for another users item', async () => {
    // Arrange
    mocks.apiRequest.mockRejectedValue(new mocks.ApiError('Niet gevonden.', 404, 'collection_item_not_found'))
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(root.querySelector('h1')?.textContent).toContain('Collectiekaart niet gevonden')
  })

  it('renders an archived item without an edit action', async () => {
    // Arrange
    mocks.apiRequest.mockResolvedValue(collectionItem({ status: 'archived' }))
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(root.textContent).toContain('Gearchiveerd')
    expect(root.textContent).toContain('kan niet meer worden gewijzigd')
    expect(root.textContent).not.toContain('Bewerken')
  })

  it('renders a retryable network-error state', async () => {
    // Arrange
    mocks.apiRequest.mockRejectedValue(new Error('Network unavailable'))
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(root.textContent).toContain('De collectiekaart kon niet worden geladen.')
    expect(root.textContent).toContain('Opnieuw proberen')
  })
})

function collectionItem(overrides: Partial<{ status: 'in_collection' | 'for_sale' | 'archived' }> = {}) {
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
    purchase: { price: { amount: 350, currency: 'EUR' }, purchasedAt: '2026-07-20', sellerName: 'Card shop' },
    notes: 'Clean copy',
    duplicate: { ownedQuantity: 2, isDuplicate: true },
    createdAt: '2026-08-01T10:30:00+00:00',
    updatedAt: '2026-08-01T10:30:00+00:00',
    ...overrides,
  }
}

function mountPage(): MountedPage {
  const root = document.createElement('div')
  document.body.append(root)
  const app = createApp(CollectionItemDetailPage)
  app.component('RouterLink', { template: '<a><slot /></a>' })
  app.mount(root)
  const page = { app, root }
  mountedPages.push(page)

  return page
}

async function flush(): Promise<void> {
  await Promise.resolve()
  await nextTick()
  await Promise.resolve()
  await nextTick()
}
