import { createApp, defineComponent, h, nextTick, ref } from 'vue'
import { afterEach, describe, expect, it } from 'vitest'

import type { App } from 'vue'

import BaseModal from '../../../src/components/BaseModal.vue'

interface MountedModalFlow {
  app: App
  opener: HTMLButtonElement
  root: HTMLDivElement
}

const mountedFlows: MountedModalFlow[] = []

const requiredElement = <T extends Element>(root: ParentNode, selector: string): T => {
  const element = root.querySelector<T>(selector)
  if (element === null) {
    throw new Error(`Expected element matching "${selector}".`)
  }

  return element
}

const mountModalFlow = (): MountedModalFlow => {
  const ModalFlow = defineComponent({
    setup: () => {
      const isOpen = ref(false)

      const openModal = (): void => {
        isOpen.value = true
      }

      const closeModal = (): void => {
        isOpen.value = false
      }

      return () => h('div', [
        h('button', { id: 'modal-opener', type: 'button', onClick: openModal }, 'Open modal'),
        isOpen.value
          ? h(BaseModal, {
              headingId: 'modal-heading',
              panelClass: 'test-panel',
              onClose: closeModal,
            }, {
              default: () => [
                h('h2', { id: 'modal-heading' }, 'Test modal'),
                h('button', { id: 'first-action', type: 'button' }, 'First action'),
                h('button', { id: 'last-action', type: 'button' }, 'Last action'),
              ],
            })
          : null,
      ])
    },
  })
  const root = document.createElement('div')
  document.body.append(root)
  const app = createApp(ModalFlow)
  app.mount(root)
  const mountedFlow = {
    app,
    opener: requiredElement<HTMLButtonElement>(root, '#modal-opener'),
    root,
  }
  mountedFlows.push(mountedFlow)

  return mountedFlow
}

const flush = async (cycles = 2): Promise<void> => {
  for (let index = 0; index < cycles; index += 1) {
    await Promise.resolve()
    await nextTick()
  }
}

afterEach(() => {
  for (const flow of mountedFlows.splice(0)) {
    flow.app.unmount()
    flow.root.remove()
  }
})

describe('BaseModal', () => {
  it('moves focus to the first available control when opened', async () => {
    // Arrange
    const { opener, root } = mountModalFlow()
    opener.focus()

    // Act
    opener.click()
    await flush()

    // Assert
    const dialog = requiredElement<HTMLElement>(root, '[role="dialog"]')
    const firstAction = requiredElement<HTMLButtonElement>(root, '#first-action')
    expect(dialog.getAttribute('aria-labelledby')).toBe('modal-heading')
    expect(document.activeElement).toBe(firstAction)
  })

  it('keeps forward and reverse tab navigation inside the dialog', async () => {
    // Arrange
    const { opener, root } = mountModalFlow()
    opener.click()
    await flush()
    const firstAction = requiredElement<HTMLButtonElement>(root, '#first-action')
    const lastAction = requiredElement<HTMLButtonElement>(root, '#last-action')

    // Act
    lastAction.focus()
    lastAction.dispatchEvent(new KeyboardEvent('keydown', {
      bubbles: true,
      cancelable: true,
      key: 'Tab',
    }))

    // Assert
    expect(document.activeElement).toBe(firstAction)

    // Act
    firstAction.dispatchEvent(new KeyboardEvent('keydown', {
      bubbles: true,
      cancelable: true,
      key: 'Tab',
      shiftKey: true,
    }))

    // Assert
    expect(document.activeElement).toBe(lastAction)
  })

  it('closes on Escape and restores focus to the opening control', async () => {
    // Arrange
    const { opener, root } = mountModalFlow()
    opener.focus()
    opener.click()
    await flush()
    const firstAction = requiredElement<HTMLButtonElement>(root, '#first-action')

    // Act
    firstAction.dispatchEvent(new KeyboardEvent('keydown', {
      bubbles: true,
      cancelable: true,
      key: 'Escape',
    }))
    await flush(3)

    // Assert
    expect(root.querySelector('[role="dialog"]')).toBeNull()
    expect(document.activeElement).toBe(opener)
  })
})
