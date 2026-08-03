import { createApp, nextTick, type App } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => ({
  apiRequest: vi.fn(),
  route: { fullPath: '/artists/mitsuhiro-arita', params: { slug: 'mitsuhiro-arita' }, query: {} },
}))

vi.mock('../../../src/api/client', () => ({
  ApiError: class ApiError extends Error {},
  apiRequest: mocks.apiRequest,
}))
vi.mock('vue-router', () => ({
  useRoute: () => mocks.route,
  useRouter: () => ({ push: vi.fn() }),
}))

import ArtistDetailPage from '../../../src/pages/ArtistDetailPage.vue'

const mounted: Array<{ app: App; root: HTMLDivElement }> = []

describe('ArtistDetailPage', () => {
  beforeEach(() => {
    mocks.apiRequest.mockReset()
    mocks.apiRequest
      .mockResolvedValueOnce({
        id: 'artist-id',
        name: 'Mitsuhiro Arita',
        slug: 'mitsuhiro-arita',
        cardCount: 1,
        biography: null,
        websiteUrl: null,
        profileImage: null,
      })
      .mockResolvedValueOnce({
        items: [{
          id: 'card-id',
          name: 'Pikachu',
          number: '25/102',
          rarity: 'Rare',
          imageUrl: 'https://images.example/pikachu.webp',
          thumbnailUrl: null,
          artists: [],
          set: { id: 'set-id', name: 'Base Set' },
          variants: [],
        }],
        total: 1,
        page: 1,
        perPage: 24,
        totalPages: 1,
      })
  })

  afterEach(() => {
    for (const page of mounted.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('shows an artist and their catalog cards', async () => {
    // Act
    const { root } = mountPage()
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenNthCalledWith(1, '/artists/slug/mitsuhiro-arita')
    expect(mocks.apiRequest).toHaveBeenNthCalledWith(2, '/artists/artist-id/cards?page=1&perPage=24')
    expect(root.textContent).toContain('Mitsuhiro Arita')
    expect(root.textContent).toContain('Pikachu')
  })
})

function mountPage(): { app: App; root: HTMLDivElement } {
  const root = document.createElement('div')
  document.body.append(root)
  const app = createApp(ArtistDetailPage)
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
