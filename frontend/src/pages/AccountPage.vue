<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import { useAuthStore } from '../stores/auth'
import type { User } from '../types/auth'

const auth = useAuthStore()
const router = useRouter()
const loading = ref(false)
const loggingOut = ref(false)
const error = ref('')

function formattedDate(value: string): string {
  return new Intl.DateTimeFormat('nl-NL', { dateStyle: 'medium' }).format(new Date(value))
}

async function loadAccount(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const response = await apiRequest<{ user: User }>('/me')
    auth.updateUser(response.user)
  } catch (caught) {
    error.value = caught instanceof ApiError ? caught.message : 'Je accountgegevens konden niet worden geladen.'
  } finally {
    loading.value = false
  }
}

async function logout(): Promise<void> {
  loggingOut.value = true
  try {
    await auth.logout()
  } catch {
    // The auth store clears local state even when revoking the remote token fails.
  } finally {
    loggingOut.value = false
    await router.push({ name: 'login' })
  }
}

onMounted(() => { void loadAccount() })
</script>

<template>
  <main class="page-container account-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">Account</p>
        <h1>Mijn account</h1>
      </div>
    </header>

    <ErrorMessage v-if="error" :message="error" />
    <p v-if="loading" class="empty-state" role="status">Accountgegevens laden…</p>

    <section v-else-if="auth.user" class="panel account-details" aria-label="Accountgegevens">
      <dl>
        <div><dt>E-mailadres</dt><dd>{{ auth.user.email }}</dd></div>
        <div>
          <dt>E-mailstatus</dt>
          <dd>{{ auth.user.emailVerifiedAt ? 'Bevestigd' : 'Nog niet bevestigd' }}</dd>
        </div>
        <div>
          <dt>Lid sinds</dt>
          <dd><time :datetime="auth.user.createdAt">{{ formattedDate(auth.user.createdAt) }}</time></dd>
        </div>
      </dl>
      <BaseButton variant="secondary" :disabled="loggingOut" @click="logout">
        {{ loggingOut ? 'Uitloggen…' : 'Uitloggen' }}
      </BaseButton>
    </section>
  </main>
</template>
