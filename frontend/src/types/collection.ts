export interface Money {
  amount: number
  currency: string
}

export interface CollectionItem {
  id: string
  card: {
    id: string
    name: string
    number: string
    imageUrl: string | null
    thumbnailUrl: string | null
    set: {
      id: string
      name: string
    }
  }
  language: string
  variant: {
    id: string
    code: string
  }
  condition: string
  status: 'in_collection' | 'archived'
  purchase: {
    price: Money | null
    purchasedAt: string | null
    sellerName: string | null
  }
  notes: string | null
  duplicate: {
    ownedQuantity: number
    isDuplicate: boolean
  }
  createdAt: string
  updatedAt: string
}

export interface CreatedCollectionItemsResponse {
  items: CollectionItem[]
  created: number
}
