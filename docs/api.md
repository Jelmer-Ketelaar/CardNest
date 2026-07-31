# CardNest API v1

All responses use JSON. Protected endpoints expect `Authorization: Bearer <token>`. Only health checks, registration,
login, and read-only catalog endpoints are public.

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
| `status` | string | `in_collection` (default) or `archived` |
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

`GET /api/v1/me/collection-items/{id}` returns one owned item, or `404 collection_item_not_found`.

`PATCH /api/v1/me/collection-items/{id}` partially updates an item — omitted fields are left untouched. Purchase
price/date use explicit `clearPurchasePrice` / `clearPurchasedAt` booleans to null them out (combining a clear flag
with a new value in the same request is a validation error). Changing an archived item returns
`409 collection_item_archived`.

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

`duplicate.ownedQuantity` counts the caller's own **in-collection** items that share the same card, language and
variant; `isDuplicate` is simply `ownedQuantity > 1`.

## Closed vocabularies

| Concept | Values |
| --- | --- |
| Card language | `en`, `nl`, `ja`, `ko`, `de`, `fr`, `it`, `es`, `pt`, `zh` |
| Card condition | `mint`, `near_mint`, `excellent`, `good`, `light_played`, `played`, `poor` |
| Card variant | `normal`, `holo`, `reverse_holo`, `cosmos_holo`, `stamped`, `promo`, `first_edition`, `unlimited`, `shadowless`, `alternate_art`, `illustration_rare`, `special_illustration_rare`, `gold`, `rainbow`, `master_ball`, `poke_ball`, `other` |
| Collection item status | `in_collection`, `archived` |
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
| `request_failed` | varies | Fallback for other framework HTTP errors (e.g. plain `404` on an unknown route) |
| `internal_error` | 500 | Unexpected server error (logged server-side, never leaks details) |
