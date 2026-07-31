# CardNest API v1

All responses use JSON. Protected endpoints expect `Authorization: Bearer <token>`. Only health checks, registration,
login, read-only catalog endpoints, and read-only active marketplace listings are public.

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
| `request_failed` | varies | Fallback for other framework HTTP errors (e.g. plain `404` on an unknown route) |
| `internal_error` | 500 | Unexpected server error (logged server-side, never leaks details) |
