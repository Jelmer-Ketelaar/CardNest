import { createApp, nextTick, type App } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '../stores/auth'
import type { Order, OrderCollection } from '../types/order'

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
    route: {
      name: 'orders',
      params: {} as Record<string, string>,
      query: {} as Record<string, string>,
    },
  }
})

vi.mock('../api/client', () => ({
  ApiError: mocks.ApiError,
  apiRequest: mocks.apiRequest,
}))

vi.mock('vue-router', () => ({
  useRoute: () => mocks.route,
}))

import OrdersPage from './OrdersPage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('OrdersPage', () => {
  beforeEach(() => {
    localStorage.clear()
    mocks.apiRequest.mockReset()
    mocks.route.name = 'orders'
    mocks.route.params = {}
    mocks.route.query = {}
  })

  afterEach(() => {
    for (const page of mountedPages.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('renders only the signed-in buyer orders in the purchase overview', async () => {
    // Arrange
    const buyerOrder = orderFixture({ id: 'buyer-order', buyerId: 'current-user', sellerId: 'seller-id' })
    const sellerOrder = orderFixture({ id: 'seller-order', buyerId: 'other-buyer', sellerId: 'current-user' })
    mockOrders([buyerOrder, sellerOrder])

    // Act
    const { root } = mountPage('current-user')
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/me/orders')
    expect(root.textContent).toContain('Mijn aankopen')
    expect(root.textContent).toContain('Order buyer-or')
    expect(root.textContent).toContain('Verkoper seller-i')
    expect(root.textContent).not.toContain('Order seller-o')
    expect(root.querySelector('[data-route*="order-detail"]')?.textContent).toContain('Bekijk orderdetails')
  })

  it('renders only seller orders and surfaces a shipment conflict', async () => {
    // Arrange
    mocks.route.name = 'seller-orders'
    const sellerOrder = orderFixture({ id: 'seller-order', buyerId: 'buyer-id', sellerId: 'current-user' })
    const buyerOrder = orderFixture({ id: 'buyer-order', buyerId: 'current-user', sellerId: 'other-seller' })
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/orders') return Promise.resolve(orderCollection([sellerOrder, buyerOrder]))
      if (path === '/me/reviews') return Promise.resolve({ items: [], total: 0 })
      if (path === '/me/orders/seller-order/mark-shipped' && options?.method === 'POST') {
        return Promise.reject(new mocks.ApiError('De order is inmiddels al verzonden.', 409, 'invalid_order_transition'))
      }

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })

    // Act
    const { root } = mountPage('current-user')
    await flush()
    clickButton(root, 'Markeer als verzonden')
    await flush(5)

    // Assert
    expect(root.textContent).toContain('Mijn verkopen')
    expect(root.textContent).toContain('Koper buyer-id')
    expect(root.textContent).not.toContain('Order buyer-or')
    expect(root.querySelector('[role="alert"]')?.textContent).toContain('De order is inmiddels al verzonden.')
  })

  it('loads the participant-safe detail route and updates shipment state after success', async () => {
    // Arrange
    mocks.route.name = 'order-detail'
    mocks.route.params = { id: 'seller-order' }
    const order = orderFixture({ id: 'seller-order', sellerId: 'current-user' })
    const shippedOrder = {
      ...order,
      status: 'shipped' as const,
      availableActions: { markShipped: false, confirmReceived: false },
      statusHistory: [
        ...order.statusHistory,
        { id: 'history-shipped', from: 'paid' as const, to: 'shipped' as const, actor: { id: 'current-user' }, occurredAt: '2026-08-01T12:00:00+00:00' },
      ],
    }
    let resolveShipment: (updated: Order) => void = () => {
      throw new Error('Shipment resolver was not initialized.')
    }
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/orders/seller-order') return Promise.resolve(order)
      if (path === '/me/reviews') return Promise.resolve({ items: [], total: 0 })
      if (path === '/me/orders/seller-order/mark-shipped' && options?.method === 'POST') {
        return new Promise<Order>((resolve) => { resolveShipment = resolve })
      }

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })

    // Act
    const { root } = mountPage('current-user')
    await flush()
    clickButton(root, 'Markeer als verzonden')
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/me/orders/seller-order')
    expect(root.textContent).toContain('Mudkip · 19/25')
    expect(root.textContent).toContain('Aflevering')
    expect(root.textContent).toContain('Nep-betaalprovider · Geslaagd')
    expect(root.textContent).not.toContain('Referentie')
    expect(button(root, 'Bijwerken…')?.disabled).toBe(true)

    // Act
    resolveShipment(shippedOrder)
    await flush(5)

    // Assert
    expect(root.textContent).toContain('De order is als verzonden gemarkeerd.')
    expect(root.textContent).toContain('Verzonden')
    expect(button(root, 'Markeer als verzonden')).toBeUndefined()
  })

  it('submits one completed-order review and removes the client-side action after success', async () => {
    // Arrange
    const order: Order = {
      ...orderFixture({ id: 'completed-order', buyerId: 'current-user' }),
      status: 'completed',
      availableActions: { markShipped: false, confirmReceived: false },
      completedAt: '2026-08-01T12:00:00+00:00',
    }
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/orders') return Promise.resolve(orderCollection([order]))
      if (path === '/me/reviews') return Promise.resolve({ items: [], total: 0 })
      if (path === '/me/orders/completed-order/reviews' && options?.method === 'POST') {
        return Promise.resolve({
          id: 'review-id',
          orderId: 'completed-order',
          reviewer: { id: 'current-user' },
          reviewedUser: { id: 'seller-id' },
          scores: {
            overall: 5,
            descriptionAccuracy: 5,
            packaging: 5,
            communication: 5,
            shippingSpeed: 5,
          },
          comment: null,
          createdAt: '2026-08-01T12:00:00+00:00',
        })
      }

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })

    // Act
    const { root } = mountPage('current-user')
    await flush()
    clickButton(root, 'Schrijf review')
    await flush()
    clickButton(root, 'Plaats review')
    await flush(5)

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/me/orders/completed-order/reviews', {
      method: 'POST',
      body: JSON.stringify({
        overallScore: 5,
        descriptionAccuracyScore: 5,
        packagingScore: 5,
        communicationScore: 5,
        shippingSpeedScore: 5,
        comment: null,
      }),
    })
    expect(root.textContent).toContain('Je review is geplaatst.')
    expect(button(root, 'Schrijf review')).toBeUndefined()
    expect(root.textContent).toContain('Review geplaatst')
  })

  it('renders accessible review validation feedback', async () => {
    // Arrange
    const order: Order = {
      ...orderFixture({ id: 'completed-order', buyerId: 'current-user' }),
      status: 'completed',
      availableActions: { markShipped: false, confirmReceived: false },
      completedAt: '2026-08-01T12:00:00+00:00',
    }
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/orders') return Promise.resolve(orderCollection([order]))
      if (path === '/me/reviews') return Promise.resolve({ items: [], total: 0 })
      if (path === '/me/orders/completed-order/reviews' && options?.method === 'POST') {
        return Promise.reject(new mocks.ApiError('Controleer de reviewgegevens.', 422, 'validation_failed'))
      }

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })

    // Act
    const { root } = mountPage('current-user')
    await flush()
    clickButton(root, 'Schrijf review')
    await flush()
    clickButton(root, 'Plaats review')
    await flush(5)

    // Assert
    expect(root.querySelector('[role="alert"]')?.textContent).toContain('Controleer de reviewgegevens.')
    expect(button(root, 'Schrijf review')).toBeDefined()
  })

  it('renders a safe error when the order detail is not accessible', async () => {
    // Arrange
    mocks.route.name = 'order-detail'
    mocks.route.params = { id: 'unknown-order' }
    mocks.apiRequest.mockImplementation((path: string) => {
      if (path === '/me/orders/unknown-order') return Promise.reject(new mocks.ApiError('Order niet gevonden.', 404, 'order_not_found'))
      if (path === '/me/reviews') return Promise.resolve({ items: [], total: 0 })

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })

    // Act
    const { root } = mountPage('current-user')
    await flush(5)

    // Assert
    expect(root.querySelector('[role="alert"]')?.textContent).toContain('Order niet gevonden.')
    expect(root.textContent).not.toContain('Mudkip · 19/25')
  })
})

function mockOrders(orders: Order[]): void {
  mocks.apiRequest.mockImplementation((path: string) => {
    if (path === '/me/orders') return Promise.resolve(orderCollection(orders))
    if (path === '/me/reviews') return Promise.resolve({ items: [], total: 0 })

    return Promise.reject(new Error(`Unexpected request: ${path}`))
  })
}

function orderCollection(items: Order[]): OrderCollection {
  return { items, total: items.length }
}

function orderFixture(overrides: Partial<{ id: string; buyerId: string; sellerId: string }> = {}): Order {
  return {
    id: overrides.id ?? 'order-id',
    status: 'paid',
    buyer: { id: overrides.buyerId ?? 'buyer-id' },
    seller: { id: overrides.sellerId ?? 'seller-id' },
    items: [{
      id: 'order-item-id',
      listingId: 'listing-id',
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
    payment: {
      id: 'payment-id',
      status: 'succeeded',
      provider: 'fake',
      providerReference: 'fake-reference',
    },
    statusHistory: [{
      id: 'history-paid',
      from: 'pending_payment',
      to: 'paid',
      actor: { id: overrides.buyerId ?? 'buyer-id' },
      occurredAt: '2026-08-01T10:30:00+00:00',
    }],
    availableActions: { markShipped: true, confirmReceived: false },
    paidAt: '2026-08-01T10:30:00+00:00',
    completedAt: null,
    createdAt: '2026-08-01T10:30:00+00:00',
    updatedAt: '2026-08-01T10:30:00+00:00',
  }
}

function button(root: HTMLDivElement, text: string): HTMLButtonElement | undefined {
  return Array.from(root.querySelectorAll<HTMLButtonElement>('button')).find((candidate) => (
    candidate.textContent?.trim() === text
  ))
}

function clickButton(root: HTMLDivElement, text: string): void {
  const target = button(root, text)
  expect(target).toBeDefined()
  target?.click()
}

function mountPage(userId: string): MountedPage {
  const root = document.createElement('div')
  document.body.append(root)
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore(pinia).updateUser({
    id: userId,
    email: 'collector@example.com',
    roles: ['ROLE_USER'],
    emailVerifiedAt: '2026-08-01T10:00:00+00:00',
    createdAt: '2026-08-01T10:00:00+00:00',
  })
  const app = createApp(OrdersPage)
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
