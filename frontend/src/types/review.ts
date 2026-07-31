export interface Review {
  id: string
  orderId?: string
  reviewer: { id: string }
  reviewedUser: { id: string }
  scores: {
    overall: number
    descriptionAccuracy: number
    packaging: number
    communication: number
    shippingSpeed: number
  }
  comment: string | null
  createdAt: string
}

export interface ReviewCollection {
  items: Review[]
  total: number
}

export interface Reputation {
  user: {
    id: string
    memberSince: string
  }
  summary: {
    reviewCount: number
    averageOverall: number | null
    averages: {
      descriptionAccuracy: number | null
      packaging: number | null
      communication: number | null
      shippingSpeed: number | null
    }
  }
  reviews: {
    items: Review[]
    page: number
    perPage: number
    total: number
    totalPages: number
  }
}
