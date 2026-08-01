import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { apiRequest } from '../api/client'
import type { AuthResponse, User } from '../types/auth'

const tokenStorageKey = 'cardnest.authToken'
const userStorageKey = 'cardnest.authUser'

function storedUser(): User | null {
  const serialized = localStorage.getItem(userStorageKey)
  if (!serialized) return null

  try {
    const value: unknown = JSON.parse(serialized)
    if (typeof value !== 'object' || value === null) return null
    const candidate = value as Partial<User>
    if (
      typeof candidate.id !== 'string'
      || typeof candidate.email !== 'string'
      || !Array.isArray(candidate.roles)
      || !candidate.roles.every((role) => typeof role === 'string')
      || (candidate.emailVerifiedAt !== null && typeof candidate.emailVerifiedAt !== 'string')
      || typeof candidate.createdAt !== 'string'
    ) return null

    return candidate as User
  } catch {
    return null
  }
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(storedUser())
  const token = ref<string | null>(localStorage.getItem(tokenStorageKey))
  const isAuthenticated = computed(() => token.value !== null)

  function updateUser(updatedUser: User): void {
    user.value = updatedUser
    localStorage.setItem(userStorageKey, JSON.stringify(updatedUser))
  }

  async function login(email: string, password: string): Promise<void> {
    const response = await apiRequest<AuthResponse>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    })
    localStorage.setItem(tokenStorageKey, response.token)
    token.value = response.token
    updateUser(response.user)
  }

  async function logout(): Promise<void> {
    try {
      await apiRequest<void>('/auth/logout', { method: 'POST' })
    } finally {
      localStorage.removeItem(tokenStorageKey)
      localStorage.removeItem(userStorageKey)
      token.value = null
      user.value = null
    }
  }

  return { user, isAuthenticated, login, logout, updateUser }
})
