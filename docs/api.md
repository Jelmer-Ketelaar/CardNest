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
