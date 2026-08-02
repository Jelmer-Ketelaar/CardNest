<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { PaginatedResponse } from '../types/api'
import type { CatalogCard } from '../types/catalog'

const variantOptions = [
  ['normal', 'Normaal'], ['holo', 'Holo'], ['reverse_holo', 'Reverse holo'], ['cosmos_holo', 'Cosmos holo'],
  ['stamped', 'Stamped'], ['promo', 'Promo'], ['first_edition', 'First edition'], ['unlimited', 'Unlimited'],
  ['shadowless', 'Shadowless'], ['alternate_art', 'Alternate art'], ['illustration_rare', 'Illustration rare'],
  ['special_illustration_rare', 'Special illustration rare'], ['gold', 'Gold'], ['rainbow', 'Rainbow'],
  ['master_ball', 'Master Ball'], ['poke_ball', 'Poké Ball'], ['other', 'Overig'],
] as const

interface SearchFilters {
  setId: string
  seriesId: string
  name: string
  number: string
  rarity: string
  variant: string
  artist: string
}

const route = useRoute()
const router = useRouter()
const cards = ref<CatalogCard[]>([])
const total = ref(0)
const totalPages = ref(0)
const loading = ref(false)
const error = ref('')
const validationMessages = ref<string[]>([])
const filters = reactive<SearchFilters>(filtersFromQuery(route.query))
let latestRequest = 0

const currentPage = computed(() => pageFromQuery(route.query.page))

function queryValue(value: unknown): string {
  const candidate = Array.isArray(value) ? value[0] : value

  return typeof candidate === 'string' ? candidate : ''
}

function pageFromQuery(value: unknown): number {
  const page = Number(queryValue(value))

  return Number.isSafeInteger(page) && page >= 1 ? page : 1
}

function filtersFromQuery(query: Record<string, unknown>): SearchFilters {
  return {
    setId: queryValue(query.setId),
    seriesId: queryValue(query.seriesId),
    name: queryValue(query.name),
    number: queryValue(query.number),
    rarity: queryValue(query.rarity),
    variant: queryValue(query.variant),
    artist: queryValue(query.artist),
  }
}

function routeQuery(page: number): Record<string, string> {
  const query: Record<string, string> = {}
  const entries = Object.entries(filters) as Array<[keyof SearchFilters, string]>

  for (const [key, value] of entries) {
    if (value.trim()) query[key] = value.trim()
  }
  if (page > 1) query.page = String(page)

  return query
}

function readableCode(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function caughtMessage(caught: unknown, fallback: string): string {
  return caught instanceof ApiError ? caught.message : fallback
}

async function loadCards(page: number): Promise<void> {
  const request = ++latestRequest
  loading.value = true
  error.value = ''
  validationMessages.value = []

  try {
    const query = new URLSearchParams({ page: String(page), perPage: '24' })
    const entries = Object.entries(filters) as Array<[keyof SearchFilters, string]>
    for (const [key, value] of entries) {
      if (value.trim()) query.set(key, value.trim())
    }

    const response = await apiRequest<PaginatedResponse<CatalogCard>>(`/cards?${query}`)
    if (request !== latestRequest) return

    cards.value = response.items
    total.value = response.total
    totalPages.value = response.totalPages
  } catch (caught) {
    if (request !== latestRequest) return

    cards.value = []
    total.value = 0
    totalPages.value = 0
    error.value = caughtMessage(caught, 'De cataloguskaarten konden niet worden geladen.')
    validationMessages.value = caught instanceof ApiError ? Object.values(caught.details).flat() : []
  } finally {
    if (request === latestRequest) loading.value = false
  }
}

async function applyFilters(): Promise<void> {
  await router.push({ name: 'catalog-cards', query: routeQuery(1) })
}

async function goToPage(page: number): Promise<void> {
  if (page < 1 || page > totalPages.value || page === currentPage.value) return

  await router.push({ name: 'catalog-cards', query: routeQuery(page) })
}

watch(
  () => route.fullPath,
  () => {
    Object.assign(filters, filtersFromQuery(route.query))
    void loadCards(currentPage.value)
  },
  { immediate: true },
)
</script>

<template>
  <main class="page-container catalog-cards-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">Officiële kaartgegevens</p>
        <h1>Kaarten zoeken</h1>
        <p>Zoek op naam, nummer, set, serie, rarity, variant of artist.</p>
      </div>
      <RouterLink class="button button--secondary" :to="{ name: 'catalog-sets' }">Bekijk kaartsets</RouterLink>
    </header>

    <form class="panel panel--wide catalog-card-filters" @submit.prevent="applyFilters">
      <BaseInput id="catalog-card-name" v-model="filters.name" label="Kaartnaam" type="search" />
      <BaseInput id="catalog-card-number" v-model="filters.number" label="Kaartnummer" />
      <BaseInput id="catalog-card-rarity" v-model="filters.rarity" label="Rarity" />
      <BaseInput id="catalog-card-artist" v-model="filters.artist" label="Artist / illustrator" type="search" />
      <label class="field">
        <span>Variant</span>
        <select v-model="filters.variant">
          <option value="">Alle varianten</option>
          <option v-for="option in variantOptions" :key="option[0]" :value="option[0]">{{ option[1] }}</option>
        </select>
      </label>
      <BaseInput id="catalog-card-set-id" v-model="filters.setId" label="Set-ID" />
      <BaseInput id="catalog-card-series-id" v-model="filters.seriesId" label="Serie-ID" />
      <BaseButton type="submit" :disabled="loading">Zoeken</BaseButton>
    </form>

    <ErrorMessage v-if="error" :message="error" />
    <ul v-if="validationMessages.length > 0" class="validation-summary" aria-label="Validatiefouten">
      <li v-for="message in validationMessages" :key="message">{{ message }}</li>
    </ul>
    <p v-if="loading" class="empty-state" role="status">Kaarten laden…</p>
    <p v-else-if="cards.length === 0" class="empty-state" role="status">Geen kaarten gevonden binnen deze filters.</p>

    <template v-else>
      <p class="results-summary">{{ total }} {{ total === 1 ? 'kaart' : 'kaarten' }}</p>
      <section class="catalog-card-grid" aria-label="Zoekresultaten">
        <RouterLink
          v-for="card in cards"
          :key="card.id"
          class="catalog-card-result"
          :to="{ name: 'catalog-card', params: { id: card.id }, query: route.query }"
        >
          <img v-if="card.thumbnailUrl ?? card.imageUrl" :src="card.thumbnailUrl ?? card.imageUrl ?? ''" :alt="`${card.name} ${card.number}`">
          <div>
            <p class="eyebrow">{{ card.set.name }}</p>
            <h2>{{ card.name }} <small>{{ card.number }}</small></h2>
            <p>{{ card.rarity }} · {{ card.variants.map((variant) => readableCode(variant.code)).join(', ') }}</p>
            <p v-if="card.artists?.length">{{ card.artists.map((artist) => artist.name).join(', ') }}</p>
          </div>
        </RouterLink>
      </section>
    </template>

    <nav v-if="totalPages > 1" class="pagination" aria-label="Paginering van cataloguskaarten">
      <BaseButton variant="secondary" :disabled="currentPage <= 1 || loading" @click="goToPage(currentPage - 1)">
        Vorige
      </BaseButton>
      <span>Pagina {{ currentPage }} van {{ totalPages }}</span>
      <BaseButton variant="secondary" :disabled="currentPage >= totalPages || loading" @click="goToPage(currentPage + 1)">
        Volgende
      </BaseButton>
    </nav>
  </main>
</template>
