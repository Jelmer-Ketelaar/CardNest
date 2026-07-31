<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { ShippingMethodCode } from '../types/marketplace'
import type { Order, OrderCollection } from '../types/order'
import { formatMoney } from '../utils/money'

const route = useRoute()
const orders = ref<Order[]>([])
const loading = ref(false)
const updatingOrderId = ref<string | null>(null)
const error = ref('')
const success = ref('')
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
    const response = await apiRequest<OrderCollection>('/me/orders')
    orders.value = response.items
  } catch (caught) {
    error.value = caughtMessage(caught, 'Je orders konden niet worden geladen.')
  } finally {
    loading.value = false
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

        <div v-if="order.availableActions.markShipped || order.availableActions.confirmReceived" class="order-card__actions">
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
        </div>

        <ul class="order-items">
          <li v-for="item in order.items" :key="item.id">
            <div>
              <strong>{{ item.card.name }} · {{ item.card.number }}</strong>
              <span>{{ item.card.set.name }}</span>
              <small>{{ readableCode(item.variant) }} · {{ readableCode(item.condition) }} · {{ item.language.toUpperCase() }}</small>
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
        </div>

        <ol class="order-timeline" aria-label="Orderstatusgeschiedenis">
          <li v-for="history in order.statusHistory" :key="history.id">
            <strong>{{ statusLabel(history.to) }}</strong>
            <time :datetime="history.occurredAt">{{ formattedDate(history.occurredAt) }}</time>
          </li>
        </ol>
      </article>
    </section>
  </main>
</template>
