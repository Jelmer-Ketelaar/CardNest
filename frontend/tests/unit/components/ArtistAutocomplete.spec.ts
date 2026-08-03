// Vendor
import { createApp, nextTick, type App } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// Components
import ArtistAutocomplete from '../../../src/components/ArtistAutocomplete.vue'

// Types
import type { ArtistSummary } from '../../../src/types/catalog'

const mocks = vi.hoisted(() => ({
  apiRequest: vi.fn(),
}))

vi.mock('../../../src/api/client', () => ({
  apiRequest: mocks.apiRequest,
}))

interface Deferred<T> {
  promise: Promise<T>
  reject: (reason?: unknown) => void
  resolve: (value: T) => void
}

interface MountedAutocomplete {
  app: App
  root: HTMLDivElement
  selectedArtists: ArtistSummary[]
}

const mountedAutocompletes: MountedAutocomplete[] = []

const arita: ArtistSummary = {
  id: 'artist-arita',
  name: 'Mitsuhiro Arita',
  slug: 'mitsuhiro-arita',
  cardCount: 184,
}

const requiredElement = <T extends Element>(root: ParentNode, selector: string): T => {
  const element = root.querySelector<T>(selector)
  if (element === null) {
    throw new Error(`Expected element matching "${selector}".`)
  }

  return element
}

const deferred = <T>(): Deferred<T> => {
  let resolvePromise!: (value: T) => void
  let rejectPromise!: (reason?: unknown) => void
  const promise = new Promise<T>((resolve, reject) => {
    resolvePromise = resolve
    rejectPromise = reject
  })

  return {
    promise,
    reject: rejectPromise,
    resolve: resolvePromise,
  }
}

const mountAutocomplete = (): MountedAutocomplete => {
  const root = document.createElement('div')
  document.body.append(root)
  const selectedArtists: ArtistSummary[] = []
  const app = createApp(ArtistAutocomplete, {
    id: 'artist-search',
    label: 'Artist / illustrator',
    modelValue: '',
    'onUpdate:modelValue': () => undefined,
    onSelect: (artist: ArtistSummary) => selectedArtists.push(artist),
  })
  app.mount(root)
  const mounted = { app, root, selectedArtists }
  mountedAutocompletes.push(mounted)

  return mounted
}

const enterQuery = (root: ParentNode, value: string): HTMLInputElement => {
  const input = requiredElement<HTMLInputElement>(root, '#artist-search')
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))

  return input
}

const flush = async (cycles = 2): Promise<void> => {
  for (let index = 0; index < cycles; index += 1) {
    await Promise.resolve()
    await nextTick()
  }
}

beforeEach(() => {
  vi.useFakeTimers()
  mocks.apiRequest.mockReset()
})

afterEach(() => {
  for (const autocomplete of mountedAutocompletes.splice(0)) {
    autocomplete.app.unmount()
    autocomplete.root.remove()
  }
  vi.useRealTimers()
})

describe('ArtistAutocomplete', () => {
  it('debounces suggestion requests and renders their card counts', async () => {
    // Arrange
    mocks.apiRequest.mockResolvedValue({ items: [arita] })
    const { root } = mountAutocomplete()

    // Act
    const input = enterQuery(root, 'Arita')
    await vi.advanceTimersByTimeAsync(249)

    // Assert
    expect(mocks.apiRequest).not.toHaveBeenCalled()

    // Act
    await vi.advanceTimersByTimeAsync(1)
    await flush()

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/artists/suggestions?q=Arita&limit=10')
    expect(root.querySelector('[role="option"]')?.textContent).toContain('Mitsuhiro Arita')
    expect(root.querySelector('[role="option"]')?.textContent).toContain('184 kaarten')
    expect(input.getAttribute('aria-expanded')).toBe('true')
  })

  it('selects the highlighted artist with the keyboard', async () => {
    // Arrange
    const secondArtist: ArtistSummary = {
      id: 'artist-ken',
      name: 'Ken Sugimori',
      slug: 'ken-sugimori',
      cardCount: 96,
    }
    mocks.apiRequest.mockResolvedValue({ items: [arita, secondArtist] })
    const { root, selectedArtists } = mountAutocomplete()
    const input = enterQuery(root, 'artist')
    await vi.advanceTimersByTimeAsync(250)
    await flush()

    // Act
    input.dispatchEvent(new KeyboardEvent('keydown', {
      bubbles: true,
      cancelable: true,
      key: 'ArrowDown',
    }))
    input.dispatchEvent(new KeyboardEvent('keydown', {
      bubbles: true,
      cancelable: true,
      key: 'Enter',
    }))
    await flush()

    // Assert
    expect(selectedArtists).toEqual([secondArtist])
    expect(input.getAttribute('aria-expanded')).toBe('false')
    expect(document.activeElement).toBe(input)
  })

  it('does not let a stale response replace newer suggestions', async () => {
    // Arrange
    const firstRequest = deferred<{ items: ArtistSummary[] }>()
    const secondRequest = deferred<{ items: ArtistSummary[] }>()
    const secondArtist: ArtistSummary = {
      id: 'artist-ken',
      name: 'Ken Sugimori',
      slug: 'ken-sugimori',
      cardCount: 96,
    }
    mocks.apiRequest
      .mockReturnValueOnce(firstRequest.promise)
      .mockReturnValueOnce(secondRequest.promise)
    const { root } = mountAutocomplete()
    enterQuery(root, 'Arita')
    await vi.advanceTimersByTimeAsync(250)
    enterQuery(root, 'Sugimori')
    await vi.advanceTimersByTimeAsync(250)

    // Act
    secondRequest.resolve({ items: [secondArtist] })
    await flush()
    firstRequest.resolve({ items: [arita] })
    await flush()

    // Assert
    expect(root.textContent).toContain('Ken Sugimori')
    expect(root.textContent).not.toContain('Mitsuhiro Arita')
  })

  it('shows explicit empty and request-error states', async () => {
    // Arrange
    mocks.apiRequest.mockResolvedValueOnce({ items: [] })
    const { root } = mountAutocomplete()

    // Act
    enterQuery(root, 'Unknown')
    await vi.advanceTimersByTimeAsync(250)
    await flush()

    // Assert
    expect(root.textContent).toContain('Geen artists gevonden voor “Unknown”.')

    // Act
    mocks.apiRequest.mockRejectedValueOnce(new Error('Network unavailable'))
    enterQuery(root, 'Arita')
    await vi.advanceTimersByTimeAsync(250)
    await flush()

    // Assert
    expect(root.querySelector('[role="alert"]')?.textContent).toContain('Artist-suggesties konden niet worden geladen.')
  })
})
