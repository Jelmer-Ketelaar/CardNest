import { createApp, nextTick, type App } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { CardPriceHistoryResponse } from '../../../src/types/pricing'

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
    route: { params: { id: 'card-id' }, query: { name: 'Mudkip' } },
  }
})

vi.mock('../../../src/api/client', () => ({
  ApiError: mocks.ApiError,
  apiRequest: mocks.apiRequest,
}))

vi.mock('vue-router', () => ({
  useRoute: () => mocks.route,
}))

import CatalogCardDetailPage from '../../../src/pages/CatalogCardDetailPage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('CatalogCardDetailPage', () => {
  beforeEach(() => {
    mocks.apiRequest.mockReset()
    mocks.route.params = { id: 'card-id' }
    mocks.route.query = { name: 'Mudkip' }
  })

  afterEach(() => {
    for (const page of mountedPages.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('loads canonical card data and displays all variants', async () => {
    // Arrange
    mocks.apiRequest.mockImplementation((path: string) => Promise.resolve(
      path.includes('/prices') ? priceHistory() : cardDetail(),
    ))
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/cards/card-id')
    expect(mocks.apiRequest).toHaveBeenCalledWith('/cards/card-id/prices?days=30')
    expect(root.querySelector('h1')?.textContent).toContain('Mudkip')
    expect(root.querySelector('[aria-label="Beschikbare kaartvarianten"]')?.textContent).toContain('Reverse Holo')
    expect(root.textContent).toContain('Cardmarket-prijsindicatie')
    expect(root.textContent).toContain('€ 0,15')
    expect(root.textContent).toContain('+€ 0,03 (+25%) sinds vorige meting')
    expect(root.textContent).toContain('Catalogustaal: Engels · Niet conditie-specifiek')
    expect(root.textContent).toContain('Gemiddelde prijsindicatie')
    expect(root.querySelector('#price-history-heading + [role="region"]')?.getAttribute('tabindex')).toBe('0')
    expect(root.textContent).toContain('Terug naar kaartzoeken')
  })

  it('renders a clear not-found state for an unknown card', async () => {
    // Arrange
    mocks.apiRequest.mockRejectedValue(new mocks.ApiError('Niet gevonden.', 404, 'request_failed'))
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(root.querySelector('h1')?.textContent).toContain('Kaart niet gevonden')
    expect(mocks.apiRequest).toHaveBeenCalledTimes(1)
  })

  it('keeps card details visible when the price endpoint fails', async () => {
    // Arrange
    mocks.apiRequest.mockImplementation((path: string) => (
      path.includes('/prices')
        ? Promise.reject(new mocks.ApiError('Prijsbron niet bereikbaar.', 503, 'request_failed'))
        : Promise.resolve(cardDetail())
    ))
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(root.querySelector('h1')?.textContent).toContain('Mudkip')
    expect(root.textContent).toContain('Prijsbron niet bereikbaar.')
  })

  it('explains when no market price is available', async () => {
    // Arrange
    mocks.apiRequest.mockImplementation((path: string) => Promise.resolve(
      path.includes('/prices') ? { ...priceHistory(), refreshStatus: 'no_data', prices: [], history: [] } : cardDetail(),
    ))
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(root.textContent).toContain('nog geen Cardmarket-prijs beschikbaar')
  })

  it('does not claim a previous measurement when there is no comparison yet', async () => {
    // Arrange
    const response = priceHistory()
    response.prices[0]!.change = null
    mocks.apiRequest.mockImplementation((path: string) => Promise.resolve(
      path.includes('/prices') ? response : cardDetail(),
    ))
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(root.textContent).toContain('Nog geen eerdere dagmeting')
    expect(root.textContent).not.toContain('Nog geen eerdere dagmeting sinds vorige meting')
  })

  it('distinguishes a stale cached price from a failed first refresh', async () => {
    // Arrange
    mocks.apiRequest.mockImplementation((path: string) => Promise.resolve(
      path.includes('/prices')
        ? { ...priceHistory(), refreshStatus: 'refresh_failed', checkedAt: null, prices: [], history: [] }
        : cardDetail(),
    ))
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(root.textContent).toContain('tijdelijk niet bereikbaar en er is nog geen opgeslagen meting')
    expect(root.textContent).not.toContain('laatst opgeslagen meting wordt getoond')
  })
})

function cardDetail() {
  return {
    id: 'card-id',
    name: 'Mudkip',
    number: '19/25',
    totalNumberInSet: '25',
    rarity: 'Promo',
    cardType: 'Water',
    primaryCollectible: 'Mudkip',
    illustrator: 'Test Illustrator',
    imageUrl: null,
    thumbnailUrl: null,
    releaseDate: '2021-10-22',
    metadata: {},
    set: {
      id: 'set-id',
      name: "McDonald's Collection 2021",
      code: 'mcd21',
      series: { id: 'series-id', name: 'Kanto' },
      language: 'en',
      releaseDate: '2021-10-22',
      logoUrl: null,
      symbolUrl: null,
      printedTotal: 25,
      total: 25,
      description: null,
      status: 'active',
      createdAt: '2026-08-01T10:30:00+00:00',
      updatedAt: '2026-08-01T10:30:00+00:00',
    },
    variants: [
      { id: 'normal-id', code: 'normal' },
      { id: 'reverse-id', code: 'reverse_holo' },
    ],
    externalReferences: [],
    createdAt: '2026-08-01T10:30:00+00:00',
    updatedAt: '2026-08-01T10:30:00+00:00',
  }
}

function priceHistory(): CardPriceHistoryResponse {
  return {
    cardId: 'card-id',
    source: 'tcgdex',
    market: 'cardmarket',
    currency: 'EUR',
    refreshStatus: 'refreshed',
    checkedAt: '2026-08-02T08:05:00+00:00',
    prices: [{
      variant: 'standard',
      lowest: { amount: 2, currency: 'EUR' },
      average: { amount: 11, currency: 'EUR' },
      trend: { amount: 15, currency: 'EUR' },
      averageOneDay: { amount: 5, currency: 'EUR' },
      averageSevenDays: { amount: 13, currency: 'EUR' },
      averageThirtyDays: { amount: 11, currency: 'EUR' },
      sourceUpdatedAt: '2026-08-02T08:03:04+00:00',
      observedAt: '2026-08-02T08:05:00+00:00',
      observedOn: '2026-08-02',
      change: { amount: 3, currency: 'EUR', percentage: 25 },
    }],
    history: [
      {
        variant: 'standard',
        lowest: { amount: 2, currency: 'EUR' },
        average: { amount: 11, currency: 'EUR' },
        trend: { amount: 15, currency: 'EUR' },
        averageOneDay: { amount: 5, currency: 'EUR' },
        averageSevenDays: { amount: 13, currency: 'EUR' },
        averageThirtyDays: { amount: 11, currency: 'EUR' },
        sourceUpdatedAt: '2026-08-02T08:03:04+00:00',
        observedAt: '2026-08-02T08:05:00+00:00',
        observedOn: '2026-08-02',
      },
      {
        variant: 'standard',
        lowest: { amount: 2, currency: 'EUR' },
        average: { amount: 10, currency: 'EUR' },
        trend: { amount: 12, currency: 'EUR' },
        averageOneDay: { amount: 4, currency: 'EUR' },
        averageSevenDays: { amount: 11, currency: 'EUR' },
        averageThirtyDays: { amount: 10, currency: 'EUR' },
        sourceUpdatedAt: '2026-08-01T08:03:04+00:00',
        observedAt: '2026-08-01T08:05:00+00:00',
        observedOn: '2026-08-01',
      },
    ],
    disclaimer: 'Externe Cardmarket-prijsindicatie via TCGdex; dit is geen gegarandeerde verkoopopbrengst.',
  }
}

function mountPage(): MountedPage {
  const root = document.createElement('div')
  document.body.append(root)
  const app = createApp(CatalogCardDetailPage)
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
