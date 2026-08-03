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

import StoragePage from '../../../src/pages/StoragePage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

interface TestLocation {
  id: string
  name: string
  type: string
  parentId: string | null
  path: Array<{ id: string; name: string; type: string }>
  currentItemCount: number
  createdAt: string
  updatedAt: string
}

const mountedPages: MountedPage[] = []

describe('StoragePage', () => {
  beforeEach(() => {
    mocks.apiRequest.mockReset()
  })

  afterEach(() => {
    for (const page of mountedPages.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('renders loading and empty states before creating a storage location', async () => {
    // Arrange
    let locations: TestLocation[] = []
    let awaitingInitialLoad = true
    let resolveInitialLoad!: (response: { items: TestLocation[]; total: number }) => void
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/storage-locations' && options?.method === 'POST') {
        const payload = JSON.parse(String(options.body)) as { name: string; type: string }
        const location = storageLocation({ name: payload.name, type: payload.type })
        locations = [location]

        return Promise.resolve(location)
      }
      if (path === '/me/storage-locations') {
        if (awaitingInitialLoad) {
          awaitingInitialLoad = false

          return new Promise<{ items: TestLocation[]; total: number }>((resolve) => {
            resolveInitialLoad = resolve
          })
        }

        return Promise.resolve({ items: locations, total: locations.length })
      }

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(root.textContent).toContain('Opslag laden…')

    // Act
    resolveInitialLoad({ items: [], total: 0 })
    await flush(4)

    // Assert
    expect(root.textContent).toContain('Maak je eerste opslaglocatie aan.')

    // Act
    const nameInput = root.querySelector<HTMLInputElement>('#storage-name')
    const form = root.querySelector<HTMLFormElement>('form.storage-form')
    expect(nameInput).not.toBeNull()
    expect(form).not.toBeNull()
    nameInput!.value = 'Top-loaderbox'
    nameInput!.dispatchEvent(new Event('input', { bubbles: true }))
    form!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flush(4)

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith(
      '/me/storage-locations',
      expect.objectContaining({ method: 'POST', body: JSON.stringify({ name: 'Top-loaderbox', type: 'other' }) }),
    )
    expect(root.textContent).toContain('De opslaglocatie is toegevoegd.')
    expect(root.textContent).toContain('Top-loaderbox')
  })

  it('lets the user edit a location and reports a safe deletion conflict', async () => {
    // Arrange
    const location = storageLocation()
    mocks.apiRequest.mockImplementation((path: string, options?: RequestInit) => {
      if (path === '/me/storage-locations') return Promise.resolve({ items: [location], total: 1 })
      if (path === `/me/storage-locations/${location.id}` && options?.method === 'PATCH') {
        return Promise.resolve({ ...location, name: 'Nieuwe binderkast' })
      }
      if (path === `/me/storage-locations/${location.id}` && options?.method === 'DELETE') {
        return Promise.reject(new mocks.ApiError('Verplaats eerst de kaarten.', 409, 'storage_location_in_use'))
      }

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })
    const { root } = mountPage()
    await flush(4)

    // Act
    clickButton(root, 'Bewerken')
    await flush()
    const editName = root.querySelector<HTMLInputElement>('#edit-storage-name')
    const editForm = root.querySelector<HTMLFormElement>('.edit-panel form')
    expect(editName).not.toBeNull()
    expect(editForm).not.toBeNull()
    editName!.value = 'Nieuwe binderkast'
    editName!.dispatchEvent(new Event('input', { bubbles: true }))
    editForm!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flush(4)

    // Assert
    expect(root.textContent).toContain('De opslaglocatie is bijgewerkt.')

    // Act
    clickButton(root, 'Verwijderen')
    await flush()
    clickButton(root, 'Definitief verwijderen')
    await flush(4)

    // Assert
    expect(root.querySelector('#delete-storage-heading')?.textContent).toContain('Binderkast verwijderen?')
    expect(root.textContent).toContain('Verplaats eerst de kaarten.')
  })
})

function storageLocation(overrides: Partial<TestLocation> = {}): TestLocation {
  const location = {
    id: 'location-id',
    name: 'Binderkast',
    type: 'other',
    parentId: null,
    path: [],
    currentItemCount: 0,
    createdAt: '2026-08-01T10:30:00+00:00',
    updatedAt: '2026-08-01T10:30:00+00:00',
    ...overrides,
  }

  return { ...location, path: location.path.length > 0 ? location.path : [{ id: location.id, name: location.name, type: location.type }] }
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
  const app = createApp(StoragePage)
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
