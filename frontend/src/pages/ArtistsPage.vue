<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { PaginatedResponse } from '../types/api'
import type { ArtistSummary } from '../types/catalog'

const route = useRoute()
const router = useRouter()
const artists = ref<ArtistSummary[]>([])
const total = ref(0)
const totalPages = ref(0)
const loading = ref(false)
const error = ref('')
const filters = reactive({ q: queryValue(route.query.q), sort: queryValue(route.query.sort) || 'name' })
const currentPage = computed(() => pageValue(route.query.page))

function queryValue(value: unknown): string {
  const candidate = Array.isArray(value) ? value[0] : value
  return typeof candidate === 'string' ? candidate : ''
}

function pageValue(value: unknown): number {
  const page = Number(queryValue(value))
  return Number.isSafeInteger(page) && page > 0 ? page : 1
}

function routeQuery(page: number): Record<string, string> {
  const query: Record<string, string> = {}
  if (filters.q.trim()) query.q = filters.q.trim()
  if (filters.sort !== 'name') query.sort = filters.sort
  if (page > 1) query.page = String(page)
  return query
}

async function loadArtists(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const query = new URLSearchParams({
      q: queryValue(route.query.q),
      sort: queryValue(route.query.sort) || 'name',
      page: String(currentPage.value),
      perPage: '24',
    })
    const response = await apiRequest<PaginatedResponse<ArtistSummary>>(`/artists?${query}`)
    artists.value = response.items
    total.value = response.total
    totalPages.value = response.totalPages
  } catch (caught) {
    artists.value = []
    error.value = caught instanceof ApiError ? caught.message : 'De artists konden niet worden geladen.'
  } finally {
    loading.value = false
  }
}

async function applyFilters(): Promise<void> {
  await router.push({ name: 'artists', query: routeQuery(1) })
}

async function goToPage(page: number): Promise<void> {
  if (page < 1 || page > totalPages.value || page === currentPage.value) return
  await router.push({ name: 'artists', query: routeQuery(page) })
}

watch(
  () => route.fullPath,
  () => {
    filters.q = queryValue(route.query.q)
    filters.sort = queryValue(route.query.sort) || 'name'
    void loadArtists()
  },
  { immediate: true },
)
</script>

<template>
  <main class="page-container artists-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">Illustrators</p>
        <h1>Artists ontdekken</h1>
        <p>Bekijk alle illustrators en hun kaarten in de CardNest-catalogus.</p>
      </div>
      <RouterLink class="button button--secondary" :to="{ name: 'catalog-cards' }">Alle kaarten</RouterLink>
    </header>

    <form class="panel artist-filters" @submit.prevent="applyFilters">
      <BaseInput id="artist-search" v-model="filters.q" label="Naam" type="search" />
      <label class="field">
        <span>Sorteren</span>
        <select v-model="filters.sort">
          <option value="name">Naam A–Z</option>
          <option value="card_count">Aantal kaarten</option>
        </select>
      </label>
      <BaseButton type="submit" :disabled="loading">Toepassen</BaseButton>
    </form>

    <ErrorMessage v-if="error" :message="error" />
    <p v-if="loading" class="empty-state" role="status">Artists laden…</p>
    <p v-else-if="artists.length === 0" class="empty-state">Geen artists gevonden.</p>
    <template v-else>
      <p class="results-summary">{{ total }} {{ total === 1 ? 'artist' : 'artists' }}</p>
      <section class="artist-grid" aria-label="Artists">
        <RouterLink
          v-for="artist in artists"
          :key="artist.id"
          class="artist-card"
          :to="{ name: 'artist-detail', params: { slug: artist.slug } }"
        >
          <span class="artist-card__monogram" aria-hidden="true">{{ artist.name.slice(0, 1) }}</span>
          <div>
            <h2>{{ artist.name }}</h2>
            <p>{{ artist.cardCount }} {{ artist.cardCount === 1 ? 'kaart' : 'kaarten' }}</p>
          </div>
        </RouterLink>
      </section>
    </template>

    <nav v-if="totalPages > 1" class="pagination" aria-label="Paginering van artists">
      <BaseButton variant="secondary" :disabled="currentPage <= 1 || loading" @click="goToPage(currentPage - 1)">Vorige</BaseButton>
      <span>Pagina {{ currentPage }} van {{ totalPages }}</span>
      <BaseButton variant="secondary" :disabled="currentPage >= totalPages || loading" @click="goToPage(currentPage + 1)">Volgende</BaseButton>
    </nav>
  </main>
</template>
