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
    route: { fullPath: '/catalog/cards', query: {} as Record<string, string> },
  }
})

vi.mock('../api/client', () => ({
  ApiError: mocks.ApiError,
  apiRequest: mocks.apiRequest,
}))

vi.mock('vue-router', () => ({
  useRoute: () => mocks.route,
  useRouter: () => ({ push: mocks.push }),
}))

import CatalogCardsPage from './CatalogCardsPage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('CatalogCardsPage', () => {
  beforeEach(() => {
    mocks.apiRequest.mockReset()
    mocks.push.mockReset()
    mocks.push.mockResolvedValue(undefined)
    mocks.route.fullPath = '/catalog/cards'
    mocks.route.query = {}
  })

  afterEach(() => {
    for (const page of mountedPages.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('loads cards with route-bound filters and pagination', async () => {
    // Arrange
    mocks.route.fullPath = '/catalog/cards?setId=set-id&name=Mudkip&variant=holo&page=2'
    mocks.route.query = { setId: 'set-id', name: 'Mudkip', variant: 'holo', page: '2' }
    mocks.apiRequest.mockResolvedValue(cardResponse())
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/cards?page=2&perPage=24&setId=set-id&name=Mudkip&variant=holo')
    expect(root.textContent).toContain('Mudkip')
    expect(root.textContent).toContain('Holo')
  })

  it('writes submitted filters to route state', async () => {
    // Arrange
    mocks.apiRequest.mockResolvedValue(cardResponse())
    const { root } = mountPage()
    await flush()
    const input = root.querySelector<HTMLInputElement>('#catalog-card-name')
    const form = root.querySelector('form')
    input!.value = 'Pikachu'
    input!.dispatchEvent(new Event('input', { bubbles: true }))

    // Act
    form?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flush()

    // Assert
    expect(mocks.push).toHaveBeenCalledWith({ name: 'catalog-cards', query: { name: 'Pikachu' } })
  })

  it('renders API validation feedback accessibly', async () => {
    // Arrange
    mocks.apiRequest.mockRejectedValue(new mocks.ApiError(
      'Controleer je zoekfilters.',
      422,
      'validation_failed',
      { variant: ['Deze variant is niet geldig.'] },
    ))
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(root.querySelector('[role="alert"]')?.textContent).toContain('Controleer je zoekfilters.')
    expect(root.querySelector('[aria-label="Validatiefouten"]')?.textContent).toContain('Deze variant is niet geldig.')
  })
})

function cardResponse() {
  return {
    items: [{
      id: 'card-id',
      name: 'Mudkip',
      number: '19/25',
      rarity: 'Promo',
      imageUrl: null,
      thumbnailUrl: null,
      set: { id: 'set-id', name: "McDonald's Collection 2021" },
      variants: [{ id: 'variant-id', code: 'holo' }],
    }],
    page: 2,
    perPage: 24,
    total: 25,
    totalPages: 2,
  }
}

function mountPage(): MountedPage {
  const root = document.createElement('div')
  document.body.append(root)
  const app = createApp(CatalogCardsPage)
  app.component('RouterLink', {
    props: { to: { type: [String, Object], required: true } },
    template: '<a :data-route="JSON.stringify(to)"><slot /></a>',
  })
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
