import type { Money } from './collection'

export interface WishlistProgress {
  desiredQuantity: number
  fulfilledQuantity: number
  remainingQuantity: number
  percentage: number
}

export interface WishlistSetProgress {
  id: string
  name: string
  desiredQuantity: number
  fulfilledQuantity: number
  progress: WishlistProgress
}

export interface WishlistSummary {
  id: string
  name: string
  itemCount: number
  completedItemCount: number
  progress: WishlistProgress
  setProgress: WishlistSetProgress[]
  createdAt: string
  updatedAt: string
}

export interface WishlistItem {
  id: string
  card: {
    id: string
    name: string
    number: string
    thumbnailUrl: string | null
    set: { id: string; name: string }
  }
  language: string | null
  variant: { id: string; code: string } | null
  minimumCondition: string | null
  maxPrice: Money | null
  desiredQuantity: number
  fulfilledQuantity: number
  remainingQuantity: number
  completed: boolean
  completedAt: string | null
  priority: 'low' | 'normal' | 'high' | 'wanted'
  notes: string | null
  createdAt: string
  updatedAt: string
}

export interface WishlistDetail extends WishlistSummary {
  items: WishlistItem[]
}

export interface WishlistCollectionResponse {
  items: WishlistSummary[]
  total: number
}
