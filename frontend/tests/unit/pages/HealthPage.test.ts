import { createApp, type App } from 'vue'
import { afterEach, describe, expect, it } from 'vitest'
import HealthPage from '../../../src/pages/HealthPage.vue'

interface MountedPage {
  app: App
  root: HTMLDivElement
}

const mountedPages: MountedPage[] = []

describe('HealthPage', () => {
  afterEach(() => {
    for (const page of mountedPages.splice(0)) {
      page.app.unmount()
      page.root.remove()
    }
  })

  it('renders the public frontend health state', () => {
    // Arrange
    const root = document.createElement('div')
    document.body.append(root)
    const app = createApp(HealthPage)
    app.component('RouterLink', { template: '<a><slot /></a>' })
    app.mount(root)
    mountedPages.push({ app, root })

    // Assert
    expect(root.querySelector('h1')?.textContent).toContain('Frontend beschikbaar')
    expect(root.querySelector('[role="status"]')?.textContent).toContain('CardNest-frontend draait')
  })
})
