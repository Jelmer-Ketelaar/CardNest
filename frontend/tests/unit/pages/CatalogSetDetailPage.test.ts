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
    route: { params: { id: 'set-id' } },
  }
})

vi.mock('../../../src/api/client', () => ({
  ApiError: mocks.ApiError,
  apiRequest: mocks.apiRequest,
}))

vi.mock('vue-router', () => ({
  useRoute: () => mocks.route,
}))

import CatalogSetDetailPage from '../../../src/pages/CatalogSetDetailPage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('CatalogSetDetailPage', () => {
  beforeEach(() => {
    mocks.apiRequest.mockReset()
    mocks.route.params = { id: 'set-id' }
  })

  afterEach(() => {
    for (const page of mountedPages.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('loads and renders the documented set-detail response', async () => {
    // Arrange
    mocks.apiRequest.mockResolvedValue(cardSet())
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/card-sets/set-id')
    expect(root.querySelector('h1')?.textContent).toContain("McDonald's Collection 2021")
    expect(root.textContent).toContain('Bekijk kaarten in deze set')
    expect(root.textContent).toContain('25')
  })

  it('renders a clear not-found state for an unknown set', async () => {
    // Arrange
    mocks.apiRequest.mockRejectedValue(new mocks.ApiError('Niet gevonden.', 404, 'request_failed'))
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(root.querySelector('h1')?.textContent).toContain('Kaartset niet gevonden')
    expect(root.textContent).toContain('Terug naar kaartsets')
  })
})

function cardSet() {
  return {
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
    description: 'Een promotionele set.',
    status: 'active',
    createdAt: '2026-08-01T10:30:00+00:00',
    updatedAt: '2026-08-01T10:30:00+00:00',
  }
}

function mountPage(): MountedPage {
  const root = document.createElement('div')
  document.body.append(root)
  const app = createApp(CatalogSetDetailPage)
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
