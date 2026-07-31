export function formatMoney(amount: number, currency: string): string {
  return new Intl.NumberFormat('nl-NL', {
    style: 'currency',
    currency,
  }).format(amount / 100)
}

export function parseOptionalMinorUnits(value: string): number | null {
  const normalized = value.trim()
  if (normalized === '') return null
  if (!/^\d+$/.test(normalized)) throw new Error('Gebruik een heel bedrag in centen.')

  const amount = Number(normalized)
  if (!Number.isSafeInteger(amount)) throw new Error('Het bedrag is te groot.')

  return amount
}
