# CardNest API v1

All responses use JSON. Protected endpoints expect `Authorization: Bearer <token>`. Only health checks, registration,
login, read-only catalog endpoints, active marketplace listings, and public reputation profiles are public.

Validation errors always return `422` with the shape shown in [Errors](#errors). Ownership is enforced on every
protected endpoint: trying to read or change a record you don't own behaves exactly like it doesn't exist (`404`),
not `403`, so its existence can't be probed.

## Health

`GET /api/v1/health` returns `200` without authentication.

## Auth

`POST /api/v1/auth/register`

```json
{
  "email": "collector@example.com",
  "password": "A-strong-password-123",
  "passwordConfirmation": "A-strong-password-123"
}
```

Returns `201` and a user DTO. E-mail verification is queued through Messenger; the development adapter logs a
token hash instead of sending mail.

`POST /api/v1/auth/login` with `email` and `password` returns a user DTO and a revocable bearer token. Attempts are
rate-limited (5/minute) per normalized e-mail + client IP; exceeding it returns `429`.

`POST /api/v1/auth/logout` revokes the current bearer token and returns `204`.

`POST /api/v1/auth/forgot-password` accepts an e-mail address and always returns `202` for a valid request,
regardless of whether an account exists. This prevents account enumeration. For an existing account, CardNest stores
only a hash of a single-use reset token that expires after one hour and queues a reset message. The endpoint is
rate-limited to three requests per hour per normalized e-mail and IP address. It never returns or logs the plaintext
reset token.

```json
{ "email": "collector@example.com" }
```

Malformed e-mail input returns the standard `422` validation response. Submitting a new request for the same account
invalidates its previous reset token.

`POST /api/v1/auth/reset-password` confirms a reset with the queued token and a new password:

```json
{
  "token": "a 64-character lowercase hexadecimal token",
  "password": "A-new-strong-password-123",
  "passwordConfirmation": "A-new-strong-password-123"
}
```

It returns `204` on success. The token must be unexpired and can be used only once; invalid, expired, or previously
used tokens return `400 invalid_password_reset_token`. A successful reset revokes every existing bearer token for
the account, so the user must sign in again with the new password. Malformed tokens or passwords return `422`.

`GET /api/v1/me` requires a bearer token and returns the authenticated user's safe profile data:

```json
{
  "user": {
    "id": "0198d5ea-37c8-7dd4-a993-48ea916b7e19",
    "email": "collector@example.com",
    "roles": ["ROLE_USER"],
    "emailVerifiedAt": null,
    "createdAt": "2026-08-01T10:30:00+00:00"
  }
}
```

The response never contains a password hash, bearer token, reset token, or other credential data. Missing or invalid
authentication returns the standard `401` JSON error.

## Card catalog

Read-only and public. Describes official card data only — never a card a specific user owns.

`GET /api/v1/card-sets?page=1&perPage=20` lists sets alphabetically. `GET /api/v1/card-sets/{id}` returns one set.

`GET /api/v1/cards` searches catalog cards with optional query parameters:

| Parameter | Type | Notes |
| --- | --- | --- |
| `setId` | UUID | exact card-set match |
| `name` | string | case-insensitive, partial |
| `number` | string | case-insensitive, partial |
| `rarity` | string | case-insensitive, exact |
| `page` | int ≥ 1 | default `1` |
| `perPage` | int 1–100 | default `20` |

`GET /api/v1/cards/{id}` returns one card with its set, every registered variant, raw metadata and external source
references. Unknown set/card ids return `404`.

List endpoints share one pagination envelope:

```json
{ "items": [], "page": 1, "perPage": 20, "total": 0, "totalPages": 0 }
```

## My collection

Protected (`ROLE_USER`). A `CollectionItem` is one physical card a user owns: quantity is never a counter on a
single row — creating `quantity: 3` inserts three separate items, because CardNest tracks each physical card
individually (condition, purchase, storage, sale status can all diverge later).

`GET /api/v1/me/collection-items` lists the caller's own items. Query parameters, all optional:

| Parameter | Type | Notes |
| --- | --- | --- |
| `cardId` | UUID | exact catalog card |
| `setId` | UUID | exact card set |
| `cardName` | string | case-insensitive, partial |
| `cardNumber` | string | case-insensitive, partial |
| `language` | string | one of the language codes below |
| `variant` | string | one of the variant codes below |
| `condition` | string | one of the condition values below |
| `status` | string | `in_collection` (default), `for_sale`, or `archived` |
| `page` / `perPage` | int | same rules as the catalog |

`POST /api/v1/me/collection-items`

```json
{
  "catalogCardId": "9ddf1d18-42ef-4a52-93d3-ef82e40fac19",
  "variantId": "2ff18db6-72d1-4fcf-af48-87f84d4ab4b2",
  "language": "en",
  "condition": "near_mint",
  "quantity": 1,
  "purchasePrice": 350,
  "purchaseCurrency": "EUR",
  "purchasedAt": "2026-07-20",
  "sellerName": "Card shop",
  "notes": "Clean copy"
}
```

- `variantId` must be a variant registered on `catalogCardId`, otherwise `422 card_variant_unavailable`.
- An unknown `catalogCardId` returns `422 catalog_card_unavailable`.
- `purchasePrice` (integer minor units) and `purchaseCurrency` (currently only `EUR`) must be given together or
  both omitted.
- `quantity` (1–100) controls how many identical items are created in one call.

Returns `201` with `{"items": [...], "created": <n>}`.

After the collection items are constructed, every matching open wishlist goal is fulfilled by one unit per
physical item, capped at its desired quantity. The wishlist changes and new collection rows are flushed together;
an item only matches a configured maximum price when its purchase price was supplied.

`GET /api/v1/me/collection-items/{id}` returns one owned item, or `404 collection_item_not_found`.

`PATCH /api/v1/me/collection-items/{id}` partially updates an item — omitted fields are left untouched. Purchase
price/date use explicit `clearPurchasePrice` / `clearPurchasedAt` booleans to null them out (combining a clear flag
with a new value in the same request is a validation error). Changing an archived item returns
`409 collection_item_archived`. A published listing makes its physical item `for_sale`; direct collection updates
and archiving are then blocked with `409 collection_item_listed` until the listing is cancelled or expires.

`DELETE /api/v1/me/collection-items/{id}` archives the item (soft removal — history is kept, not hard-deleted) and
returns `204`.

### Example collection item

```json
{
  "id": "2ff18db6-72d1-4fcf-af48-87f84d4ab4b2",
  "card": {
    "id": "9ddf1d18-42ef-4a52-93d3-ef82e40fac19",
    "name": "Mudkip",
    "number": "19/25",
    "imageUrl": "https://placehold.co/245x342?text=Mudkip+19%2F25",
    "thumbnailUrl": "https://placehold.co/122x171?text=Mudkip+19%2F25",
    "set": { "id": "e403dd7c-31f6-4782-a98f-5bcac431179c", "name": "McDonald's Collection 2021" }
  },
  "language": "en",
  "variant": { "id": "3c6e...", "code": "holo" },
  "condition": "near_mint",
  "status": "in_collection",
  "purchase": { "price": { "amount": 350, "currency": "EUR" }, "purchasedAt": "2026-07-20", "sellerName": "Card shop" },
  "notes": "Clean copy",
  "duplicate": { "ownedQuantity": 3, "isDuplicate": true },
  "createdAt": "2026-07-20T10:00:00+00:00",
  "updatedAt": "2026-07-20T10:00:00+00:00"
}
```

`duplicate.ownedQuantity` counts the caller's own non-archived items that share the same card, language and variant,
including `for_sale` items; `isDuplicate` is simply `ownedQuantity > 1`.

## Physical storage

Protected (`ROLE_USER`) and owner-scoped. Storage locations form an adjacency-list hierarchy: `parentId` is nullable,
while `path` returns the complete root-to-location trail needed to answer “where is my card?”.

`GET /api/v1/me/storage-locations` lists the caller's locations with their path and current item count.

`POST /api/v1/me/storage-locations`

```json
{
  "name": "Binderkast",
  "type": "other",
  "parentId": "fd542305-16c4-46a8-a36d-d776b7e0437c"
}
```

`parentId` is optional and must identify another location owned by the caller. `GET
/api/v1/me/storage-locations/{id}` returns one location. `PATCH /api/v1/me/storage-locations/{id}` changes `name`,
`type`, or its parent; use `clearParent: true` to move it to the root. Cycles and cross-owner parents return `422`.

`POST /api/v1/me/collection-items/{itemId}/location` places or moves one physical card into a generic storage
location atomically:

```json
{ "locationId": "12403b06-a415-4f58-a45a-57c8ef748b16" }
```

The previous current placement is closed before the new placement is inserted, inside one database transaction.
`DELETE /api/v1/me/collection-items/{itemId}/location` unplaces the item and returns `204`.

`GET /api/v1/me/collection-items/{itemId}/location` returns the current placement. `GET
/api/v1/me/collection-items/{itemId}/location-history` additionally returns all past placements, newest first.
Archived collection items cannot be moved. Archiving an item automatically closes its current placement but keeps
the history. A placement response identifies its target with `type: storage_location` or `type: binder_slot`, and
only one of `location` and `binderSlot` is populated. Unknown and other users' item/location UUIDs all use the same
owner-safe `404` behavior.

## Visual binders

Protected (`ROLE_USER`) and owner-scoped. A binder owns immutable page dimensions and creates all page surfaces and
slots in the same transaction. `pageCount` is the number of physical pages; a double-sided binder exposes both a
`front` and `back` surface for each page.

`GET /api/v1/me/binders` lists binder summaries. `GET /api/v1/me/binders/{id}` returns the complete page and slot
layout, including current physical cards and optional placeholders.

`POST /api/v1/me/binders`

```json
{
  "name": "Water collection",
  "rows": 3,
  "columns": 3,
  "pageCount": 12,
  "doubleSided": true,
  "layoutStrategy": "set_number",
  "parentLocationId": "fd542305-16c4-46a8-a36d-d776b7e0437c"
}
```

Rows and columns are each limited to 1–6, physical pages to 1–100, and a binder to 2,000 slots. `parentLocationId`
is optional and links the binder to an owned generic storage location. `PATCH /api/v1/me/binders/{id}` can change
the name, planned layout strategy, or parent location (`clearParentLocation: true` unlinks it); dimensions are
immutable after creation.

`POST /api/v1/me/binders/{id}/slots/{slotId}/assign`

```json
{ "collectionItemId": "2ff18db6-72d1-4fcf-af48-87f84d4ab4b2" }
```

Assignment is atomic: the item's previous generic location or binder slot is closed before the new placement is
created. The database prevents an item from having multiple current placements and prevents a slot from holding
multiple current items. An occupied slot returns `409 binder_slot_occupied`; a reserved slot only accepts the
matching catalog card and otherwise returns `409 binder_slot_reserved`. `DELETE
/api/v1/me/binders/{id}/slots/{slotId}/assignment` removes the current card while preserving its placement history.

`PATCH /api/v1/me/binders/{id}/slots/{slotId}/placeholder` accepts exactly one of:

```json
{ "catalogCardId": "9ddf1d18-42ef-4a52-93d3-ef82e40fac19" }
```

```json
{ "label": "Energy divider" }
```

```json
{ "clear": true }
```

A catalog-card placeholder reserves a missing card slot without inventing a physical `CollectionItem`. Layout
strategies are stored as planner intent; automatic rearrangement is not part of this endpoint set.

## Wishlists

Protected (`ROLE_USER`) and owner-scoped. A wishlist records collection goals; a `WishlistItem` always refers to a
catalog card and never stands in for a physical `CollectionItem`.

`GET /api/v1/me/wishlists` lists summaries with overall and per-set progress. `POST /api/v1/me/wishlists` creates
one:

```json
{ "name": "Mudkip master set" }
```

`GET /api/v1/me/wishlists/{id}` returns the complete list and its goals. `PATCH /api/v1/me/wishlists/{id}` accepts
`{"name": "Water master set"}`. `DELETE /api/v1/me/wishlists/{id}` removes the private list and returns `204`;
catalog and collection records are unaffected.

`POST /api/v1/me/wishlists/{id}/items`

```json
{
  "catalogCardId": "9ddf1d18-42ef-4a52-93d3-ef82e40fac19",
  "language": "en",
  "variantId": "2ff18db6-72d1-4fcf-af48-87f84d4ab4b2",
  "minimumCondition": "excellent",
  "maxPrice": 800,
  "maxCurrency": "EUR",
  "desiredQuantity": 2,
  "priority": "wanted",
  "notes": "Clean copies only"
}
```

Only `catalogCardId` is mandatory; omitted match fields mean “any”. `maxPrice` is an integer in minor units and must
be paired with `maxCurrency` (`EUR` in the first release). A variant must belong to the selected card. Minimum
condition includes that grade and every better grade (for example, `excellent` accepts Excellent, Near Mint, and
Mint). Adding a goal immediately counts matching cards currently in the collection.

The response preserves `desiredQuantity` and exposes `fulfilledQuantity`, `remainingQuantity`, `completed`, and
`completedAt`. Registering new physical cards automatically advances every matching open goal, never beyond the
desired quantity. `DELETE /api/v1/me/wishlists/{id}/items/{itemId}` removes one goal and returns the updated wishlist.
Foreign wishlist and item UUIDs use owner-safe `404` responses.

## Marketplace listings

Public reads expose only active, non-expired listings. Seller writes are protected (`ROLE_USER`) and always resolve
the physical collection item through its owner; another user's listing or collection item is returned as `404`.

`GET /api/v1/listings` supports these optional filters:

| Parameter | Type | Notes |
| --- | --- | --- |
| `cardId` / `setId` | UUID | exact catalog card or set |
| `cardName` / `cardNumber` | string | case-insensitive, partial |
| `language` | string | card-language code |
| `variant` | string | catalog variant code |
| `condition` | string | exact physical-card condition |
| `minimumPrice` / `maximumPrice` | int ≥ 0 | item price in EUR minor units |
| `shippingMethod` | string | `letter_mail`, `parcel`, or `pickup` |
| `pickupAllowed` | boolean | when true, require free pickup; takes precedence over `shippingMethod` |
| `page` / `perPage` | int | defaults `1` / `24`; maximum page size `100` |

`GET /api/v1/listings/{id}` returns one public active listing. Unknown, draft, cancelled, or time-expired listings
return `404 listing_not_found`.

`GET /api/v1/me/listings` lists every listing owned by the caller, including drafts and closed states. `GET
/api/v1/me/listings/{id}` returns one owned listing and includes the private `collectionItemId` that is deliberately
omitted from public responses.

`POST /api/v1/listings` creates a seller-owned draft:

```json
{
  "collectionItemId": "2ff18db6-72d1-4fcf-af48-87f84d4ab4b2",
  "price": 750,
  "currency": "EUR",
  "description": "Clean copy from my collection",
  "letterMailPrice": 150,
  "parcelPrice": 595,
  "pickupAllowed": true,
  "frontImageUrl": "https://objects.example/listings/front.jpg",
  "backImageUrl": "https://objects.example/listings/back.jpg",
  "expiresAt": "2026-08-31T20:00:00+02:00"
}
```

The item price and every delivery price are integer minor units; only `EUR` is accepted. At least one delivery
option is required and pickup always costs zero. Front and back HTTPS references are both mandatory. This endpoint
stores references suitable for an S3-compatible object-storage adapter; upload signing and binary upload are not
part of this listing increment. `expiresAt` is optional, ISO 8601, and must be in the future.

`PATCH /api/v1/listings/{id}` changes an owned draft or active listing. Price/currency and front/back photo URLs must
be supplied as pairs. `clearDescription: true` and `clearExpiration: true` explicitly clear nullable fields. Shipping
methods are immutable in this API version. Every actual price change appends an immutable price-history entry.

`POST /api/v1/listings/{id}/publish` applies the `publish` workflow transition and changes the physical item from
`in_collection` to `for_sale`. A PostgreSQL partial unique index ensures that only one blocking listing can be
published for a physical item, including under concurrent requests. `POST /api/v1/listings/{id}/cancel` cancels an
owned draft or active listing; cancelling an active listing returns the item to `in_collection`.

When an active listing has `expiresAt`, publishing or changing that date schedules an `ExpireListing` message with
a Messenger delay. The Redis-backed worker applies the `expire` transition only if the listing is still active and
actually due, then releases the physical item. Stale and duplicate messages are safe no-ops. Run the worker with the
Compose `worker` service (included in `docker compose up`) or `php bin/console messenger:consume async` outside
Compose.

Every shipping method in a listing response contains both its own `price` and the complete `totalPrice`:

```json
{
  "price": { "amount": 750, "currency": "EUR" },
  "shippingMethods": [
    {
      "code": "letter_mail",
      "price": { "amount": 150, "currency": "EUR" },
      "totalPrice": { "amount": 900, "currency": "EUR" }
    },
    {
      "code": "pickup",
      "price": { "amount": 0, "currency": "EUR" },
      "totalPrice": { "amount": 750, "currency": "EUR" }
    }
  ]
}
```

## Cart, checkout and orders

All endpoints in this section are protected. A user has one private cart. Adding an item does not reserve stock;
availability and price are recalculated on every cart read and checked again under database locks at checkout.

`GET /api/v1/me/cart` returns the cart grouped by seller. An empty cart has `id: null`, no groups, `itemCount: 0`,
and `checkoutAvailable: false`. `POST /api/v1/me/cart/items` accepts `{"listingId": "<uuid>"}` and returns the
updated cart with `201`. `DELETE /api/v1/me/cart/items/{id}` removes one owned cart item and returns the updated cart.

```json
{
  "id": "2ff18db6-72d1-4fcf-af48-87f84d4ab4b2",
  "sellerGroups": [
    {
      "seller": { "id": "e403dd7c-31f6-4782-a98f-5bcac431179c" },
      "items": [
        {
          "id": "6d8974e7-2487-4516-b339-9fb2c1d96e50",
          "listing": {
            "id": "85fd006a-6748-440d-9dac-d238c749ead6",
            "status": "active",
            "seller": { "id": "e403dd7c-31f6-4782-a98f-5bcac431179c" },
            "card": {
              "id": "9ddf1d18-42ef-4a52-93d3-ef82e40fac19",
              "name": "Mudkip",
              "number": "19/25",
              "set": { "id": "ccd6acdb-7c74-4b4b-820a-84eb61519b79", "name": "McDonald's Collection 2021" }
            },
            "language": "en",
            "variant": "holo",
            "condition": "near_mint",
            "price": { "amount": 750, "currency": "EUR" },
            "description": null,
            "images": [
              { "id": "18c8a87a-03db-485f-82c9-84273f334ec2", "side": "front", "url": "https://objects.example/front.jpg" },
              { "id": "a897d212-47c9-468b-960c-781756ac897d", "side": "back", "url": "https://objects.example/back.jpg" }
            ],
            "shippingMethods": [
              {
                "code": "letter_mail",
                "price": { "amount": 150, "currency": "EUR" },
                "totalPrice": { "amount": 900, "currency": "EUR" }
              }
            ],
            "priceHistory": [
              {
                "previousPrice": null,
                "newPrice": { "amount": 750, "currency": "EUR" },
                "changedAt": "2026-07-31T19:00:00+00:00"
              }
            ],
            "expiresAt": null,
            "publishedAt": "2026-07-31T19:00:00+00:00",
            "createdAt": "2026-07-31T19:00:00+00:00",
            "updatedAt": "2026-07-31T19:00:00+00:00"
          },
          "addedPrice": { "amount": 750, "currency": "EUR" },
          "priceChanged": false,
          "available": true,
          "addedAt": "2026-07-31T20:00:00+00:00"
        }
      ],
      "itemSubtotal": { "amount": 750, "currency": "EUR" },
      "shippingMethods": [
        {
          "code": "letter_mail",
          "price": { "amount": 150, "currency": "EUR" },
          "totalPrice": { "amount": 900, "currency": "EUR" }
        }
      ]
    }
  ],
  "itemCount": 1,
  "checkoutAvailable": true
}
```

For multiple cards from one seller, a delivery method is offered only when every listing supports it. Its combined
shipping price is the highest shipping price configured on those listings, not the sum of all individual postage.
`priceChanged` compares the current listing price with the price recorded when the item entered the cart.

`POST /api/v1/me/cart/checkout` uses the fake provider and accepts exactly one delivery choice per seller plus an
NL/BE address snapshot:

```json
{
  "shippingMethods": {
    "e403dd7c-31f6-4782-a98f-5bcac431179c": "letter_mail"
  },
  "fullName": "Sam Collector",
  "addressLine1": "Dorpsstraat 1",
  "addressLine2": null,
  "postalCode": "1234 AB",
  "city": "Utrecht",
  "countryCode": "NL"
}
```

The checkout locks the buyer/cart first and then listing rows in UUID order. It revalidates ownership,
availability, expiration, seller grouping and shipping choices; creates a temporary reservation per listing, one
payment for the complete checkout and one order per seller; runs the replaceable fake `PaymentProvider`; and marks
the payment, orders, reservations and listings complete in one database transaction. A validation, availability or
payment failure rolls back every change and leaves the cart intact. The current fake provider succeeds immediately,
so reservation records finish as `completed` in the same request. It never receives card or bank credentials.

The response contains the shared payment and every newly created seller order:

```json
{
  "payment": {
    "id": "220f2c32-698a-4548-8a38-6b0dd237a88b",
    "status": "succeeded",
    "provider": "fake",
    "providerReference": "fake_220f2c32-698a-4548-8a38-6b0dd237a88b",
    "amount": { "amount": 900, "currency": "EUR" }
  },
  "orders": [
    {
      "id": "e6152016-56cb-45d3-9c69-3f8b3edb0152",
      "status": "paid",
      "buyer": { "id": "f9774749-9c03-4cf1-9f66-40b6c1d1e9a4" },
      "seller": { "id": "e403dd7c-31f6-4782-a98f-5bcac431179c" },
      "items": [],
      "shippingMethod": "letter_mail",
      "costs": {
        "items": { "amount": 750, "currency": "EUR" },
        "shipping": { "amount": 150, "currency": "EUR" },
        "serviceFee": { "amount": 0, "currency": "EUR" },
        "total": { "amount": 900, "currency": "EUR" }
      },
      "shippingAddress": {
        "fullName": "Sam Collector",
        "addressLine1": "Dorpsstraat 1",
        "addressLine2": null,
        "postalCode": "1234 AB",
        "city": "Utrecht",
        "countryCode": "NL"
      },
      "payment": {
        "id": "220f2c32-698a-4548-8a38-6b0dd237a88b",
        "status": "succeeded",
        "provider": "fake",
        "providerReference": "fake_220f2c32-698a-4548-8a38-6b0dd237a88b"
      },
      "statusHistory": [
        {
          "id": "7fd13e4f-ad34-4f58-b1f7-60e3c95cb0d2",
          "from": null,
          "to": "pending_payment",
          "actor": { "id": "f9774749-9c03-4cf1-9f66-40b6c1d1e9a4" },
          "occurredAt": "2026-07-31T20:00:00+00:00"
        },
        {
          "id": "1e81db2e-24d5-459d-a120-aa87191c585f",
          "from": "pending_payment",
          "to": "paid",
          "actor": { "id": "f9774749-9c03-4cf1-9f66-40b6c1d1e9a4" },
          "occurredAt": "2026-07-31T20:00:01+00:00"
        }
      ],
      "availableActions": {
        "markShipped": false,
        "confirmReceived": false
      },
      "paidAt": "2026-07-31T20:00:00+00:00",
      "completedAt": null,
      "createdAt": "2026-07-31T20:00:00+00:00",
      "updatedAt": "2026-07-31T20:00:00+00:00"
    }
  ]
}
```

`GET /api/v1/me/orders` returns `{"items": [...], "total": 1}` for orders where the caller is buyer or seller.
`GET /api/v1/me/orders/{id}` returns one such order. An unrelated user receives owner-safe
`404 order_not_found`. Every order DTO contains immutable `statusHistory` entries with the transition actor and
timestamp. `availableActions` is calculated for the authenticated participant; it is guidance for the UI, while
the backend independently enforces the same ownership and transition rules.

`POST /api/v1/me/orders/{id}/mark-shipped` is seller-only and changes a `paid` order to `shipped`. It creates the
order's one shipment record and returns the updated order DTO. A buyer or unrelated user receives owner-safe
`404 order_not_found`; a repeated or otherwise invalid transition returns `409 invalid_order_transition`.

`POST /api/v1/me/orders/{id}/confirm-received` is buyer-only and changes a `shipped` order to `completed`. In the
same transaction it marks the shipment delivered, archives every sold physical item in the seller's collection,
and closes any current storage or binder placement while preserving its location history. A seller or unrelated
user receives owner-safe `404 order_not_found`; an invalid repeat returns `409 invalid_order_transition`.

Every order item contains a caller-aware import state:

```json
{
  "collectionImport": {
    "available": true,
    "imported": false,
    "collectionItemId": null,
    "importedAt": null
  }
}
```

`POST /api/v1/me/orders/{id}/items/{itemId}/import-to-collection` is buyer-only and available exactly once after
the order is `completed`:

```json
{
  "language": "en",
  "condition": "near_mint",
  "storageLocationId": "d55f328a-d498-48d7-b422-08f635f13963",
  "notes": "Condition checked after receipt."
}
```

`language` and `condition` are required confirmations. `storageLocationId` and `notes` are optional; a supplied
location must belong to the buyer. The service copies catalog card, configured variant, item price, currency, and
payment date from the immutable order data, creates one separate buyer-owned physical `CollectionItem`, updates
matching wishlist progress, optionally places it in storage, and links it back to the order item in one transaction.
The seller's source record remains archived for sale and purchase history. The updated order DTO is returned with
`imported: true`, `importedAt`, and `collectionItemId` only when that new item is currently owned by the caller.

A seller or unrelated user receives owner-safe `404 order_not_found`; a foreign storage location returns
`404 storage_location_not_found`; a pre-completion or repeated import returns `409 invalid_collection_import`.
Refunds and cancellations remain outside this increment.

## Reviews and public reputation

`POST /api/v1/me/orders/{orderId}/reviews` lets the authenticated buyer or seller review the other participant after
the order is `completed`. The reviewed user is derived from the order; callers cannot provide or redirect it. Each
participant can submit exactly one immutable review per order.

```json
{
  "overallScore": 5,
  "descriptionAccuracyScore": 5,
  "packagingScore": 4,
  "communicationScore": 5,
  "shippingSpeedScore": 4,
  "comment": "Correct beschreven en zorgvuldig verpakt."
}
```

All five scores are required integers from 1 through 5. `comment` is optional, trimmed, and limited to 2,000
characters. The endpoint returns `201` with the review DTO, including its `orderId` for the caller's private UI.
An unrelated participant receives owner-safe `404 order_not_found`; a non-completed order returns
`409 invalid_review`; a second review by the same participant returns `409 review_already_exists`.

`GET /api/v1/me/reviews` returns the caller's authored reviews as `{"items": [...], "total": 1}`. These private
items include `orderId`, allowing the order page to show whether its review has already been submitted.

`GET /api/v1/users/{userId}/reputation?page=1&perPage=20` is public and returns aggregate scores plus paginated
reviews received by that user:

```json
{
  "user": {
    "id": "e403dd7c-31f6-4782-a98f-5bcac431179c",
    "memberSince": "2026-07-20T10:00:00+00:00"
  },
  "summary": {
    "reviewCount": 1,
    "averageOverall": 5,
    "averages": {
      "descriptionAccuracy": 5,
      "packaging": 4,
      "communication": 5,
      "shippingSpeed": 4
    }
  },
  "reviews": {
    "items": [
      {
        "id": "a2c3244a-a69d-49c4-98e8-838384a14ef2",
        "reviewer": { "id": "f9774749-9c03-4cf1-9f66-40b6c1d1e9a4" },
        "reviewedUser": { "id": "e403dd7c-31f6-4782-a98f-5bcac431179c" },
        "scores": {
          "overall": 5,
          "descriptionAccuracy": 5,
          "packaging": 4,
          "communication": 5,
          "shippingSpeed": 4
        },
        "comment": "Correct beschreven en zorgvuldig verpakt.",
        "createdAt": "2026-07-31T20:00:00+00:00"
      }
    ],
    "page": 1,
    "perPage": 20,
    "total": 1,
    "totalPages": 1
  }
}
```

Public review items deliberately omit `orderId`; no payment, address, listing, or other transaction reference is
exposed. Unknown users return `404 user_not_found`. `page` must be positive and `perPage` must be between 1 and 100.

## Closed vocabularies

| Concept | Values |
| --- | --- |
| Card language | `en`, `nl`, `ja`, `ko`, `de`, `fr`, `it`, `es`, `pt`, `zh` |
| Card condition | `mint`, `near_mint`, `excellent`, `good`, `light_played`, `played`, `poor` |
| Card variant | `normal`, `holo`, `reverse_holo`, `cosmos_holo`, `stamped`, `promo`, `first_edition`, `unlimited`, `shadowless`, `alternate_art`, `illustration_rare`, `special_illustration_rare`, `gold`, `rainbow`, `master_ball`, `poke_ball`, `other` |
| Collection item status | `in_collection`, `for_sale`, `archived` |
| Storage location type | `binder`, `box`, `top_loader_box`, `graded_card_box`, `display`, `safe`, `other` |
| Binder page side | `front`, `back` |
| Binder layout strategy | `set_number`, `pokedex_number`, `alphabetical`, `collectible_type`, `rarity`, `value`, `purchase_date`, `custom` |
| Wishlist priority | `low`, `normal`, `high`, `wanted` |
| Listing status | `draft`, `pending_review`, `active`, `reserved`, `sold`, `cancelled`, `expired`, `suspended` |
| Listing image side | `front`, `back` |
| Listing shipping method | `letter_mail`, `parcel`, `pickup` |
| Listing reservation status | `active`, `completed`, `released`, `expired` |
| Payment status | `pending`, `succeeded`, `failed`, `refunded` |
| Shipment status | `shipped`, `delivered` |
| Order status | `pending_payment`, `paid`, `preparing`, `shipped`, `delivered`, `completed`, `cancelled`, `refunded`, `disputed` |
| Card set status | `announced`, `active`, `out_of_print`, `unknown` |

## Errors

Every error response follows the same shape:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Controleer de ingevulde gegevens.",
    "details": {
      "email": ["This value is not a valid email address."]
    }
  }
}
```

`details` is only present for validation errors. Codes returned today:

| Code | Status | Meaning |
| --- | --- | --- |
| `validation_failed` | 422 | Request body or query parameters failed validation |
| `email_already_registered` | 409 | Registration e-mail is already in use |
| `invalid_credentials` | 401 | Login e-mail/password did not match |
| `authentication_required` / `authentication_failed` | 401 | Missing, invalid or expired bearer token |
| `collection_item_not_found` | 404 | Item doesn't exist, or isn't owned by the caller |
| `catalog_card_unavailable` | 422 | Referenced `catalogCardId` doesn't exist |
| `card_variant_unavailable` | 422 | Referenced `variantId` doesn't exist or doesn't belong to the card |
| `collection_item_archived` | 409 | Attempted to change an archived collection item |
| `collection_item_listed` | 409 | Attempted to change or archive a physical item currently for sale |
| `storage_location_not_found` | 404 | Location doesn't exist, or isn't owned by the caller |
| `invalid_storage_hierarchy` | 422 | Parent creates a cycle or belongs to another user |
| `binder_not_found` | 404 | Binder/slot doesn't exist, or isn't owned by the caller |
| `binder_slot_occupied` | 409 | Another physical item already occupies the target slot |
| `binder_slot_reserved` | 409 | Physical card doesn't match the catalog-card reservation |
| `invalid_binder_configuration` | 422 | Binder dimensions, parent, or slot configuration is invalid |
| `wishlist_not_found` | 404 | Wishlist doesn't exist, or isn't owned by the caller |
| `wishlist_item_not_found` | 404 | Wishlist item doesn't exist in an owned wishlist |
| `invalid_wishlist` | 422 | Wishlist or goal violates a domain invariant |
| `listing_not_found` | 404 | Listing doesn't exist, isn't public, or isn't owned by the caller |
| `listing_already_exists` | 409 | Physical item already has a blocking listing |
| `invalid_listing_transition` | 409 | Listing workflow does not allow the requested action |
| `invalid_listing` | 422 | Price, expiration, photos, shipping, or physical-item state violates a listing rule |
| `own_listing_purchase` | 409 | Buyer attempted to add or purchase their own listing |
| `listing_unavailable` | 409 | Listing was sold, reserved, cancelled, expired, or otherwise changed before checkout |
| `cart_item_not_found` | 404 | Cart item doesn't exist in the caller's cart |
| `cart_empty` | 409 | Checkout was requested without cart items |
| `invalid_cart` | 409 | Duplicate listing or cart-size invariant was violated |
| `invalid_shipping_selection` | 422 | Missing, extra, unsupported, or incompatible seller delivery choice |
| `order_not_found` | 404 | Order doesn't exist or caller is neither its buyer nor seller |
| `order_item_not_found` | 404 | Item doesn't exist in the buyer's referenced order |
| `invalid_order` | 422 | Address, totals, currency, seller grouping, or payment data violates an invariant |
| `invalid_order_transition` | 409 | Order or shipment is not in the required state for the requested participant action |
| `invalid_collection_import` | 409 | Order item is not completed, was already imported, or cannot preserve its card/variant identity |
| `user_not_found` | 404 | Public reputation was requested for an unknown user |
| `review_already_exists` | 409 | Caller already reviewed this order |
| `invalid_review` | 409 | Order is incomplete, participant pairing is invalid, or a review invariant was violated |
| `payment_failed` | 402 | Payment provider rejected checkout; transaction was rolled back |
| `request_failed` | varies | Fallback for other framework HTTP errors (e.g. plain `404` on an unknown route) |
| `internal_error` | 500 | Unexpected server error (logged server-side, never leaks details) |
