<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { PaginatedResponse } from '../types/api'
import type { ArtistDetail, CatalogCard } from '../types/catalog'

const route = useRoute()
const router = useRouter()
const artist = ref<ArtistDetail | null>(null)
const cards = ref<CatalogCard[]>([])
const loading = ref(false)
const notFound = ref(false)
const error = ref('')
const totalPages = ref(0)
const currentPage = computed(() => {
  const page = Number(typeof route.query.page === 'string' ? route.query.page : '1')
  return Number.isSafeInteger(page) && page > 0 ? page : 1
})

async function loadArtist(): Promise<void> {
  const slug = typeof route.params.slug === 'string' ? route.params.slug : ''
  loading.value = true
  notFound.value = false
  error.value = ''
  try {
    const detail = await apiRequest<ArtistDetail>(`/artists/slug/${encodeURIComponent(slug)}`)
    const response = await apiRequest<PaginatedResponse<CatalogCard>>(
      `/artists/${encodeURIComponent(detail.id)}/cards?page=${currentPage.value}&perPage=24`,
    )
    artist.value = detail
    cards.value = response.items
    totalPages.value = response.totalPages
  } catch (caught) {
    artist.value = null
    cards.value = []
    if (caught instanceof ApiError && caught.status === 404) notFound.value = true
    else error.value = caught instanceof ApiError ? caught.message : 'De artist kon niet worden geladen.'
  } finally {
    loading.value = false
  }
}

async function goToPage(page: number): Promise<void> {
  if (page < 1 || page > totalPages.value || page === currentPage.value) return
  await router.push({ name: 'artist-detail', params: route.params, query: page > 1 ? { page: String(page) } : {} })
}

watch(() => route.fullPath, () => { void loadArtist() }, { immediate: true })
</script>

<template>
  <main class="page-container artist-detail-page">
    <p v-if="loading" class="empty-state" role="status">Artist laden…</p>
    <section v-else-if="notFound" class="panel catalog-not-found">
      <h1>Artist niet gevonden</h1>
      <RouterLink class="button button--secondary" :to="{ name: 'artists' }">Terug naar artists</RouterLink>
    </section>
    <ErrorMessage v-else-if="error" :message="error" />

    <template v-else-if="artist">
      <header class="page-heading artist-detail-heading">
        <div>
          <p class="eyebrow">Artist / illustrator</p>
          <h1>{{ artist.name }}</h1>
          <p>{{ artist.biography ?? `${artist.cardCount} kaarten in de CardNest-catalogus.` }}</p>
          <a v-if="artist.websiteUrl" :href="artist.websiteUrl" target="_blank" rel="noopener noreferrer">Website van de artist</a>
        </div>
        <RouterLink class="button button--secondary" :to="{ name: 'artists' }">Alle artists</RouterLink>
      </header>

      <p v-if="cards.length === 0" class="empty-state">Voor deze artist zijn nog geen kaarten beschikbaar.</p>
      <section v-else class="catalog-card-grid" :aria-label="`Kaarten van ${artist.name}`">
        <RouterLink
          v-for="card in cards"
          :key="card.id"
          class="catalog-card-result"
          :to="{ name: 'catalog-card', params: { id: card.id } }"
        >
          <img
            v-if="card.thumbnailUrl ?? card.imageUrl"
            :src="card.thumbnailUrl ?? card.imageUrl ?? ''"
            :alt="`${card.name} ${card.number}, geïllustreerd door ${artist.name}`"
          >
          <div>
            <p class="eyebrow">{{ card.set.name }}</p>
            <h2>{{ card.name }} <small>{{ card.number }}</small></h2>
            <p>{{ card.rarity }}</p>
          </div>
        </RouterLink>
      </section>

      <nav v-if="totalPages > 1" class="pagination" aria-label="Paginering van artistkaarten">
        <BaseButton variant="secondary" :disabled="currentPage <= 1 || loading" @click="goToPage(currentPage - 1)">Vorige</BaseButton>
        <span>Pagina {{ currentPage }} van {{ totalPages }}</span>
        <BaseButton variant="secondary" :disabled="currentPage >= totalPages || loading" @click="goToPage(currentPage + 1)">Volgende</BaseButton>
      </nav>
    </template>
  </main>
</template>
