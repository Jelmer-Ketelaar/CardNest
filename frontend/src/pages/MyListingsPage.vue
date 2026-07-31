<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { PaginatedResponse } from '../types/api'
import type { CollectionItem } from '../types/collection'
import type { Listing } from '../types/marketplace'
import { formatMoney, parseOptionalMinorUnits } from '../utils/money'

const route = useRoute()
const listings = ref<Listing[]>([])
const collectionItems = ref<CollectionItem[]>([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')
const form = reactive({
  collectionItemId: '',
  price: '',
  letterMailPrice: '150',
  parcelPrice: '',
  pickupAllowed: false,
  frontImageUrl: '',
  backImageUrl: '',
  expiresAt: '',
  description: '',
})

function caughtMessage(caught: unknown, fallback: string): string {
  return caught instanceof ApiError ? caught.message : fallback
}

function readableCode(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function statusLabel(status: Listing['status']): string {
  return {
    draft: 'Concept', pending_review: 'In controle', active: 'Actief', reserved: 'Gereserveerd', sold: 'Verkocht',
    cancelled: 'Geannuleerd', expired: 'Verlopen', suspended: 'Gepauzeerd',
  }[status]
}

async function loadData(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const [listingResponse, collectionResponse] = await Promise.all([
      apiRequest<PaginatedResponse<Listing>>('/me/listings'),
      apiRequest<PaginatedResponse<CollectionItem>>('/me/collection-items?status=in_collection&perPage=100'),
    ])
    listings.value = listingResponse.items
    collectionItems.value = collectionResponse.items
    const requestedItem = typeof route.query.itemId === 'string' ? route.query.itemId : ''
    if (requestedItem && collectionItems.value.some((item) => item.id === requestedItem)) {
      form.collectionItemId = requestedItem
    } else if (!form.collectionItemId && collectionItems.value[0]) {
      form.collectionItemId = collectionItems.value[0].id
    }
  } catch (caught) {
    error.value = caughtMessage(caught, 'Je verkoopgegevens konden niet worden geladen.')
  } finally {
    loading.value = false
  }
}

async function createAndPublish(): Promise<void> {
  error.value = ''
  success.value = ''
  if (!form.collectionItemId) {
    error.value = 'Selecteer een beschikbare fysieke kaart.'
    return
  }

  let price: number | null
  let letterMailPrice: number | null
  let parcelPrice: number | null
  try {
    price = parseOptionalMinorUnits(form.price)
    letterMailPrice = parseOptionalMinorUnits(form.letterMailPrice)
    parcelPrice = parseOptionalMinorUnits(form.parcelPrice)
  } catch (caught) {
    error.value = caughtMessage(caught, 'Gebruik hele bedragen in centen.')
    return
  }
  if (price === null || price <= 0) {
    error.value = 'De vraagprijs moet groter zijn dan nul.'
    return
  }
  if (letterMailPrice === null && parcelPrice === null && !form.pickupAllowed) {
    error.value = 'Kies minimaal één verzend- of afhaaloptie.'
    return
  }

  const payload: Record<string, boolean | number | string> = {
    collectionItemId: form.collectionItemId,
    price,
    currency: 'EUR',
    pickupAllowed: form.pickupAllowed,
    frontImageUrl: form.frontImageUrl,
    backImageUrl: form.backImageUrl,
  }
  if (letterMailPrice !== null) payload.letterMailPrice = letterMailPrice
  if (parcelPrice !== null) payload.parcelPrice = parcelPrice
  if (form.description.trim()) payload.description = form.description.trim()
  if (form.expiresAt) payload.expiresAt = new Date(form.expiresAt).toISOString()

  saving.value = true
  try {
    const draft = await apiRequest<Listing>('/listings', { method: 'POST', body: JSON.stringify(payload) })
    await apiRequest<Listing>(`/listings/${draft.id}/publish`, { method: 'POST' })
    form.price = ''
    form.frontImageUrl = ''
    form.backImageUrl = ''
    form.description = ''
    form.expiresAt = ''
    success.value = 'De fysieke kaart staat nu actief te koop.'
    await loadData()
  } catch (caught) {
    error.value = caughtMessage(caught, 'De listing kon niet worden gepubliceerd.')
    await loadData()
  } finally {
    saving.value = false
  }
}

async function publishListing(listing: Listing): Promise<void> {
  saving.value = true
  error.value = ''
  try {
    await apiRequest<Listing>(`/listings/${listing.id}/publish`, { method: 'POST' })
    success.value = 'De conceptlisting is gepubliceerd.'
    await loadData()
  } catch (caught) {
    error.value = caughtMessage(caught, 'De listing kon niet worden gepubliceerd.')
  } finally {
    saving.value = false
  }
}

async function cancelListing(listing: Listing): Promise<void> {
  saving.value = true
  error.value = ''
  try {
    await apiRequest<Listing>(`/listings/${listing.id}/cancel`, { method: 'POST' })
    success.value = 'De listing is geannuleerd; de kaart is weer beschikbaar in je collectie.'
    await loadData()
  } catch (caught) {
    error.value = caughtMessage(caught, 'De listing kon niet worden geannuleerd.')
  } finally {
    saving.value = false
  }
}

onMounted(() => { void loadData() })
</script>

<template>
  <main class="page-container my-listings-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">Eén fysieke kaart · één listing</p>
        <h1>Mijn verkoop</h1>
        <p>De kaartgegevens komen rechtstreeks uit je collectie. Voeg eigen conditiefoto’s en complete verzendprijzen toe.</p>
      </div>
    </header>

    <ErrorMessage v-if="error" :message="error" />
    <p v-if="success" class="success-message" role="status">{{ success }}</p>

    <section class="seller-workspace">
      <form class="panel listing-form" @submit.prevent="createAndPublish">
        <p class="eyebrow">Nieuwe aanbieding</p>
        <h2>Kaart te koop zetten</h2>
        <label class="field">
          <span>Fysiek exemplaar</span>
          <select v-model="form.collectionItemId" required>
            <option value="">Selecteer een kaart</option>
            <option v-for="item in collectionItems" :key="item.id" :value="item.id">
              {{ item.card.name }} · {{ item.card.number }} · {{ readableCode(item.condition) }}
            </option>
          </select>
        </label>
        <BaseInput id="listing-price" v-model="form.price" label="Vaste vraagprijs (centen)" type="number" min="1" required />
        <div class="compact-fields">
          <BaseInput id="letter-mail-price" v-model="form.letterMailPrice" label="Brievenbuspost (centen)" type="number" min="0" />
          <BaseInput id="parcel-price" v-model="form.parcelPrice" label="Pakketpost (centen)" type="number" min="0" />
        </div>
        <label class="checkbox-field">
          <input v-model="form.pickupAllowed" type="checkbox">
          <span>Ophalen toegestaan (€0)</span>
        </label>
        <BaseInput id="front-image-url" v-model="form.frontImageUrl" label="HTTPS-foto voorkant" type="url" required />
        <BaseInput id="back-image-url" v-model="form.backImageUrl" label="HTTPS-foto achterkant" type="url" required />
        <BaseInput id="listing-expiration" v-model="form.expiresAt" label="Einddatum (optioneel)" type="datetime-local" />
        <label class="field">
          <span>Aanvullende omschrijving</span>
          <textarea v-model="form.description" rows="4" maxlength="5000" />
        </label>
        <p class="form-help">Foto-uploads via object storage volgen als afzonderlijke infrastructuurstap; deze versie accepteert uitsluitend HTTPS-verwijzingen.</p>
        <BaseButton type="submit" :disabled="saving || loading">{{ saving ? 'Publiceren…' : 'Direct publiceren' }}</BaseButton>
      </form>

      <section class="seller-listings" aria-labelledby="seller-listings-heading">
        <div>
          <p class="eyebrow">{{ listings.length }} listings</p>
          <h2 id="seller-listings-heading">Mijn listings</h2>
        </div>
        <p v-if="loading" class="empty-state" role="status">Listings laden…</p>
        <p v-else-if="listings.length === 0" class="empty-state">Je hebt nog geen listings.</p>
        <article v-for="listing in listings" :key="listing.id" class="seller-listing-card">
          <img :src="listing.images.find((image) => image.side === 'front')?.url" :alt="`${listing.card.name} voorkant`">
          <div>
            <span class="badge">{{ statusLabel(listing.status) }}</span>
            <h3>{{ listing.card.name }} · {{ listing.card.number }}</h3>
            <p>{{ readableCode(listing.variant) }} · {{ readableCode(listing.condition) }}</p>
            <strong>{{ formatMoney(listing.price.amount, listing.price.currency) }}</strong>
          </div>
          <div class="seller-listing-actions">
            <BaseButton v-if="listing.status === 'draft'" :disabled="saving" @click="publishListing(listing)">Publiceren</BaseButton>
            <BaseButton v-if="['draft', 'active'].includes(listing.status)" variant="danger" :disabled="saving" @click="cancelListing(listing)">Annuleren</BaseButton>
          </div>
        </article>
      </section>
    </section>
  </main>
</template>
