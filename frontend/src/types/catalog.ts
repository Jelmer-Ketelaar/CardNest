export interface CatalogCardVariant {
  id: string
  code: string
}

export interface CardArtist {
  id: string
  name: string
  slug: string
  role: string
}

export interface ArtistSummary {
  id: string
  name: string
  slug: string
  cardCount: number
}

export interface ArtistDetail extends ArtistSummary {
  biography: string | null
  websiteUrl: string | null
  profileImage: string | null
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
  artists: CardArtist[]
  set: {
    id: string
    name: string
  }
  variants: CatalogCardVariant[]
}

export interface CatalogCardDetail extends CatalogCard {
  totalNumberInSet: string | null
  cardType: string
  primaryCollectible: string | null
  illustrator: string | null
  releaseDate: string | null
  metadata: Record<string, unknown>
  externalReferences: Array<{
    id: string
    sourceName: string
    externalId: string
    lastSyncedAt: string | null
  }>
  set: CardSet
  createdAt: string
  updatedAt: string
}
