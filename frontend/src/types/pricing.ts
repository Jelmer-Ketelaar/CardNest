import type { Money } from './collection'

export type PriceVariant = 'standard' | 'foil'

export interface CardPriceSnapshot {
  variant: PriceVariant
  lowest: Money | null
  average: Money | null
  trend: Money | null
  averageOneDay: Money | null
  averageSevenDays: Money | null
  averageThirtyDays: Money | null
  sourceUpdatedAt: string | null
  observedAt: string
  observedOn: string
}

export interface CardPriceChange {
  amount: number
  currency: string
  percentage: number | null
}

export interface CurrentCardPrice extends CardPriceSnapshot {
  change: CardPriceChange | null
}

export interface CardPriceHistoryResponse {
  cardId: string
  source: 'tcgdex'
  market: 'cardmarket'
  currency: 'EUR'
  refreshStatus: 'refreshed' | 'current' | 'no_data' | 'unsupported' | 'refresh_failed'
  checkedAt: string | null
  prices: CurrentCardPrice[]
  history: CardPriceSnapshot[]
  disclaimer: string
}
