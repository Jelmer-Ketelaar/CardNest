export interface CatalogCardVariant {
  id: string
  code: string
}

export interface CardSeries {
  id: string
  name: string
}

export interface CardSet {
  id: string
  name: string
  code: string
  series: CardSeries
  language: string
  releaseDate: string | null
  logoUrl: string | null
  symbolUrl: string | null
  printedTotal: number
  total: number
  description: string | null
  status: string
  createdAt: string
  updatedAt: string
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
