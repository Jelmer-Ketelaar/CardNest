export interface BinderSummary {
  id: string
  name: string
  rows: number
  columns: number
  pageCount: number
  surfaceCount: number
  doubleSided: boolean
  layoutStrategy: string
  slotCount: number
  occupiedSlots: number
  parentLocation: { id: string; name: string } | null
  createdAt: string
  updatedAt: string
}

export interface BinderSlotCollectionItem {
  id: string
  card: {
    id: string
    name: string
    number: string
    thumbnailUrl: string | null
  }
  language: string
  variant: string
  condition: string
}

export interface BinderSlot {
  id: string
  position: number
  row: number
  column: number
  collectionItem: BinderSlotCollectionItem | null
  placeholder: {
    catalogCard: { id: string; name: string; number: string; thumbnailUrl: string | null } | null
    label: string | null
  } | null
}

export interface BinderPage {
  id: string
  number: number
  side: 'front' | 'back'
  slots: BinderSlot[]
}

export interface BinderDetail extends BinderSummary {
  pages: BinderPage[]
}

export interface BinderCollectionResponse {
  items: BinderSummary[]
  total: number
}
