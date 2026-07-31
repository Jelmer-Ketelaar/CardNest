import type { Money } from './collection'
import type { Listing, ShippingMethodCode } from './marketplace'

export interface CartItem {
  id: string
  listing: Listing
  addedPrice: Money
  priceChanged: boolean
  available: boolean
  addedAt: string
}

export interface CartShippingMethod {
  code: ShippingMethodCode
  price: Money
  totalPrice: Money
}

export interface CartSellerGroup {
  seller: { id: string }
  items: CartItem[]
  itemSubtotal: Money
  shippingMethods: CartShippingMethod[]
}

export interface Cart {
  id: string | null
  sellerGroups: CartSellerGroup[]
  itemCount: number
  checkoutAvailable: boolean
}

export interface ShippingAddress {
  fullName: string
  addressLine1: string
  addressLine2: string | null
  postalCode: string
  city: string
  countryCode: 'NL' | 'BE'
}

export interface OrderItem {
  id: string
  listingId: string
  card: {
    id: string
    name: string
    number: string
    set: { id: string; name: string }
  }
  language: string
  variant: string
  condition: string
  price: Money
  collectionImport: {
    available: boolean
    imported: boolean
    collectionItemId: string | null
    importedAt: string | null
  }
}

export interface Order {
  id: string
  status: 'pending_payment' | 'paid' | 'preparing' | 'shipped' | 'delivered' | 'completed' | 'cancelled' | 'refunded' | 'disputed'
  buyer: { id: string }
  seller: { id: string }
  items: OrderItem[]
  shippingMethod: ShippingMethodCode
  costs: {
    items: Money
    shipping: Money
    serviceFee: Money
    total: Money
  }
  shippingAddress: ShippingAddress
  payment: {
    id: string
    status: 'pending' | 'succeeded' | 'failed' | 'refunded'
    provider: string
    providerReference: string | null
  }
  statusHistory: Array<{
    id: string
    from: Order['status'] | null
    to: Order['status']
    actor: { id: string }
    occurredAt: string
  }>
  availableActions: {
    markShipped: boolean
    confirmReceived: boolean
  }
  paidAt: string | null
  completedAt: string | null
  createdAt: string
  updatedAt: string
}

export interface OrderCollection {
  items: Order[]
  total: number
}

export interface CheckoutResponse {
  payment: {
    id: string
    status: 'pending' | 'succeeded' | 'failed' | 'refunded'
    provider: string
    providerReference: string | null
    amount: Money
  }
  orders: Order[]
}
