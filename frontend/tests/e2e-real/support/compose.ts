// Vendor
import { execFileSync } from 'node:child_process'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

interface MarketplaceJourneyState {
  buyerCollectionItemId: string
  buyerOwnerEmail: string
  listingStatus: string
  orderStatus: string
  paymentStatus: string
  reviewCount: number
  sellerCollectionItemStatus: string
}

const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../../../..')
const composeFile = process.env.CARDNEST_E2E_COMPOSE_FILE ?? resolve(repositoryRoot, 'compose.e2e.yaml')
const projectName = process.env.CARDNEST_E2E_PROJECT_NAME ?? 'cardnest-real-e2e'

const composeExec = (service: string, command: readonly string[]): string => execFileSync(
  'docker',
  [
    'compose',
    '--project-name',
    projectName,
    '--file',
    composeFile,
    'exec',
    '-T',
    service,
    ...command,
  ],
  { encoding: 'utf8' },
).trim()

export const verifySyntheticUser = (email: string): void => {
  composeExec('backend', ['php', 'bin/console', 'app:e2e:verify-user', email, '--no-interaction'])
}

export const redisPing = (): string => composeExec('redis', ['redis-cli', 'ping'])

export const marketplaceJourneyState = (orderId: string): MarketplaceJourneyState => {
  if (!/^[0-9a-f-]{36}$/i.test(orderId)) {
    throw new Error('A valid order UUID is required for the E2E database assertion.')
  }

  const sql = `
    SELECT json_build_object(
      'orderStatus', orders.status,
      'listingStatus', listings.status,
      'sellerCollectionItemStatus', seller_item.status,
      'buyerCollectionItemId', order_items.imported_collection_item_id,
      'buyerOwnerEmail', buyer.email,
      'paymentStatus', payments.status,
      'reviewCount', (SELECT count(*) FROM reviews WHERE reviews.order_id = orders.id)
    )::text
    FROM orders
    JOIN order_items ON order_items.order_id = orders.id
    JOIN listings ON listings.id = order_items.listing_id
    JOIN collection_items seller_item ON seller_item.id = listings.collection_item_id
    JOIN collection_items buyer_item ON buyer_item.id = order_items.imported_collection_item_id
    JOIN collections buyer_collection ON buyer_collection.id = buyer_item.collection_id
    JOIN users buyer ON buyer.id = buyer_collection.owner_id
    JOIN payments ON payments.id = orders.payment_id
    WHERE orders.id = '${orderId}'
  `
  const output = composeExec('postgres', [
    'psql',
    '-U',
    'cardnest',
    '-d',
    'cardnest_e2e',
    '-X',
    '--no-align',
    '--tuples-only',
    '--command',
    sql,
  ])
  if (output === '') {
    throw new Error(`No completed marketplace journey found for order ${orderId}.`)
  }

  return JSON.parse(output) as MarketplaceJourneyState
}
