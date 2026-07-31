<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { PaginatedResponse } from '../types/api'
import type { CatalogCard } from '../types/catalog'
import type { WishlistCollectionResponse, WishlistDetail, WishlistItem } from '../types/wishlist'
import { formatMoney, parseOptionalMinorUnits } from '../utils/money'

const languages = [
  ['en', 'Engels'], ['nl', 'Nederlands'], ['ja', 'Japans'], ['ko', 'Koreaans'], ['de', 'Duits'],
  ['fr', 'Frans'], ['it', 'Italiaans'], ['es', 'Spaans'], ['pt', 'Portugees'], ['zh', 'Chinees'],
] as const
const conditions = [
  ['mint', 'Mint'], ['near_mint', 'Near Mint'], ['excellent', 'Excellent'], ['good', 'Good'],
  ['light_played', 'Light Played'], ['played', 'Played'], ['poor', 'Poor'],
] as const
const priorities = [
  ['low', 'Laag'], ['normal', 'Normaal'], ['high', 'Hoog'], ['wanted', 'Gezocht'],
] as const

const wishlists = ref<WishlistCollectionResponse['items']>([])
const activeWishlist = ref<WishlistDetail | null>(null)
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')
const createName = ref('')
const editName = ref('')
const showCompleted = ref(true)

const catalogQuery = ref('')
const catalogMatches = ref<CatalogCard[]>([])
const selectedCard = ref<CatalogCard | null>(null)
const searching = ref(false)
const itemForm = reactive({
  language: '',
  variantId: '',
  minimumCondition: '',
  maxPrice: '',
  desiredQuantity: '1',
  priority: 'normal',
  notes: '',
})

const visibleItems = computed<WishlistItem[]>(() => {
  const items = activeWishlist.value?.items ?? []
  return showCompleted.value ? items : items.filter((item) => !item.completed)
})

function caughtMessage(caught: unknown, fallback: string): string {
  return caught instanceof ApiError ? caught.message : fallback
}

function readableCode(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function syncSummary(wishlist: WishlistDetail): void {
  const index = wishlists.value.findIndex((candidate) => candidate.id === wishlist.id)
  if (index >= 0) wishlists.value[index] = wishlist
}

async function loadWishlists(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const response = await apiRequest<WishlistCollectionResponse>('/me/wishlists')
    wishlists.value = response.items
    if (!activeWishlist.value && response.items[0]) await selectWishlist(response.items[0].id)
  } catch (caught) {
    error.value = caughtMessage(caught, 'De wishlists konden niet worden geladen.')
  } finally {
    loading.value = false
  }
}

async function selectWishlist(id: string): Promise<void> {
  error.value = ''
  try {
    activeWishlist.value = await apiRequest<WishlistDetail>(`/me/wishlists/${id}`)
    editName.value = activeWishlist.value.name
    resetItemForm()
  } catch (caught) {
    error.value = caughtMessage(caught, 'De wishlist kon niet worden geopend.')
  }
}

async function createWishlist(): Promise<void> {
  error.value = ''
  success.value = ''
  saving.value = true
  try {
    const wishlist = await apiRequest<WishlistDetail>('/me/wishlists', {
      method: 'POST',
      body: JSON.stringify({ name: createName.value }),
    })
    activeWishlist.value = wishlist
    editName.value = wishlist.name
    createName.value = ''
    success.value = 'De wishlist is aangemaakt.'
    await loadWishlists()
  } catch (caught) {
    error.value = caughtMessage(caught, 'De wishlist kon niet worden aangemaakt.')
  } finally {
    saving.value = false
  }
}

async function renameWishlist(): Promise<void> {
  const wishlist = activeWishlist.value
  if (!wishlist) return

  error.value = ''
  saving.value = true
  try {
    const updated = await apiRequest<WishlistDetail>(`/me/wishlists/${wishlist.id}`, {
      method: 'PATCH',
      body: JSON.stringify({ name: editName.value }),
    })
    activeWishlist.value = updated
    syncSummary(updated)
    success.value = 'De wishlistnaam is bijgewerkt.'
  } catch (caught) {
    error.value = caughtMessage(caught, 'De wishlist kon niet worden bijgewerkt.')
  } finally {
    saving.value = false
  }
}

async function deleteWishlist(): Promise<void> {
  const wishlist = activeWishlist.value
  if (!wishlist || !window.confirm(`Wishlist “${wishlist.name}” verwijderen?`)) return

  error.value = ''
  saving.value = true
  try {
    await apiRequest<void>(`/me/wishlists/${wishlist.id}`, { method: 'DELETE' })
    wishlists.value = wishlists.value.filter((candidate) => candidate.id !== wishlist.id)
    activeWishlist.value = null
    if (wishlists.value[0]) await selectWishlist(wishlists.value[0].id)
    success.value = 'De wishlist is verwijderd.'
  } catch (caught) {
    error.value = caughtMessage(caught, 'De wishlist kon niet worden verwijderd.')
  } finally {
    saving.value = false
  }
}

async function searchCatalog(): Promise<void> {
  catalogMatches.value = []
  selectedCard.value = null
  if (catalogQuery.value.trim().length < 2) {
    error.value = 'Vul minimaal twee tekens van de kaartnaam in.'
    return
  }

  searching.value = true
  error.value = ''
  try {
    const query = new URLSearchParams({ name: catalogQuery.value.trim(), perPage: '8' })
    const response = await apiRequest<PaginatedResponse<CatalogCard>>(`/cards?${query}`)
    catalogMatches.value = response.items
    if (response.items.length === 0) error.value = 'Geen cataloguskaarten gevonden.'
  } catch (caught) {
    error.value = caughtMessage(caught, 'De catalogus kon niet worden doorzocht.')
  } finally {
    searching.value = false
  }
}

function selectCard(card: CatalogCard): void {
  selectedCard.value = card
  itemForm.variantId = ''
  catalogMatches.value = []
}

function resetItemForm(): void {
  catalogQuery.value = ''
  catalogMatches.value = []
  selectedCard.value = null
  itemForm.language = ''
  itemForm.variantId = ''
  itemForm.minimumCondition = ''
  itemForm.maxPrice = ''
  itemForm.desiredQuantity = '1'
  itemForm.priority = 'normal'
  itemForm.notes = ''
}

async function addWishlistItem(): Promise<void> {
  const wishlist = activeWishlist.value
  if (!wishlist || !selectedCard.value) {
    error.value = 'Selecteer eerst een cataloguskaart.'
    return
  }

  const desiredQuantity = Number(itemForm.desiredQuantity)
  if (!Number.isInteger(desiredQuantity) || desiredQuantity < 1 || desiredQuantity > 100) {
    error.value = 'Het gewenste aantal moet een heel getal tussen 1 en 100 zijn.'
    return
  }

  let maxPrice: number | null
  try {
    maxPrice = parseOptionalMinorUnits(itemForm.maxPrice)
  } catch (caught) {
    error.value = caughtMessage(caught, 'Vul een geldige maximumprijs in centen in.')
    return
  }

  const payload: Record<string, number | string> = {
    catalogCardId: selectedCard.value.id,
    desiredQuantity,
    priority: itemForm.priority,
  }
  if (itemForm.language) payload.language = itemForm.language
  if (itemForm.variantId) payload.variantId = itemForm.variantId
  if (itemForm.minimumCondition) payload.minimumCondition = itemForm.minimumCondition
  if (maxPrice !== null) {
    payload.maxPrice = maxPrice
    payload.maxCurrency = 'EUR'
  }
  if (itemForm.notes.trim()) payload.notes = itemForm.notes.trim()

  error.value = ''
  saving.value = true
  try {
    const updated = await apiRequest<WishlistDetail>(`/me/wishlists/${wishlist.id}/items`, {
      method: 'POST',
      body: JSON.stringify(payload),
    })
    activeWishlist.value = updated
    syncSummary(updated)
    resetItemForm()
    success.value = 'De kaart is aan de wishlist toegevoegd.'
  } catch (caught) {
    error.value = caughtMessage(caught, 'De kaart kon niet worden toegevoegd.')
  } finally {
    saving.value = false
  }
}

async function deleteItem(item: WishlistItem): Promise<void> {
  const wishlist = activeWishlist.value
  if (!wishlist) return

  error.value = ''
  saving.value = true
  try {
    const updated = await apiRequest<WishlistDetail>(`/me/wishlists/${wishlist.id}/items/${item.id}`, {
      method: 'DELETE',
    })
    activeWishlist.value = updated
    syncSummary(updated)
    success.value = 'Het wishlist-item is verwijderd.'
  } catch (caught) {
    error.value = caughtMessage(caught, 'Het wishlist-item kon niet worden verwijderd.')
  } finally {
    saving.value = false
  }
}

onMounted(() => { void loadWishlists() })
</script>

<template>
  <main class="page-container wishlists-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">Gericht verzamelen</p>
        <h1>Wishlists</h1>
        <p>Leg per kaart je criteria vast. Nieuwe fysieke kaarten werken passende doelen automatisch bij.</p>
      </div>
    </header>

    <ErrorMessage v-if="error" :message="error" />
    <p v-if="success" class="success-message" role="status">{{ success }}</p>

    <section class="wishlist-workspace">
      <aside class="wishlist-sidebar">
        <form class="panel" @submit.prevent="createWishlist">
          <p class="eyebrow">Nieuwe lijst</p>
          <h2>Wishlist maken</h2>
          <BaseInput id="wishlist-name" v-model="createName" label="Naam" required />
          <BaseButton type="submit" :disabled="saving">Wishlist maken</BaseButton>
        </form>

        <section class="panel wishlist-list" aria-labelledby="wishlist-list-heading">
          <p class="eyebrow">{{ wishlists.length }} lijsten</p>
          <h2 id="wishlist-list-heading">Mijn wishlists</h2>
          <p v-if="loading" role="status">Wishlists laden…</p>
          <button
            v-for="wishlist in wishlists"
            :key="wishlist.id"
            type="button"
            :class="{ active: activeWishlist?.id === wishlist.id }"
            @click="selectWishlist(wishlist.id)"
          >
            <strong>{{ wishlist.name }}</strong>
            <span>{{ wishlist.progress.fulfilledQuantity }}/{{ wishlist.progress.desiredQuantity }} gevonden</span>
            <progress :value="wishlist.progress.fulfilledQuantity" :max="wishlist.progress.desiredQuantity || 1">
              {{ wishlist.progress.percentage }}%
            </progress>
          </button>
        </section>
      </aside>

      <section v-if="activeWishlist" class="wishlist-main">
        <section class="panel wishlist-overview">
          <div class="wishlist-title-row">
            <div>
              <p class="eyebrow">{{ activeWishlist.progress.percentage }}% compleet</p>
              <h2>{{ activeWishlist.name }}</h2>
              <p>{{ activeWishlist.progress.remainingQuantity }} van {{ activeWishlist.progress.desiredQuantity }} kaarten ontbreken nog.</p>
            </div>
            <progress :value="activeWishlist.progress.fulfilledQuantity" :max="activeWishlist.progress.desiredQuantity || 1">
              {{ activeWishlist.progress.percentage }}%
            </progress>
          </div>
          <form class="wishlist-settings" @submit.prevent="renameWishlist">
            <BaseInput id="edit-wishlist-name" v-model="editName" label="Naam aanpassen" required />
            <BaseButton type="submit" variant="secondary" :disabled="saving">Naam opslaan</BaseButton>
            <BaseButton variant="danger" :disabled="saving" @click="deleteWishlist">Wishlist verwijderen</BaseButton>
          </form>
          <div v-if="activeWishlist.setProgress.length" class="set-progress-list">
            <article v-for="set in activeWishlist.setProgress" :key="set.id">
              <strong>{{ set.name }}</strong>
              <span>{{ set.progress.fulfilledQuantity }}/{{ set.progress.desiredQuantity }}</span>
              <progress :value="set.progress.fulfilledQuantity" :max="set.progress.desiredQuantity">
                {{ set.progress.percentage }}%
              </progress>
            </article>
          </div>
        </section>

        <section class="panel wishlist-add-card">
          <p class="eyebrow">Ontbrekende kaart</p>
          <h2>Kaart toevoegen</h2>
          <form class="catalog-search" @submit.prevent="searchCatalog">
            <BaseInput id="wishlist-card-search" v-model="catalogQuery" label="Zoek op kaartnaam" type="search" />
            <BaseButton type="submit" :disabled="searching">{{ searching ? 'Zoeken…' : 'Zoeken' }}</BaseButton>
          </form>
          <div v-if="catalogMatches.length" class="catalog-results">
            <button v-for="card in catalogMatches" :key="card.id" type="button" @click="selectCard(card)">
              <strong>{{ card.name }} · {{ card.number }}</strong>
              <span>{{ card.set.name }}</span>
            </button>
          </div>

          <form v-if="selectedCard" class="wishlist-item-form" @submit.prevent="addWishlistItem">
            <div class="selected-card-summary">
              <strong>{{ selectedCard.name }} · {{ selectedCard.number }}</strong>
              <span>{{ selectedCard.set.name }}</span>
            </div>
            <label class="field">
              <span>Gewenste taal</span>
              <select v-model="itemForm.language">
                <option value="">Iedere taal</option>
                <option v-for="language in languages" :key="language[0]" :value="language[0]">{{ language[1] }}</option>
              </select>
            </label>
            <label class="field">
              <span>Gewenste variant</span>
              <select v-model="itemForm.variantId">
                <option value="">Iedere variant</option>
                <option v-for="variant in selectedCard.variants" :key="variant.id" :value="variant.id">
                  {{ readableCode(variant.code) }}
                </option>
              </select>
            </label>
            <label class="field">
              <span>Minimale conditie</span>
              <select v-model="itemForm.minimumCondition">
                <option value="">Iedere conditie</option>
                <option v-for="condition in conditions" :key="condition[0]" :value="condition[0]">{{ condition[1] }}</option>
              </select>
            </label>
            <BaseInput id="wishlist-max-price" v-model="itemForm.maxPrice" label="Maximumprijs (centen)" type="number" min="0" />
            <BaseInput id="wishlist-quantity" v-model="itemForm.desiredQuantity" label="Gewenst aantal" type="number" min="1" max="100" required />
            <label class="field">
              <span>Prioriteit</span>
              <select v-model="itemForm.priority">
                <option v-for="priority in priorities" :key="priority[0]" :value="priority[0]">{{ priority[1] }}</option>
              </select>
            </label>
            <label class="field wishlist-notes">
              <span>Opmerking</span>
              <textarea v-model="itemForm.notes" rows="3" maxlength="2000" />
            </label>
            <BaseButton type="submit" :disabled="saving">Kaart toevoegen</BaseButton>
          </form>
        </section>

        <section class="wishlist-items" aria-labelledby="wishlist-items-heading">
          <header>
            <div>
              <p class="eyebrow">{{ activeWishlist.itemCount }} doelen</p>
              <h2 id="wishlist-items-heading">Ontbrekende kaarten</h2>
            </div>
            <label class="checkbox-field">
              <input v-model="showCompleted" type="checkbox">
              <span>Voltooide tonen</span>
            </label>
          </header>
          <p v-if="visibleItems.length === 0" class="empty-state">Voeg je eerste ontbrekende kaart toe.</p>
          <article
            v-for="item in visibleItems"
            :key="item.id"
            class="wishlist-card"
            :class="{ 'wishlist-card--completed': item.completed }"
          >
            <img v-if="item.card.thumbnailUrl" :src="item.card.thumbnailUrl" alt="">
            <div class="wishlist-card__body">
              <div class="wishlist-card__heading">
                <div>
                  <span class="priority-badge" :data-priority="item.priority">{{ readableCode(item.priority) }}</span>
                  <h3>{{ item.card.name }} · {{ item.card.number }}</h3>
                  <p>{{ item.card.set.name }}</p>
                </div>
                <strong>{{ item.remainingQuantity }} ontbreekt</strong>
              </div>
              <dl>
                <div><dt>Taal</dt><dd>{{ item.language ? readableCode(item.language) : 'Iedere taal' }}</dd></div>
                <div><dt>Variant</dt><dd>{{ item.variant ? readableCode(item.variant.code) : 'Iedere variant' }}</dd></div>
                <div><dt>Min. conditie</dt><dd>{{ item.minimumCondition ? readableCode(item.minimumCondition) : 'Geen minimum' }}</dd></div>
                <div><dt>Maximum</dt><dd>{{ item.maxPrice ? formatMoney(item.maxPrice.amount, item.maxPrice.currency) : 'Geen maximum' }}</dd></div>
              </dl>
              <p v-if="item.notes">{{ item.notes }}</p>
              <progress :value="item.fulfilledQuantity" :max="item.desiredQuantity">
                {{ item.fulfilledQuantity }}/{{ item.desiredQuantity }}
              </progress>
              <BaseButton variant="danger" :disabled="saving" @click="deleteItem(item)">Verwijderen</BaseButton>
            </div>
          </article>
        </section>
      </section>

      <p v-else-if="!loading" class="empty-state">Maak een wishlist aan om ontbrekende kaarten vast te leggen.</p>
    </section>
  </main>
</template>
