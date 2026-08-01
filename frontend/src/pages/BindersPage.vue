<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { PaginatedResponse } from '../types/api'
import type { BinderCollectionResponse, BinderDetail, BinderSlot } from '../types/binder'
import type { CatalogCard } from '../types/catalog'
import type { CollectionItem } from '../types/collection'
import type { StorageLocation, StorageLocationCollectionResponse } from '../types/storage'

const strategies = [
  ['set_number', 'Setnummer'],
  ['pokedex_number', 'Pokédex-nummer'],
  ['alphabetical', 'Alfabetisch'],
  ['collectible_type', 'Kaarttype'],
  ['rarity', 'Zeldzaamheid'],
  ['value', 'Waarde'],
  ['purchase_date', 'Aankoopdatum'],
  ['custom', 'Eigen volgorde'],
] as const

const binders = ref<BinderCollectionResponse['items']>([])
const activeBinder = ref<BinderDetail | null>(null)
const activePageIndex = ref(0)
const collectionItems = ref<CollectionItem[]>([])
const storageLocations = ref<StorageLocation[]>([])
const selectedItemId = ref('')
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')
const createForm = reactive({
  name: '',
  rows: '3',
  columns: '3',
  pageCount: '10',
  doubleSided: false,
  layoutStrategy: 'custom',
  parentLocationId: '',
})

const configuringSlot = ref<BinderSlot | null>(null)
const placeholderLabel = ref('')
const placeholderQuery = ref('')
const placeholderMatches = ref<CatalogCard[]>([])
const searchingPlaceholder = ref(false)

const activePage = computed(() => activeBinder.value?.pages[activePageIndex.value] ?? null)

function caughtMessage(caught: unknown, fallback: string): string {
  return caught instanceof ApiError ? caught.message : fallback
}

function readableCode(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function slotLabel(slot: BinderSlot): string {
  const prefix = `Vak ${slot.position}`
  if (slot.collectionItem) {
    return `${prefix}, gevuld met ${slot.collectionItem.card.name} ${slot.collectionItem.card.number}`
  }
  if (slot.placeholder?.catalogCard) {
    return `${prefix}, gereserveerd voor ${slot.placeholder.catalogCard.name} ${slot.placeholder.catalogCard.number}`
  }
  if (slot.placeholder?.label) {
    return `${prefix}, placeholder ${slot.placeholder.label}`
  }

  return `${prefix}, leeg`
}

function syncBinderSummary(binder: BinderDetail): void {
  const index = binders.value.findIndex((candidate) => candidate.id === binder.id)
  if (index >= 0) binders.value[index] = binder
}

async function loadOverview(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const [binderResponse, collectionResponse, storageResponse] = await Promise.all([
      apiRequest<BinderCollectionResponse>('/me/binders'),
      apiRequest<PaginatedResponse<CollectionItem>>('/me/collection-items?status=in_collection&perPage=100'),
      apiRequest<StorageLocationCollectionResponse>('/me/storage-locations'),
    ])
    binders.value = binderResponse.items
    collectionItems.value = collectionResponse.items
    storageLocations.value = storageResponse.items
    if (!activeBinder.value && binders.value[0]) await selectBinder(binders.value[0].id)
  } catch (caught) {
    error.value = caughtMessage(caught, 'De binders konden niet worden geladen.')
  } finally {
    loading.value = false
  }
}

async function selectBinder(id: string): Promise<void> {
  error.value = ''
  try {
    activeBinder.value = await apiRequest<BinderDetail>(`/me/binders/${id}`)
    activePageIndex.value = 0
    selectedItemId.value = ''
  } catch (caught) {
    error.value = caughtMessage(caught, 'De binder kon niet worden geopend.')
  }
}

async function createBinder(): Promise<void> {
  error.value = ''
  success.value = ''
  const rows = Number(createForm.rows)
  const columns = Number(createForm.columns)
  const pageCount = Number(createForm.pageCount)
  if (![rows, columns, pageCount].every(Number.isInteger)) {
    error.value = 'Rijen, kolommen en pagina’s moeten gehele getallen zijn.'
    return
  }

  const payload: Record<string, boolean | number | string> = {
    name: createForm.name,
    rows,
    columns,
    pageCount,
    doubleSided: createForm.doubleSided,
    layoutStrategy: createForm.layoutStrategy,
  }
  if (createForm.parentLocationId) payload.parentLocationId = createForm.parentLocationId

  saving.value = true
  try {
    const binder = await apiRequest<BinderDetail>('/me/binders', {
      method: 'POST',
      body: JSON.stringify(payload),
    })
    activeBinder.value = binder
    activePageIndex.value = 0
    createForm.name = ''
    success.value = 'De binder en alle vakken zijn aangemaakt.'
    const response = await apiRequest<BinderCollectionResponse>('/me/binders')
    binders.value = response.items
  } catch (caught) {
    error.value = caughtMessage(caught, 'De binder kon niet worden aangemaakt.')
  } finally {
    saving.value = false
  }
}

function selectCollectionItem(itemId: string): void {
  selectedItemId.value = selectedItemId.value === itemId ? '' : itemId
}

function startDrag(event: DragEvent, itemId: string): void {
  event.dataTransfer?.setData('text/cardnest-collection-item', itemId)
  event.dataTransfer?.setData('text/plain', itemId)
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

async function dropOnSlot(event: DragEvent, slot: BinderSlot): Promise<void> {
  const itemId = event.dataTransfer?.getData('text/cardnest-collection-item')
    || event.dataTransfer?.getData('text/plain')
    || ''
  if (itemId) await assignToSlot(slot, itemId)
}

function requestSlotDrop(event: DragEvent, slot: BinderSlot): void {
  if (saving.value) return

  void dropOnSlot(event, slot)
}

async function activateSlot(slot: BinderSlot): Promise<void> {
  if (saving.value) return

  if (selectedItemId.value) {
    await assignToSlot(slot, selectedItemId.value)
    return
  }

  configuringSlot.value = slot
  placeholderLabel.value = slot.placeholder?.label ?? ''
  placeholderQuery.value = ''
  placeholderMatches.value = []
}

function requestSlotActivation(slot: BinderSlot): void {
  if (saving.value) return

  void activateSlot(slot)
}

async function assignToSlot(slot: BinderSlot, itemId: string): Promise<void> {
  const binder = activeBinder.value
  if (!binder || saving.value) return

  error.value = ''
  success.value = ''
  saving.value = true
  try {
    const updated = await apiRequest<BinderDetail>(`/me/binders/${binder.id}/slots/${slot.id}/assign`, {
      method: 'POST',
      body: JSON.stringify({ collectionItemId: itemId }),
    })
    activeBinder.value = updated
    syncBinderSummary(updated)
    selectedItemId.value = ''
    success.value = 'De kaart is in het bindervak geplaatst.'
  } catch (caught) {
    error.value = caughtMessage(caught, 'De kaart kon niet in dit vak worden geplaatst.')
  } finally {
    saving.value = false
  }
}

async function removeAssignment(slot: BinderSlot): Promise<void> {
  const binder = activeBinder.value
  if (!binder || saving.value) return

  error.value = ''
  saving.value = true
  try {
    const updated = await apiRequest<BinderDetail>(
      `/me/binders/${binder.id}/slots/${slot.id}/assignment`,
      { method: 'DELETE' },
    )
    activeBinder.value = updated
    syncBinderSummary(updated)
    success.value = 'De kaart is uit het bindervak gehaald.'
  } catch (caught) {
    error.value = caughtMessage(caught, 'Het bindervak kon niet worden leeggemaakt.')
  } finally {
    saving.value = false
  }
}

async function searchPlaceholderCard(): Promise<void> {
  placeholderMatches.value = []
  if (placeholderQuery.value.trim().length < 2) {
    error.value = 'Vul minimaal twee tekens van de kaartnaam in.'
    return
  }

  searchingPlaceholder.value = true
  error.value = ''
  try {
    const query = new URLSearchParams({ name: placeholderQuery.value.trim(), perPage: '6' })
    const response = await apiRequest<PaginatedResponse<CatalogCard>>(`/cards?${query}`)
    placeholderMatches.value = response.items
  } catch (caught) {
    error.value = caughtMessage(caught, 'De catalogus kon niet worden doorzocht.')
  } finally {
    searchingPlaceholder.value = false
  }
}

async function configurePlaceholder(payload: Record<string, boolean | string>): Promise<void> {
  const binder = activeBinder.value
  const slot = configuringSlot.value
  if (!binder || !slot) return

  error.value = ''
  saving.value = true
  try {
    const updated = await apiRequest<BinderDetail>(
      `/me/binders/${binder.id}/slots/${slot.id}/placeholder`,
      { method: 'PATCH', body: JSON.stringify(payload) },
    )
    activeBinder.value = updated
    syncBinderSummary(updated)
    configuringSlot.value = null
    success.value = 'De placeholder is bijgewerkt.'
  } catch (caught) {
    error.value = caughtMessage(caught, 'De placeholder kon niet worden bijgewerkt.')
  } finally {
    saving.value = false
  }
}

onMounted(() => { void loadOverview() })
</script>

<template>
  <main class="page-container binders-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">Visuele planning</p>
        <h1>Binders</h1>
        <p>Elke pagina en elk vak komt uit de backend. Sleep een fysieke kaart of selecteer haar voor mobiel gebruik.</p>
      </div>
    </header>

    <ErrorMessage v-if="error" :message="error" />
    <p v-if="success" class="success-message" role="status">{{ success }}</p>

    <section class="binder-workspace">
      <aside class="binder-sidebar">
        <form class="panel binder-create-form" @submit.prevent="createBinder">
          <p class="eyebrow">Nieuwe binder</p>
          <h2>Binder aanmaken</h2>
          <BaseInput id="binder-name" v-model="createForm.name" label="Naam" required />
          <div class="compact-fields">
            <BaseInput id="binder-rows" v-model="createForm.rows" label="Rijen" type="number" min="1" max="6" required />
            <BaseInput id="binder-columns" v-model="createForm.columns" label="Kolommen" type="number" min="1" max="6" required />
          </div>
          <BaseInput id="binder-pages" v-model="createForm.pageCount" label="Fysieke pagina’s" type="number" min="1" max="100" required />
          <label class="checkbox-field">
            <input v-model="createForm.doubleSided" type="checkbox">
            <span>Dubbelzijdig</span>
          </label>
          <label class="field">
            <span>Indelingsstrategie</span>
            <select v-model="createForm.layoutStrategy">
              <option v-for="strategy in strategies" :key="strategy[0]" :value="strategy[0]">{{ strategy[1] }}</option>
            </select>
          </label>
          <label class="field">
            <span>Fysieke bovenliggende locatie</span>
            <select v-model="createForm.parentLocationId">
              <option value="">Niet ingesteld</option>
              <option v-for="location in storageLocations" :key="location.id" :value="location.id">
                {{ location.path.map((entry) => entry.name).join(' › ') }}
              </option>
            </select>
          </label>
          <BaseButton type="submit" :disabled="saving">{{ saving ? 'Aanmaken…' : 'Binder aanmaken' }}</BaseButton>
        </form>

        <section class="panel binder-list" aria-labelledby="binder-list-heading">
          <p class="eyebrow">{{ binders.length }} binders</p>
          <h2 id="binder-list-heading">Mijn binders</h2>
          <p v-if="loading" role="status">Binders laden…</p>
          <button
            v-for="binder in binders"
            :key="binder.id"
            type="button"
            :class="{ active: activeBinder?.id === binder.id }"
            @click="selectBinder(binder.id)"
          >
            <strong>{{ binder.name }}</strong>
            <span>{{ binder.rows }}×{{ binder.columns }} · {{ binder.occupiedSlots }}/{{ binder.slotCount }} gevuld</span>
          </button>
        </section>
      </aside>

      <section v-if="activeBinder" class="binder-main">
        <header class="binder-heading">
          <div>
            <p class="eyebrow">{{ readableCode(activeBinder.layoutStrategy) }}</p>
            <h2>{{ activeBinder.name }}</h2>
            <p>{{ activeBinder.occupiedSlots }} van {{ activeBinder.slotCount }} vakken gevuld</p>
          </div>
          <div class="page-navigation">
            <BaseButton variant="secondary" :disabled="activePageIndex === 0" @click="activePageIndex--">Vorige</BaseButton>
            <span v-if="activePage">Pagina {{ activePage.number }} · {{ activePage.side === 'front' ? 'voorzijde' : 'achterzijde' }}</span>
            <BaseButton variant="secondary" :disabled="activePageIndex >= activeBinder.pages.length - 1" @click="activePageIndex++">Volgende</BaseButton>
          </div>
        </header>

        <div v-if="activePage" class="binder-page" :style="{ '--binder-columns': activeBinder.columns }">
          <div
            v-for="slot in activePage.slots"
            :key="slot.id"
            class="binder-slot"
            :class="{ 'binder-slot--selected-target': selectedItemId && !slot.collectionItem }"
            @dragover.prevent
            @drop.prevent="requestSlotDrop($event, slot)"
          >
            <button
              type="button"
              class="binder-slot__action"
              :aria-label="slotLabel(slot)"
              :disabled="saving"
              @click="requestSlotActivation(slot)"
            >
              <span class="slot-number">{{ slot.position }}</span>
              <template v-if="slot.collectionItem">
                <span
                  class="slot-card"
                  :draggable="!saving"
                  @dragstart.stop="startDrag($event, slot.collectionItem.id)"
                >
                  <img v-if="slot.collectionItem.card.thumbnailUrl" :src="slot.collectionItem.card.thumbnailUrl" alt="">
                  <strong>{{ slot.collectionItem.card.name }}</strong>
                  <small>{{ slot.collectionItem.card.number }} · {{ readableCode(slot.collectionItem.variant) }}</small>
                </span>
              </template>
              <template v-else-if="slot.placeholder">
                <span class="slot-placeholder">
                  <strong>{{ slot.placeholder.catalogCard?.name ?? slot.placeholder.label }}</strong>
                  <small v-if="slot.placeholder.catalogCard">{{ slot.placeholder.catalogCard.number }} · ontbreekt</small>
                  <small v-else>Placeholder</small>
                </span>
              </template>
              <span v-else class="slot-empty">Leeg vak</span>
            </button>
            <button
              v-if="slot.collectionItem"
              type="button"
              class="slot-remove"
              :disabled="saving"
              @click="removeAssignment(slot)"
            >
              Uit vak
            </button>
          </div>
        </div>

        <section class="collection-palette" aria-labelledby="palette-heading">
          <div>
            <p class="eyebrow">Fysieke kaarten</p>
            <h3 id="palette-heading">Sleep of selecteer een kaart</h3>
          </div>
          <div class="palette-items">
            <button
              v-for="item in collectionItems"
              :key="item.id"
              type="button"
              :draggable="!saving"
              :disabled="saving"
              :aria-pressed="selectedItemId === item.id"
              @dragstart="startDrag($event, item.id)"
              @click="selectCollectionItem(item.id)"
            >
              <strong>{{ item.card.name }} · {{ item.card.number }}</strong>
              <span>{{ readableCode(item.variant.code) }} · {{ readableCode(item.condition) }}</span>
            </button>
          </div>
        </section>
      </section>

      <p v-else-if="!loading" class="empty-state binder-empty">Maak een binder aan om de visuele planner te openen.</p>
    </section>

    <div v-if="configuringSlot" class="modal-backdrop" @click.self="configuringSlot = null">
      <section class="panel edit-panel" role="dialog" aria-modal="true" aria-labelledby="placeholder-heading">
        <p class="eyebrow">Vak {{ configuringSlot.position }}</p>
        <h2 id="placeholder-heading">Placeholder instellen</h2>
        <form @submit.prevent="configurePlaceholder({ label: placeholderLabel })">
          <BaseInput id="placeholder-label" v-model="placeholderLabel" label="Handmatig label" required />
          <BaseButton type="submit" :disabled="saving">Label opslaan</BaseButton>
        </form>
        <div class="placeholder-divider"><span>of reserveer een cataloguskaart</span></div>
        <form class="catalog-search" @submit.prevent="searchPlaceholderCard">
          <BaseInput id="placeholder-card-query" v-model="placeholderQuery" label="Kaartnaam" type="search" />
          <BaseButton type="submit" :disabled="searchingPlaceholder">Zoeken</BaseButton>
        </form>
        <div class="catalog-results">
          <button
            v-for="card in placeholderMatches"
            :key="card.id"
            type="button"
            @click="configurePlaceholder({ catalogCardId: card.id })"
          >
            <strong>{{ card.name }} · {{ card.number }}</strong>
            <span>{{ card.set.name }}</span>
          </button>
        </div>
        <div class="dialog-actions">
          <BaseButton v-if="configuringSlot.placeholder" variant="danger" @click="configurePlaceholder({ clear: true })">Placeholder wissen</BaseButton>
          <BaseButton variant="secondary" @click="configuringSlot = null">Sluiten</BaseButton>
        </div>
      </section>
    </div>
  </main>
</template>
