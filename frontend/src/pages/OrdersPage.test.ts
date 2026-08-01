import { createApp, nextTick, type App } from 'vue'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import OrdersPage from './OrdersPage.vue'
import type { Order } from '../types/order'

function response(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

function paidOrder(): Order {
  const now = new Date().toISOString()
  return {
    id: '019fbcc6-4581-700b-aa1d-30288c99a2bd',
    status: 'paid',
    buyer: { id: '019fbcc6-457f-7d7f-b341-73ce3f58fe5b' },
    seller: { id: '019fbcc6-457f-7f53-b341-73ce41853783' },
    items: [{
      id: '019fbcc6-4581-7017-aa1d-30288cfa1b7c',
      listingId: '019fbcc6-4580-7dbb-a924-8522ad61e329',
      card: { id: 'card', name: 'Mudkip', number: '1/1', set: { id: 'set', name: 'Promo set' } },
      language: 'en',
      variant: 'holo',
      condition: 'near_mint',
      price: { amount: 2_000, currency: 'EUR' },
      collectionImport: { available: false, imported: false, collectionItemId: null, importedAt: null },
    }],
    shippingMethod: 'letter_mail',
    costs: {
      items: { amount: 2_000, currency: 'EUR' },
      shipping: { amount: 250, currency: 'EUR' },
      serviceFee: { amount: 0, currency: 'EUR' },
      total: { amount: 2_250, currency: 'EUR' },
    },
    shippingAddress: {
      fullName: 'Sam Collector', addressLine1: 'Dorpsstraat 1', addressLine2: null,
      postalCode: '1234 AB', city: 'Utrecht', countryCode: 'NL',
    },
    payment: { id: 'payment', status: 'succeeded', provider: 'fake', providerReference: 'fake_payment' },
    cancellation: null,
    refund: null,
    statusHistory: [
      { id: 'history-1', from: null, to: 'pending_payment', actor: { id: 'buyer' }, occurredAt: now },
      { id: 'history-2', from: 'pending_payment', to: 'paid', actor: { id: 'buyer' }, occurredAt: now },
    ],
    availableActions: { cancel: true, markShipped: false, confirmReceived: false },
    paidAt: now,
    completedAt: null,
    createdAt: now,
    updatedAt: now,
  }
}

async function mountOrders(fetchMock: typeof fetch): Promise<{ app: App; router: Router; element: HTMLDivElement }> {
  vi.stubGlobal('fetch', fetchMock)
  localStorage.setItem('cardnest.authToken', 'test-token')
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/orders', name: 'orders', component: OrdersPage },
      { path: '/cart', name: 'cart', component: { template: '<div />' } },
      { path: '/storage', name: 'storage', component: { template: '<div />' } },
      { path: '/marketplace', name: 'marketplace', component: { template: '<div />' } },
    ],
  })
  await router.push('/orders')
  await router.isReady()
  const element = document.createElement('div')
  document.body.append(element)
  const app = createApp(OrdersPage)
  app.use(router)
  app.mount(element)
  await flush()

  return { app, router, element }
}

async function flush(): Promise<void> {
  await Promise.resolve()
  await nextTick()
  await new Promise<void>((resolve) => { setTimeout(resolve, 0) })
  await Promise.resolve()
  await nextTick()
}

function clickByText(element: Element, text: string): void {
  const button = Array.from(element.querySelectorAll('button')).find((entry) => entry.textContent?.trim() === text)
  expect(button, element.innerHTML).toBeDefined()
  button?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
}

describe('OrdersPage cancellation', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    localStorage.clear()
    document.body.replaceChildren()
  })

  it('shows the cancel action only when the backend allows it and validates the reason', async () => {
    const order = paidOrder()
    const fetchMock = vi.fn(async (input: RequestInfo | URL, _init?: RequestInit) => {
      const path = typeof input === 'string' ? input : input.toString()
      if (path.endsWith('/me/orders')) return response({ items: [order], total: 1 })
      return response({ items: [], total: 0 })
    })
    const { app, element } = await mountOrders(fetchMock)

    expect(fetchMock).toHaveBeenCalledTimes(2)

    clickByText(element, 'Bestelling annuleren')
    await flush()

    expect(element.querySelector('[role="dialog"]')).not.toBeNull()
    clickByText(element, 'Annuleren en terugbetalen')
    await flush()
    expect(element.textContent).toContain('Vul een annuleringsreden in.')
    expect(fetchMock).toHaveBeenCalledTimes(2)

    app.unmount()
  })

  it('disables duplicate submits, refreshes the order, and removes the action after success', async () => {
    const order = paidOrder()
    let resolveCancellation: ((value: Response) => void) | undefined
    const cancellationResponse = new Promise<Response>((resolve) => { resolveCancellation = resolve })
    const refundedOrder: Order = {
      ...order,
      status: 'refunded',
      cancellation: {
        reason: 'Seller cannot fulfil the order.',
        cancelledBy: order.seller,
        cancelledAt: new Date().toISOString(),
      },
      refund: { status: 'succeeded', amount: order.costs.total },
      availableActions: { cancel: false, markShipped: false, confirmReceived: false },
    }
    const fetchMock = vi.fn((input: RequestInfo | URL, _init?: RequestInit): Promise<Response> => {
      const path = typeof input === 'string' ? input : input.toString()
      if (path.endsWith('/me/orders')) return Promise.resolve(response({ items: [order], total: 1 }))
      if (path.endsWith('/me/reviews')) return Promise.resolve(response({ items: [], total: 0 }))
      return cancellationResponse
    })
    const { app, element } = await mountOrders(fetchMock)

    clickByText(element, 'Bestelling annuleren')
    await flush()
    const textarea = element.querySelector<HTMLTextAreaElement>('#cancellation-reason')
    expect(textarea).not.toBeNull()
    if (textarea) {
      textarea.value = ' Seller cannot fulfil the order. '
      textarea.dispatchEvent(new Event('input', { bubbles: true }))
    }
    clickByText(element, 'Annuleren en terugbetalen')
    await nextTick()
    const submittingButton = Array.from(element.querySelectorAll('button')).find((entry) => entry.textContent?.includes('Annuleren…'))
    expect(submittingButton).toBeDefined()
    expect(submittingButton?.disabled).toBe(true)
    resolveCancellation?.(response(refundedOrder))
    await flush()

    expect(element.textContent).toContain('Order geannuleerd en € 22,50 terugbetaald.')
    expect(element.textContent).toContain('Terugbetaald')
    expect(Array.from(element.querySelectorAll('button')).some((entry) => entry.textContent?.trim() === 'Bestelling annuleren')).toBe(false)
    const request = fetchMock.mock.calls.at(-1)
    expect(request?.[0]).toBe('/api/v1/me/orders/019fbcc6-4581-700b-aa1d-30288c99a2bd/cancel')
    const requestBody = request?.[1]?.body
    expect(typeof requestBody).toBe('string')
    expect(JSON.parse(requestBody as string)).toEqual({ reason: 'Seller cannot fulfil the order.' })

    app.unmount()
  })

  it('shows a clear conflict message when the order changes while the dialog is open', async () => {
    const order = paidOrder()
    const fetchMock = vi.fn(async (input: RequestInfo | URL, _init?: RequestInit) => {
      const path = typeof input === 'string' ? input : input.toString()
      if (path.endsWith('/me/orders')) return response({ items: [order], total: 1 })
      if (path.endsWith('/me/reviews')) return response({ items: [], total: 0 })
      return response({ error: { code: 'order_not_cancellable', message: 'Order cannot be cancelled.' } }, 409)
    })
    const { app, element } = await mountOrders(fetchMock)

    clickByText(element, 'Bestelling annuleren')
    await flush()
    const textarea = element.querySelector<HTMLTextAreaElement>('#cancellation-reason')
    if (textarea) {
      textarea.value = 'Seller cannot fulfil the order.'
      textarea.dispatchEvent(new Event('input', { bubbles: true }))
    }
    clickByText(element, 'Annuleren en terugbetalen')
    await flush()

    expect(element.textContent).toContain('Deze order is inmiddels verzonden of al afgehandeld.')

    app.unmount()
  })
})
