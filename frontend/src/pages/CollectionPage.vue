<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { PaginatedResponse } from '../types/api'
import type { CatalogCard, CatalogCardVariant } from '../types/catalog'
import type { CollectionItem, CreatedCollectionItemsResponse } from '../types/collection'
import type {
  CollectionItemLocationResponse,
  StorageLocation,
  StorageLocationCollectionResponse,
} from '../types/storage'
import { formatMoney, parseOptionalMinorUnits } from '../utils/money'

const route = useRoute()
const router = useRouter()

const languages = [
  ['en', 'Engels'],
  ['nl', 'Nederlands'],
  ['ja', 'Japans'],
  ['ko', 'Koreaans'],
  ['de', 'Duits'],
  ['fr', 'Frans'],
  ['it', 'Italiaans'],
  ['es', 'Spaans'],
  ['pt', 'Portugees'],
  ['zh', 'Chinees'],
] as const

const conditions = [
  ['mint', 'Mint'],
  ['near_mint', 'Near Mint'],
  ['excellent', 'Excellent'],
  ['good', 'Good'],
  ['light_played', 'Light Played'],
  ['played', 'Played'],
  ['poor', 'Poor'],
] as const

const variantCodes = [
  'normal', 'holo', 'reverse_holo', 'cosmos_holo', 'stamped', 'promo', 'first_edition', 'unlimited',
  'shadowless', 'alternate_art', 'illustration_rare', 'special_illustration_rare', 'gold', 'rainbow',
  'master_ball', 'poke_ball', 'other',
] as const

const items = ref<CollectionItem[]>([])
const total = ref(0)
const totalPages = ref(0)
const page = ref(1)
const loading = ref(false)
const error = ref('')
const success = ref('')
const viewMode = ref<'grid' | 'list'>('grid')
const archivingId = ref<string | null>(null)
const storageLocations = ref<StorageLocation[]>([])
const filters = reactive({ cardName: '', language: '', variant: '', condition: '', status: 'in_collection' })

const catalogQuery = ref('')
const catalogMatches = ref<CatalogCard[]>([])
const selectedCard = ref<CatalogCard | null>(null)
const catalogSearching = ref(false)
const catalogError = ref('')
const creating = ref(false)
const createErrors = ref<Record<string, string[]>>({})
const createForm = reactive({
  variantId: '',
  language: 'en',
  condition: 'near_mint',
  quantity: '1',
  purchasePrice: '',
  purchasedAt: '',
  sellerName: '',
  notes: '',
})

const editingItem = ref<CollectionItem | null>(null)
const editVariants = ref<CatalogCardVariant[]>([])
const originalLocationId = ref('')
const currentBinderPlacementLabel = ref('')
const editSaving = ref(false)
const editErrors = ref<Record<string, string[]>>({})
const editForm = reactive({
  variantId: '',
  language: '',
  condition: '',
  purchasePrice: '',
  purchasedAt: '',
  sellerName: '',
  notes: '',
  locationId: '',
})

const requestedEditId = computed(() => routeId(route.query.edit))
let latestEditRequest = 0

function readableCode(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function caughtMessage(caught: unknown, fallback: string): string {
  return caught instanceof ApiError ? caught.message : fallback
}

function routeId(value: unknown): string | null {
  return typeof value === 'string' && value !== '' ? value : null
}

function fieldError(errors: Record<string, string[]>, field: string): string | undefined {
  return errors[field]?.[0]
}

async function loadItems(): Promise<void> {
  loading.value = true
  error.value = ''
  const query = new URLSearchParams({ page: String(page.value), perPage: '24' })
  for (const [key, value] of Object.entries(filters)) {
    if (value !== '') query.set(key, value)
  }

  try {
    const response = await apiRequest<PaginatedResponse<CollectionItem>>(`/me/collection-items?${query}`)
    items.value = response.items
    total.value = response.total
    totalPages.value = response.totalPages
  } catch (caught) {
    error.value = caughtMessage(caught, 'Je collectie kon niet worden geladen.')
  } finally {
    loading.value = false
  }
}

async function loadStorageLocations(): Promise<void> {
  try {
    const response = await apiRequest<StorageLocationCollectionResponse>('/me/storage-locations')
    storageLocations.value = response.items
  } catch (caught) {
    error.value = caughtMessage(caught, 'De opslaglocaties konden niet worden geladen.')
  }
}

async function applyFilters(): Promise<void> {
  page.value = 1
  await loadItems()
}

async function goToPage(target: number): Promise<void> {
  page.value = target
  await loadItems()
}

async function searchCatalog(): Promise<void> {
  catalogError.value = ''
  selectedCard.value = null
  catalogMatches.value = []
  if (catalogQuery.value.trim().length < 2) {
    catalogError.value = 'Vul minimaal twee tekens van de kaartnaam in.'
    return
  }

  catalogSearching.value = true
  try {
    const query = new URLSearchParams({ name: catalogQuery.value.trim(), perPage: '8' })
    const response = await apiRequest<PaginatedResponse<CatalogCard>>(`/cards?${query}`)
    catalogMatches.value = response.items
    if (response.items.length === 0) catalogError.value = 'Geen cataloguskaarten gevonden.'
  } catch (caught) {
    catalogError.value = caughtMessage(caught, 'De catalogus kon niet worden doorzocht.')
  } finally {
    catalogSearching.value = false
  }
}

function selectCard(card: CatalogCard): void {
  selectedCard.value = card
  createForm.variantId = card.variants[0]?.id ?? ''
  catalogMatches.value = []
}

async function createItems(): Promise<void> {
  error.value = ''
  success.value = ''
  createErrors.value = {}
  if (!selectedCard.value) {
    createErrors.value = { catalogCardId: ['Selecteer eerst een cataloguskaart.'] }
    return
  }
  if (createForm.variantId === '') {
    createErrors.value = { variantId: ['Selecteer een variant.'] }
    return
  }

  const quantity = Number(createForm.quantity)
  if (!Number.isInteger(quantity) || quantity < 1 || quantity > 100) {
    createErrors.value = { quantity: ['Het aantal moet een heel getal tussen 1 en 100 zijn.'] }
    return
  }

  let purchasePrice: number | null
  try {
    purchasePrice = parseOptionalMinorUnits(createForm.purchasePrice)
  } catch (caught) {
    createErrors.value = { purchasePrice: [caughtMessage(caught, 'Vul een geldige aankoopprijs in centen in.')] }
    return
  }

  const payload: Record<string, boolean | number | string> = {
    catalogCardId: selectedCard.value.id,
    variantId: createForm.variantId,
    language: createForm.language,
    condition: createForm.condition,
    quantity,
  }
  if (purchasePrice !== null) {
    payload.purchasePrice = purchasePrice
    payload.purchaseCurrency = 'EUR'
  }
  if (createForm.purchasedAt) payload.purchasedAt = createForm.purchasedAt
  if (createForm.sellerName.trim()) payload.sellerName = createForm.sellerName.trim()
  if (createForm.notes.trim()) payload.notes = createForm.notes.trim()

  creating.value = true
  try {
    const response = await apiRequest<CreatedCollectionItemsResponse>('/me/collection-items', {
      method: 'POST',
      body: JSON.stringify(payload),
    })
    success.value = response.created === 1
      ? 'De fysieke kaart is aan je collectie toegevoegd.'
      : `${response.created} fysieke kaarten zijn afzonderlijk toegevoegd.`
    createForm.quantity = '1'
    createForm.purchasePrice = ''
    createForm.purchasedAt = ''
    createForm.sellerName = ''
    createForm.notes = ''
    page.value = 1
    await loadItems()
  } catch (caught) {
    if (caught instanceof ApiError && Object.keys(caught.details).length > 0) {
      createErrors.value = caught.details
    } else {
      error.value = caughtMessage(caught, 'De kaart kon niet worden toegevoegd.')
    }
  } finally {
    creating.value = false
  }
}

async function startEdit(item: CollectionItem): Promise<void> {
  error.value = ''
  editErrors.value = {}
  try {
    const [card, location] = await Promise.all([
      apiRequest<CatalogCard>(`/cards/${item.card.id}`),
      apiRequest<CollectionItemLocationResponse>(`/me/collection-items/${item.id}/location`),
    ])
    editingItem.value = item
    editVariants.value = card.variants
    editForm.variantId = item.variant.id
    editForm.language = item.language
    editForm.condition = item.condition
    editForm.purchasePrice = item.purchase.price ? String(item.purchase.price.amount) : ''
    editForm.purchasedAt = item.purchase.purchasedAt ?? ''
    editForm.sellerName = item.purchase.sellerName ?? ''
    editForm.notes = item.notes ?? ''
    if (location.current?.binderSlot) {
      editForm.locationId = `binder:${location.current.binderSlot.id}`
      currentBinderPlacementLabel.value = [
        location.current.binderSlot.binder.name,
        `pagina ${location.current.binderSlot.page.number}`,
        location.current.binderSlot.page.side === 'front' ? 'voorzijde' : 'achterzijde',
        `vak ${location.current.binderSlot.position}`,
      ].join(' · ')
    } else {
      editForm.locationId = location.current?.location?.id ?? ''
      currentBinderPlacementLabel.value = ''
    }
    originalLocationId.value = editForm.locationId
  } catch (caught) {
    error.value = caughtMessage(caught, 'De kaart kon niet worden geopend voor bewerking.')
  }
}

function closeEdit(): void {
  latestEditRequest += 1
  editingItem.value = null
  editVariants.value = []
  currentBinderPlacementLabel.value = ''
  if (route.query.edit !== undefined) {
    const query = { ...route.query }
    delete query.edit
    void router.replace({ name: 'collection', query })
  }
}

async function saveEdit(): Promise<void> {
  const item = editingItem.value
  if (!item) return

  error.value = ''
  success.value = ''
  editErrors.value = {}
  let purchasePrice: number | null
  try {
    purchasePrice = parseOptionalMinorUnits(editForm.purchasePrice)
  } catch (caught) {
    editErrors.value = { purchasePrice: [caughtMessage(caught, 'Vul een geldige aankoopprijs in centen in.')] }
    return
  }

  const payload: Record<string, boolean | number | string> = {
    variantId: editForm.variantId,
    language: editForm.language,
    condition: editForm.condition,
    sellerName: editForm.sellerName,
    notes: editForm.notes,
  }
  if (purchasePrice === null) {
    if (item.purchase.price !== null) payload.clearPurchasePrice = true
  } else {
    payload.purchasePrice = purchasePrice
    payload.purchaseCurrency = 'EUR'
  }
  if (editForm.purchasedAt === '') {
    if (item.purchase.purchasedAt !== null) payload.clearPurchasedAt = true
  } else {
    payload.purchasedAt = editForm.purchasedAt
  }

  editSaving.value = true
  try {
    await apiRequest<CollectionItem>(`/me/collection-items/${item.id}`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    })
    if (editForm.locationId !== originalLocationId.value) {
      if (editForm.locationId === '') {
        await apiRequest<void>(`/me/collection-items/${item.id}/location`, { method: 'DELETE' })
      } else if (!editForm.locationId.startsWith('binder:')) {
        await apiRequest<CollectionItemLocationResponse>(`/me/collection-items/${item.id}/location`, {
          method: 'POST',
          body: JSON.stringify({ locationId: editForm.locationId }),
        })
      }
    }
    success.value = 'De collectiekaart is bijgewerkt.'
    closeEdit()
    await loadItems()
  } catch (caught) {
    if (caught instanceof ApiError && Object.keys(caught.details).length > 0) {
      editErrors.value = caught.details
    } else {
      error.value = caughtMessage(caught, 'De kaart kon niet worden bijgewerkt.')
    }
  } finally {
    editSaving.value = false
  }
}

async function archiveItem(item: CollectionItem): Promise<void> {
  if (!window.confirm(`Archiveer ${item.card.name} ${item.card.number}?`)) return

  archivingId.value = item.id
  error.value = ''
  success.value = ''
  try {
    await apiRequest<void>(`/me/collection-items/${item.id}`, { method: 'DELETE' })
    success.value = 'De collectiekaart is gearchiveerd.'
    if (editingItem.value?.id === item.id) closeEdit()
    await loadItems()
  } catch (caught) {
    error.value = caughtMessage(caught, 'De kaart kon niet worden gearchiveerd.')
  } finally {
    archivingId.value = null
  }
}

async function openEditFromRoute(id: string): Promise<void> {
  const request = ++latestEditRequest
  error.value = ''
  try {
    const item = await apiRequest<CollectionItem>(`/me/collection-items/${encodeURIComponent(id)}`)
    if (request !== latestEditRequest) return

    await startEdit(item)
  } catch (caught) {
    if (request !== latestEditRequest) return

    error.value = caughtMessage(caught, 'De kaart kon niet worden geopend voor bewerking.')
  }
}

watch(requestedEditId, (id) => {
  if (id === null || editingItem.value?.id === id) return

  void openEditFromRoute(id)
}, { immediate: true })

onMounted(() => {
  void loadItems()
  void loadStorageLocations()
})
</script>

<template>
  <main class="page-container collection-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">Mijn kaarten</p>
        <h1>Collectie</h1>
        <p>{{ total }} fysieke {{ total === 1 ? 'kaart' : 'kaarten' }} binnen de huidige filters.</p>
      </div>
      <div class="segmented-control" aria-label="Weergave">
        <button type="button" :aria-pressed="viewMode === 'grid'" @click="viewMode = 'grid'">Raster</button>
        <button type="button" :aria-pressed="viewMode === 'list'" @click="viewMode = 'list'">Lijst</button>
      </div>
    </header>

    <ErrorMessage v-if="error" :message="error" />
    <p v-if="success" class="success-message" role="status">{{ success }}</p>

    <section class="panel panel--wide add-collection-panel" aria-labelledby="add-card-heading">
      <div>
        <p class="eyebrow">Eenmalig registreren</p>
        <h2 id="add-card-heading">Voeg fysieke kaarten toe</h2>
        <p>Zoek eerst de officiële cataloguskaart. Een aantal groter dan één maakt voor elk exemplaar een eigen record.</p>
      </div>

      <form class="catalog-search" @submit.prevent="searchCatalog">
        <BaseInput id="catalog-query" v-model="catalogQuery" label="Kaartnaam" type="search" autocomplete="off" />
        <BaseButton type="submit" :disabled="catalogSearching">
          {{ catalogSearching ? 'Zoeken…' : 'Zoek in catalogus' }}
        </BaseButton>
      </form>
      <ErrorMessage v-if="catalogError" :message="catalogError" />
      <p v-if="fieldError(createErrors, 'catalogCardId')" class="field-error" role="alert">
        {{ fieldError(createErrors, 'catalogCardId') }}
      </p>

      <div v-if="catalogMatches.length" class="catalog-results" aria-label="Zoekresultaten">
        <button v-for="card in catalogMatches" :key="card.id" type="button" @click="selectCard(card)">
          <strong>{{ card.name }} · {{ card.number }}</strong>
          <span>{{ card.set.name }} · {{ card.rarity }}</span>
        </button>
      </div>

      <form v-if="selectedCard" class="collection-form" @submit.prevent="createItems">
        <div class="selected-card">
          <strong>{{ selectedCard.name }} · {{ selectedCard.number }}</strong>
          <span>{{ selectedCard.set.name }}</span>
          <button type="button" @click="selectedCard = null">Andere kaart kiezen</button>
        </div>

        <label class="field">
          <span>Variant</span>
          <select
            v-model="createForm.variantId"
            required
            :aria-invalid="Boolean(fieldError(createErrors, 'variantId'))"
            :aria-describedby="fieldError(createErrors, 'variantId') ? 'create-variant-error' : undefined"
          >
            <option v-for="variant in selectedCard.variants" :key="variant.id" :value="variant.id">
              {{ readableCode(variant.code) }}
            </option>
          </select>
          <span v-if="fieldError(createErrors, 'variantId')" id="create-variant-error" class="field-error">
            {{ fieldError(createErrors, 'variantId') }}
          </span>
        </label>
        <label class="field">
          <span>Taal</span>
          <select
            v-model="createForm.language"
            required
            :aria-invalid="Boolean(fieldError(createErrors, 'language'))"
            :aria-describedby="fieldError(createErrors, 'language') ? 'create-language-error' : undefined"
          >
            <option v-for="language in languages" :key="language[0]" :value="language[0]">{{ language[1] }}</option>
          </select>
          <span v-if="fieldError(createErrors, 'language')" id="create-language-error" class="field-error">
            {{ fieldError(createErrors, 'language') }}
          </span>
        </label>
        <label class="field">
          <span>Conditie</span>
          <select
            v-model="createForm.condition"
            required
            :aria-invalid="Boolean(fieldError(createErrors, 'condition'))"
            :aria-describedby="fieldError(createErrors, 'condition') ? 'create-condition-error' : undefined"
          >
            <option v-for="condition in conditions" :key="condition[0]" :value="condition[0]">{{ condition[1] }}</option>
          </select>
          <span v-if="fieldError(createErrors, 'condition')" id="create-condition-error" class="field-error">
            {{ fieldError(createErrors, 'condition') }}
          </span>
        </label>
        <BaseInput id="quantity" v-model="createForm.quantity" label="Aantal" type="number" min="1" max="100" required :error="fieldError(createErrors, 'quantity')" />
        <BaseInput id="purchase-price" v-model="createForm.purchasePrice" label="Aankoopprijs per kaart (centen)" type="number" min="0" :error="fieldError(createErrors, 'purchasePrice') ?? fieldError(createErrors, 'purchaseCurrency')" />
        <BaseInput id="purchased-at" v-model="createForm.purchasedAt" label="Aankoopdatum" type="date" :error="fieldError(createErrors, 'purchasedAt')" />
        <BaseInput id="seller-name" v-model="createForm.sellerName" label="Verkoper of winkel" :error="fieldError(createErrors, 'sellerName')" />
        <label class="field field--full">
          <span>Notities</span>
          <textarea
            v-model="createForm.notes"
            rows="3"
            maxlength="5000"
            :aria-invalid="Boolean(fieldError(createErrors, 'notes'))"
            :aria-describedby="fieldError(createErrors, 'notes') ? 'create-notes-error' : undefined"
          />
          <span v-if="fieldError(createErrors, 'notes')" id="create-notes-error" class="field-error">
            {{ fieldError(createErrors, 'notes') }}
          </span>
        </label>
        <BaseButton type="submit" :disabled="creating">
          {{ creating ? 'Toevoegen…' : 'Aan collectie toevoegen' }}
        </BaseButton>
      </form>
    </section>

    <form class="filter-bar" aria-label="Collectie filteren" @submit.prevent="applyFilters">
      <BaseInput id="filter-card-name" v-model="filters.cardName" label="Kaartnaam" type="search" />
      <label class="field">
        <span>Taal</span>
        <select v-model="filters.language">
          <option value="">Alle talen</option>
          <option v-for="language in languages" :key="language[0]" :value="language[0]">{{ language[1] }}</option>
        </select>
      </label>
      <label class="field">
        <span>Variant</span>
        <select v-model="filters.variant">
          <option value="">Alle varianten</option>
          <option v-for="variant in variantCodes" :key="variant" :value="variant">{{ readableCode(variant) }}</option>
        </select>
      </label>
      <label class="field">
        <span>Conditie</span>
        <select v-model="filters.condition">
          <option value="">Alle condities</option>
          <option v-for="condition in conditions" :key="condition[0]" :value="condition[0]">{{ condition[1] }}</option>
        </select>
      </label>
      <label class="field">
        <span>Status</span>
        <select v-model="filters.status">
          <option value="in_collection">In collectie</option>
          <option value="for_sale">Te koop</option>
          <option value="archived">Gearchiveerd</option>
        </select>
      </label>
      <BaseButton type="submit" :disabled="loading">Filters toepassen</BaseButton>
    </form>

    <p v-if="loading" class="empty-state" role="status">Collectie laden…</p>
    <p v-else-if="items.length === 0" class="empty-state">Geen fysieke kaarten gevonden binnen deze filters.</p>

    <section v-else class="collection-items" :class="`collection-items--${viewMode}`" aria-label="Collectiekaarten">
      <article v-for="item in items" :key="item.id" class="collection-card">
        <div class="card-art">
          <img v-if="item.card.thumbnailUrl" :src="item.card.thumbnailUrl" :alt="`${item.card.name} ${item.card.number}`">
          <span v-else aria-hidden="true">{{ item.card.name.slice(0, 1) }}</span>
        </div>
        <div class="collection-card__body">
          <div class="collection-card__heading">
            <div>
              <p class="eyebrow">{{ item.card.set.name }}</p>
              <h2>{{ item.card.name }} <small>{{ item.card.number }}</small></h2>
            </div>
            <span v-if="item.duplicate.isDuplicate" class="badge badge--duplicate">
              {{ item.duplicate.ownedQuantity }} in bezit
            </span>
          </div>
          <dl class="card-facts">
            <div><dt>Taal</dt><dd>{{ languages.find((entry) => entry[0] === item.language)?.[1] ?? item.language }}</dd></div>
            <div><dt>Variant</dt><dd>{{ readableCode(item.variant.code) }}</dd></div>
            <div><dt>Conditie</dt><dd>{{ readableCode(item.condition) }}</dd></div>
            <div><dt>Aankoop</dt><dd>{{ item.purchase.price ? formatMoney(item.purchase.price.amount, item.purchase.price.currency) : 'Niet ingevuld' }}</dd></div>
          </dl>
          <p v-if="item.notes" class="card-notes">{{ item.notes }}</p>
          <div class="card-actions">
            <RouterLink class="button button--secondary" :to="{ name: 'collection-item', params: { id: item.id } }">
              Bekijk details
            </RouterLink>
            <template v-if="item.status === 'in_collection'">
              <RouterLink class="button button--primary" :to="{ name: 'my-listings', query: { itemId: item.id } }">
                Te koop zetten
              </RouterLink>
              <BaseButton variant="secondary" @click="startEdit(item)">Bewerken</BaseButton>
              <BaseButton variant="danger" :disabled="archivingId === item.id" @click="archiveItem(item)">
                {{ archivingId === item.id ? 'Archiveren…' : 'Archiveren' }}
              </BaseButton>
            </template>
          </div>
          <span v-if="item.status !== 'in_collection'" class="badge">
            {{ item.status === 'for_sale' ? 'Actief te koop' : 'Gearchiveerd' }}
          </span>
        </div>
      </article>
    </section>

    <nav v-if="totalPages > 1" class="pagination" aria-label="Paginering">
      <BaseButton variant="secondary" :disabled="page <= 1 || loading" @click="goToPage(page - 1)">Vorige</BaseButton>
      <span>Pagina {{ page }} van {{ totalPages }}</span>
      <BaseButton variant="secondary" :disabled="page >= totalPages || loading" @click="goToPage(page + 1)">Volgende</BaseButton>
    </nav>

    <div v-if="editingItem" class="modal-backdrop" @click.self="closeEdit">
      <section class="panel edit-panel" role="dialog" aria-modal="true" aria-labelledby="edit-heading">
        <p class="eyebrow">Fysiek exemplaar</p>
        <h2 id="edit-heading">{{ editingItem.card.name }} bewerken</h2>
        <p class="immutable-note">
          Cataloguskaart: {{ editingItem.card.name }} · {{ editingItem.card.number }} uit {{ editingItem.card.set.name }}.
          Deze officiële kaartgegevens kunnen hier niet worden gewijzigd.
        </p>
        <form class="edit-form" @submit.prevent="saveEdit">
          <label class="field">
            <span>Variant</span>
            <select
              v-model="editForm.variantId"
              required
              :aria-invalid="Boolean(fieldError(editErrors, 'variantId'))"
              :aria-describedby="fieldError(editErrors, 'variantId') ? 'edit-variant-error' : undefined"
            >
              <option v-for="variant in editVariants" :key="variant.id" :value="variant.id">{{ readableCode(variant.code) }}</option>
            </select>
            <span v-if="fieldError(editErrors, 'variantId')" id="edit-variant-error" class="field-error">
              {{ fieldError(editErrors, 'variantId') }}
            </span>
          </label>
          <label class="field">
            <span>Taal</span>
            <select
              v-model="editForm.language"
              required
              :aria-invalid="Boolean(fieldError(editErrors, 'language'))"
              :aria-describedby="fieldError(editErrors, 'language') ? 'edit-language-error' : undefined"
            >
              <option v-for="language in languages" :key="language[0]" :value="language[0]">{{ language[1] }}</option>
            </select>
            <span v-if="fieldError(editErrors, 'language')" id="edit-language-error" class="field-error">
              {{ fieldError(editErrors, 'language') }}
            </span>
          </label>
          <label class="field">
            <span>Conditie</span>
            <select
              v-model="editForm.condition"
              required
              :aria-invalid="Boolean(fieldError(editErrors, 'condition'))"
              :aria-describedby="fieldError(editErrors, 'condition') ? 'edit-condition-error' : undefined"
            >
              <option v-for="condition in conditions" :key="condition[0]" :value="condition[0]">{{ condition[1] }}</option>
            </select>
            <span v-if="fieldError(editErrors, 'condition')" id="edit-condition-error" class="field-error">
              {{ fieldError(editErrors, 'condition') }}
            </span>
          </label>
          <BaseInput id="edit-purchase-price" v-model="editForm.purchasePrice" label="Aankoopprijs (centen)" type="number" min="0" :error="fieldError(editErrors, 'purchasePrice') ?? fieldError(editErrors, 'purchaseCurrency')" />
          <BaseInput id="edit-purchased-at" v-model="editForm.purchasedAt" label="Aankoopdatum" type="date" :error="fieldError(editErrors, 'purchasedAt')" />
          <BaseInput id="edit-seller-name" v-model="editForm.sellerName" label="Verkoper of winkel" :error="fieldError(editErrors, 'sellerName')" />
          <label class="field">
            <span>Fysieke opslaglocatie</span>
            <select v-model="editForm.locationId">
              <option value="">Niet geplaatst</option>
              <option v-if="currentBinderPlacementLabel" :value="originalLocationId">
                {{ currentBinderPlacementLabel }}
              </option>
              <option v-for="location in storageLocations" :key="location.id" :value="location.id">
                {{ location.path.map((entry) => entry.name).join(' › ') }}
              </option>
            </select>
            <RouterLink v-if="storageLocations.length === 0" :to="{ name: 'storage' }">
              Maak eerst een opslaglocatie
            </RouterLink>
          </label>
          <label class="field">
            <span>Notities</span>
            <textarea
              v-model="editForm.notes"
              rows="4"
              maxlength="5000"
              :aria-invalid="Boolean(fieldError(editErrors, 'notes'))"
              :aria-describedby="fieldError(editErrors, 'notes') ? 'edit-notes-error' : undefined"
            />
            <span v-if="fieldError(editErrors, 'notes')" id="edit-notes-error" class="field-error">
              {{ fieldError(editErrors, 'notes') }}
            </span>
          </label>
          <div class="dialog-actions">
            <BaseButton
              v-if="editingItem.status === 'in_collection'"
              variant="danger"
              :disabled="archivingId === editingItem.id || editSaving"
              @click="archiveItem(editingItem)"
            >
              {{ archivingId === editingItem.id ? 'Archiveren…' : 'Archiveren' }}
            </BaseButton>
            <BaseButton variant="secondary" @click="closeEdit">Annuleren</BaseButton>
            <BaseButton type="submit" :disabled="editSaving">{{ editSaving ? 'Opslaan…' : 'Opslaan' }}</BaseButton>
          </div>
        </form>
      </section>
    </div>
  </main>
</template>
