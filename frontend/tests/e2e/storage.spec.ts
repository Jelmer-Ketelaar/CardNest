import { expect, test } from '@playwright/test'

test('a collector can build a nested storage hierarchy', async ({ page }) => {
  type Location = {
    id: string
    name: string
    type: string
    parentId: string | null
    path: Array<{ id: string; name: string; type: string }>
    currentItemCount: number
    createdAt: string
    updatedAt: string
  }
  const locations: Location[] = []

  await page.addInitScript(() => localStorage.setItem('cardnest.authToken', 'test-token'))
  await page.route(/\/api\/v1\/me\/storage-locations(?:\/[^/?]+)?$/, async (route) => {
    if (route.request().method() === 'POST') {
      const payload = route.request().postDataJSON() as { name: string; type: string; parentId?: string }
      const parent = locations.find((location) => location.id === payload.parentId)
      const location: Location = {
        id: crypto.randomUUID(),
        name: payload.name,
        type: payload.type,
        parentId: parent?.id ?? null,
        path: [...(parent?.path ?? []), { id: '', name: payload.name, type: payload.type }],
        currentItemCount: 0,
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
      }
      location.path[location.path.length - 1].id = location.id
      locations.push(location)
      await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify(location) })
      return
    }

    if (route.request().method() === 'DELETE') {
      const locationId = route.request().url().split('/').at(-1)
      const index = locations.findIndex((location) => location.id === locationId)
      if (index >= 0) locations.splice(index, 1)
      await route.fulfill({ status: 204 })
      return
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: locations, total: locations.length }),
    })
  })

  await page.goto('/storage')
  await page.getByLabel('Naam').fill('Collectiekamer')
  await page.getByRole('button', { name: 'Locatie toevoegen' }).click()
  await expect(page.locator('.storage-list strong').filter({ hasText: 'Collectiekamer' })).toBeVisible()

  await page.getByLabel('Naam').fill('Kast links')
  await page.getByLabel('Bovenliggende locatie').selectOption({ label: 'Collectiekamer' })
  await page.getByRole('button', { name: 'Locatie toevoegen' }).click()

  await expect(page.locator('.storage-list small').filter({ hasText: 'Collectiekamer › Kast links' })).toBeVisible()
  await expect(page.getByText('2 locaties')).toBeVisible()

  const child = page.locator('.storage-list li').filter({ hasText: 'Kast links' })
  await child.getByRole('button', { name: 'Verwijderen' }).click()
  await expect(page.getByRole('heading', { name: 'Kast links verwijderen?' })).toBeVisible()
  await page.getByRole('button', { name: 'Definitief verwijderen' }).click()

  await expect(page.getByText('De opslaglocatie is verwijderd.')).toBeVisible()
  await expect(page.getByText('1 locaties')).toBeVisible()
  await expect(page.locator('.storage-list small').filter({ hasText: 'Kast links' })).not.toBeVisible()
})
