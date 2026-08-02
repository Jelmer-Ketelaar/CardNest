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
    push: vi.fn(),
    replace: vi.fn(),
    route: { name: 'wishlists', params: {} as Record<string, string> },
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

import WishlistsPage from './WishlistsPage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('WishlistsPage', () => {
  beforeEach(() => {
    mocks.apiRequest.mockReset()
    mocks.push.mockReset()
    mocks.push.mockResolvedValue(undefined)
    mocks.replace.mockReset()
    mocks.replace.mockResolvedValue(undefined)
    mocks.route.name = 'wishlists'
    mocks.route.params = {}
  })

  afterEach(() => {
    for (const page of mountedPages.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('renders loading and empty states for the signed-in user wishlists', async () => {
    // Arrange
    let resolveWishlists!: (response: { items: []; total: number }) => void
    mocks.apiRequest.mockImplementation((path: string) => {
      if (path === '/me/wishlists') {
        return new Promise<{ items: []; total: number }>((resolve) => {
          resolveWishlists = resolve
        })
      }

      return Promise.reject(new Error('Unexpected request: ' + path))
    })
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(root.textContent).toContain('Wishlists laden…')

    // Act
    resolveWishlists({ items: [], total: 0 })
    await flush(4)

    // Assert
    expect(root.textContent).toContain('Maak een wishlist aan om ontbrekende kaarten vast te leggen.')
    expect(root.querySelector('#wishlist-name')).not.toBeNull()
  })

  it('loads the owner-safe detail route and updates all supported preferences', async () => {
    // Arrange
    const initial = wishlistDetail()
    const updated = wishlistDetail({
      desiredQuantity: 2,
      fulfilledQuantity: 1,
      remainingQuantity: 1,
      priority: 'high',
      notes: 'Updated note',
    })
    mocks.route.name = 'wishlist-detail'
    mocks.route.params = { id: initial.id }
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/wishlists') return Promise.resolve(wishlistCollection(initial))
      if (path === '/me/wishlists/' + initial.id) return Promise.resolve(initial)
      if (path === '/cards/card-id') return Promise.resolve(catalogCard())
      if (path === '/me/wishlists/' + initial.id + '/items/item-id' && options?.method === 'PATCH') {
        return Promise.resolve(updated)
      }

      return Promise.reject(new Error('Unexpected request: ' + path))
    })
    const { root } = mountPage()
    await flush(5)

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/me/wishlists/' + initial.id)
    expect(root.textContent).toContain('Mudkip master set')
    expect(root.textContent).toContain('Bekijk kaart en prijsindicatie')
    expect(root.querySelector('[aria-label="Bekijk Mudkip en prijsindicatie"]')?.getAttribute('data-card-id'))
      .toBe('card-id')

    // Act
    clickButton(root, 'Voorkeuren bewerken')
    await flush(4)
    const quantity = root.querySelector<HTMLInputElement>('#edit-wishlist-quantity-item-id')
    const notes = root.querySelector<HTMLTextAreaElement>('#edit-wishlist-notes-item-id')
    const form = root.querySelector<HTMLFormElement>('form.wishlist-item-edit-form')
    expect(quantity).not.toBeNull()
    expect(notes).not.toBeNull()
    expect(form).not.toBeNull()
    quantity!.value = '2'
    quantity!.dispatchEvent(new Event('input', { bubbles: true }))
    notes!.value = 'Updated note'
    notes!.dispatchEvent(new Event('input', { bubbles: true }))
    form!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flush(4)

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith(
      '/me/wishlists/' + initial.id + '/items/item-id',
      expect.objectContaining({
        method: 'PATCH',
        body: JSON.stringify({
          desiredQuantity: 2,
          priority: 'wanted',
          language: 'en',
          variantId: 'variant-id',
          minimumCondition: 'excellent',
          maxPrice: 800,
          maxCurrency: 'EUR',
          notes: 'Updated note',
        }),
      }),
    )
    expect(root.textContent).toContain('De wishlist-voorkeuren zijn bijgewerkt.')
    expect(root.textContent).toContain('1 ontbreekt')
  })

  it('renders field-level validation feedback from a failed preference update', async () => {
    // Arrange
    const detail = wishlistDetail()
    mocks.route.name = 'wishlist-detail'
    mocks.route.params = { id: detail.id }
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/wishlists') return Promise.resolve(wishlistCollection(detail))
      if (path === '/me/wishlists/' + detail.id) return Promise.resolve(detail)
      if (path === '/cards/card-id') return Promise.resolve(catalogCard())
      if (path === '/me/wishlists/' + detail.id + '/items/item-id' && options?.method === 'PATCH') {
        return Promise.reject(new mocks.ApiError('Ongeldige invoer.', 422, 'validation_failed', {
          desiredQuantity: ['Kies een aantal tussen 1 en 100.'],
        }))
      }

      return Promise.reject(new Error('Unexpected request: ' + path))
    })
    const { root } = mountPage()
    await flush(5)
    clickButton(root, 'Voorkeuren bewerken')
    await flush(4)

    // Act
    root.querySelector<HTMLFormElement>('form.wishlist-item-edit-form')
      ?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flush(4)

    // Assert
    expect(root.querySelector('#edit-wishlist-quantity-item-id-error')?.textContent)
      .toContain('Kies een aantal tussen 1 en 100.')
    expect(root.querySelector<HTMLInputElement>('#edit-wishlist-quantity-item-id')?.getAttribute('aria-invalid'))
      .toBe('true')
  })
})

function wishlistCollection(detail: ReturnType<typeof wishlistDetail>) {
  return {
    items: [{
      id: detail.id,
      name: detail.name,
      itemCount: detail.itemCount,
      completedItemCount: detail.completedItemCount,
      progress: detail.progress,
      setProgress: detail.setProgress,
      createdAt: detail.createdAt,
      updatedAt: detail.updatedAt,
    }],
    total: 1,
  }
}

function wishlistDetail(overrides: Partial<{
  desiredQuantity: number
  fulfilledQuantity: number
  remainingQuantity: number
  priority: 'low' | 'normal' | 'high' | 'wanted'
  notes: string | null
}> = {}) {
  const item = {
    id: 'item-id',
    card: {
      id: 'card-id',
      name: 'Mudkip',
      number: '19/25',
      thumbnailUrl: null,
      set: { id: 'set-id', name: "McDonald's Collection 2021" },
    },
    language: 'en',
    variant: { id: 'variant-id', code: 'holo' },
    minimumCondition: 'excellent',
    maxPrice: { amount: 800, currency: 'EUR' },
    desiredQuantity: 1,
    fulfilledQuantity: 0,
    remainingQuantity: 1,
    completed: false,
    completedAt: null,
    priority: 'wanted' as const,
    notes: 'Clean copies',
    createdAt: '2026-08-01T10:30:00+00:00',
    updatedAt: '2026-08-01T10:30:00+00:00',
    ...overrides,
  }

  return {
    id: 'wishlist-id',
    name: 'Mudkip master set',
    itemCount: 1,
    completedItemCount: item.completed ? 1 : 0,
    progress: {
      desiredQuantity: item.desiredQuantity,
      fulfilledQuantity: item.fulfilledQuantity,
      remainingQuantity: item.remainingQuantity,
      percentage: item.desiredQuantity === 0 ? 0 : Math.round((item.fulfilledQuantity / item.desiredQuantity) * 100),
    },
    setProgress: [],
    createdAt: '2026-08-01T10:30:00+00:00',
    updatedAt: '2026-08-01T10:30:00+00:00',
    items: [item],
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
  const app = createApp(WishlistsPage)
  app.component('RouterLink', {
    props: ['to'],
    template: '<a :data-card-id="to.params ? to.params.id : null"><slot /></a>',
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
