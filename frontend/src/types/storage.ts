export interface StoragePathEntry {
  id: string
  name: string
  type: string
}

export interface StorageLocation {
  id: string
  name: string
  type: string
  parentId: string | null
  path: StoragePathEntry[]
  currentItemCount: number
  createdAt: string
  updatedAt: string
}

export interface StorageLocationCollectionResponse {
  items: StorageLocation[]
  total: number
}

export interface CollectionItemLocationEntry {
  id: string
  type: 'storage_location' | 'binder_slot'
  location: StorageLocation | null
  binderSlot: {
    id: string
    position: number
    page: { id: string; number: number; side: 'front' | 'back' }
    binder: { id: string; name: string }
  } | null
  placedAt: string
  removedAt: string | null
  current: boolean
}

export interface CollectionItemLocationResponse {
  collectionItemId: string
  current: CollectionItemLocationEntry | null
  history: CollectionItemLocationEntry[]
}
