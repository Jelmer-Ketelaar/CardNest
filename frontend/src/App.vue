<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { type RouteLocationRaw, useRoute, useRouter } from 'vue-router'
import BaseButton from './components/BaseButton.vue'
import { useAuthStore } from './stores/auth'

interface NavigationItem {
  label: string
  to: RouteLocationRaw
}

interface NavigationGroup {
  label: string
  items: readonly NavigationItem[]
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const isNavigationOpen = ref(false)
const navigationToggle = ref<HTMLButtonElement | null>(null)
const showShell = computed(() => !['login', 'register', 'verify-email'].includes(String(route.name)))
const publicNavigation: readonly NavigationItem[] = [
  { label: 'Catalogus', to: { name: 'catalog-sets' } },
  { label: 'Marktplaats', to: { name: 'marketplace' } },
]
const collectionNavigation: readonly NavigationItem[] = [
  { label: 'Dashboard', to: { name: 'dashboard' } },
  { label: 'Collectie', to: { name: 'collection' } },
  { label: 'Opslag', to: { name: 'storage' } },
  { label: 'Binders', to: { name: 'binders' } },
  { label: 'Wishlists', to: { name: 'wishlists' } },
]
const marketplaceNavigation: readonly NavigationItem[] = [
  { label: 'Mijn verkoop', to: { name: 'my-listings' } },
  { label: 'Winkelmand', to: { name: 'cart' } },
  { label: 'Mijn aankopen', to: { name: 'orders' } },
  { label: 'Mijn verkopen', to: { name: 'seller-orders' } },
]
const accountNavigation: readonly NavigationItem[] = [
  { label: 'Account', to: { name: 'account' } },
]
const navigationGroups = computed<readonly NavigationGroup[]>(() => {
  const groups: NavigationGroup[] = [{ label: 'Ontdekken', items: publicNavigation }]
  if (auth.isAuthenticated) {
    groups.push(
      { label: 'Mijn verzameling', items: collectionNavigation },
      { label: 'Handel', items: marketplaceNavigation },
      { label: 'Account', items: accountNavigation },
    )
  }

  return groups
})

function toggleNavigation(): void {
  isNavigationOpen.value = !isNavigationOpen.value
}

function closeNavigation(restoreFocus = false): void {
  if (!isNavigationOpen.value) return

  isNavigationOpen.value = false
  if (restoreFocus) {
    void nextTick(() => navigationToggle.value?.focus())
  }
}

watch(() => route.fullPath, () => closeNavigation())

async function logout(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div v-if="showShell" class="app-shell">
    <header class="site-header">
      <div class="site-header__bar">
        <RouterLink
          class="brand"
          :to="{ name: auth.isAuthenticated ? 'dashboard' : 'marketplace' }"
          @click="closeNavigation"
        >
          CardNest
        </RouterLink>
        <div class="site-header__actions">
          <button
            ref="navigationToggle"
            class="navigation-toggle"
            type="button"
            aria-controls="main-navigation"
            :aria-expanded="isNavigationOpen"
            @click="toggleNavigation"
            @keydown.esc.prevent="closeNavigation(true)"
          >
            <span class="navigation-toggle__icon" aria-hidden="true">☰</span>
            <span>{{ isNavigationOpen ? 'Sluit menu' : 'Menu' }}</span>
          </button>
          <BaseButton v-if="auth.isAuthenticated" variant="secondary" @click="logout">Uitloggen</BaseButton>
          <RouterLink v-else class="button button--secondary" :to="{ name: 'login' }" @click="closeNavigation">Inloggen</RouterLink>
        </div>
      </div>
      <nav
        id="main-navigation"
        class="site-navigation"
        :class="{ 'site-navigation--open': isNavigationOpen }"
        aria-label="Hoofdnavigatie"
        @keydown.esc.prevent="closeNavigation(true)"
      >
        <div v-for="group in navigationGroups" :key="group.label" class="site-navigation__group">
          <p class="site-navigation__label">{{ group.label }}</p>
          <RouterLink v-for="item in group.items" :key="item.label" :to="item.to" @click="closeNavigation">
            {{ item.label }}
          </RouterLink>
        </div>
      </nav>
    </header>
    <RouterView />
  </div>
  <RouterView v-else />
</template>
