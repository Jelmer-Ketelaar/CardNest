<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { PaginatedResponse } from '../types/api'
import type { CardSet } from '../types/catalog'

const route = useRoute()
const router = useRouter()
const sets = ref<CardSet[]>([])
const total = ref(0)
const totalPages = ref(0)
const loading = ref(false)
const error = ref('')
let latestRequest = 0

const currentPage = computed(() => pageFromQuery(route.query.page))

function pageFromQuery(value: unknown): number {
  const candidate = Array.isArray(value) ? value[0] : value
  if (typeof candidate !== 'string') return 1

  const page = Number(candidate)

  return Number.isSafeInteger(page) && page >= 1 ? page : 1
}

function readableStatus(status: string): string {
  return status.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
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

async function goToPage(page: number): Promise<void> {
  if (page < 1 || page > totalPages.value || page === currentPage.value) return

  const query = { ...route.query }
  if (page === 1) {
    delete query.page
  } else {
    query.page = String(page)
  }

  await router.push({ name: 'catalog-sets', query })
}

watch(currentPage, (page) => { void loadSets(page) }, { immediate: true })
</script>

<template>
  <main class="page-container catalog-sets-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">Officiële kaartgegevens</p>
        <h1>Kaartsets</h1>
        <p>Verken de beschikbare series en sets voordat je kaarten aan je collectie toevoegt.</p>
      </div>
    </header>

    <ErrorMessage v-if="error" :message="error" />
    <p v-if="loading" class="empty-state" role="status">Kaartsets laden…</p>
    <p v-else-if="sets.length === 0" class="empty-state" role="status">Er zijn geen kaartsets gevonden.</p>

    <template v-else>
      <p class="results-summary">{{ total }} {{ total === 1 ? 'kaartset' : 'kaartsets' }}</p>
      <section class="catalog-set-grid" aria-label="Beschikbare kaartsets">
        <article v-for="set in sets" :key="set.id" class="catalog-set-card">
          <img v-if="set.logoUrl" :src="set.logoUrl" alt="">
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
