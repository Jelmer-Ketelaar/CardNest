import { createApp, nextTick, type App } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Reputation } from '../types/review'

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
    route: { params: { id: 'profile-user-id' } as Record<string, string> },
  }
})

vi.mock('../api/client', () => ({
  ApiError: mocks.ApiError,
  apiRequest: mocks.apiRequest,
}))

vi.mock('vue-router', () => ({
  useRoute: () => mocks.route,
}))

import UserProfilePage from './UserProfilePage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('UserProfilePage', () => {
  beforeEach(() => {
    mocks.apiRequest.mockReset()
    mocks.route.params = { id: 'profile-user-id' }
  })

  afterEach(() => {
    for (const page of mountedPages.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('loads and renders public reputation data without private order data', async () => {
    // Arrange
    mocks.apiRequest.mockResolvedValue(reputationFixture())

    // Act
    const { root } = mountPage()
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/users/profile-user-id/reputation?page=1&perPage=20')
    expect(root.textContent).toContain('4.50 / 5')
    expect(root.textContent).toContain('Zorgvuldig verpakt.')
    expect(root.textContent).not.toContain('private-order-id')
  })

  it('renders an empty public review state', async () => {
    // Arrange
    const reputation = reputationFixture()
    reputation.summary = {
      reviewCount: 0,
      averageOverall: null,
      averages: {
        descriptionAccuracy: null,
        packaging: null,
        communication: null,
        shippingSpeed: null,
      },
    }
    reputation.reviews = { items: [], page: 1, perPage: 20, total: 0, totalPages: 0 }
    mocks.apiRequest.mockResolvedValue(reputation)

    // Act
    const { root } = mountPage()
    await flush()

    // Assert
    expect(root.textContent).toContain('Nog geen reviews.')
    expect(root.textContent).toContain('0 reviews')
  })

  it('loads the next public review page', async () => {
    // Arrange
    const firstPage = reputationFixture()
    firstPage.reviews.totalPages = 2
    const secondPage = reputationFixture()
    secondPage.reviews = { ...secondPage.reviews, page: 2, totalPages: 2 }
    mocks.apiRequest.mockImplementation((path: string) => {
      if (path === '/users/profile-user-id/reputation?page=1&perPage=20') return Promise.resolve(firstPage)
      if (path === '/users/profile-user-id/reputation?page=2&perPage=20') return Promise.resolve(secondPage)

      return Promise.reject(new Error(`Unexpected request: ${path}`))
    })
    const { root } = mountPage()
    await flush()

    // Act
    clickButton(root, 'Volgende')
    await flush(5)

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/users/profile-user-id/reputation?page=2&perPage=20')
    expect(root.textContent).toContain('Pagina 2 van 2')
  })

  it('renders a safe not-found error without reputation content', async () => {
    // Arrange
    mocks.apiRequest.mockRejectedValue(new mocks.ApiError('Gebruiker niet gevonden.', 404, 'user_not_found'))

    // Act
    const { root } = mountPage()
    await flush(5)

    // Assert
    expect(root.querySelector('[role="alert"]')?.textContent).toContain('Gebruiker niet gevonden.')
    expect(root.querySelector('#reputation-summary-heading')).toBeNull()
  })
})

function reputationFixture(): Reputation {
  return {
    user: {
      id: 'profile-user-id',
      memberSince: '2026-01-01T10:30:00+00:00',
    },
    summary: {
      reviewCount: 1,
      averageOverall: 4.5,
      averages: {
        descriptionAccuracy: 5,
        packaging: 4,
        communication: 4.5,
        shippingSpeed: 4,
      },
    },
    reviews: {
      items: [{
        id: 'review-id',
        orderId: 'private-order-id',
        reviewer: { id: 'reviewer-id' },
        reviewedUser: { id: 'profile-user-id' },
        scores: {
          overall: 5,
          descriptionAccuracy: 5,
          packaging: 4,
          communication: 4,
          shippingSpeed: 4,
        },
        comment: 'Zorgvuldig verpakt.',
        createdAt: '2026-08-01T10:30:00+00:00',
      }],
      page: 1,
      perPage: 20,
      total: 1,
      totalPages: 1,
    },
  }
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
  const app = createApp(UserProfilePage)
  app.component('RouterLink', {
    props: { to: { type: [String, Object], required: true } },
    template: '<a :data-route="JSON.stringify(to)"><slot /></a>',
  })
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
