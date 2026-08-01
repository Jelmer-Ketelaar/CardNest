<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { CatalogCardDetail } from '../types/catalog'

const route = useRoute()
const card = ref<CatalogCardDetail | null>(null)
const loading = ref(false)
const notFound = ref(false)
const error = ref('')
let latestRequest = 0

const cardId = computed(() => routeId(route.params.id))

function routeId(value: unknown): string | null {
  return typeof value === 'string' && value !== '' ? value : null
}

function readableCode(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

async function loadCard(id: string | null): Promise<void> {
  const request = ++latestRequest
  card.value = null
  loading.value = true
  notFound.value = false
  error.value = ''

  if (id === null) {
    loading.value = false
    notFound.value = true

    return
  }

  try {
    const response = await apiRequest<CatalogCardDetail>(`/cards/${encodeURIComponent(id)}`)
    if (request !== latestRequest) return

    card.value = response
  } catch (caught) {
    if (request !== latestRequest) return

    if (caught instanceof ApiError && caught.status === 404) {
      notFound.value = true
    } else {
      error.value = caught instanceof ApiError ? caught.message : 'De kaart kon niet worden geladen.'
    }
  } finally {
    if (request === latestRequest) loading.value = false
  }
}

watch(cardId, (id) => { void loadCard(id) }, { immediate: true })
</script>

<template>
  <main class="page-container catalog-card-detail-page">
    <p v-if="loading" class="empty-state" role="status">Kaart laden…</p>

    <section v-else-if="notFound" class="panel catalog-not-found" aria-labelledby="card-not-found-heading">
      <p class="eyebrow">Catalogus</p>
      <h1 id="card-not-found-heading">Kaart niet gevonden</h1>
      <p>Deze kaart bestaat niet of is niet langer beschikbaar in de catalogus.</p>
      <RouterLink class="button button--secondary" :to="{ name: 'catalog-cards', query: route.query }">Terug naar kaartzoeken</RouterLink>
    </section>

    <template v-else-if="card">
      <header class="page-heading">
        <div>
          <p class="eyebrow">{{ card.set.name }}</p>
          <h1>{{ card.name }} <small>{{ card.number }}</small></h1>
          <p>{{ card.rarity }} · {{ card.cardType }}</p>
        </div>
        <div class="page-heading__actions">
          <RouterLink class="button button--secondary" :to="{ name: 'catalog-cards', query: route.query }">Terug naar kaartzoeken</RouterLink>
          <RouterLink class="button button--primary" :to="{ name: 'catalog-set', params: { id: card.set.id } }">Bekijk set</RouterLink>
        </div>
      </header>

      <section class="catalog-card-detail panel panel--wide" aria-label="Kaartgegevens">
        <img v-if="card.imageUrl ?? card.thumbnailUrl" :src="card.imageUrl ?? card.thumbnailUrl ?? ''" :alt="`${card.name} ${card.number}`">
        <div>
          <h2>Officiële kaartgegevens</h2>
          <dl class="card-facts">
            <div><dt>Kaartnummer</dt><dd>{{ card.number }}</dd></div>
            <div><dt>Setnummer</dt><dd>{{ card.totalNumberInSet ?? 'Onbekend' }}</dd></div>
            <div><dt>Rarity</dt><dd>{{ card.rarity }}</dd></div>
            <div><dt>Type</dt><dd>{{ card.cardType }}</dd></div>
            <div><dt>Pokémon</dt><dd>{{ card.primaryCollectible ?? 'Onbekend' }}</dd></div>
            <div><dt>Illustrator</dt><dd>{{ card.illustrator ?? 'Onbekend' }}</dd></div>
            <div><dt>Releasedatum</dt><dd>{{ card.releaseDate ?? 'Onbekend' }}</dd></div>
          </dl>

          <h2>Varianten</h2>
          <ul class="variant-list" aria-label="Beschikbare kaartvarianten">
            <li v-for="variant in card.variants" :key="variant.id">{{ readableCode(variant.code) }}</li>
          </ul>
        </div>
      </section>
    </template>

    <ErrorMessage v-else-if="error" :message="error" />
  </main>
</template>
