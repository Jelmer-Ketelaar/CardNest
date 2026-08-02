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

    // Act
    await router.push({ name: 'catalog-upcoming' })

    // Assert
    expect(router.currentRoute.value.name).toBe('catalog-upcoming')
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

  it('allows an unauthenticated visitor to browse artists', async () => {
    // Act
    await router.push({ name: 'artists' })

    // Assert
    expect(router.currentRoute.value.name).toBe('artists')

    // Act
    await router.push({ name: 'artist-detail', params: { slug: 'mitsuhiro-arita' } })

    // Assert
    expect(router.currentRoute.value.name).toBe('artist-detail')
  })

  it('allows an unauthenticated visitor to view a public marketplace listing', async () => {
    // Act
    await router.push({ name: 'marketplace-listing', params: { id: 'listing-id' } })

    // Assert
    expect(router.currentRoute.value.name).toBe('marketplace-listing')
  })

  it('redirects an unauthenticated visitor away from a collection item', async () => {
    // Act
    await router.push({ name: 'collection-item', params: { id: 'item-id' } })

    // Assert
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('redirects an unauthenticated visitor away from order pages', async () => {
    // Act
    await router.push({ name: 'orders' })

    // Assert
    expect(router.currentRoute.value.name).toBe('login')

    // Act
    await router.push({ name: 'seller-orders' })

    // Assert
    expect(router.currentRoute.value.name).toBe('login')

    // Act
    await router.push({ name: 'order-detail', params: { id: 'order-id' } })

    // Assert
    expect(router.currentRoute.value.name).toBe('login')
  })
})
