<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import { useAuthStore } from '../stores/auth'

const email = ref('')
const password = ref('')
const error = ref('')
const submitting = ref(false)
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const registrationCompleted = computed(() => route.query.registered === '1')

async function submit(): Promise<void> {
  error.value = ''
  submitting.value = true
  try {
    await auth.login(email.value, password.value)
    await router.push({ name: 'dashboard' })
  } catch (caught) {
    error.value = caught instanceof ApiError ? caught.message : 'Inloggen is mislukt.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="auth-page">
    <form class="panel" @submit.prevent="submit">
      <p class="eyebrow">Welkom terug</p>
      <h1>Inloggen</h1>
      <p v-if="registrationCompleted" class="success-message" role="status">
        Account aangemaakt. Log in en bevestig daarna je e-mailadres via de verzonden link.
      </p>
      <ErrorMessage v-if="error" :message="error" />
      <BaseInput id="email" v-model="email" label="E-mailadres" type="email" autocomplete="email" required />
      <BaseInput id="password" v-model="password" label="Wachtwoord" type="password" autocomplete="current-password" required />
      <BaseButton type="submit" :disabled="submitting">{{ submitting ? 'Bezig…' : 'Inloggen' }}</BaseButton>
      <RouterLink :to="{ name: 'register' }">Nog geen account? Registreer je</RouterLink>
    </form>
  </main>
</template>
