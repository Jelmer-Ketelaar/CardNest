export interface CatalogCardVariant {
  id: string
  code: string
}

export interface CatalogCard {
  id: string
  name: string
  number: string
  rarity: string
  imageUrl: string | null
  thumbnailUrl: string | null
  set: {
    id: string
    name: string
  }
  variants: CatalogCardVariant[]
}
