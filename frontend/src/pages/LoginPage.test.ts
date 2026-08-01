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
    login: vi.fn(),
    push: vi.fn(),
    route: { query: {} as Record<string, unknown> },
  }
})

vi.mock('../api/client', () => ({ ApiError: mocks.ApiError }))

vi.mock('../stores/auth', () => ({
  useAuthStore: () => ({ login: mocks.login }),
}))

vi.mock('vue-router', () => ({
  useRoute: () => mocks.route,
  useRouter: () => ({ push: mocks.push }),
}))

import LoginPage from './LoginPage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('LoginPage', () => {
  beforeEach(() => {
    mocks.login.mockReset()
    mocks.push.mockReset()
    mocks.push.mockResolvedValue(undefined)
    mocks.route.query = {}
  })

  afterEach(() => {
    for (const page of mountedPages.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('updates the authenticated session and redirects after login', async () => {
    // Arrange
    mocks.login.mockResolvedValue(undefined)
    const { root } = mountPage()
    fill(root, 'email', 'collector@example.com')
    fill(root, 'password', 'A-strong-password-123')

    // Act
    await submit(root)

    // Assert
    expect(root.querySelector<HTMLInputElement>('#email')?.required).toBe(true)
    expect(root.querySelector<HTMLInputElement>('#password')?.required).toBe(true)
    expect(mocks.login).toHaveBeenCalledWith('collector@example.com', 'A-strong-password-123')
    expect(mocks.push).toHaveBeenCalledWith({ name: 'dashboard' })
  })

  it('renders the generic server error for invalid credentials', async () => {
    // Arrange
    mocks.login.mockRejectedValue(new mocks.ApiError(
      'Ongeldig e-mailadres of wachtwoord.',
      401,
      'invalid_credentials',
    ))
    const { root } = mountPage()
    fill(root, 'email', 'collector@example.com')
    fill(root, 'password', 'wrong-password')

    // Act
    await submit(root)

    // Assert
    expect(root.querySelector('[role="alert"]')?.textContent).toContain('Ongeldig e-mailadres of wachtwoord.')
    expect(mocks.push).not.toHaveBeenCalled()
  })
})

function mountPage(): MountedPage {
  const root = document.createElement('div')
  document.body.append(root)
  const app = createApp(LoginPage)
  app.component('RouterLink', { template: '<a><slot /></a>' })
  app.mount(root)

  const page = { app, root }
  mountedPages.push(page)

  return page
}

function fill(root: HTMLElement, id: string, value: string): void {
  const input = root.querySelector<HTMLInputElement>(`#${id}`)
  expect(input).not.toBeNull()
  if (!input) return

  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
}

async function submit(root: HTMLElement): Promise<void> {
  const form = root.querySelector('form')
  expect(form).not.toBeNull()
  form?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
  await Promise.resolve()
  await nextTick()
  await Promise.resolve()
  await nextTick()
}
