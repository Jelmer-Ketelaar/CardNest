# CardNest API v1

All responses use JSON. Protected endpoints expect `Authorization: Bearer <token>`.

## Health

`GET /api/v1/health` returns `200` without authentication.

## Register

`POST /api/v1/auth/register`

```json
{
  "email": "collector@example.com",
  "password": "A-strong-password-123",
  "passwordConfirmation": "A-strong-password-123"
}
```

Returns `201` and a user response DTO. E-mail verification is queued through Messenger; the development
adapter logs only a token hash and does not send mail.

## Login

`POST /api/v1/auth/login` with `email` and `password` returns a user response DTO and a revocable token.
Login attempts are rate-limited by normalized e-mail and client IP address.

## Logout

`POST /api/v1/auth/logout` revokes the current bearer token and returns `204`.

## Card sets

`GET /api/v1/card-sets?page=1&perPage=20` lists sets in alphabetical order. `page` starts at `1`; `perPage` accepts
values from `1` through `100`.

`GET /api/v1/card-sets/{id}` returns one set or a structured `404` error.

List responses use this pagination shape:

```json
{
  "items": [],
  "page": 1,
  "perPage": 20,
  "total": 0,
  "totalPages": 0
}
```

## Catalog cards

`GET /api/v1/cards` supports these optional query parameters:

- `setId`: exact card-set UUID;
- `name`: case-insensitive partial name;
- `number`: case-insensitive partial card number;
- `rarity`: case-insensitive exact rarity;
- `page`: positive page number;
- `perPage`: page size from `1` through `100`.

`GET /api/v1/cards/{id}` returns one catalog card with its set, variants, source metadata and external references.
Catalog cards describe official card data only; they never represent a physical card owned by a user.

Invalid filters return `422`. Unknown set and card identifiers return `404`.

## Errors

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
