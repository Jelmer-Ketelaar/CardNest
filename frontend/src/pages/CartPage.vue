<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError, apiRequest } from '../api/client'
import BaseButton from '../components/BaseButton.vue'
import BaseInput from '../components/BaseInput.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import type { ShippingMethodCode } from '../types/marketplace'
import type { Cart, CartSellerGroup, CheckoutResponse } from '../types/order'
import { formatMoney } from '../utils/money'

const router = useRouter()
const cart = ref<Cart | null>(null)
const loading = ref(false)
const checkingOut = ref(false)
const removingItemId = ref<string | null>(null)
const error = ref('')
const shippingMethods = reactive<Record<string, ShippingMethodCode>>({})
const address = reactive({
  fullName: '',
  addressLine1: '',
  addressLine2: '',
  postalCode: '',
  city: '',
  countryCode: 'NL' as 'NL' | 'BE',
})

const selectedTotal = computed(() => cart.value?.sellerGroups.reduce((total, group) => {
  const selected = selectedShipping(group)
  return total + group.itemSubtotal.amount + (selected?.price.amount ?? 0)
}, 0) ?? 0)

const allShippingSelected = computed(() => cart.value?.sellerGroups.every((group) => (
  selectedShipping(group) !== undefined
)) ?? false)

function caughtMessage(caught: unknown, fallback: string): string {
  return caught instanceof ApiError ? caught.message : fallback
}

function shippingLabel(code: ShippingMethodCode): string {
  return { letter_mail: 'Brievenbuspost', parcel: 'Pakketpost', pickup: 'Ophalen' }[code]
}

function readableCode(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function sellerLabel(sellerId: string): string {
  return `Verkoper ${sellerId.slice(0, 8)}`
}

function selectedShipping(group: CartSellerGroup) {
  return group.shippingMethods.find((method) => method.code === shippingMethods[group.seller.id])
}

function syncShippingSelections(currentCart: Cart): void {
  const currentSellerIds = new Set(currentCart.sellerGroups.map((group) => group.seller.id))
  for (const sellerId of Object.keys(shippingMethods)) {
    if (!currentSellerIds.has(sellerId)) delete shippingMethods[sellerId]
  }

  for (const group of currentCart.sellerGroups) {
    const selectedCode = shippingMethods[group.seller.id]
    if (!group.shippingMethods.some((method) => method.code === selectedCode)) {
      const firstMethod = group.shippingMethods[0]
      if (firstMethod) shippingMethods[group.seller.id] = firstMethod.code
    }
  }
}

async function loadCart(clearError = true): Promise<void> {
  loading.value = true
  if (clearError) error.value = ''
  try {
    const response = await apiRequest<Cart>('/me/cart')
    cart.value = response
    syncShippingSelections(response)
  } catch (caught) {
    error.value = caughtMessage(caught, 'Je winkelmand kon niet worden geladen.')
  } finally {
    loading.value = false
  }
}

async function removeItem(itemId: string): Promise<void> {
  removingItemId.value = itemId
  error.value = ''
  try {
    const response = await apiRequest<Cart>(`/me/cart/items/${itemId}`, { method: 'DELETE' })
    cart.value = response
    syncShippingSelections(response)
  } catch (caught) {
    error.value = caughtMessage(caught, 'De kaart kon niet uit je winkelmand worden verwijderd.')
  } finally {
    removingItemId.value = null
  }
}

async function checkout(): Promise<void> {
  if (checkingOut.value) return

  if (!cart.value?.checkoutAvailable || !allShippingSelected.value) {
    error.value = 'Controleer de beschikbaarheid en kies een verzendmethode per verkoper.'
    return
  }

  checkingOut.value = true
  error.value = ''
  try {
    const response = await apiRequest<CheckoutResponse>('/me/cart/checkout', {
      method: 'POST',
      body: JSON.stringify({
        shippingMethods: { ...shippingMethods },
        fullName: address.fullName.trim(),
        addressLine1: address.addressLine1.trim(),
        addressLine2: address.addressLine2.trim() || null,
        postalCode: address.postalCode.trim(),
        city: address.city.trim(),
        countryCode: address.countryCode,
      }),
    })
    await router.push({ name: 'orders', query: { checkedOut: String(response.orders.length) } })
  } catch (caught) {
    const message = caughtMessage(caught, 'De testbetaling kon niet worden afgerond.')
    await loadCart(false)
    error.value = message
  } finally {
    checkingOut.value = false
  }
}

onMounted(() => { void loadCart() })
</script>

<template>
  <main class="page-container cart-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">{{ cart?.itemCount ?? 0 }} fysieke kaarten</p>
        <h1>Winkelmand</h1>
        <p>Aanbiedingen worden per verkoper afgerekend en vormen afzonderlijke orders.</p>
      </div>
      <RouterLink class="button button--secondary" :to="{ name: 'orders' }">Mijn orders</RouterLink>
    </header>

    <ErrorMessage v-if="error" :message="error" />
    <p v-if="loading && !cart" class="empty-state" role="status">Winkelmand laden…</p>
    <section v-else-if="!cart || cart.itemCount === 0" class="empty-state">
      <h2>Je winkelmand is leeg</h2>
      <p>Voeg een actieve aanbieding toe vanaf de marktplaats.</p>
      <RouterLink class="button button--primary" :to="{ name: 'marketplace' }">Bekijk marktplaats</RouterLink>
    </section>

    <form v-else class="checkout-layout" @submit.prevent="checkout">
      <section class="cart-groups" aria-label="Aanbiedingen per verkoper">
        <article v-for="group in cart.sellerGroups" :key="group.seller.id" class="panel panel--wide cart-seller-group">
          <header>
            <div>
              <p class="eyebrow">{{ group.items.length }} {{ group.items.length === 1 ? 'kaart' : 'kaarten' }}</p>
              <h2>{{ sellerLabel(group.seller.id) }}</h2>
            </div>
            <strong>{{ formatMoney(group.itemSubtotal.amount, group.itemSubtotal.currency) }}</strong>
          </header>

          <ul class="cart-items">
            <li v-for="item in group.items" :key="item.id" :class="{ 'cart-item--unavailable': !item.available }">
              <img
                v-if="item.listing.images.find((image) => image.side === 'front')"
                :src="item.listing.images.find((image) => image.side === 'front')?.url"
                :alt="`${item.listing.card.name} voorkant`"
              >
              <div class="cart-item__details">
                <span v-if="!item.available" class="badge badge--warning">Niet meer beschikbaar</span>
                <span v-else-if="item.priceChanged" class="badge badge--warning">Prijs gewijzigd</span>
                <h3>{{ item.listing.card.name }} · {{ item.listing.card.number }}</h3>
                <p>{{ item.listing.card.set.name }}</p>
                <small>{{ readableCode(item.listing.variant) }} · {{ readableCode(item.listing.condition) }}</small>
              </div>
              <strong>{{ formatMoney(item.listing.price.amount, item.listing.price.currency) }}</strong>
              <BaseButton
                variant="danger"
                :disabled="removingItemId === item.id || checkingOut"
                @click="removeItem(item.id)"
              >
                Verwijderen
              </BaseButton>
            </li>
          </ul>

          <fieldset class="shipping-options">
            <legend>Levering voor {{ sellerLabel(group.seller.id) }}</legend>
            <label v-for="method in group.shippingMethods" :key="method.code">
              <input v-model="shippingMethods[group.seller.id]" type="radio" :name="`shipping-${group.seller.id}`" :value="method.code">
              <span>{{ shippingLabel(method.code) }}</span>
              <small>{{ formatMoney(method.price.amount, method.price.currency) }}</small>
              <strong>{{ formatMoney(method.totalPrice.amount, method.totalPrice.currency) }} totaal</strong>
            </label>
          </fieldset>
        </article>
      </section>

      <aside class="checkout-sidebar">
        <section class="panel checkout-address">
          <p class="eyebrow">Nederland & België</p>
          <h2>Afleveradres</h2>
          <BaseInput id="checkout-name" v-model="address.fullName" label="Volledige naam" autocomplete="name" required />
          <BaseInput id="checkout-address-1" v-model="address.addressLine1" label="Straat en huisnummer" autocomplete="address-line1" required />
          <BaseInput id="checkout-address-2" v-model="address.addressLine2" label="Adresregel 2 (optioneel)" autocomplete="address-line2" />
          <div class="compact-fields">
            <BaseInput id="checkout-postal-code" v-model="address.postalCode" label="Postcode" autocomplete="postal-code" required />
            <BaseInput id="checkout-city" v-model="address.city" label="Plaats" autocomplete="address-level2" required />
          </div>
          <label class="field">
            <span>Land</span>
            <select v-model="address.countryCode" autocomplete="country" required>
              <option value="NL">Nederland</option>
              <option value="BE">België</option>
            </select>
          </label>
          <p class="form-help">Het adres wordt als ordermomentopname opgeslagen, ook wanneer je kiest voor ophalen.</p>
        </section>

        <section class="panel checkout-summary">
          <p class="eyebrow">Testcheckout</p>
          <h2>Te betalen</h2>
          <dl>
            <div v-for="group in cart.sellerGroups" :key="group.seller.id">
              <dt>{{ sellerLabel(group.seller.id) }}</dt>
              <dd>{{ formatMoney((selectedShipping(group)?.totalPrice.amount ?? group.itemSubtotal.amount), 'EUR') }}</dd>
            </div>
            <div class="checkout-summary__total">
              <dt>Totaal</dt>
              <dd>{{ formatMoney(selectedTotal, 'EUR') }}</dd>
            </div>
          </dl>
          <p>De nep-betaalprovider bevestigt deze betaling direct. Er worden geen betaalgegevens gevraagd of opgeslagen.</p>
          <BaseButton type="submit" :disabled="checkingOut || !cart.checkoutAvailable || !allShippingSelected">
            {{ checkingOut ? 'Afrekenen…' : 'Testbetaling afronden' }}
          </BaseButton>
        </section>
      </aside>
    </form>
  </main>
</template>
