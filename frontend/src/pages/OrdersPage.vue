<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { ShippingMethodCode } from '../types/marketplace'
import type { Order, OrderCollection, OrderItem } from '../types/order'
import type { Review, ReviewCollection } from '../types/review'
import type { StorageLocation, StorageLocationCollectionResponse } from '../types/storage'
import { formatMoney } from '../utils/money'

const languages = [
  ['en', 'Engels'], ['nl', 'Nederlands'], ['ja', 'Japans'], ['ko', 'Koreaans'], ['de', 'Duits'],
  ['fr', 'Frans'], ['it', 'Italiaans'], ['es', 'Spaans'], ['pt', 'Portugees'], ['zh', 'Chinees'],
] as const

const conditions = [
  ['mint', 'Mint'], ['near_mint', 'Near Mint'], ['excellent', 'Excellent'], ['good', 'Good'],
  ['light_played', 'Light Played'], ['played', 'Played'], ['poor', 'Poor'],
] as const

const route = useRoute()
const orders = ref<Order[]>([])
const loading = ref(false)
const updatingOrderId = ref<string | null>(null)
const importingItemId = ref<string | null>(null)
const cancellingOrderId = ref<string | null>(null)
const importTarget = ref<{ order: Order; item: OrderItem } | null>(null)
const cancelTarget = ref<Order | null>(null)
const reviewTarget = ref<Order | null>(null)
const submittingReview = ref(false)
const reviewedOrderIds = ref<Set<string>>(new Set())
const storageLocations = ref<StorageLocation[]>([])
const storageLoaded = ref(false)
const error = ref('')
const success = ref('')
const cancellationError = ref('')
const cancellationReason = ref('')
const importForm = reactive({ language: 'en', condition: 'near_mint', storageLocationId: '', notes: '' })
const reviewForm = reactive({
  overallScore: 5,
  descriptionAccuracyScore: 5,
  packagingScore: 5,
  communicationScore: 5,
  shippingSpeedScore: 5,
  comment: '',
})
const completedCheckoutCount = computed(() => {
  const value = typeof route.query.checkedOut === 'string' ? Number.parseInt(route.query.checkedOut, 10) : 0
  return Number.isFinite(value) && value > 0 ? value : 0
})

function caughtMessage(caught: unknown, fallback: string): string {
  return caught instanceof ApiError ? caught.message : fallback
}

function readableCode(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function statusLabel(status: Order['status']): string {
  return {
    pending_payment: 'Wacht op betaling', paid: 'Betaald', preparing: 'Wordt voorbereid', shipped: 'Verzonden',
    delivered: 'Afgeleverd', completed: 'Voltooid', cancelled: 'Geannuleerd', refunded: 'Terugbetaald', disputed: 'Geschil',
  }[status]
}

function shippingLabel(code: ShippingMethodCode): string {
  return { letter_mail: 'Brievenbuspost', parcel: 'Pakketpost', pickup: 'Ophalen' }[code]
}

function paymentStatusLabel(status: Order['payment']['status']): string {
  return { pending: 'In afwachting', succeeded: 'Geslaagd', failed: 'Mislukt', refunded: 'Terugbetaald' }[status]
}

function formattedDate(value: string): string {
  return new Intl.DateTimeFormat('nl-NL', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

async function loadOrders(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const [orderResponse, reviewResponse] = await Promise.all([
      apiRequest<OrderCollection>('/me/orders'),
      apiRequest<ReviewCollection>('/me/reviews'),
    ])
    orders.value = orderResponse.items
    reviewedOrderIds.value = new Set(
      reviewResponse.items.flatMap((review) => review.orderId ? [review.orderId] : []),
    )
  } catch (caught) {
    error.value = caughtMessage(caught, 'Je orders konden niet worden geladen.')
  } finally {
    loading.value = false
  }
}

function canReview(order: Order): boolean {
  return order.status === 'completed' && !reviewedOrderIds.value.has(order.id)
}

function openCancellation(order: Order): void {
  cancelTarget.value = order
  cancellationReason.value = ''
  cancellationError.value = ''
}

function closeCancellation(): void {
  if (cancellingOrderId.value !== null) return

  cancelTarget.value = null
  cancellationError.value = ''
}

function cancellationReasonError(reason: string): string | null {
  const normalized = reason.trim()
  if (!normalized) return 'Vul een annuleringsreden in.'
  if (normalized.length < 3) return 'De annuleringsreden moet minimaal 3 tekens bevatten.'
  if (normalized.length > 500) return 'De annuleringsreden mag maximaal 500 tekens bevatten.'

  return null
}

function cancellationFailureMessage(caught: unknown): string {
  if (!(caught instanceof ApiError)) return 'De order kon niet worden geannuleerd. Controleer je verbinding en probeer opnieuw.'
  if (caught.status === 409) return 'Deze order is inmiddels verzonden of al afgehandeld. Vernieuw de pagina voor de actuele status.'
  if (caught.code === 'refund_failed') return 'De terugbetaling kon niet worden verwerkt. Er is niets aan de order gewijzigd; probeer opnieuw.'

  return caught.message
}

async function submitCancellation(): Promise<void> {
  const target = cancelTarget.value
  if (!target || cancellingOrderId.value !== null) return

  const validationError = cancellationReasonError(cancellationReason.value)
  if (validationError) {
    cancellationError.value = validationError

    return
  }

  cancellingOrderId.value = target.id
  cancellationError.value = ''
  error.value = ''
  success.value = ''
  try {
    const updated = await apiRequest<Order>(`/me/orders/${target.id}/cancel`, {
      method: 'POST',
      body: JSON.stringify({ reason: cancellationReason.value.trim() }),
    })
    orders.value = orders.value.map((current) => current.id === updated.id ? updated : current)
    success.value = updated.status === 'refunded'
      ? `Order geannuleerd en ${formatMoney(updated.costs.total.amount, updated.costs.total.currency)} terugbetaald.`
      : 'Order geannuleerd.'
    cancelTarget.value = null
  } catch (caught) {
    cancellationError.value = cancellationFailureMessage(caught)
  } finally {
    cancellingOrderId.value = null
  }
}

function openReview(order: Order): void {
  reviewTarget.value = order
  reviewForm.overallScore = 5
  reviewForm.descriptionAccuracyScore = 5
  reviewForm.packagingScore = 5
  reviewForm.communicationScore = 5
  reviewForm.shippingSpeedScore = 5
  reviewForm.comment = ''
}

function closeReview(): void {
  reviewTarget.value = null
}

async function submitReview(): Promise<void> {
  const target = reviewTarget.value
  if (!target) return

  submittingReview.value = true
  error.value = ''
  success.value = ''
  const payload = {
    overallScore: reviewForm.overallScore,
    descriptionAccuracyScore: reviewForm.descriptionAccuracyScore,
    packagingScore: reviewForm.packagingScore,
    communicationScore: reviewForm.communicationScore,
    shippingSpeedScore: reviewForm.shippingSpeedScore,
    comment: reviewForm.comment.trim() || null,
  }

  try {
    await apiRequest<Review>(`/me/orders/${target.id}/reviews`, {
      method: 'POST',
      body: JSON.stringify(payload),
    })
    reviewedOrderIds.value = new Set([...reviewedOrderIds.value, target.id])
    success.value = 'Je review is geplaatst.'
    closeReview()
  } catch (caught) {
    error.value = caughtMessage(caught, 'Je review kon niet worden geplaatst.')
  } finally {
    submittingReview.value = false
  }
}

async function updateOrder(order: Order, action: 'mark-shipped' | 'confirm-received'): Promise<void> {
  updatingOrderId.value = order.id
  error.value = ''
  success.value = ''
  try {
    const updated = await apiRequest<Order>(`/me/orders/${order.id}/${action}`, { method: 'POST' })
    orders.value = orders.value.map((current) => current.id === updated.id ? updated : current)
    success.value = action === 'mark-shipped'
      ? 'De order is als verzonden gemarkeerd.'
      : 'Ontvangst bevestigd. De order is voltooid.'
  } catch (caught) {
    error.value = caughtMessage(caught, 'De orderstatus kon niet worden gewijzigd.')
  } finally {
    updatingOrderId.value = null
  }
}

async function openCollectionImport(order: Order, item: OrderItem): Promise<void> {
  importTarget.value = { order, item }
  importForm.language = item.language
  importForm.condition = item.condition
  importForm.storageLocationId = ''
  importForm.notes = ''
  if (storageLoaded.value) return

  try {
    const response = await apiRequest<StorageLocationCollectionResponse>('/me/storage-locations')
    storageLocations.value = response.items
    storageLoaded.value = true
  } catch (caught) {
    error.value = caughtMessage(caught, 'De opslaglocaties konden niet worden geladen.')
  }
}

function closeCollectionImport(): void {
  importTarget.value = null
}

async function importToCollection(): Promise<void> {
  const target = importTarget.value
  if (!target) return

  importingItemId.value = target.item.id
  error.value = ''
  success.value = ''
  const payload: Record<string, string> = {
    language: importForm.language,
    condition: importForm.condition,
  }
  if (importForm.storageLocationId) payload.storageLocationId = importForm.storageLocationId
  if (importForm.notes.trim()) payload.notes = importForm.notes.trim()

  try {
    const updated = await apiRequest<Order>(
      `/me/orders/${target.order.id}/items/${target.item.id}/import-to-collection`,
      { method: 'POST', body: JSON.stringify(payload) },
    )
    orders.value = orders.value.map((current) => current.id === updated.id ? updated : current)
    success.value = `${target.item.card.name} is aan je collectie toegevoegd.`
    closeCollectionImport()
  } catch (caught) {
    error.value = caughtMessage(caught, 'De aankoop kon niet aan je collectie worden toegevoegd.')
  } finally {
    importingItemId.value = null
  }
}

onMounted(() => { void loadOrders() })
</script>

<template>
  <main class="page-container orders-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">Aankopen en verkopen</p>
        <h1>Mijn orders</h1>
        <p>Iedere verkoper krijgt een afzonderlijke order met vastgelegde kaart- en verzendkosten.</p>
      </div>
      <RouterLink class="button button--secondary" :to="{ name: 'cart' }">Naar winkelmand</RouterLink>
    </header>

    <p v-if="completedCheckoutCount" class="success-message" role="status">
      Testbetaling geslaagd. {{ completedCheckoutCount }} {{ completedCheckoutCount === 1 ? 'order is' : 'orders zijn' }} aangemaakt.
    </p>
    <p v-if="success" class="success-message" role="status">{{ success }}</p>
    <ErrorMessage v-if="error" :message="error" />
    <p v-if="loading" class="empty-state" role="status">Orders laden…</p>
    <section v-else-if="orders.length === 0" class="empty-state">
      <h2>Nog geen orders</h2>
      <p>Na een geslaagde testcheckout verschijnen je orders hier.</p>
      <RouterLink class="button button--primary" :to="{ name: 'marketplace' }">Bekijk marktplaats</RouterLink>
    </section>

    <section v-else class="orders-list" aria-label="Orders">
      <article v-for="order in orders" :key="order.id" class="panel panel--wide order-card">
        <header class="order-card__heading">
          <div>
            <span class="badge">{{ statusLabel(order.status) }}</span>
            <h2>Order {{ order.id.slice(0, 8) }}</h2>
            <p>{{ formattedDate(order.createdAt) }}</p>
          </div>
          <strong>{{ formatMoney(order.costs.total.amount, order.costs.total.currency) }}</strong>
        </header>

        <div
          v-if="order.availableActions.cancel || order.availableActions.markShipped || order.availableActions.confirmReceived || order.status === 'completed'"
          class="order-card__actions"
        >
          <BaseButton
            v-if="order.availableActions.cancel"
            variant="danger"
            :disabled="cancellingOrderId === order.id"
            @click="openCancellation(order)"
          >
            Bestelling annuleren
          </BaseButton>
          <BaseButton
            v-if="order.availableActions.markShipped"
            :disabled="updatingOrderId === order.id"
            @click="updateOrder(order, 'mark-shipped')"
          >
            {{ updatingOrderId === order.id ? 'Bijwerken…' : 'Markeer als verzonden' }}
          </BaseButton>
          <BaseButton
            v-if="order.availableActions.confirmReceived"
            :disabled="updatingOrderId === order.id"
            @click="updateOrder(order, 'confirm-received')"
          >
            {{ updatingOrderId === order.id ? 'Bijwerken…' : 'Bevestig ontvangst' }}
          </BaseButton>
          <BaseButton v-if="canReview(order)" variant="secondary" @click="openReview(order)">
            Schrijf review
          </BaseButton>
          <span v-else-if="order.status === 'completed'" class="badge">Review geplaatst</span>
        </div>

        <ul class="order-items">
          <li v-for="item in order.items" :key="item.id">
            <div class="order-item__details">
              <strong>{{ item.card.name }} · {{ item.card.number }}</strong>
              <span>{{ item.card.set.name }}</span>
              <small>{{ readableCode(item.variant) }} · {{ readableCode(item.condition) }} · {{ item.language.toUpperCase() }}</small>
              <div class="order-item__import">
                <BaseButton
                  v-if="item.collectionImport.available"
                  variant="secondary"
                  :disabled="importingItemId === item.id"
                  @click="openCollectionImport(order, item)"
                >
                  Voeg toe aan collectie
                </BaseButton>
                <span v-else-if="item.collectionImport.imported" class="badge">Aan collectie toegevoegd</span>
              </div>
            </div>
            <strong>{{ formatMoney(item.price.amount, item.price.currency) }}</strong>
          </li>
        </ul>

        <div class="order-card__details">
          <dl>
            <div><dt>Kaarten</dt><dd>{{ formatMoney(order.costs.items.amount, order.costs.items.currency) }}</dd></div>
            <div><dt>{{ shippingLabel(order.shippingMethod) }}</dt><dd>{{ formatMoney(order.costs.shipping.amount, order.costs.shipping.currency) }}</dd></div>
            <div><dt>Servicekosten</dt><dd>{{ formatMoney(order.costs.serviceFee.amount, order.costs.serviceFee.currency) }}</dd></div>
            <div><dt>Totaal</dt><dd>{{ formatMoney(order.costs.total.amount, order.costs.total.currency) }}</dd></div>
          </dl>
          <div>
            <h3>Aflevering</h3>
            <address>
              {{ order.shippingAddress.fullName }}<br>
              {{ order.shippingAddress.addressLine1 }}<br>
              <template v-if="order.shippingAddress.addressLine2">{{ order.shippingAddress.addressLine2 }}<br></template>
              {{ order.shippingAddress.postalCode }} {{ order.shippingAddress.city }}<br>
              {{ order.shippingAddress.countryCode }}
            </address>
          </div>
          <div>
            <h3>Betaling</h3>
            <p>{{ order.payment.provider === 'fake' ? 'Nep-betaalprovider' : order.payment.provider }} · {{ paymentStatusLabel(order.payment.status) }}</p>
            <small v-if="order.payment.providerReference">Referentie {{ order.payment.providerReference }}</small>
          </div>
          <div v-if="order.cancellation">
            <h3>Annulering</h3>
            <p>{{ order.cancellation.reason }}</p>
            <small>{{ formattedDate(order.cancellation.cancelledAt) }}</small>
          </div>
          <div v-if="order.refund">
            <h3>Terugbetaling</h3>
            <p>{{ order.refund.status === 'succeeded' ? 'Geslaagd' : readableCode(order.refund.status) }}</p>
            <strong>{{ formatMoney(order.refund.amount.amount, order.refund.amount.currency) }}</strong>
          </div>
        </div>

        <ol class="order-timeline" aria-label="Orderstatusgeschiedenis">
          <li v-for="history in order.statusHistory" :key="history.id">
            <strong>{{ statusLabel(history.to) }}</strong>
            <time :datetime="history.occurredAt">{{ formattedDate(history.occurredAt) }}</time>
          </li>
        </ol>
      </article>
    </section>

    <div v-if="cancelTarget" class="modal-backdrop" @click.self="closeCancellation">
      <section class="panel edit-panel cancellation-panel" role="dialog" aria-modal="true" aria-labelledby="cancellation-heading">
        <p class="eyebrow">Order annuleren</p>
        <h2 id="cancellation-heading">Bestelling annuleren</h2>
        <p v-if="cancelTarget.status === 'paid'">
          Hiermee annuleer je de order en wordt {{ formatMoney(cancelTarget.costs.total.amount, cancelTarget.costs.total.currency) }}
          terugbetaald aan de koper. De oorspronkelijke listing wordt niet opnieuw gepubliceerd.
        </p>
        <p v-else>
          Hiermee annuleer je de order. Er is nog geen geslaagde betaling, dus er wordt geen terugbetaling verwerkt.
          De oorspronkelijke listing wordt niet opnieuw gepubliceerd.
        </p>
        <p>Verzonden orders kunnen niet worden geannuleerd.</p>
        <ErrorMessage v-if="cancellationError" :message="cancellationError" />
        <form class="edit-form" novalidate @submit.prevent="submitCancellation">
          <label class="field" for="cancellation-reason">
            <span>Annuleringsreden</span>
            <textarea
              id="cancellation-reason"
              v-model="cancellationReason"
              rows="4"
              maxlength="500"
              required
              :disabled="cancellingOrderId === cancelTarget.id"
            />
          </label>
          <div class="dialog-actions">
            <BaseButton variant="secondary" :disabled="cancellingOrderId === cancelTarget.id" @click="closeCancellation">
              Order behouden
            </BaseButton>
            <BaseButton type="submit" variant="danger" :disabled="cancellingOrderId === cancelTarget.id">
              {{ cancellingOrderId === cancelTarget.id ? 'Annuleren…' : 'Annuleren en terugbetalen' }}
            </BaseButton>
          </div>
        </form>
      </section>
    </div>

    <div v-if="importTarget" class="modal-backdrop" @click.self="closeCollectionImport">
      <section class="panel edit-panel order-import-panel" role="dialog" aria-modal="true" aria-labelledby="import-heading">
        <p class="eyebrow">Ontvangen aankoop</p>
        <h2 id="import-heading">{{ importTarget.item.card.name }} toevoegen</h2>
        <p>Controleer het fysieke exemplaar. Kaart, variant en aankoopprijs komen rechtstreeks uit de order.</p>
        <form class="edit-form" @submit.prevent="importToCollection">
          <label class="field">
            <span>Taal</span>
            <select v-model="importForm.language" required>
              <option v-for="language in languages" :key="language[0]" :value="language[0]">{{ language[1] }}</option>
            </select>
          </label>
          <label class="field">
            <span>Conditie na ontvangst</span>
            <select v-model="importForm.condition" required>
              <option v-for="condition in conditions" :key="condition[0]" :value="condition[0]">{{ condition[1] }}</option>
            </select>
          </label>
          <label class="field">
            <span>Fysieke opslaglocatie</span>
            <select v-model="importForm.storageLocationId">
              <option value="">Nog niet plaatsen</option>
              <option v-for="location in storageLocations" :key="location.id" :value="location.id">
                {{ location.path.map((entry) => entry.name).join(' › ') }}
              </option>
            </select>
            <RouterLink v-if="storageLoaded && storageLocations.length === 0" :to="{ name: 'storage' }">
              Maak eerst een opslaglocatie
            </RouterLink>
          </label>
          <label class="field">
            <span>Notities</span>
            <textarea v-model="importForm.notes" rows="4" maxlength="5000" />
          </label>
          <div class="dialog-actions">
            <BaseButton variant="secondary" @click="closeCollectionImport">Annuleren</BaseButton>
            <BaseButton type="submit" :disabled="importingItemId === importTarget.item.id">
              {{ importingItemId === importTarget.item.id ? 'Toevoegen…' : 'Bevestig en voeg toe' }}
            </BaseButton>
          </div>
        </form>
      </section>
    </div>

    <div v-if="reviewTarget" class="modal-backdrop" @click.self="closeReview">
      <section class="panel edit-panel review-panel" role="dialog" aria-modal="true" aria-labelledby="review-heading">
        <p class="eyebrow">Voltooide transactie</p>
        <h2 id="review-heading">Review plaatsen</h2>
        <p>Beoordeel de andere deelnemer van order {{ reviewTarget.id.slice(0, 8) }}.</p>
        <form class="edit-form review-form" @submit.prevent="submitReview">
          <label class="field">
            <span>Algemene score</span>
            <select v-model.number="reviewForm.overallScore" required>
              <option v-for="score in 5" :key="score" :value="score">{{ score }} / 5</option>
            </select>
          </label>
          <label class="field">
            <span>Beschrijving correct</span>
            <select v-model.number="reviewForm.descriptionAccuracyScore" required>
              <option v-for="score in 5" :key="score" :value="score">{{ score }} / 5</option>
            </select>
          </label>
          <label class="field">
            <span>Verpakking</span>
            <select v-model.number="reviewForm.packagingScore" required>
              <option v-for="score in 5" :key="score" :value="score">{{ score }} / 5</option>
            </select>
          </label>
          <label class="field">
            <span>Communicatie</span>
            <select v-model.number="reviewForm.communicationScore" required>
              <option v-for="score in 5" :key="score" :value="score">{{ score }} / 5</option>
            </select>
          </label>
          <label class="field">
            <span>Verzendsnelheid</span>
            <select v-model.number="reviewForm.shippingSpeedScore" required>
              <option v-for="score in 5" :key="score" :value="score">{{ score }} / 5</option>
            </select>
          </label>
          <label class="field review-form__comment">
            <span>Toelichting (optioneel)</span>
            <textarea v-model="reviewForm.comment" rows="4" maxlength="2000" />
          </label>
          <div class="dialog-actions review-form__actions">
            <BaseButton variant="secondary" @click="closeReview">Annuleren</BaseButton>
            <BaseButton type="submit" :disabled="submittingReview">
              {{ submittingReview ? 'Plaatsen…' : 'Plaats review' }}
            </BaseButton>
          </div>
        </form>
      </section>
    </div>
  </main>
</template>
