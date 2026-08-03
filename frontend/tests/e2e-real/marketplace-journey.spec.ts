// Vendor
import { expect, test, type Page } from '@playwright/test'

// Utilities
import { marketplaceJourneyState, redisPing, verifySyntheticUser } from './support/compose'

interface CreatedCollectionItemsResponse {
  items: Array<{ id: string }>
}

interface ListingResponse {
  id: string
}

interface CheckoutResponse {
  orders: Array<{ id: string }>
}

const sellerEmail = 'seller-marketplace@example.test'
const buyerEmail = 'buyer-marketplace@example.test'
const password = 'CardNest-E2E-Password-2026!'

const registerAndVerify = async (page: Page, email: string): Promise<void> => {
  await page.goto('/register')
  await page.getByLabel('E-mailadres').fill(email)
  await page.getByLabel('Wachtwoord', { exact: true }).fill(password)
  await page.getByLabel('Herhaal wachtwoord').fill(password)
  const registrationResponse = page.waitForResponse((response) => (
    response.url().endsWith('/api/v1/auth/register') && response.request().method() === 'POST'
  ))
  await page.getByRole('button', { name: 'Registreren' }).click()
  expect((await registrationResponse).status()).toBe(201)
  await expect(page).toHaveURL(/\/login\?registered=1$/)
  verifySyntheticUser(email)
}

const login = async (page: Page, email: string): Promise<void> => {
  await page.goto('/login')
  await page.getByLabel('E-mailadres').fill(email)
  await page.getByLabel('Wachtwoord').fill(password)
  await page.getByRole('button', { name: 'Inloggen' }).click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

const logout = async (page: Page): Promise<void> => {
  await page.getByRole('button', { name: 'Uitloggen' }).click()
  await expect(page).toHaveURL(/\/login$/)
}

test('a seller and buyer complete one real marketplace journey', async ({ page }) => {
  // Arrange
  expect(redisPing()).toBe('PONG')
  const unauthorizedResponse = await page.request.get('/api/v1/me/orders')

  // Assert
  expect(unauthorizedResponse.status()).toBe(401)

  // Act: seller registration, catalog search, collection registration and listing publication
  await registerAndVerify(page, sellerEmail)
  await login(page, sellerEmail)
  await page.goto('/collection')
  await page.getByLabel('Kaartnaam').first().fill('Mudkip')
  await page.getByRole('button', { name: 'Zoek in catalogus' }).click()
  await page.getByRole('button', { name: /Mudkip · 19\/25/ }).click()
  await page.getByLabel('Aankoopprijs per kaart (centen)').fill('350')
  const collectionResponse = page.waitForResponse((response) => (
    response.url().endsWith('/api/v1/me/collection-items') && response.request().method() === 'POST'
  ))
  await page.getByRole('button', { name: 'Aan collectie toevoegen' }).click()
  const createdCollectionResponse = await collectionResponse
  expect(createdCollectionResponse.status()).toBe(201)
  const collectionPayload = await createdCollectionResponse.json() as CreatedCollectionItemsResponse
  const sellerCollectionItemId = collectionPayload.items[0]?.id
  if (sellerCollectionItemId === undefined) {
    throw new Error('The collection response did not contain a collection-item ID.')
  }
  await expect(page.getByText('De fysieke kaart is aan je collectie toegevoegd.')).toBeVisible()

  await page.goto(`/my-listings?itemId=${sellerCollectionItemId}`)
  await page.getByLabel('Vaste vraagprijs (centen)').fill('750')
  await page.getByLabel('Aanvullende omschrijving').fill('Real E2E condition description.')
  const listingResponse = page.waitForResponse((response) => (
    response.url().endsWith('/api/v1/listings') && response.request().method() === 'POST'
  ))
  await page.getByRole('button', { name: 'Direct publiceren' }).click()
  const createdListingResponse = await listingResponse
  expect(createdListingResponse.status()).toBe(201)
  const listingPayload = await createdListingResponse.json() as ListingResponse
  expect(listingPayload.id).toMatch(/^[0-9a-f-]{36}$/i)
  await expect(page.getByText('De fysieke kaart staat nu actief te koop.')).toBeVisible()
  await logout(page)

  // Act: buyer registration, cart and fake checkout
  await registerAndVerify(page, buyerEmail)
  await login(page, buyerEmail)
  await page.goto('/marketplace')
  await page.getByLabel('Kaartnaam').fill('Mudkip')
  await page.getByRole('button', { name: 'Zoeken' }).click()
  await page.getByRole('button', { name: 'Bekijk aanbieding' }).click()
  await page.getByRole('button', { name: 'In winkelmand' }).click()
  await expect(page.getByText(/Mudkip is toegevoegd/)).toBeVisible()
  await page.getByRole('link', { name: 'Winkelmand' }).first().click()
  await page.getByLabel('Volledige naam').fill('Buyer Example')
  await page.getByLabel('Straat en huisnummer').fill('Teststraat 1')
  await page.getByLabel('Postcode').fill('1234 AB')
  await page.getByLabel('Plaats').fill('Utrecht')
  const checkoutResponse = page.waitForResponse((response) => (
    response.url().endsWith('/api/v1/me/cart/checkout') && response.request().method() === 'POST'
  ))
  await page.getByRole('button', { name: 'Testbetaling afronden' }).click()
  const completedCheckoutResponse = await checkoutResponse
  expect(completedCheckoutResponse.status()).toBe(200)
  const checkoutPayload = await completedCheckoutResponse.json() as CheckoutResponse
  const orderId = checkoutPayload.orders[0]?.id
  if (orderId === undefined) {
    throw new Error('The checkout response did not contain an order ID.')
  }
  expect(orderId).toMatch(/^[0-9a-f-]{36}$/i)
  await expect(page.getByText('Testbetaling geslaagd. 1 order is aangemaakt.')).toBeVisible()
  await logout(page)

  // Act: seller ships, buyer receives, imports and reviews
  await login(page, sellerEmail)
  await page.goto('/orders/sales')
  await page.getByRole('button', { name: 'Markeer als verzonden' }).click()
  await expect(page.getByText('De order is als verzonden gemarkeerd.')).toBeVisible()
  await logout(page)

  await login(page, buyerEmail)
  await page.goto('/orders')
  await page.getByRole('button', { name: 'Bevestig ontvangst' }).click()
  await expect(page.getByText('Ontvangst bevestigd. De order is voltooid.')).toBeVisible()
  await page.getByRole('button', { name: 'Voeg toe aan collectie' }).click()
  await page.getByLabel('Notities').fill('Imported by the real E2E journey.')
  await page.getByRole('button', { name: 'Bevestig en voeg toe' }).click()
  await expect(page.getByText('Mudkip is aan je collectie toegevoegd.')).toBeVisible()
  await page.getByRole('button', { name: 'Schrijf review' }).click()
  await page.getByLabel('Toelichting (optioneel)').fill('Synthetic E2E review.')
  await page.getByRole('button', { name: 'Plaats review' }).click()
  await expect(page.getByText('Je review is geplaatst.')).toBeVisible()

  // Assert: immutable transaction state reached PostgreSQL
  const state = marketplaceJourneyState(orderId)
  expect(state).toMatchObject({
    buyerOwnerEmail: buyerEmail,
    listingStatus: 'sold',
    orderStatus: 'completed',
    paymentStatus: 'succeeded',
    reviewCount: 1,
    sellerCollectionItemStatus: 'archived',
  })
  expect(state.buyerCollectionItemId).toMatch(/^[0-9a-f-]{36}$/i)
})
