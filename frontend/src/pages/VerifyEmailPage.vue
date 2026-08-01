<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import ErrorMessage from '../components/ErrorMessage.vue'
import { useAuthStore } from '../stores/auth'
import type { User } from '../types/auth'

const route = useRoute()
const auth = useAuthStore()
const verifying = ref(true)
const verified = ref(false)
const error = ref('')

async function verify(): Promise<void> {
  const token = typeof route.query.token === 'string' ? route.query.token : ''
  if (!token) {
    error.value = 'De bevestigingslink bevat geen geldige token.'
    verifying.value = false
    return
  }

  try {
    const response = await apiRequest<{ user: User }>('/auth/email-verification/verify', {
      method: 'POST',
      body: JSON.stringify({ token }),
    })
    if (auth.user?.id === response.user.id) auth.updateUser(response.user)
    verified.value = true
  } catch (caught) {
    error.value = caught instanceof ApiError ? caught.message : 'Je e-mailadres kon niet worden bevestigd.'
  } finally {
    verifying.value = false
  }
}

onMounted(() => { void verify() })
</script>

<template>
  <main class="auth-page">
    <section class="panel" aria-labelledby="verify-email-heading">
      <p class="eyebrow">Accountbeveiliging</p>
      <h1 id="verify-email-heading">E-mailadres bevestigen</h1>
      <p v-if="verifying" role="status">Bevestigingslink controleren…</p>
      <template v-else-if="verified">
        <p class="success-message" role="status">Je e-mailadres is bevestigd.</p>
        <RouterLink class="button button--primary" :to="{ name: auth.isAuthenticated ? 'dashboard' : 'login' }">
          {{ auth.isAuthenticated ? 'Naar dashboard' : 'Inloggen' }}
        </RouterLink>
      </template>
      <template v-else>
        <ErrorMessage :message="error" />
        <RouterLink :to="{ name: auth.isAuthenticated ? 'dashboard' : 'login' }">
          {{ auth.isAuthenticated ? 'Terug naar dashboard' : 'Naar inloggen' }}
        </RouterLink>
      </template>
    </section>
  </main>
</template>
