export const cardLanguages = [
  ['en', 'Engels'],
  ['nl', 'Nederlands'],
  ['ja', 'Japans'],
  ['ko', 'Koreaans'],
  ['de', 'Duits'],
  ['fr', 'Frans'],
  ['it', 'Italiaans'],
  ['es', 'Spaans'],
  ['es-mx', 'Spaans (Mexico)'],
  ['pt', 'Portugees'],
  ['pt-br', 'Portugees (Brazilië)'],
  ['pt-pt', 'Portugees (Portugal)'],
  ['pl', 'Pools'],
  ['ru', 'Russisch'],
  ['id', 'Indonesisch'],
  ['th', 'Thai'],
  ['zh', 'Chinees (algemeen)'],
  ['zh-cn', 'Chinees (vereenvoudigd)'],
  ['zh-tw', 'Chinees (traditioneel)'],
] as const

export type CardLanguageCode = (typeof cardLanguages)[number][0]
