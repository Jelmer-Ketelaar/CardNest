import { formatMoney, parseOptionalMinorUnits } from './money'

describe('money utilities', () => {
  it('formats integer minor units as euros', () => {
    expect(formatMoney(750, 'EUR')).toMatch(/7,50/)
  })

  it('parses only whole non-negative minor units', () => {
    expect(parseOptionalMinorUnits(' 350 ')).toBe(350)
    expect(parseOptionalMinorUnits('')).toBeNull()
    expect(() => parseOptionalMinorUnits('3.50')).toThrow('heel bedrag in centen')
    expect(() => parseOptionalMinorUnits('-1')).toThrow('heel bedrag in centen')
  })
})
