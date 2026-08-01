<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { User } from '../types/auth'

const form = reactive({ email: '', password: '', passwordConfirmation: '' })
const fieldErrors = ref<Record<string, string[]>>({})
const error = ref('')
const submitting = ref(false)
const router = useRouter()

async function submit(): Promise<void> {
  error.value = ''
  fieldErrors.value = {}
  submitting.value = true
  try {
    await apiRequest<{ user: User }>('/auth/register', { method: 'POST', body: JSON.stringify(form) })
    await router.push({ name: 'login', query: { registered: '1' } })
  } catch (caught) {
    if (caught instanceof ApiError) {
      error.value = caught.message
      fieldErrors.value = caught.details
    } else {
      error.value = 'Registreren is mislukt.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="auth-page">
    <form class="panel" @submit.prevent="submit">
      <p class="eyebrow">Start je digitale binder</p>
      <h1>Account maken</h1>
      <ErrorMessage v-if="error" :message="error" />
      <BaseInput id="email" v-model="form.email" label="E-mailadres" type="email" autocomplete="email" required :error="fieldErrors.email?.[0]" />
      <BaseInput id="password" v-model="form.password" label="Wachtwoord" type="password" autocomplete="new-password" required :error="fieldErrors.password?.[0]" />
      <BaseInput id="password-confirmation" v-model="form.passwordConfirmation" label="Herhaal wachtwoord" type="password" autocomplete="new-password" required :error="fieldErrors.passwordConfirmation?.[0]" />
      <BaseButton type="submit" :disabled="submitting">{{ submitting ? 'Bezig…' : 'Registreren' }}</BaseButton>
      <RouterLink :to="{ name: 'login' }">Al een account? Log in</RouterLink>
    </form>
  </main>
</template>
