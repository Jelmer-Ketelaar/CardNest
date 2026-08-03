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
    push: vi.fn(),
    route: {
      fullPath: '/catalog/sets',
      name: 'catalog-sets',
      query: {} as Record<string, string>,
    },
  }
})

vi.mock('../../../src/api/client', () => ({
  ApiError: mocks.ApiError,
  apiRequest: mocks.apiRequest,
}))

vi.mock('vue-router', () => ({
  useRoute: () => mocks.route,
  useRouter: () => ({ push: mocks.push }),
}))

import CatalogSetsPage from '../../../src/pages/CatalogSetsPage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('CatalogSetsPage', () => {
  beforeEach(() => {
    mocks.apiRequest.mockReset()
    mocks.push.mockReset()
    mocks.push.mockResolvedValue(undefined)
    mocks.route.fullPath = '/catalog/sets'
    mocks.route.name = 'catalog-sets'
    mocks.route.query = {}
  })

  afterEach(() => {
    for (const page of mountedPages.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('loads catalog sets from the route page and renders them', async () => {
    // Arrange
    mocks.route.query = { page: '2' }
    mocks.apiRequest.mockResolvedValue(pageResponse())
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/card-sets?page=2&perPage=20')
    expect(root.textContent).toContain("McDonald's Collection 2021")
    expect(root.textContent).toContain('Kanto')
  })

  it('keeps route query state when pagination changes', async () => {
    // Arrange
    mocks.route.query = { page: '2', source: 'catalog' }
    mocks.apiRequest.mockResolvedValue(pageResponse({ page: 2, totalPages: 3 }))
    const { root } = mountPage()
    await flush()
    const next = Array.from(root.querySelectorAll('button')).find((button) => button.textContent?.includes('Volgende'))

    // Act
    next?.click()
    await flush()

    // Assert
    expect(mocks.push).toHaveBeenCalledWith({
      name: 'catalog-sets',
      query: { page: '3', source: 'catalog' },
    })
  })

  it('loads announced sets with search and language filters on the upcoming route', async () => {
    // Arrange
    mocks.route.fullPath = '/catalog/upcoming?name=mega&language=en'
    mocks.route.name = 'catalog-upcoming'
    mocks.route.query = { name: 'mega', language: 'en' }
    mocks.apiRequest.mockResolvedValue(pageResponse())
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith(
      '/card-sets?page=1&perPage=20&name=mega&language=en&release=upcoming',
    )
    expect(root.querySelector('h1')?.textContent).toContain('Aankomende kaartsets')
    expect(root.textContent).toContain('Bekijk alle sets')
  })

  it('renders accessible empty and API-error states', async () => {
    // Arrange
    mocks.apiRequest.mockResolvedValue(pageResponse({ items: [], total: 0, totalPages: 0 }))
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(root.querySelector('[role="status"]')?.textContent).toContain('geen kaartsets')

    // Arrange
    mocks.apiRequest.mockRejectedValue(new mocks.ApiError('Service onbereikbaar.', 503, 'service_unavailable'))
    const failing = mountPage()

    // Act
    await flush()

    // Assert
    expect(failing.root.querySelector('[role="alert"]')?.textContent).toContain('Service onbereikbaar.')
  })
})

function pageResponse(overrides: Partial<Record<'items' | 'page' | 'perPage' | 'total' | 'totalPages', unknown>> = {}) {
  return {
    items: [{
      id: '0198d5ea-37c8-7dd4-a993-48ea916b7e19',
      name: "McDonald's Collection 2021",
      code: 'mcd21',
      series: { id: '0198d5ea-37c8-7dd4-a993-48ea916b7e20', name: 'Kanto' },
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
    }],
    page: 1,
    perPage: 20,
    total: 1,
    totalPages: 1,
    ...overrides,
  }
}

function mountPage(): MountedPage {
  const root = document.createElement('div')
  document.body.append(root)
  const app = createApp(CatalogSetsPage)
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
