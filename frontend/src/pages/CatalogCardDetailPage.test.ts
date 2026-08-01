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
    route: { params: { id: 'card-id' }, query: { name: 'Mudkip' } },
  }
})

vi.mock('../api/client', () => ({
  ApiError: mocks.ApiError,
  apiRequest: mocks.apiRequest,
}))

vi.mock('vue-router', () => ({
  useRoute: () => mocks.route,
}))

import CatalogCardDetailPage from './CatalogCardDetailPage.vue'

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
    mocks.apiRequest.mockResolvedValue(cardDetail())
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/cards/card-id')
    expect(root.querySelector('h1')?.textContent).toContain('Mudkip')
    expect(root.querySelector('[aria-label="Beschikbare kaartvarianten"]')?.textContent).toContain('Reverse Holo')
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
