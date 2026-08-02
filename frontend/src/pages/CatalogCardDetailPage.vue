<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import ErrorMessage from '../components/ErrorMessage.vue'
import { cardLanguages } from '../constants/cardLanguages'
import type { CatalogCardDetail } from '../types/catalog'
import type { CardPriceChange, CardPriceHistoryResponse, CardPriceSnapshot, PriceVariant } from '../types/pricing'
import { formatMoney } from '../utils/money'

const route = useRoute()
const card = ref<CatalogCardDetail | null>(null)
const loading = ref(false)
const notFound = ref(false)
const error = ref('')
const pricing = ref<CardPriceHistoryResponse | null>(null)
const priceLoading = ref(false)
const priceError = ref('')
let latestRequest = 0

const cardId = computed(() => routeId(route.params.id))
const recentPriceHistory = computed<CardPriceSnapshot[]>(() => (
  pricing.value?.history
    .filter((snapshot) => snapshot.lowest !== null || snapshot.average !== null || snapshot.trend !== null)
    .slice(0, 24) ?? []
))

function routeId(value: unknown): string | null {
  return typeof value === 'string' && value !== '' ? value : null
}

function readableCode(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function variantLabel(variant: PriceVariant): string {
  return variant === 'foil' ? 'Holo / foil' : 'Standard / non-foil'
}

function languageLabel(code: string): string {
  return cardLanguages.find(([languageCode]) => languageCode === code)?.[1] ?? code.toUpperCase()
}

function formatTimestamp(value: string | null): string {
  if (value === null) return 'Onbekend'

  return new Intl.DateTimeFormat('nl-NL', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function formatDate(value: string): string {
  return new Intl.DateTimeFormat('nl-NL', {
    dateStyle: 'medium',
    timeZone: 'UTC',
  }).format(new Date(`${value}T00:00:00Z`))
}

function changeDirection(change: CardPriceChange | null): 'down' | 'neutral' | 'up' {
  if (!change || change.amount === 0) return 'neutral'
  return change.amount > 0 ? 'up' : 'down'
}

function changeLabel(change: CardPriceChange | null): string {
  if (!change) return 'Nog geen eerdere dagmeting'

  const sign = change.amount > 0 ? '+' : change.amount < 0 ? '−' : ''
  const amount = `${sign}${formatMoney(Math.abs(change.amount), change.currency)}`
  if (change.percentage === null) return `${amount} sinds vorige meting`

  const percentageSign = change.percentage > 0 ? '+' : ''
  return `${amount} (${percentageSign}${change.percentage.toLocaleString('nl-NL')}%) sinds vorige meting`
}

async function loadPrices(id: string, request: number): Promise<void> {
  pricing.value = null
  priceLoading.value = true
  priceError.value = ''

  try {
    const response = await apiRequest<CardPriceHistoryResponse>(`/cards/${encodeURIComponent(id)}/prices?days=30`)
    if (request !== latestRequest) return
    pricing.value = response
  } catch (caught) {
    if (request !== latestRequest) return
    priceError.value = caught instanceof ApiError
      ? caught.message
      : 'De actuele prijsindicatie kon niet worden geladen.'
  } finally {
    if (request === latestRequest) priceLoading.value = false
  }
}

async function loadCard(id: string | null): Promise<void> {
  const request = ++latestRequest
  card.value = null
  loading.value = true
  notFound.value = false
  error.value = ''
  pricing.value = null
  priceError.value = ''

  if (id === null) {
    loading.value = false
    notFound.value = true

    return
  }

  try {
    const response = await apiRequest<CatalogCardDetail>(`/cards/${encodeURIComponent(id)}`)
    if (request !== latestRequest) return

    card.value = response
    void loadPrices(id, request)
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

      <section class="panel panel--wide card-price-panel" aria-labelledby="card-price-heading">
        <header class="card-price-panel__heading">
          <div>
            <p class="eyebrow">Dagelijks ververst</p>
            <h2 id="card-price-heading">Cardmarket-prijsindicatie</h2>
          </div>
          <span v-if="pricing?.checkedAt">
            Laatste meting <time :datetime="pricing.checkedAt">{{ formatTimestamp(pricing.checkedAt) }}</time>
          </span>
        </header>

        <p class="price-context">
          Catalogustaal: {{ languageLabel(card.set.language) }} · Niet conditie-specifiek
        </p>

        <p v-if="priceLoading" class="empty-state" role="status">Actuele prijzen ophalen…</p>
        <p v-else-if="priceError" class="price-inline-error" role="alert">{{ priceError }}</p>
        <template v-else-if="pricing">
          <p
            v-if="pricing.refreshStatus === 'refresh_failed' && pricing.prices.length > 0"
            class="price-inline-error"
            role="status"
          >
            De prijsbron is tijdelijk niet bereikbaar; de laatst opgeslagen meting wordt getoond.
          </p>
          <p
            v-if="pricing.refreshStatus === 'refresh_failed' && pricing.prices.length === 0"
            class="price-inline-error"
            role="status"
          >
            De prijsbron is tijdelijk niet bereikbaar en er is nog geen opgeslagen meting.
          </p>
          <p v-else-if="pricing.prices.length === 0" class="empty-state" role="status">
            Voor deze kaart is nog geen Cardmarket-prijs beschikbaar.
          </p>
          <div v-else class="card-price-variants">
            <article v-for="price in pricing.prices" :key="price.variant" class="card-price-variant">
              <div class="card-price-variant__heading">
                <h3>{{ variantLabel(price.variant) }}</h3>
                <span
                  class="price-change"
                  :data-direction="changeDirection(price.change)"
                >
                  {{ changeLabel(price.change) }}
                </span>
              </div>
              <dl class="card-price-metrics">
                <div>
                  <dt>Laagste prijs</dt>
                  <dd>{{ price.lowest ? formatMoney(price.lowest.amount, price.lowest.currency) : '—' }}</dd>
                </div>
                <div>
                  <dt>Gemiddelde prijsindicatie</dt>
                  <dd>{{ price.average ? formatMoney(price.average.amount, price.average.currency) : '—' }}</dd>
                </div>
                <div>
                  <dt>Trendprijs</dt>
                  <dd>{{ price.trend ? formatMoney(price.trend.amount, price.trend.currency) : '—' }}</dd>
                </div>
              </dl>
              <p class="price-source-time">
                Bron bijgewerkt:
                <time v-if="price.sourceUpdatedAt" :datetime="price.sourceUpdatedAt">
                  {{ formatTimestamp(price.sourceUpdatedAt) }}
                </time>
                <span v-else>Onbekend</span>
              </p>
            </article>
          </div>

          <div v-if="recentPriceHistory.length" class="price-history-table">
            <h3 id="price-history-heading">Dagelijkse prijshistorie</h3>
            <div class="table-scroll" role="region" aria-labelledby="price-history-heading" tabindex="0">
              <table>
                <thead>
                  <tr><th>Datum</th><th>Variant</th><th>Laagste</th><th>Gemiddeld</th><th>Trend</th></tr>
                </thead>
                <tbody>
                  <tr v-for="snapshot in recentPriceHistory" :key="`${snapshot.variant}-${snapshot.observedOn}`">
                    <td><time :datetime="snapshot.observedOn">{{ formatDate(snapshot.observedOn) }}</time></td>
                    <td>{{ variantLabel(snapshot.variant) }}</td>
                    <td>{{ snapshot.lowest ? formatMoney(snapshot.lowest.amount, snapshot.lowest.currency) : '—' }}</td>
                    <td>{{ snapshot.average ? formatMoney(snapshot.average.amount, snapshot.average.currency) : '—' }}</td>
                    <td>{{ snapshot.trend ? formatMoney(snapshot.trend.amount, snapshot.trend.currency) : '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <p class="price-disclaimer">{{ pricing.disclaimer }}</p>
        </template>
      </section>
    </template>

    <ErrorMessage v-else-if="error" :message="error" />
  </main>
</template>
