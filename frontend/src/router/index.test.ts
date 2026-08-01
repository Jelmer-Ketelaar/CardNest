import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { router } from './index'

describe('router authentication guard', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  afterEach(async () => {
    await router.replace({ name: 'login' })
  })

  it('redirects an unauthenticated visitor away from the account page', async () => {
    // Arrange
    localStorage.clear()

    // Act
    await router.push({ name: 'account' })

    // Assert
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('allows an unauthenticated visitor to open the health page', async () => {
    // Act
    await router.push({ name: 'health' })

    // Assert
    expect(router.currentRoute.value.name).toBe('health')
  })

  it('allows an unauthenticated visitor to browse catalog sets', async () => {
    // Act
    await router.push({ name: 'catalog-sets' })

    // Assert
    expect(router.currentRoute.value.name).toBe('catalog-sets')
  })

  it('allows an unauthenticated visitor to view a catalog set', async () => {
    // Act
    await router.push({ name: 'catalog-set', params: { id: 'set-id' } })

    // Assert
    expect(router.currentRoute.value.name).toBe('catalog-set')
  })

  it('allows an unauthenticated visitor to browse and view catalog cards', async () => {
    // Act
    await router.push({ name: 'catalog-cards', query: { setId: 'set-id' } })

    // Assert
    expect(router.currentRoute.value.name).toBe('catalog-cards')

    // Act
    await router.push({ name: 'catalog-card', params: { id: 'card-id' } })

    // Assert
    expect(router.currentRoute.value.name).toBe('catalog-card')
  })

  it('redirects an unauthenticated visitor away from a collection item', async () => {
    // Act
    await router.push({ name: 'collection-item', params: { id: 'item-id' } })

    // Assert
    expect(router.currentRoute.value.name).toBe('login')
  })
})
