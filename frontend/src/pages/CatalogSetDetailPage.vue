<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { CardSet } from '../types/catalog'

const route = useRoute()
const set = ref<CardSet | null>(null)
const loading = ref(false)
const notFound = ref(false)
const error = ref('')
let latestRequest = 0

const setId = computed(() => routeId(route.params.id))

function routeId(value: unknown): string | null {
  return typeof value === 'string' && value !== '' ? value : null
}

function readableStatus(status: string): string {
  return status.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

async function loadSet(id: string | null): Promise<void> {
  const request = ++latestRequest
  set.value = null
  loading.value = true
  notFound.value = false
  error.value = ''

  if (id === null) {
    loading.value = false
    notFound.value = true

    return
  }

  try {
    const response = await apiRequest<CardSet>(`/card-sets/${encodeURIComponent(id)}`)
    if (request !== latestRequest) return

    set.value = response
  } catch (caught) {
    if (request !== latestRequest) return

    if (caught instanceof ApiError && caught.status === 404) {
      notFound.value = true
    } else {
      error.value = caught instanceof ApiError ? caught.message : 'De kaartset kon niet worden geladen.'
    }
  } finally {
    if (request === latestRequest) loading.value = false
  }
}

watch(setId, (id) => { void loadSet(id) }, { immediate: true })
</script>

<template>
  <main class="page-container catalog-set-detail-page">
    <p v-if="loading" class="empty-state" role="status">Kaartset laden…</p>

    <section v-else-if="notFound" class="panel catalog-not-found" aria-labelledby="set-not-found-heading">
      <p class="eyebrow">Catalogus</p>
      <h1 id="set-not-found-heading">Kaartset niet gevonden</h1>
      <p>Deze set bestaat niet of is niet langer beschikbaar in de catalogus.</p>
      <RouterLink class="button button--secondary" :to="{ name: 'catalog-sets' }">Terug naar kaartsets</RouterLink>
    </section>

    <template v-else-if="set">
      <header class="page-heading catalog-set-detail-heading">
        <div>
          <p class="eyebrow">{{ set.series.name }}</p>
          <h1>{{ set.name }}</h1>
          <p>{{ set.description ?? 'Geen aanvullende beschrijving beschikbaar.' }}</p>
        </div>
        <div class="page-heading__actions">
          <RouterLink class="button button--secondary" :to="{ name: 'catalog-sets' }">Alle kaartsets</RouterLink>
          <RouterLink class="button button--primary" :to="{ name: 'catalog-cards', query: { setId: set.id } }">
            Bekijk kaarten in deze set
          </RouterLink>
        </div>
      </header>

      <section class="catalog-set-overview panel panel--wide" aria-label="Setgegevens">
        <img v-if="set.logoUrl" :src="set.logoUrl" :alt="`${set.name} logo`">
        <dl class="card-facts">
          <div><dt>Setcode</dt><dd>{{ set.code }}</dd></div>
          <div><dt>Taal</dt><dd>{{ set.language.toUpperCase() }}</dd></div>
          <div><dt>Reguliere kaarten</dt><dd>{{ set.printedTotal }}</dd></div>
          <div><dt>Totaal aantal kaarten</dt><dd>{{ set.total }}</dd></div>
          <div><dt>Status</dt><dd>{{ readableStatus(set.status) }}</dd></div>
          <div><dt>Releasedatum</dt><dd>{{ set.releaseDate ?? 'Onbekend' }}</dd></div>
        </dl>
      </section>
    </template>

    <ErrorMessage v-else-if="error" :message="error" />
  </main>
</template>
