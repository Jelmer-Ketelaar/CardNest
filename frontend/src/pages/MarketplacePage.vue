<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import { useAuthStore } from '../stores/auth'
import type { PaginatedResponse } from '../types/api'
import type { Listing, ListingImage } from '../types/marketplace'
import type { Cart } from '../types/order'
import { formatMoney, parseOptionalMinorUnits } from '../utils/money'

const languages = [
  ['en', 'Engels'], ['nl', 'Nederlands'], ['ja', 'Japans'], ['ko', 'Koreaans'], ['de', 'Duits'],
  ['fr', 'Frans'], ['it', 'Italiaans'], ['es', 'Spaans'], ['pt', 'Portugees'], ['zh', 'Chinees'],
] as const
const conditions = [
  ['mint', 'Mint'], ['near_mint', 'Near Mint'], ['excellent', 'Excellent'], ['good', 'Good'],
  ['light_played', 'Light Played'], ['played', 'Played'], ['poor', 'Poor'],
] as const

const auth = useAuthStore()
const router = useRouter()
const listings = ref<Listing[]>([])
const selectedListing = ref<Listing | null>(null)
const total = ref(0)
const totalPages = ref(0)
const page = ref(1)
const loading = ref(false)
const addingListingId = ref<string | null>(null)
const error = ref('')
const success = ref('')
const filters = reactive({ cardName: '', language: '', condition: '', maximumPrice: '', pickupAllowed: false })

function caughtMessage(caught: unknown, fallback: string): string {
  return caught instanceof ApiError ? caught.message : fallback
}

function readableCode(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function imageFor(listing: Listing, side: ListingImage['side']): string | null {
  return listing.images.find((image) => image.side === side)?.url ?? null
}

function lowestTotal(listing: Listing): number {
  return Math.min(...listing.shippingMethods.map((method) => method.totalPrice.amount))
}

async function loadListings(): Promise<void> {
  loading.value = true
  error.value = ''
  const query = new URLSearchParams({ page: String(page.value), perPage: '24' })
  if (filters.cardName.trim()) query.set('cardName', filters.cardName.trim())
  if (filters.language) query.set('language', filters.language)
  if (filters.condition) query.set('condition', filters.condition)
  if (filters.pickupAllowed) query.set('pickupAllowed', '1')
  try {
    const maximumPrice = parseOptionalMinorUnits(filters.maximumPrice)
    if (maximumPrice !== null) query.set('maximumPrice', String(maximumPrice))
  } catch (caught) {
    error.value = caughtMessage(caught, 'Vul een geldige maximumprijs in centen in.')
    loading.value = false
    return
  }

  try {
    const response = await apiRequest<PaginatedResponse<Listing>>(`/listings?${query}`)
    listings.value = response.items
    total.value = response.total
    totalPages.value = response.totalPages
  } catch (caught) {
    error.value = caughtMessage(caught, 'De marktplaats kon niet worden geladen.')
  } finally {
    loading.value = false
  }
}

async function applyFilters(): Promise<void> {
  page.value = 1
  await loadListings()
}

async function goToPage(target: number): Promise<void> {
  page.value = target
  await loadListings()
}

async function openListing(id: string): Promise<void> {
  error.value = ''
  success.value = ''
  try {
    selectedListing.value = await apiRequest<Listing>(`/listings/${id}`)
  } catch (caught) {
    error.value = caughtMessage(caught, 'De listing kon niet worden geopend.')
  }
}

async function addToCart(listing: Listing): Promise<void> {
  if (!auth.isAuthenticated) {
    await router.push({ name: 'login' })
    return
  }

  addingListingId.value = listing.id
  error.value = ''
  success.value = ''
  try {
    const cart = await apiRequest<Cart>('/me/cart/items', {
      method: 'POST',
      body: JSON.stringify({ listingId: listing.id }),
    })
    selectedListing.value = null
    success.value = `${listing.card.name} is toegevoegd. Je winkelmand bevat nu ${cart.itemCount} ${cart.itemCount === 1 ? 'kaart' : 'kaarten'}.`
  } catch (caught) {
    error.value = caughtMessage(caught, 'De aanbieding kon niet aan je winkelmand worden toegevoegd.')
  } finally {
    addingListingId.value = null
  }
}

onMounted(() => { void loadListings() })
</script>

<template>
  <main class="page-container marketplace-page">
    <header class="page-heading marketplace-heading">
      <div>
        <p class="eyebrow">Vaste prijzen · Nederland & België</p>
        <h1>Marktplaats</h1>
        <p>Vergelijk altijd de kaartprijs én verzendkosten. Iedere aanbieding hoort bij één fysieke kaart.</p>
      </div>
      <div class="page-heading__actions">
        <RouterLink v-if="auth.isAuthenticated" class="button button--secondary" :to="{ name: 'cart' }">Winkelmand</RouterLink>
        <RouterLink
          class="button button--primary"
          :to="auth.isAuthenticated ? { name: 'my-listings' } : { name: 'login' }"
        >
          {{ auth.isAuthenticated ? 'Mijn verkoop' : 'Inloggen om te verkopen' }}
        </RouterLink>
      </div>
    </header>

    <ErrorMessage v-if="error" :message="error" />
    <p v-if="success" class="success-message" role="status">{{ success }}</p>

    <form class="panel panel--wide marketplace-filters" @submit.prevent="applyFilters">
      <BaseInput id="marketplace-name" v-model="filters.cardName" label="Kaartnaam" type="search" />
      <label class="field">
        <span>Taal</span>
        <select v-model="filters.language">
          <option value="">Alle talen</option>
          <option v-for="language in languages" :key="language[0]" :value="language[0]">{{ language[1] }}</option>
        </select>
      </label>
      <label class="field">
        <span>Conditie</span>
        <select v-model="filters.condition">
          <option value="">Alle condities</option>
          <option v-for="condition in conditions" :key="condition[0]" :value="condition[0]">{{ condition[1] }}</option>
        </select>
      </label>
      <BaseInput id="marketplace-max-price" v-model="filters.maximumPrice" label="Max. kaartprijs (centen)" type="number" min="0" />
      <label class="checkbox-field marketplace-pickup">
        <input v-model="filters.pickupAllowed" type="checkbox">
        <span>Ophalen mogelijk</span>
      </label>
      <BaseButton type="submit" :disabled="loading">Zoeken</BaseButton>
    </form>

    <p class="results-summary">{{ total }} actieve aanbiedingen</p>
    <p v-if="loading" class="empty-state" role="status">Aanbiedingen laden…</p>
    <p v-else-if="listings.length === 0" class="empty-state">Geen aanbiedingen gevonden binnen deze filters.</p>

    <section v-else class="listing-grid" aria-label="Actieve aanbiedingen">
      <article v-for="listing in listings" :key="listing.id" class="listing-card">
        <img v-if="imageFor(listing, 'front')" :src="imageFor(listing, 'front') ?? ''" :alt="`${listing.card.name} voorkant`">
        <div class="listing-card__body">
          <p class="eyebrow">{{ listing.card.set.name }}</p>
          <h2>{{ listing.card.name }} <small>{{ listing.card.number }}</small></h2>
          <p>{{ readableCode(listing.variant) }} · {{ readableCode(listing.condition) }} · {{ listing.language.toUpperCase() }}</p>
          <div class="listing-price">
            <strong>{{ formatMoney(listing.price.amount, listing.price.currency) }}</strong>
            <span>vanaf {{ formatMoney(lowestTotal(listing), listing.price.currency) }} totaal</span>
          </div>
          <BaseButton @click="openListing(listing.id)">Bekijk aanbieding</BaseButton>
        </div>
      </article>
    </section>

    <nav v-if="totalPages > 1" class="pagination" aria-label="Paginering">
      <BaseButton variant="secondary" :disabled="page <= 1 || loading" @click="goToPage(page - 1)">Vorige</BaseButton>
      <span>Pagina {{ page }} van {{ totalPages }}</span>
      <BaseButton variant="secondary" :disabled="page >= totalPages || loading" @click="goToPage(page + 1)">Volgende</BaseButton>
    </nav>

    <div v-if="selectedListing" class="modal-backdrop" @click.self="selectedListing = null">
      <section class="panel listing-detail" role="dialog" aria-modal="true" aria-labelledby="listing-detail-heading">
        <div class="listing-detail__photos">
          <img v-for="image in selectedListing.images" :key="image.id" :src="image.url" :alt="`${selectedListing.card.name} ${image.side}`">
        </div>
        <p class="eyebrow">{{ selectedListing.card.set.name }}</p>
        <h2 id="listing-detail-heading">{{ selectedListing.card.name }} · {{ selectedListing.card.number }}</h2>
        <p>{{ selectedListing.description ?? 'Geen aanvullende omschrijving.' }}</p>
        <p>
          <RouterLink :to="{ name: 'user-profile', params: { id: selectedListing.seller.id } }">
            Bekijk reputatie van deze verkoper
          </RouterLink>
        </p>
        <dl class="card-facts">
          <div><dt>Taal</dt><dd>{{ selectedListing.language.toUpperCase() }}</dd></div>
          <div><dt>Variant</dt><dd>{{ readableCode(selectedListing.variant) }}</dd></div>
          <div><dt>Conditie</dt><dd>{{ readableCode(selectedListing.condition) }}</dd></div>
          <div><dt>Vraagprijs</dt><dd>{{ formatMoney(selectedListing.price.amount, selectedListing.price.currency) }}</dd></div>
        </dl>
        <h3>Volledige prijzen</h3>
        <ul class="shipping-totals">
          <li v-for="method in selectedListing.shippingMethods" :key="method.code">
            <span>{{ readableCode(method.code) }}</span>
            <span>{{ formatMoney(method.price.amount, method.price.currency) }} verzending</span>
            <strong>{{ formatMoney(method.totalPrice.amount, method.totalPrice.currency) }} totaal</strong>
          </li>
        </ul>
        <div class="dialog-actions">
          <BaseButton variant="secondary" @click="selectedListing = null">Sluiten</BaseButton>
          <BaseButton :disabled="addingListingId === selectedListing.id" @click="addToCart(selectedListing)">
            {{ addingListingId === selectedListing.id ? 'Toevoegen…' : (auth.isAuthenticated ? 'In winkelmand' : 'Inloggen om te kopen') }}
          </BaseButton>
        </div>
      </section>
    </div>
  </main>
</template>
