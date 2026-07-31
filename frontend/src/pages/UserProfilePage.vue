<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { Reputation } from '../types/review'

const route = useRoute()
const reputation = ref<Reputation | null>(null)
const loading = ref(false)
const error = ref('')

function caughtMessage(caught: unknown): string {
  return caught instanceof ApiError ? caught.message : 'Het openbare profiel kon niet worden geladen.'
}

function formatScore(score: number | null): string {
  return score === null ? 'Nog niet beoordeeld' : `${score.toFixed(2)} / 5`
}

function formattedDate(value: string): string {
  return new Intl.DateTimeFormat('nl-NL', { dateStyle: 'medium' }).format(new Date(value))
}

async function loadReputation(page = 1): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const userId = String(route.params.id)
    reputation.value = await apiRequest<Reputation>(`/users/${userId}/reputation?page=${page}&perPage=20`)
  } catch (caught) {
    error.value = caughtMessage(caught)
  } finally {
    loading.value = false
  }
}

onMounted(() => { void loadReputation() })
</script>

<template>
  <main class="page-container reputation-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">Openbaar verzamelaarsprofiel</p>
        <h1>Reputatie</h1>
        <p v-if="reputation">Lid sinds {{ formattedDate(reputation.user.memberSince) }}</p>
      </div>
      <RouterLink class="button button--secondary" :to="{ name: 'marketplace' }">Terug naar marktplaats</RouterLink>
    </header>

    <ErrorMessage v-if="error" :message="error" />
    <p v-if="loading" class="empty-state" role="status">Reputatie laden…</p>

    <template v-else-if="reputation">
      <section class="panel panel--wide reputation-summary" aria-labelledby="reputation-summary-heading">
        <div>
          <p class="eyebrow">
            {{ reputation.summary.reviewCount }} {{ reputation.summary.reviewCount === 1 ? 'review' : 'reviews' }}
          </p>
          <h2 id="reputation-summary-heading">{{ formatScore(reputation.summary.averageOverall) }}</h2>
        </div>
        <dl>
          <div><dt>Beschrijving</dt><dd>{{ formatScore(reputation.summary.averages.descriptionAccuracy) }}</dd></div>
          <div><dt>Verpakking</dt><dd>{{ formatScore(reputation.summary.averages.packaging) }}</dd></div>
          <div><dt>Communicatie</dt><dd>{{ formatScore(reputation.summary.averages.communication) }}</dd></div>
          <div><dt>Verzendsnelheid</dt><dd>{{ formatScore(reputation.summary.averages.shippingSpeed) }}</dd></div>
        </dl>
      </section>

      <section class="review-list" aria-label="Reviews">
        <p v-if="reputation.reviews.items.length === 0" class="empty-state">Nog geen reviews.</p>
        <article v-for="review in reputation.reviews.items" :key="review.id" class="panel review-card">
          <header>
            <strong>{{ review.scores.overall }} / 5</strong>
            <time :datetime="review.createdAt">{{ formattedDate(review.createdAt) }}</time>
          </header>
          <p v-if="review.comment">{{ review.comment }}</p>
          <dl>
            <div><dt>Beschrijving</dt><dd>{{ review.scores.descriptionAccuracy }}/5</dd></div>
            <div><dt>Verpakking</dt><dd>{{ review.scores.packaging }}/5</dd></div>
            <div><dt>Communicatie</dt><dd>{{ review.scores.communication }}/5</dd></div>
            <div><dt>Snelheid</dt><dd>{{ review.scores.shippingSpeed }}/5</dd></div>
          </dl>
        </article>
      </section>

      <nav v-if="reputation.reviews.totalPages > 1" class="pagination" aria-label="Reviewpaginering">
        <BaseButton
          variant="secondary"
          :disabled="reputation.reviews.page <= 1 || loading"
          @click="loadReputation(reputation.reviews.page - 1)"
        >
          Vorige
        </BaseButton>
        <span>Pagina {{ reputation.reviews.page }} van {{ reputation.reviews.totalPages }}</span>
        <BaseButton
          variant="secondary"
          :disabled="reputation.reviews.page >= reputation.reviews.totalPages || loading"
          @click="loadReputation(reputation.reviews.page + 1)"
        >
          Volgende
        </BaseButton>
      </nav>
    </template>
  </main>
</template>
