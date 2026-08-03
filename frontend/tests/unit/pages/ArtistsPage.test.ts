import { createApp, nextTick, type App } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => ({
  apiRequest: vi.fn(),
  push: vi.fn(),
  route: { fullPath: '/artists', query: {} as Record<string, string> },
}))

vi.mock('../../../src/api/client', () => ({
  ApiError: class ApiError extends Error {},
  apiRequest: mocks.apiRequest,
}))
vi.mock('vue-router', () => ({
  useRoute: () => mocks.route,
  useRouter: () => ({ push: mocks.push }),
}))

import ArtistsPage from '../../../src/pages/ArtistsPage.vue'

const mounted: Array<{ app: App; root: HTMLDivElement }> = []

describe('ArtistsPage', () => {
  beforeEach(() => {
    mocks.apiRequest.mockReset()
    mocks.push.mockReset()
    mocks.route.fullPath = '/artists'
    mocks.route.query = {}
  })

  afterEach(() => {
    for (const page of mounted.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('lists artists with their card counts', async () => {
    // Arrange
    mocks.apiRequest.mockResolvedValue({
      items: [{ id: 'artist-id', name: 'Mitsuhiro Arita', slug: 'mitsuhiro-arita', cardCount: 184 }],
      total: 1,
      page: 1,
      perPage: 24,
      totalPages: 1,
    })

    // Act
    const { root } = mountPage()
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/artists?q=&sort=name&page=1&perPage=24')
    expect(root.textContent).toContain('Mitsuhiro Arita')
    expect(root.textContent).toContain('184 kaarten')
  })
})

function mountPage(): { app: App; root: HTMLDivElement } {
  const root = document.createElement('div')
  document.body.append(root)
  const app = createApp(ArtistsPage)
  app.component('RouterLink', { props: ['to'], template: '<a><slot /></a>' })
  app.mount(root)
  const page = { app, root }
  mounted.push(page)
  return page
}

async function flush(): Promise<void> {
  await Promise.resolve()
  await nextTick()
  await Promise.resolve()
  await nextTick()
}
