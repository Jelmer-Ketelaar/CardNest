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

  return { ApiError, apiRequest: vi.fn() }
})

vi.mock('../../../src/api/client', () => ({
  ApiError: mocks.ApiError,
  apiRequest: mocks.apiRequest,
}))

import BindersPage from '../../../src/pages/BindersPage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('BindersPage', () => {
  beforeEach(() => {
    mocks.apiRequest.mockReset()
  })

  afterEach(() => {
    for (const page of mountedPages.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('loads an owned binder overview with accessible visual slot labels', async () => {
    // Arrange
    const detail = binderDetail()
    mockOverviewRequests(detail)
    const { root } = mountPage()

    // Act
    await flush(5)

    // Assert
    expect(root.textContent).toContain('Water cards')
    const slotButton = root.querySelector<HTMLButtonElement>('.binder-slot__action')
    expect(slotButton?.getAttribute('aria-label')).toBe('Vak 1, leeg')
    expect(slotButton?.tagName).toBe('BUTTON')
    expect(root.textContent).not.toContain('Maak een binder aan om de visuele planner te openen.')
  })

  it('moves a selected card through the semantic slot button and avoids duplicate submissions while saving', async () => {
    // Arrange
    const initial = binderDetail()
    const updated = binderDetail(assignedSlotItem())
    let assignmentCalls = 0
    let resolveAssignment!: (response: ReturnType<typeof binderDetail>) => void
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/binders') return Promise.resolve(binderCollection(initial))
      if (path === '/me/collection-items?status=in_collection&perPage=100') return Promise.resolve(collectionResponse())
      if (path === '/me/storage-locations') return Promise.resolve({ items: [], total: 0 })
      if (path === '/me/binders/binder-id') return Promise.resolve(initial)
      if (path === '/me/binders/binder-id/slots/slot-id/assign' && options?.method === 'POST') {
        assignmentCalls += 1

        return new Promise<ReturnType<typeof binderDetail>>((resolve) => {
          resolveAssignment = resolve
        })
      }

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })
    const { root } = mountPage()
    await flush(5)
    const paletteItem = root.querySelector<HTMLButtonElement>('.palette-items button')
    const slot = root.querySelector<HTMLButtonElement>('.binder-slot__action')
    expect(paletteItem).not.toBeNull()
    expect(slot).not.toBeNull()

    // Act
    paletteItem!.click()
    slot!.click()
    slot!.click()
    await flush()

    // Assert
    expect(assignmentCalls).toBe(1)
    expect(slot?.disabled).toBe(true)

    // Act
    resolveAssignment(updated)
    await flush(4)

    // Assert
    expect(root.textContent).toContain('De kaart is in het bindervak geplaatst.')
    expect(root.querySelector('.binder-slot__action')?.getAttribute('aria-label')).toBe('Vak 1, gevuld met Mudkip 19/25')
  })

  it('shows a conflict returned by the validated move API', async () => {
    // Arrange
    const detail = binderDetail()
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/binders') return Promise.resolve(binderCollection(detail))
      if (path === '/me/collection-items?status=in_collection&perPage=100') return Promise.resolve(collectionResponse())
      if (path === '/me/storage-locations') return Promise.resolve({ items: [], total: 0 })
      if (path === '/me/binders/binder-id') return Promise.resolve(detail)
      if (path === '/me/binders/binder-id/slots/slot-id/assign' && options?.method === 'POST') {
        return Promise.reject(new mocks.ApiError('Dit vak is intussen gevuld.', 409, 'binder_slot_occupied'))
      }

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })
    const { root } = mountPage()
    await flush(5)

    // Act
    root.querySelector<HTMLButtonElement>('.palette-items button')?.click()
    root.querySelector<HTMLButtonElement>('.binder-slot__action')?.click()
    await flush(4)

    // Assert
    expect(root.textContent).toContain('Dit vak is intussen gevuld.')
  })
})

function binderDetail(slotItem: ReturnType<typeof assignedSlotItem> | null = null) {
  return {
    id: 'binder-id',
    name: 'Water cards',
    rows: 2,
    columns: 2,
    pageCount: 1,
    surfaceCount: 1,
    doubleSided: false,
    layoutStrategy: 'custom',
    slotCount: 4,
    occupiedSlots: slotItem ? 1 : 0,
    parentLocation: null,
    createdAt: '2026-08-01T10:30:00+00:00',
    updatedAt: '2026-08-01T10:30:00+00:00',
    pages: [{
      id: 'page-id',
      number: 1,
      side: 'front' as const,
      slots: [{
        id: 'slot-id',
        position: 1,
        row: 1,
        column: 1,
        collectionItem: slotItem,
        placeholder: null,
      }],
    }],
  }
}

function binderCollection(detail: ReturnType<typeof binderDetail>) {
  return {
    items: [{
      id: detail.id,
      name: detail.name,
      rows: detail.rows,
      columns: detail.columns,
      pageCount: detail.pageCount,
      surfaceCount: detail.surfaceCount,
      doubleSided: detail.doubleSided,
      layoutStrategy: detail.layoutStrategy,
      slotCount: detail.slotCount,
      occupiedSlots: detail.occupiedSlots,
      parentLocation: detail.parentLocation,
      createdAt: detail.createdAt,
      updatedAt: detail.updatedAt,
    }],
    total: 1,
  }
}

function collectionItem() {
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
    purchase: { price: null, purchasedAt: null, sellerName: null },
    notes: null,
    duplicate: { ownedQuantity: 1, isDuplicate: false },
    createdAt: '2026-08-01T10:30:00+00:00',
    updatedAt: '2026-08-01T10:30:00+00:00',
  }
}

function assignedSlotItem() {
  return {
    id: 'item-id',
    card: { id: 'card-id', name: 'Mudkip', number: '19/25', thumbnailUrl: null },
    language: 'en',
    variant: 'holo',
    condition: 'near_mint',
  }
}

function collectionResponse() {
  return { items: [collectionItem()], total: 1, page: 1, perPage: 100, totalPages: 1 }
}

function mockOverviewRequests(detail: ReturnType<typeof binderDetail>): void {
  mocks.apiRequest.mockImplementation((path: string) => {
    if (path === '/me/binders') return Promise.resolve(binderCollection(detail))
    if (path === '/me/collection-items?status=in_collection&perPage=100') return Promise.resolve(collectionResponse())
    if (path === '/me/storage-locations') return Promise.resolve({ items: [], total: 0 })
    if (path === '/me/binders/binder-id') return Promise.resolve(detail)

    return Promise.reject(new Error(`Unexpected request: ${path}`))
  })
}

function mountPage(): MountedPage {
  const root = document.createElement('div')
  document.body.append(root)
  const app = createApp(BindersPage)
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
