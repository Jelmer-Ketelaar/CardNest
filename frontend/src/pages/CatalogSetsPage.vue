<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { PaginatedResponse } from '../types/api'
import type { CardSet } from '../types/catalog'

const languageOptions = [
  ['de', 'Duits'], ['en', 'Engels'], ['es', 'Spaans'], ['es-mx', 'Spaans (Mexico)'], ['fr', 'Frans'],
  ['id', 'Indonesisch'], ['it', 'Italiaans'], ['ja', 'Japans'], ['ko', 'Koreaans'], ['nl', 'Nederlands'],
  ['pl', 'Pools'], ['pt', 'Portugees'], ['pt-br', 'Portugees (Brazilië)'], ['pt-pt', 'Portugees (Portugal)'],
  ['ru', 'Russisch'], ['th', 'Thais'], ['zh-cn', 'Chinees (vereenvoudigd)'], ['zh-tw', 'Chinees (traditioneel)'],
] as const

interface SetFilters {
  name: string
  language: string
}

const route = useRoute()
const router = useRouter()
const sets = ref<CardSet[]>([])
const total = ref(0)
const totalPages = ref(0)
const loading = ref(false)
const error = ref('')
const filters = reactive<SetFilters>(filtersFromQuery(route.query))
let latestRequest = 0

const currentPage = computed(() => pageFromQuery(route.query.page))
const isUpcomingPage = computed(() => route.name === 'catalog-upcoming')

function queryValue(value: unknown): string {
  const candidate = Array.isArray(value) ? value[0] : value

  return typeof candidate === 'string' ? candidate : ''
}

function pageFromQuery(value: unknown): number {
  const page = Number(queryValue(value))

  return Number.isSafeInteger(page) && page >= 1 ? page : 1
}

function filtersFromQuery(query: Record<string, unknown>): SetFilters {
  return {
    name: queryValue(query.name),
    language: queryValue(query.language),
  }
}

function readableStatus(status: string): string {
  return {
    announced: 'Aangekondigd',
    active: 'Actief',
    out_of_print: 'Niet meer gedrukt',
    unknown: 'Onbekend',
  }[status] ?? status
}

function routeQuery(page: number): Record<string, string> {
  const query: Record<string, string> = {}
  for (const [key, value] of Object.entries(route.query)) {
    const resolved = queryValue(value)
    if (resolved && !['page', 'name', 'language'].includes(key)) query[key] = resolved
  }
  if (filters.name.trim()) query.name = filters.name.trim()
  if (filters.language) query.language = filters.language
  if (page > 1) query.page = String(page)

  return query
}

function caughtMessage(caught: unknown, fallback: string): string {
  return caught instanceof ApiError ? caught.message : fallback
}

async function loadSets(page: number): Promise<void> {
  const request = ++latestRequest
  loading.value = true
  error.value = ''

  try {
    const query = new URLSearchParams({ page: String(page), perPage: '20' })
    if (filters.name.trim()) query.set('name', filters.name.trim())
    if (filters.language) query.set('language', filters.language)
    if (isUpcomingPage.value) query.set('release', 'upcoming')
    const response = await apiRequest<PaginatedResponse<CardSet>>(`/card-sets?${query}`)
    if (request !== latestRequest) return

    sets.value = response.items
    total.value = response.total
    totalPages.value = response.totalPages
  } catch (caught) {
    if (request !== latestRequest) return

    sets.value = []
    total.value = 0
    totalPages.value = 0
    error.value = caughtMessage(caught, 'De kaartsets konden niet worden geladen.')
  } finally {
    if (request === latestRequest) loading.value = false
  }
}

async function applyFilters(): Promise<void> {
  await router.push({
    name: isUpcomingPage.value ? 'catalog-upcoming' : 'catalog-sets',
    query: routeQuery(1),
  })
}

async function goToPage(page: number): Promise<void> {
  if (page < 1 || page > totalPages.value || page === currentPage.value) return

  await router.push({
    name: isUpcomingPage.value ? 'catalog-upcoming' : 'catalog-sets',
    query: routeQuery(page),
  })
}

watch(
  () => route.fullPath,
  () => {
    Object.assign(filters, filtersFromQuery(route.query))
    void loadSets(currentPage.value)
  },
  { immediate: true },
)
</script>

<template>
  <main class="page-container catalog-sets-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">Officiële kaartgegevens</p>
        <h1>{{ isUpcomingPage ? 'Aankomende kaartsets' : 'Kaartsets' }}</h1>
        <p v-if="isUpcomingPage">Bekijk aangekondigde sets op volgorde van de verwachte releasedatum.</p>
        <p v-else>Verken alle beschikbare series en sets voordat je kaarten aan je collectie toevoegt.</p>
      </div>
      <RouterLink
        class="button button--secondary"
        :to="{ name: isUpcomingPage ? 'catalog-sets' : 'catalog-upcoming' }"
      >
        {{ isUpcomingPage ? 'Bekijk alle sets' : 'Bekijk aankomende sets' }}
      </RouterLink>
    </header>

    <form class="panel panel--wide catalog-set-filters" @submit.prevent="applyFilters">
      <BaseInput id="catalog-set-name" v-model="filters.name" label="Setnaam" type="search" />
      <label class="field" for="catalog-set-language">
        <span>Taal</span>
        <select id="catalog-set-language" v-model="filters.language">
          <option value="">Alle talen</option>
          <option v-for="option in languageOptions" :key="option[0]" :value="option[0]">{{ option[1] }}</option>
        </select>
      </label>
      <BaseButton type="submit" :disabled="loading">Filteren</BaseButton>
    </form>

    <ErrorMessage v-if="error" :message="error" />
    <p v-if="loading" class="empty-state" role="status">Kaartsets laden…</p>
    <p v-else-if="sets.length === 0" class="empty-state" role="status">
      {{ isUpcomingPage ? 'Er zijn op dit moment geen aangekondigde kaartsets gevonden.' : 'Er zijn geen kaartsets gevonden.' }}
    </p>

    <template v-else>
      <p class="results-summary">{{ total }} {{ total === 1 ? 'kaartset' : 'kaartsets' }}</p>
      <section class="catalog-set-grid" aria-label="Beschikbare kaartsets">
        <article v-for="set in sets" :key="set.id" class="catalog-set-card">
          <img v-if="set.logoUrl" :src="set.logoUrl" alt="" loading="lazy" decoding="async">
          <div class="catalog-set-card__body">
            <p class="eyebrow">{{ set.series.name }}</p>
            <h2>{{ set.name }}</h2>
            <p>{{ set.description ?? 'Geen aanvullende beschrijving beschikbaar.' }}</p>
            <dl>
              <div><dt>Code</dt><dd>{{ set.code }}</dd></div>
              <div><dt>Taal</dt><dd>{{ set.language.toUpperCase() }}</dd></div>
              <div><dt>Kaarten</dt><dd>{{ set.printedTotal }} regulier · {{ set.total }} totaal</dd></div>
              <div><dt>Status</dt><dd>{{ readableStatus(set.status) }}</dd></div>
              <div><dt>Releasedatum</dt><dd>{{ set.releaseDate ?? 'Onbekend' }}</dd></div>
            </dl>
            <RouterLink class="button button--secondary" :to="{ name: 'catalog-set', params: { id: set.id } }">
              Bekijk set
            </RouterLink>
          </div>
        </article>
      </section>
    </template>

    <nav v-if="totalPages > 1" class="pagination" aria-label="Paginering van kaartsets">
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
