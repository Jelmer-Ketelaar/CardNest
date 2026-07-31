# Contributing to CardNest

CardNest is built one module (or one use case inside a module) at a time, deliberately — not generated in bulk. If
you're adding to it, keep changes scoped the same way: a good change implements one thing end to end (domain rule,
persistence, endpoint, tests, docs) rather than laying groundwork for several modules at once.

See [docs/architecture.md](docs/architecture.md) for how the codebase is organized and [docs/api.md](docs/api.md)
for the current HTTP contract.

## Ground rules

These carry through every module, existing or future:

- **No microservices.** CardNest stays a modular monolith; modules talk to each other through PHP interfaces, not
  over the network.
- **Don't build ahead of what's asked.** Model the concepts a step needs, not everything the product spec eventually
  describes.
- **The backend is the only source of truth.** Never rely on frontend-only validation for a business rule.
- **Money is always an integer + currency.** Use `App\Shared\Domain\ValueObject\Money` (minor units, ISO currency
  code), never a float, in the database or in PHP.
- **Doctrine entities never leave the backend.** Controllers only ever return an explicit `*Response` DTO's
  `toArray()`.
- **Check ownership, not just role.** A logged-in user must never be able to read or change another user's data by
  guessing an id. Prefer failing with `404` over `403` so existence can't be probed (see
  `CollectionItemRepository::getForOwner()` for the pattern).
- **Closed vocabularies are backed enums**, mapped with Doctrine's `enumType`, not free-text columns — see
  `VariantCode`, `CardCondition`, `CardLanguage`.
- **External integrations sit behind an interface** with a fake/local implementation usable in development and
  tests (see `EmailVerificationSender`). Don't call a third-party API directly from application code.
- **Every non-trivial business rule has a test** that fails without the code that enforces it.
- **Reservations, payments and orders use database transactions.** Keep external providers behind an interface and
  preserve the deterministic row-lock order used by `CheckoutCart`.

## Project layout

```text
backend/src/<Module>/
  Domain/          entities, enums, value objects, repository interfaces — no framework code
  Application/     one class per use case, plus request/response DTOs
  Infrastructure/  Doctrine repository implementations, fixtures, adapters
  Presentation/    HTTP controllers
```

`backend/src/Controller`, `backend/src/Entity` and `backend/src/Repository` are unused leftovers from Symfony's
default skeleton (kept empty on purpose so Symfony Flex recipes don't recreate them) — all real code lives under a
module directory as shown above. `backend/src/Shared` holds cross-cutting building blocks (`Money`, the API
exception handler) that any module may depend on, but that must never depend back on a specific module.

Implemented modules today: `Identity`, `Catalog`, `Collection`, `Storage`, `Binder`, `Wishlist`, `Marketplace`,
`Order`, `Payment`, `Shipping`, `Review`, `Shared`.

## Adding a new module or endpoint

This is the workflow `Catalog` and `Collection` were both built with:

1. Scaffold `backend/src/<Module>/{Domain,Application,Infrastructure,Presentation}`.
2. **Domain**: write the entities (Doctrine attributes, `Uuid::v7()` ids generated in the constructor), enums for
   any closed vocabulary, and the repository *interfaces* the use cases will need. Keep invariants (e.g. "a variant
   must belong to its card") inside the entity, not the service layer.
3. Register the module's entity directory in `config/packages/doctrine.yaml` under `doctrine.orm.mappings`.
4. **Application**: request DTOs (validated with `Symfony\Component\Validator\Constraints`) and response DTOs (a
   `toArray()` method, nothing else), plus one class per use case.
5. **Infrastructure**: implement each repository interface with Doctrine, then alias interface → implementation in
   `config/services.yaml` (see the existing `App\Catalog\Domain\Repository\CardSetRepository` entry for the pattern).
6. **Presentation**: a controller per aggregate under `/api/v1/...`. Use `#[MapRequestPayload]` for JSON bodies and
   `#[MapQueryString]` for filters/pagination on `GET` endpoints; always give the `#[MapQueryString]` argument a
   default instance (e.g. `= new ListCardSetsRequest()`) so an empty query string still resolves.
7. If the endpoint should be reachable without a token, add it explicitly to `access_control` in
   `config/packages/security.yaml` — everything under `/api/v1` is `ROLE_USER`-protected by default, and only
   `GET` routes should ever be made public.
8. Map any new domain/application exception to a structured error in
   `App\Shared\Infrastructure\Http\JsonExceptionSubscriber` (HTTP status + machine-readable `code` + message).
9. Generate the migration — don't hand-write schema SQL:
   ```bash
   docker compose run --rm backend php bin/console doctrine:migrations:diff --no-interaction
   ```
   Then review the generated file by hand: add `CHECK` constraints for invariants Doctrine can't express (enum
   value lists, "total ≥ printed total", ...), and double-check cascade/`onDelete` behavior.
10. Update [docs/api.md](docs/api.md) for any HTTP contract change.

## Tests

Follow the three-tier split already used by every module:

- `tests/Unit/<Module>/...` — domain rules with no framework or database involved (entities, enums, value objects).
- `tests/Integration/<Module>/*RepositoryTest.php` — a Doctrine repository against a real PostgreSQL database
  (`KernelTestCase`, fetch the repository from the container).
- `tests/Integration/<Module>/*ApiTest.php` — full HTTP flows (`WebTestCase`), including the unhappy paths: invalid
  input, unauthenticated access, and accessing another user's data.

If the module ships fixtures, add a `*FixturesTest.php` that loads them and asserts the basic shape (row counts,
key example records) so a broken fixture fails CI instead of only being noticed locally.

Integration tests run through `DAMADoctrineTestBundle`, which wraps each test in a transaction that's rolled back
afterwards — write data directly in the test (or via the module's own repository/service), don't rely on fixtures
being present, and don't worry about cleaning up afterwards.

Before opening a PR:

```bash
docker compose run --rm -e APP_ENV=test backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm -e APP_ENV=test backend php bin/phpunit
docker compose run --rm backend vendor/bin/phpstan analyse --no-progress --memory-limit=512M
docker compose run --rm backend vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes
docker compose run --rm --no-deps frontend npm test
docker compose run --rm --no-deps frontend npm run build
docker compose run --rm --no-deps frontend npm run test:e2e
```

## Coding standards

Backend (enforced by `phpstan.neon` at level 8 and `.php-cs-fixer.dist.php`'s `@Symfony` ruleset):

- `declare(strict_types=1);` in every file; explicit return types everywhere.
- `final` classes and `readonly` properties/classes unless a class is specifically designed for extension.
- Dependency injection only — no service locators, no `new` for a collaborator inside a class that could take it
  as a constructor argument.
- No business logic in controllers or in Doctrine entities' framework glue; entities may (and should) contain their
  own invariants and behavior (see `CollectionItem::assertVariantBelongsToCard()`).

Frontend (see [frontend/README.md](frontend/README.md) for the full picture):

- TypeScript strict mode; avoid `any` without a comment explaining why.
- Vue 3 Single File Components with `<script setup lang="ts">` and the Composition API.
- Reuse the small hand-rolled components in `src/components` instead of introducing a UI kit.
- No business rule enforced only in the frontend — it must be re-validated by the API.

## Definition of done

A change is ready for review when:

- [ ] The behavior works and is covered by tests at the appropriate tier(s) above.
- [ ] Input is validated server-side and object ownership is checked.
- [ ] A migration is included (generated from the entity mapping, then reviewed).
- [ ] PHPUnit, PHPStan and PHP-CS-Fixer all pass; frontend type-checking and tests pass.
- [ ] No Doctrine entity is returned directly from an endpoint.
- [ ] Error responses are structured and understandable (see `JsonExceptionSubscriber`).
- [ ] `docs/api.md` (and `docs/architecture.md`, if the module map changed) are updated.
- [ ] No sensitive data (passwords, tokens, full card payment details once that exists) is logged.
- [ ] Edge cases are tested: not found, unauthorized, invalid input, duplicate/conflicting state, archived or
      otherwise immutable records.
