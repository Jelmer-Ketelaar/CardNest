<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { CollectionItem } from '../types/collection'
import { formatMoney } from '../utils/money'

const route = useRoute()
const item = ref<CollectionItem | null>(null)
const loading = ref(false)
const notFound = ref(false)
const error = ref('')
let latestRequest = 0

const itemId = computed(() => routeId(route.params.id))

function routeId(value: unknown): string | null {
  return typeof value === 'string' && value !== '' ? value : null
}

function readableCode(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function formatDate(value: string | null): string {
  if (value === null) return 'Niet ingevuld'

  return new Intl.DateTimeFormat('nl-NL', { dateStyle: 'medium' }).format(new Date(`${value}T00:00:00`))
}

async function loadItem(id: string | null): Promise<void> {
  const request = ++latestRequest
  item.value = null
  loading.value = true
  notFound.value = false
  error.value = ''

  if (id === null) {
    loading.value = false
    notFound.value = true

    return
  }

  try {
    const response = await apiRequest<CollectionItem>(`/me/collection-items/${encodeURIComponent(id)}`)
    if (request !== latestRequest) return

    item.value = response
  } catch (caught) {
    if (request !== latestRequest) return

    if (caught instanceof ApiError && caught.status === 404) {
      notFound.value = true
    } else {
      error.value = caught instanceof ApiError ? caught.message : 'De collectiekaart kon niet worden geladen.'
    }
  } finally {
    if (request === latestRequest) loading.value = false
  }
}

watch(itemId, (id) => { void loadItem(id) }, { immediate: true })
</script>

<template>
  <main class="page-container collection-item-detail-page">
    <p v-if="loading" class="empty-state" role="status">Collectiekaart laden…</p>

    <section v-else-if="notFound" class="panel catalog-not-found" aria-labelledby="collection-item-not-found-heading">
      <p class="eyebrow">Mijn collectie</p>
      <h1 id="collection-item-not-found-heading">Collectiekaart niet gevonden</h1>
      <p>Deze fysieke kaart bestaat niet of hoort niet bij jouw collectie.</p>
      <RouterLink class="button button--secondary" :to="{ name: 'collection' }">Terug naar collectie</RouterLink>
    </section>

    <template v-else-if="item">
      <header class="page-heading">
        <div>
          <p class="eyebrow">Fysiek exemplaar · {{ item.card.set.name }}</p>
          <h1>{{ item.card.name }} <small>{{ item.card.number }}</small></h1>
          <p>{{ readableCode(item.variant.code) }} · {{ readableCode(item.condition) }}</p>
        </div>
        <div class="page-heading__actions">
          <RouterLink class="button button--secondary" :to="{ name: 'collection' }">Terug naar collectie</RouterLink>
          <RouterLink
            v-if="item.status !== 'archived'"
            class="button button--primary"
            :to="{ name: 'collection', query: { edit: item.id } }"
          >
            Bewerken
          </RouterLink>
        </div>
      </header>

      <section class="collection-item-detail panel panel--wide" aria-label="Gegevens van fysieke kaart">
        <div class="card-art collection-item-detail__art">
          <img v-if="item.card.imageUrl ?? item.card.thumbnailUrl" :src="item.card.imageUrl ?? item.card.thumbnailUrl ?? ''" :alt="`${item.card.name} ${item.card.number}`">
          <span v-else aria-hidden="true">{{ item.card.name.slice(0, 1) }}</span>
        </div>
        <div>
          <p v-if="item.status === 'archived'" class="badge">Gearchiveerd</p>
          <p v-else-if="item.status === 'for_sale'" class="badge">Actief te koop</p>
          <p v-if="item.status === 'archived'" class="immutable-note">
            Dit exemplaar blijft historisch zichtbaar, maar kan niet meer worden gewijzigd of opnieuw actief gebruikt.
          </p>
          <dl class="card-facts">
            <div><dt>Cataloguskaart</dt><dd>{{ item.card.name }} · {{ item.card.number }}</dd></div>
            <div><dt>Set</dt><dd>{{ item.card.set.name }}</dd></div>
            <div><dt>Taal</dt><dd>{{ readableCode(item.language) }}</dd></div>
            <div><dt>Variant</dt><dd>{{ readableCode(item.variant.code) }}</dd></div>
            <div><dt>Conditie</dt><dd>{{ readableCode(item.condition) }}</dd></div>
            <div><dt>Status</dt><dd>{{ readableCode(item.status) }}</dd></div>
            <div><dt>Aankoopprijs</dt><dd>{{ item.purchase.price ? formatMoney(item.purchase.price.amount, item.purchase.price.currency) : 'Niet ingevuld' }}</dd></div>
            <div><dt>Aankoopdatum</dt><dd>{{ formatDate(item.purchase.purchasedAt) }}</dd></div>
            <div><dt>Verkoper of winkel</dt><dd>{{ item.purchase.sellerName ?? 'Niet ingevuld' }}</dd></div>
          </dl>
          <p v-if="item.notes" class="card-notes">{{ item.notes }}</p>
          <p v-if="item.duplicate.isDuplicate" class="duplicate-note">
            Je bezit {{ item.duplicate.ownedQuantity }} exemplaren met deze kaart, taal en variant.
          </p>
        </div>
      </section>
    </template>

    <section v-else-if="error" class="panel" aria-label="Fout bij laden van collectiekaart">
      <ErrorMessage :message="error" />
      <BaseButton variant="secondary" @click="loadItem(itemId)">Opnieuw proberen</BaseButton>
    </section>
  </main>
</template>
