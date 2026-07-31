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
  location: StorageLocation
  placedAt: string
  removedAt: string | null
  current: boolean
}

export interface CollectionItemLocationResponse {
  collectionItemId: string
  current: CollectionItemLocationEntry | null
  history: CollectionItemLocationEntry[]
}
