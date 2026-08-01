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
})
