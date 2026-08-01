<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
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
const savingAction = ref<'save-draft' | 'publish-new' | 'publish' | 'cancel' | null>(null)
const error = ref('')
const success = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
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

const isSaving = computed(() => savingAction.value !== null)
const eligibleCollectionItems = computed(() => {
  const unavailableItemIds = new Set(
    listings.value
      .filter((listing) => ['draft', 'pending_review', 'active', 'reserved', 'sold'].includes(listing.status))
      .flatMap((listing) => listing.collectionItemId === undefined ? [] : [listing.collectionItemId]),
  )

  return collectionItems.value.filter((item) => !unavailableItemIds.has(item.id))
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

async function loadData(clearError = true): Promise<void> {
  loading.value = true
  if (clearError) error.value = ''
  try {
    const [listingResponse, collectionResponse] = await Promise.all([
      apiRequest<PaginatedResponse<Listing>>('/me/listings'),
      apiRequest<PaginatedResponse<CollectionItem>>('/me/collection-items?status=in_collection&perPage=100'),
    ])
    listings.value = listingResponse.items
    collectionItems.value = collectionResponse.items
    const requestedItem = typeof route.query.itemId === 'string' ? route.query.itemId : ''
    if (requestedItem && eligibleCollectionItems.value.some((item) => item.id === requestedItem)) {
      form.collectionItemId = requestedItem
    } else if (!eligibleCollectionItems.value.some((item) => item.id === form.collectionItemId)) {
      form.collectionItemId = eligibleCollectionItems.value[0]?.id ?? ''
    }
  } catch (caught) {
    error.value = caughtMessage(caught, 'Je verkoopgegevens konden niet worden geladen.')
  } finally {
    loading.value = false
  }
}

function resetForm(): void {
  form.collectionItemId = ''
  form.price = ''
  form.letterMailPrice = '150'
  form.parcelPrice = ''
  form.pickupAllowed = false
  form.frontImageUrl = ''
  form.backImageUrl = ''
  form.expiresAt = ''
  form.description = ''
}

function addFieldError(field: string, message: string): void {
  fieldErrors.value = { ...fieldErrors.value, [field]: [message] }
}

function isHttpsUrl(value: string): boolean {
  try {
    return new URL(value).protocol === 'https:'
  } catch {
    return false
  }
}

function createPayload(): Record<string, boolean | number | string> | null {
  fieldErrors.value = {}
  if (!form.collectionItemId) addFieldError('collectionItemId', 'Selecteer een beschikbare fysieke kaart.')

  let price: number | null = null
  let letterMailPrice: number | null = null
  let parcelPrice: number | null = null
  try {
    price = parseOptionalMinorUnits(form.price)
  } catch (caught) {
    addFieldError('price', caughtMessage(caught, 'Gebruik een heel bedrag in centen.'))
  }
  try {
    letterMailPrice = parseOptionalMinorUnits(form.letterMailPrice)
  } catch (caught) {
    addFieldError('letterMailPrice', caughtMessage(caught, 'Gebruik een heel bedrag in centen.'))
  }
  try {
    parcelPrice = parseOptionalMinorUnits(form.parcelPrice)
  } catch (caught) {
    addFieldError('parcelPrice', caughtMessage(caught, 'Gebruik een heel bedrag in centen.'))
  }
  if (price === null && fieldErrors.value.price === undefined) {
    addFieldError('price', 'De vraagprijs moet groter zijn dan nul.')
  } else if (price !== null && price <= 0) {
    addFieldError('price', 'De vraagprijs moet groter zijn dan nul.')
  }
  if (letterMailPrice === null && parcelPrice === null && !form.pickupAllowed) {
    if (fieldErrors.value.letterMailPrice === undefined && fieldErrors.value.parcelPrice === undefined) {
      addFieldError('letterMailPrice', 'Kies minimaal één verzend- of afhaaloptie.')
    }
  }
  if (!isHttpsUrl(form.frontImageUrl)) addFieldError('frontImageUrl', 'Gebruik een geldige HTTPS-link voor de voorkant.')
  if (!isHttpsUrl(form.backImageUrl)) addFieldError('backImageUrl', 'Gebruik een geldige HTTPS-link voor de achterkant.')
  if (form.expiresAt && Number.isNaN(new Date(form.expiresAt).valueOf())) {
    addFieldError('expiresAt', 'Kies een geldige einddatum.')
  }
  if (Object.keys(fieldErrors.value).length > 0 || price === null) return null

  const payload: Record<string, boolean | number | string> = {
    collectionItemId: form.collectionItemId,
    price,
    currency: 'EUR',
    pickupAllowed: form.pickupAllowed,
    frontImageUrl: form.frontImageUrl.trim(),
    backImageUrl: form.backImageUrl.trim(),
  }
  if (letterMailPrice !== null) payload.letterMailPrice = letterMailPrice
  if (parcelPrice !== null) payload.parcelPrice = parcelPrice
  if (form.description.trim()) payload.description = form.description.trim()
  if (form.expiresAt) payload.expiresAt = new Date(form.expiresAt).toISOString()

  return payload
}

function handleActionError(caught: unknown, fallback: string): void {
  if (caught instanceof ApiError) {
    error.value = caught.message
    fieldErrors.value = caught.details

    return
  }

  error.value = fallback
}

async function createListing(publish: boolean): Promise<void> {
  if (isSaving.value) return

  error.value = ''
  success.value = ''
  const payload = createPayload()
  if (payload === null) return

  savingAction.value = publish ? 'publish-new' : 'save-draft'
  try {
    const draft = await apiRequest<Listing>('/listings', { method: 'POST', body: JSON.stringify(payload) })
    if (publish) {
      await apiRequest<Listing>(`/listings/${draft.id}/publish`, { method: 'POST' })
      success.value = 'De fysieke kaart staat nu actief te koop.'
    } else {
      success.value = 'Concept opgeslagen. Publiceer de listing wanneer je klaar bent.'
    }
    resetForm()
  } catch (caught) {
    handleActionError(caught, publish ? 'De listing kon niet worden gepubliceerd.' : 'Het concept kon niet worden opgeslagen.')
  } finally {
    savingAction.value = null
    await loadData(false)
  }
}

async function saveDraft(): Promise<void> {
  await createListing(false)
}

async function createAndPublish(): Promise<void> {
  await createListing(true)
}

async function publishListing(listing: Listing): Promise<void> {
  if (isSaving.value) return

  savingAction.value = 'publish'
  error.value = ''
  fieldErrors.value = {}
  try {
    await apiRequest<Listing>(`/listings/${listing.id}/publish`, { method: 'POST' })
    success.value = 'De conceptlisting is gepubliceerd.'
  } catch (caught) {
    handleActionError(caught, 'De listing kon niet worden gepubliceerd.')
  } finally {
    savingAction.value = null
    await loadData(false)
  }
}

async function cancelListing(listing: Listing): Promise<void> {
  if (isSaving.value) return

  savingAction.value = 'cancel'
  error.value = ''
  fieldErrors.value = {}
  try {
    await apiRequest<Listing>(`/listings/${listing.id}/cancel`, { method: 'POST' })
    success.value = 'De listing is geannuleerd; de kaart is weer beschikbaar in je collectie.'
  } catch (caught) {
    handleActionError(caught, 'De listing kon niet worden geannuleerd.')
  } finally {
    savingAction.value = null
    await loadData(false)
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
          <select
            v-model="form.collectionItemId"
            required
            :aria-invalid="Boolean(fieldErrors.collectionItemId?.[0])"
            :aria-describedby="fieldErrors.collectionItemId?.[0] ? 'listing-collection-item-error' : undefined"
          >
            <option value="">Selecteer een kaart</option>
            <option v-for="item in eligibleCollectionItems" :key="item.id" :value="item.id">
              {{ item.card.name }} · {{ item.card.number }} · {{ readableCode(item.condition) }}
            </option>
          </select>
          <span v-if="fieldErrors.collectionItemId?.[0]" id="listing-collection-item-error" class="field-error">
            {{ fieldErrors.collectionItemId[0] }}
          </span>
        </label>
        <BaseInput id="listing-price" v-model="form.price" label="Vaste vraagprijs (centen)" type="number" min="1" required :error="fieldErrors.price?.[0]" />
        <div class="compact-fields">
          <BaseInput id="letter-mail-price" v-model="form.letterMailPrice" label="Brievenbuspost (centen)" type="number" min="0" :error="fieldErrors.letterMailPrice?.[0]" />
          <BaseInput id="parcel-price" v-model="form.parcelPrice" label="Pakketpost (centen)" type="number" min="0" :error="fieldErrors.parcelPrice?.[0]" />
        </div>
        <label class="checkbox-field">
          <input v-model="form.pickupAllowed" type="checkbox">
          <span>Ophalen toegestaan (€0)</span>
        </label>
        <BaseInput id="front-image-url" v-model="form.frontImageUrl" label="HTTPS-foto voorkant" type="url" required :error="fieldErrors.frontImageUrl?.[0]" />
        <BaseInput id="back-image-url" v-model="form.backImageUrl" label="HTTPS-foto achterkant" type="url" required :error="fieldErrors.backImageUrl?.[0]" />
        <BaseInput id="listing-expiration" v-model="form.expiresAt" label="Einddatum (optioneel)" type="datetime-local" :error="fieldErrors.expiresAt?.[0]" />
        <label class="field">
          <span>Aanvullende omschrijving</span>
          <textarea
            v-model="form.description"
            rows="4"
            maxlength="5000"
            :aria-invalid="Boolean(fieldErrors.description?.[0])"
            :aria-describedby="fieldErrors.description?.[0] ? 'listing-description-error' : undefined"
          />
          <span v-if="fieldErrors.description?.[0]" id="listing-description-error" class="field-error">
            {{ fieldErrors.description[0] }}
          </span>
        </label>
        <p class="form-help">Sla eerst een concept op als je nog niet wilt publiceren. Foto-uploads via object storage volgen als afzonderlijke infrastructuurstap; deze versie accepteert uitsluitend HTTPS-verwijzingen.</p>
        <div class="dialog-actions">
          <BaseButton type="button" variant="secondary" :disabled="isSaving || loading" @click="saveDraft">
            {{ savingAction === 'save-draft' ? 'Opslaan…' : 'Concept opslaan' }}
          </BaseButton>
          <BaseButton type="submit" :disabled="isSaving || loading">
            {{ savingAction === 'publish-new' ? 'Publiceren…' : 'Direct publiceren' }}
          </BaseButton>
        </div>
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
            <BaseButton v-if="listing.status === 'draft'" :disabled="isSaving" @click="publishListing(listing)">
              {{ savingAction === 'publish' ? 'Publiceren…' : 'Publiceren' }}
            </BaseButton>
            <BaseButton v-if="['draft', 'active'].includes(listing.status)" variant="danger" :disabled="isSaving" @click="cancelListing(listing)">
              {{ savingAction === 'cancel' ? 'Annuleren…' : 'Annuleren' }}
            </BaseButton>
          </div>
        </article>
      </section>
    </section>
  </main>
</template>
