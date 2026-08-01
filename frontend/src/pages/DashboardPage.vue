<script setup lang="ts">
import { ref } from 'vue'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const resending = ref(false)
const resendSuccess = ref('')
const resendError = ref('')

async function resendVerification(): Promise<void> {
  resending.value = true
  resendSuccess.value = ''
  resendError.value = ''
  try {
    await apiRequest<void>('/auth/email-verification/resend', { method: 'POST' })
    resendSuccess.value = 'Er is een nieuwe bevestigingslink aangevraagd.'
  } catch (caught) {
    resendError.value = caught instanceof ApiError ? caught.message : 'De bevestigingslink kon niet worden aangevraagd.'
  } finally {
    resending.value = false
  }
}
</script>

<template>
  <main class="page-container">
    <section v-if="auth.user?.emailVerifiedAt === null" class="verification-banner" aria-labelledby="verification-heading">
      <div>
        <h2 id="verification-heading">Bevestig je e-mailadres</h2>
        <p>Gebruik de link uit het verificatiebericht. De link blijft 24 uur geldig.</p>
        <p v-if="resendSuccess" class="success-message" role="status">{{ resendSuccess }}</p>
        <ErrorMessage v-if="resendError" :message="resendError" />
      </div>
      <BaseButton variant="secondary" :disabled="resending" @click="resendVerification">
        {{ resending ? 'Aanvragen…' : 'Nieuwe link aanvragen' }}
      </BaseButton>
    </section>
    <section class="panel panel--wide dashboard-hero">
      <p class="eyebrow">Dashboard</p>
      <h1>Je verzameling begint hier.</h1>
      <p>Registreer ieder fysiek exemplaar afzonderlijk en zie direct welke kaarten je dubbel hebt.</p>
      <RouterLink class="button button--primary inline-button" :to="{ name: 'collection' }">
        Open mijn collectie
      </RouterLink>
    </section>
  </main>
</template>
