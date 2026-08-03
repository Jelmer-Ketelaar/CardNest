import { createApp, nextTick, type App } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => ({
  auth: {
    isAuthenticated: false,
    logout: vi.fn(),
  },
  push: vi.fn(),
  route: {
    fullPath: '/marketplace',
    name: 'marketplace',
  },
}))

vi.mock('../../src/stores/auth', () => ({
  useAuthStore: () => mocks.auth,
}))

vi.mock('vue-router', () => ({
  useRoute: () => mocks.route,
  useRouter: () => ({ push: mocks.push }),
}))

import AppShell from '../../src/App.vue'

interface MountedApp {
  app: App
  root: HTMLDivElement
}

const mountedApps: MountedApp[] = []

describe('App navigation', () => {
  beforeEach(() => {
    mocks.auth.isAuthenticated = false
    mocks.auth.logout.mockReset()
    mocks.push.mockReset()
    mocks.route.fullPath = '/marketplace'
    mocks.route.name = 'marketplace'
  })

  afterEach(() => {
    for (const mounted of mountedApps.splice(0)) {
      mounted.app.unmount()
      mounted.root.remove()
    }
  })

  it('shows only public navigation to a visitor', () => {
    // Arrange
    const { root } = mountApp()

    // Act
    const navigation = root.querySelector('#main-navigation')

    // Assert
    expect(navigation?.textContent).toContain('Catalogus')
    expect(navigation?.textContent).toContain('Aankomende sets')
    expect(navigation?.textContent).toContain('Marktplaats')
    expect(navigation?.textContent).not.toContain('Mijn verzameling')
    expect(root.textContent).toContain('Inloggen')
  })

  it('groups authenticated navigation and opens it from the menu button', async () => {
    // Arrange
    mocks.auth.isAuthenticated = true
    const { root } = mountApp()
    const toggle = findMenuToggle(root)
    const navigation = root.querySelector<HTMLElement>('#main-navigation')

    // Act
    toggle?.click()
    await nextTick()

    // Assert
    expect(toggle?.getAttribute('aria-expanded')).toBe('true')
    expect(navigation?.classList.contains('site-navigation--open')).toBe(true)
    expect(navigation?.textContent).toContain('Mijn verzameling')
    expect(navigation?.textContent).toContain('Handel')
    expect(navigation?.textContent).toContain('Mijn verkopen')
  })

  it('closes the mobile navigation when escape is pressed from the menu button', async () => {
    // Arrange
    const { root } = mountApp()
    const toggle = findMenuToggle(root)
    toggle?.click()
    await nextTick()

    // Act
    toggle.dispatchEvent(new KeyboardEvent('keydown', { bubbles: true, key: 'Escape' }))
    await nextTick()

    // Assert
    expect(toggle?.getAttribute('aria-expanded')).toBe('false')
    expect(root.querySelector('#main-navigation')?.classList.contains('site-navigation--open')).toBe(false)
  })

  it('closes the mobile navigation after a destination is selected', async () => {
    // Arrange
    const { root } = mountApp()
    const toggle = findMenuToggle(root)
    toggle.click()
    await nextTick()

    // Act
    findNavigationLink(root, 'Catalogus').click()
    await nextTick()

    // Assert
    expect(toggle.getAttribute('aria-expanded')).toBe('false')
    expect(root.querySelector('#main-navigation')?.classList.contains('site-navigation--open')).toBe(false)
  })
})

function mountApp(): MountedApp {
  const root = document.createElement('div')
  document.body.append(root)
  const app = createApp(AppShell)
  app.component('RouterLink', {
    emits: ['click'],
    props: ['to'],
    template: '<a @click="$emit(\'click\')"><slot /></a>',
  })
  app.component('RouterView', { template: '<main />' })
  app.mount(root)

  const mounted = { app, root }
  mountedApps.push(mounted)

  return mounted
}

function findMenuToggle(root: HTMLElement): HTMLButtonElement {
  const toggle = root.querySelector<HTMLButtonElement>('.navigation-toggle')
  if (!toggle) throw new Error('Expected the navigation toggle to be rendered.')

  return toggle
}

function findNavigationLink(root: HTMLElement, label: string): HTMLAnchorElement {
  const link = [...root.querySelectorAll<HTMLAnchorElement>('#main-navigation a')]
    .find((candidate) => candidate.textContent?.trim() === label)
  if (!link) throw new Error(`Expected the ${label} navigation link to be rendered.`)

  return link
}
