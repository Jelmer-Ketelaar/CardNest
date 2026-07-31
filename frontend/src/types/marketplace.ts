import type { Money } from './collection'

export interface ListingImage {
  id: string
  side: 'front' | 'back'
  url: string
}

export interface ListingShippingMethod {
  code: 'letter_mail' | 'parcel' | 'pickup'
  price: Money
  totalPrice: Money
}

export interface ListingPriceHistory {
  previousPrice: Money | null
  newPrice: Money
  changedAt: string
}

export interface Listing {
  id: string
  status: 'draft' | 'pending_review' | 'active' | 'reserved' | 'sold' | 'cancelled' | 'expired' | 'suspended'
  seller: { id: string }
  card: {
    id: string
    name: string
    number: string
    set: { id: string; name: string }
  }
  collectionItemId?: string
  language: string
  variant: string
  condition: string
  price: Money
  description: string | null
  images: ListingImage[]
  shippingMethods: ListingShippingMethod[]
  priceHistory: ListingPriceHistory[]
  expiresAt: string | null
  publishedAt: string | null
  createdAt: string
  updatedAt: string
}
