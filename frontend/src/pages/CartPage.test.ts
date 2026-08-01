import { createApp, nextTick, type App } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Cart, CheckoutResponse } from '../types/order'

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
  }
})

vi.mock('../api/client', () => ({
  ApiError: mocks.ApiError,
  apiRequest: mocks.apiRequest,
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mocks.push }),
}))

import CartPage from './CartPage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('CartPage', () => {
  beforeEach(() => {
    mocks.apiRequest.mockReset()
    mocks.push.mockReset()
    mocks.push.mockResolvedValue(undefined)
  })

  afterEach(() => {
    for (const page of mountedPages.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('groups cart entries by seller and removes an item', async () => {
    // Arrange
    const cart = cartFixture()
    const emptyCart = { ...cart, sellerGroups: [], itemCount: 0 }
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/cart') return Promise.resolve(cart)
      if (path === '/me/cart/items/cart-item-id' && options?.method === 'DELETE') return Promise.resolve(emptyCart)

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })
    const { root } = mountPage()
    await flush()

    // Assert
    expect(root.textContent).toContain('Verkoper seller-i')
    expect(root.textContent).toContain('Mudkip · 19/25')
    expect(root.textContent).toContain('€ 9,00')

    // Act
    clickButton(root, 'Verwijderen')
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/me/cart/items/cart-item-id', { method: 'DELETE' })
    expect(root.textContent).toContain('Je winkelmand is leeg')
  })

  it('submits only one checkout request while the payment is pending', async () => {
    // Arrange
    const cart = cartFixture()
    let resolveCheckout: (response: CheckoutResponse) => void = () => {
      throw new Error('Checkout resolver was not initialized.')
    }
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/cart') return Promise.resolve(cart)
      if (path === '/me/cart/checkout' && options?.method === 'POST') {
        return new Promise<CheckoutResponse>((resolve) => { resolveCheckout = resolve })
      }

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })
    const { root } = mountPage()
    await flush()
    fill(root, '#checkout-name', 'Sam Collector')
    fill(root, '#checkout-address-1', 'Dorpsstraat 1')
    fill(root, '#checkout-postal-code', '1234 AB')
    fill(root, '#checkout-city', 'Utrecht')

    // Act
    submit(root)
    submit(root)
    await flush()

    // Assert
    expect(mocks.apiRequest.mock.calls.filter(([path]) => path === '/me/cart/checkout')).toHaveLength(1)
    expect(button(root, 'Afrekenen…')?.disabled).toBe(true)

    // Act
    resolveCheckout(checkoutResponse())
    await flush(5)

    // Assert
    expect(mocks.push).toHaveBeenCalledWith({ name: 'orders', query: { checkedOut: '1' } })
  })

  it('renders a checkout conflict and refreshes the cart state', async () => {
    // Arrange
    const cart = cartFixture()
    let cartRequests = 0
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/cart') {
        cartRequests += 1

        return Promise.resolve(cart)
      }
      if (path === '/me/cart/checkout' && options?.method === 'POST') {
        return Promise.reject(new mocks.ApiError('Deze listing is niet meer beschikbaar.', 409, 'listing_unavailable'))
      }

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })
    const { root } = mountPage()
    await flush()
    fill(root, '#checkout-name', 'Sam Collector')
    fill(root, '#checkout-address-1', 'Dorpsstraat 1')
    fill(root, '#checkout-postal-code', '1234 AB')
    fill(root, '#checkout-city', 'Utrecht')

    // Act
    submit(root)
    await flush(5)

    // Assert
    expect(root.querySelector('[role="alert"]')?.textContent).toContain('Deze listing is niet meer beschikbaar.')
    expect(cartRequests).toBeGreaterThanOrEqual(2)
    expect(mocks.push).not.toHaveBeenCalled()
  })
})

function cartFixture(): Cart {
  return {
    id: 'cart-id',
    itemCount: 1,
    checkoutAvailable: true,
    sellerGroups: [{
      seller: { id: 'seller-id' },
      itemSubtotal: { amount: 750, currency: 'EUR' },
      items: [{
        id: 'cart-item-id',
        addedPrice: { amount: 750, currency: 'EUR' },
        priceChanged: false,
        available: true,
        addedAt: '2026-08-01T10:30:00+00:00',
        listing: {
          id: 'listing-id',
          status: 'active',
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
          description: null,
          images: [{ id: 'front-image', side: 'front', url: 'https://images.example/front.jpg' }],
          shippingMethods: [{
            code: 'letter_mail',
            price: { amount: 150, currency: 'EUR' },
            totalPrice: { amount: 900, currency: 'EUR' },
          }],
          priceHistory: [],
          expiresAt: null,
          publishedAt: '2026-08-01T10:30:00+00:00',
          createdAt: '2026-08-01T10:30:00+00:00',
          updatedAt: '2026-08-01T10:30:00+00:00',
        },
      }],
      shippingMethods: [{
        code: 'letter_mail',
        price: { amount: 150, currency: 'EUR' },
        totalPrice: { amount: 900, currency: 'EUR' },
      }],
    }],
  }
}

function checkoutResponse(): CheckoutResponse {
  return {
    payment: {
      id: 'payment-id',
      status: 'succeeded',
      provider: 'fake',
      providerReference: 'fake-reference',
      amount: { amount: 900, currency: 'EUR' },
    },
    orders: [{ id: 'order-id' } as CheckoutResponse['orders'][number]],
  }
}

function fill(root: HTMLDivElement, selector: string, value: string): void {
  const input = root.querySelector<HTMLInputElement>(selector)
  expect(input).not.toBeNull()
  input!.value = value
  input!.dispatchEvent(new Event('input', { bubbles: true }))
}

function submit(root: HTMLDivElement): void {
  const form = root.querySelector<HTMLFormElement>('form.checkout-layout')
  expect(form).not.toBeNull()
  form?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
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

function mountPage(): MountedPage {
  const root = document.createElement('div')
  document.body.append(root)
  const app = createApp(CartPage)
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
