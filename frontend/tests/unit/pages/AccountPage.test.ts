import { createApp, nextTick, type App } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
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
  }
})

vi.mock('../../../src/api/client', () => ({
  ApiError: mocks.ApiError,
  apiRequest: mocks.apiRequest,
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mocks.push }),
}))

import AccountPage from '../../../src/pages/AccountPage.vue'
import { useAuthStore } from '../../../src/stores/auth'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('AccountPage', () => {
  beforeEach(() => {
    localStorage.clear()
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

  it('loads and displays only safe account fields', async () => {
    // Arrange
    mocks.apiRequest.mockResolvedValue({
      user: {
        id: '0198d5ea-37c8-7dd4-a993-48ea916b7e19',
        email: 'collector@example.com',
        roles: ['ROLE_ADMIN'],
        emailVerifiedAt: null,
        createdAt: '2026-08-01T10:30:00+00:00',
        passwordHash: 'must-not-be-rendered',
      },
    })
    const { root } = mountPage()

    // Act
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/me')
    expect(root.textContent).toContain('collector@example.com')
    expect(root.textContent).toContain('Nog niet bevestigd')
    expect(root.textContent).toContain('Lid sinds')
    expect(root.textContent).not.toContain('ROLE_ADMIN')
    expect(root.textContent).not.toContain('must-not-be-rendered')
  })

  it('logs out and redirects to the login page', async () => {
    // Arrange
    localStorage.setItem('cardnest.authToken', 'active-token')
    mocks.apiRequest.mockResolvedValueOnce({
      user: {
        id: '0198d5ea-37c8-7dd4-a993-48ea916b7e19',
        email: 'collector@example.com',
        roles: ['ROLE_USER'],
        emailVerifiedAt: '2026-08-01T10:30:00+00:00',
        createdAt: '2026-08-01T10:30:00+00:00',
      },
    })
    mocks.apiRequest.mockResolvedValueOnce(undefined)
    const { root, pinia } = mountPage()
    await flush()
    const button = root.querySelector<HTMLButtonElement>('button')
    expect(button).not.toBeNull()

    // Act
    button?.click()
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenLastCalledWith('/auth/logout', { method: 'POST' })
    expect(useAuthStore(pinia).isAuthenticated).toBe(false)
    expect(localStorage.getItem('cardnest.authToken')).toBeNull()
    expect(mocks.push).toHaveBeenCalledWith({ name: 'login' })
  })
})

function mountPage(): MountedPage & { pinia: ReturnType<typeof createPinia> } {
  const root = document.createElement('div')
  document.body.append(root)
  const pinia = createPinia()
  setActivePinia(pinia)
  const app = createApp(AccountPage)
  app.use(pinia)
  app.mount(root)

  const page = { app, root, pinia }
  mountedPages.push(page)

  return page
}

async function flush(): Promise<void> {
  await Promise.resolve()
  await nextTick()
  await Promise.resolve()
  await nextTick()
}
