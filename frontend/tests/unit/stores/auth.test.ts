import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => ({ apiRequest: vi.fn() }))

vi.mock('../../../src/api/client', () => ({ apiRequest: mocks.apiRequest }))

import { useAuthStore } from '../../../src/stores/auth'

describe('auth store', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    mocks.apiRequest.mockReset()
  })

  it('stores the authenticated session after a successful login', async () => {
    // Arrange
    const response = {
      token: 'a-session-token',
      user: {
        id: '0198d5ea-37c8-7dd4-a993-48ea916b7e19',
        email: 'collector@example.com',
        roles: ['ROLE_USER'],
        emailVerifiedAt: null,
        createdAt: '2026-08-01T10:30:00+00:00',
      },
    }
    mocks.apiRequest.mockResolvedValue(response)
    const store = useAuthStore()

    // Act
    await store.login('collector@example.com', 'A-strong-password-123')

    // Assert
    expect(mocks.apiRequest).toHaveBeenCalledWith('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email: 'collector@example.com', password: 'A-strong-password-123' }),
    })
    expect(store.isAuthenticated).toBe(true)
    expect(store.user).toEqual(response.user)
    expect(localStorage.getItem('cardnest.authToken')).toBe(response.token)
    expect(JSON.parse(localStorage.getItem('cardnest.authUser') ?? 'null')).toEqual(response.user)
  })
})
