<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from './components/BaseButton.vue'
import { useAuthStore } from './stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const showShell = computed(() => !['login', 'register'].includes(String(route.name)))

async function logout(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div v-if="showShell" class="app-shell">
    <header class="site-header">
      <RouterLink class="brand" :to="{ name: auth.isAuthenticated ? 'dashboard' : 'marketplace' }">CardNest</RouterLink>
      <nav aria-label="Hoofdnavigatie">
        <RouterLink :to="{ name: 'marketplace' }">Marktplaats</RouterLink>
        <template v-if="auth.isAuthenticated">
          <RouterLink :to="{ name: 'dashboard' }">Dashboard</RouterLink>
          <RouterLink :to="{ name: 'collection' }">Collectie</RouterLink>
          <RouterLink :to="{ name: 'storage' }">Opslag</RouterLink>
          <RouterLink :to="{ name: 'binders' }">Binders</RouterLink>
          <RouterLink :to="{ name: 'wishlists' }">Wishlists</RouterLink>
          <RouterLink :to="{ name: 'my-listings' }">Mijn verkoop</RouterLink>
          <RouterLink :to="{ name: 'cart' }">Winkelmand</RouterLink>
          <RouterLink :to="{ name: 'orders' }">Orders</RouterLink>
        </template>
      </nav>
      <BaseButton v-if="auth.isAuthenticated" variant="secondary" @click="logout">Uitloggen</BaseButton>
      <RouterLink v-else class="button button--secondary" :to="{ name: 'login' }">Inloggen</RouterLink>
    </header>
    <RouterView />
  </div>
  <RouterView v-else />
</template>
