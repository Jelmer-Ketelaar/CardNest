<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from './components/BaseButton.vue'
import { useAuthStore } from './stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const showNavigation = computed(() => Boolean(route.meta.requiresAuth))

async function logout(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div v-if="showNavigation" class="app-shell">
    <header class="site-header">
      <RouterLink class="brand" :to="{ name: 'dashboard' }">CardNest</RouterLink>
      <nav aria-label="Hoofdnavigatie">
        <RouterLink :to="{ name: 'dashboard' }">Dashboard</RouterLink>
        <RouterLink :to="{ name: 'collection' }">Collectie</RouterLink>
        <RouterLink :to="{ name: 'storage' }">Opslag</RouterLink>
        <RouterLink :to="{ name: 'binders' }">Binders</RouterLink>
        <RouterLink :to="{ name: 'wishlists' }">Wishlists</RouterLink>
      </nav>
      <BaseButton variant="secondary" @click="logout">Uitloggen</BaseButton>
    </header>
    <RouterView />
  </div>
  <RouterView v-else />
</template>
