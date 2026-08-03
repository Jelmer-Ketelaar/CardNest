// Vendor
import { expect, test } from '@playwright/test'

test('a visitor filters catalog cards with the artist autocomplete', async ({ page }) => {
  const artist = {
    id: crypto.randomUUID(),
    name: 'Mitsuhiro Arita',
    slug: 'mitsuhiro-arita',
    cardCount: 184,
  }
  const card = {
    id: crypto.randomUUID(),
    name: 'Charizard',
    number: '4/102',
    rarity: 'Rare',
    imageUrl: null,
    thumbnailUrl: null,
    artists: [{ ...artist, role: 'primary_illustrator' }],
    set: { id: crypto.randomUUID(), name: 'Base Set' },
    variants: [{ id: crypto.randomUUID(), code: 'holo' }],
  }
  const cardRequestUrls: string[] = []

  await page.route(/\/api\/v1\/artists\/suggestions\?.*$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: [artist] }),
    })
  })
  await page.route(/\/api\/v1\/cards\?.*$/, async (route) => {
    cardRequestUrls.push(route.request().url())
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: [card], page: 1, perPage: 24, total: 1, totalPages: 1 }),
    })
  })

  await page.goto('/catalog/cards')
  const artistSearch = page.getByRole('combobox', { name: 'Artist / illustrator' })
  await artistSearch.fill('Arita')

  const suggestion = page.getByRole('option', { name: /Mitsuhiro Arita 184 kaarten/ })
  await expect(suggestion).toBeVisible()
  await expect(artistSearch).toHaveAttribute('aria-expanded', 'true')
  await artistSearch.press('Escape')
  await expect(artistSearch).toHaveAttribute('aria-expanded', 'false')
  await artistSearch.press('Tab')
  await expect(page.getByLabel('Variant')).toBeFocused()
  await page.keyboard.press('Shift+Tab')
  await expect(artistSearch).toBeFocused()
  await expect(artistSearch).toHaveAttribute('aria-expanded', 'true')
  await artistSearch.press('ArrowDown')
  await artistSearch.press('Enter')

  await expect.poll(() => new URL(page.url()).searchParams.get('artist')).toBe('Mitsuhiro Arita')
  await expect(page.getByLabel('Actieve filters')).toContainText(/Artist\s*Mitsuhiro Arita/)
  await expect(page.getByRole('heading', { name: 'Charizard 4/102' })).toBeVisible()
  await expect.poll(() => cardRequestUrls.some((url) => (
    new URL(url).searchParams.get('artist') === 'Mitsuhiro Arita'
  ))).toBe(true)

  await page.getByRole('button', { name: 'Verwijder artistfilter Mitsuhiro Arita' }).click()

  await expect.poll(() => new URL(page.url()).searchParams.has('artist')).toBe(false)
  await expect(page.getByLabel('Actieve filters')).toHaveCount(0)
  await expect(artistSearch).toHaveValue('')
})
