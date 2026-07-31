import { ref } from 'vue'
import { defineStore } from 'pinia'
import { apiRequest } from '../api/client'
import type { AuthResponse, User } from '../types/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)

  async function login(email: string, password: string): Promise<void> {
    const response = await apiRequest<AuthResponse>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    })
    localStorage.setItem('cardnest.authToken', response.token)
    user.value = response.user
  }

  async function logout(): Promise<void> {
    try {
      await apiRequest<void>('/auth/logout', { method: 'POST' })
    } finally {
      localStorage.removeItem('cardnest.authToken')
      user.value = null
    }
  }

  return { user, login, logout }
})
