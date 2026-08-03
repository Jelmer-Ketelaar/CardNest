import { beforeEach } from 'vitest'

const values = new Map<string, string>()

const browserStorage: Storage = {
  get length(): number {
    return values.size
  },
  clear(): void {
    values.clear()
  },
  getItem(key: string): string | null {
    return values.get(key) ?? null
  },
  key(index: number): string | null {
    return [...values.keys()][index] ?? null
  },
  removeItem(key: string): void {
    values.delete(key)
  },
  setItem(key: string, value: string): void {
    values.set(key, value)
  },
}

function installBrowserLocalStorage(): void {
  Object.defineProperty(globalThis, 'localStorage', {
    configurable: true,
    value: browserStorage,
    writable: true,
  })
}

installBrowserLocalStorage()
beforeEach(installBrowserLocalStorage)
