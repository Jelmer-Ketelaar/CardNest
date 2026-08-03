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
  }
})

vi.mock('../../../src/api/client', () => ({
  ApiError: mocks.ApiError,
  apiRequest: mocks.apiRequest,
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mocks.push }),
}))

import RegistrationPage from '../../../src/pages/RegistrationPage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('RegistrationPage', () => {
  beforeEach(() => {
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

  it('submits registration data and redirects to login after success', async () => {
    // Arrange
    mocks.apiRequest.mockResolvedValue({
      user: {
        id: '0198d5ea-37c8-7dd4-a993-48ea916b7e19',
        email: 'collector@example.com',
        roles: ['ROLE_USER'],
        emailVerifiedAt: null,
        createdAt: '2026-08-01T10:30:00+00:00',
      },
    })
    const { root } = mountPage()
    fill(root, 'email', 'collector@example.com')
    fill(root, 'password', 'A-strong-password-123')
    fill(root, 'password-confirmation', 'A-strong-password-123')

    // Act
    await submit(root)

    // Assert
    expect(root.querySelector<HTMLInputElement>('#email')?.required).toBe(true)
    expect(root.querySelector<HTMLInputElement>('#password')?.required).toBe(true)
    expect(root.querySelector<HTMLInputElement>('#password-confirmation')?.required).toBe(true)
    expect(mocks.apiRequest).toHaveBeenCalledWith('/auth/register', {
      method: 'POST',
      body: JSON.stringify({
        email: 'collector@example.com',
        password: 'A-strong-password-123',
        passwordConfirmation: 'A-strong-password-123',
      }),
    })
    expect(mocks.push).toHaveBeenCalledWith({ name: 'login', query: { registered: '1' } })
  })

  it('renders server-side validation errors beside the matching fields', async () => {
    // Arrange
    mocks.apiRequest.mockRejectedValue(new mocks.ApiError(
      'Controleer de ingevulde gegevens.',
      422,
      'validation_failed',
      {
        email: ['Vul een geldig e-mailadres in.'],
        password: ['Kies een sterker wachtwoord.'],
      },
    ))
    const { root } = mountPage()
    fill(root, 'email', 'not-an-email')
    fill(root, 'password', 'short')
    fill(root, 'password-confirmation', 'short')

    // Act
    await submit(root)

    // Assert
    expect(root.textContent).toContain('Controleer de ingevulde gegevens.')
    expect(root.querySelector('#email-error')?.textContent).toContain('Vul een geldig e-mailadres in.')
    expect(root.querySelector('#password-error')?.textContent).toContain('Kies een sterker wachtwoord.')
    expect(root.querySelector<HTMLInputElement>('#email')?.getAttribute('aria-invalid')).toBe('true')
    expect(mocks.push).not.toHaveBeenCalled()
  })
})

function mountPage(): MountedPage {
  const root = document.createElement('div')
  document.body.append(root)
  const app = createApp(RegistrationPage)
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
