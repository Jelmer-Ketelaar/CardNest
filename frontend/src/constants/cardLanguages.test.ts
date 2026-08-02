import { describe, expect, it } from 'vitest'
import { cardLanguages } from './cardLanguages'

describe('cardLanguages', () => {
  it('offers every language supported by the catalog importer', () => {
    const codes = cardLanguages.map(([code]) => code)

    expect(codes).toEqual(expect.arrayContaining([
      'en', 'fr', 'es', 'es-mx', 'it', 'pt', 'pt-br', 'pt-pt', 'de', 'nl', 'pl', 'ru',
      'ja', 'ko', 'zh-tw', 'id', 'th', 'zh-cn',
    ]))
  })
})
