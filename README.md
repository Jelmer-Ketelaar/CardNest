# CardNest

CardNest is a collection-management platform and marketplace for trading cards. It is being built as a **modular
monolith** from a detailed product specification, one module at a time, rather than generated all at once. "CardNest"
is a working title; the codebase deliberately avoids trademarked names in its domain model (`CatalogCard`, `CardSet`,
`CollectionItem`, ...) so the platform is never tied to a specific card brand at the code level, even though the
first release targets Pokémon-style trading cards.

## What problem does it solve?

Collectors currently spread the same information across many disconnected tools: one app for their collection, a
spreadsheet for purchase prices, another site for current values, a marketplace for selling, phone photos as
condition proof, and a note for missing cards. CardNest's core idea is:

> Every physical card is registered exactly once, as a single, unique digital object.

From that one registration, the same physical card can be added to a collection, placed in a binder or storage box,
flagged as a duplicate, put on a wishlist, listed for sale, sold, and tracked through its full price and ownership
history — without ever re-entering its details.

## Who it's for

The first release targets private Pokémon card collectors in the Netherlands and Belgium who collect individual
cards, chase full sets, buy and sell, store their cards in binders/boxes/top loaders, and want one place to see what
they own, where it physically is, and what it's worth. Professional sellers, card shops, graded-card collectors, and
other TCGs are explicitly out of scope for this first release.

## What you can do today

CardNest is implemented incrementally, one module at a time. This is the current state of the project:

### Accounts (Identity module)

- Register with e-mail and password (strength and confirmation validated server-side)
- Log in and receive a revocable bearer token (only a SHA-256 hash is stored; tokens expire after 30 days)
- Log out, which immediately revokes the token
- E-mail verification is modeled end-to-end — queued asynchronously through Symfony Messenger behind an
  `EmailVerificationSender` interface — but the development adapter only logs the message, so no real mail provider
  is required yet
- Login attempts are rate-limited per e-mail + IP address (5 attempts per minute)

### Card catalog (Catalog module)

- Browse card series and sets (`GET /api/v1/card-sets`), each with release date, language, printed/total card
  counts, and lifecycle status (announced, active, out of print, unknown)
- Search the catalog (`GET /api/v1/cards`) filtered by set, name, card number, or rarity, with pagination
- Look up a single set or card in full detail, including every variant it was printed in (holo, reverse holo,
  illustration rare, ...) drawn from a closed, configurable vocabulary instead of free text
- Every catalog card keeps a record of the external source(s) it was imported from (source name, external id, raw
  payload, last sync date), so a future import adapter can refresh data without silently overwriting manual
  corrections
- Catalog data is intentionally public and read-only. User ownership is modeled separately by the Collection module
- Seed data ships two real-shaped sets and twenty cards for local exploration, including the "Mudkip 19/25" example
  used throughout the product specification

### Physical collection (Collection module)

- Register one or up to one hundred copies of a catalog card in one action; each physical copy receives its own UUID
- Record language, controlled card variant, condition, purchase price in integer euro cents, purchase date, seller,
  and notes
- Browse and filter only your own collection by card, set, name, number, language, variant, condition, and status
- Detect duplicates by catalog card + language + variant while excluding archived cards from the owned count
- Update or archive an owned item; archived items remain queryable for history but become immutable
- Object-level authorization deliberately returns the same `404` for unknown and other users' private items

### Frontend shell

- Vue 3 + TypeScript single-page app with registration, login, dashboard, and an authenticated collection workspace
- Search the catalog, add one or more physical cards, switch between grid/list views, filter, edit, see duplicate
  counts, and archive an item from the collection workspace
- A small hand-rolled component layer (button, input, error message) instead of a full UI kit
- A typed API client and a Pinia store for authentication state

### Platform

- Dockerized backend (PHP / Symfony), frontend, PostgreSQL, and Redis
- A `/api/v1/health` endpoint for uptime checks
- Structured JSON error responses (`{"error": {"code", "message", "details"}}`) across the whole API
- Continuous integration that runs the backend and frontend test suites on every push

The full HTTP contract is documented in [docs/api.md](docs/api.md).

## What's not built yet

Everything below belongs to the product specification but is intentionally not implemented yet. Each will land as
its own module, in roughly this order:

- **Storage & Binder** — hierarchical storage locations, visual binder pages/slots, drag-and-drop placement, and
  placeholders for missing cards in an incomplete set
- **Wishlist** — tracking wanted cards per set/variant/condition/price and auto-matching them against purchases
- **Marketplace, Order & Payment** — listings, shopping cart and checkout, temporary stock reservations, a fake
  payment provider for development, and the full order lifecycle
- **Shipping, Review, Notification, Pricing, Moderation, Reporting** — supporting modules around the above
  (shipment tracking, buyer/seller reviews, price history, fraud signals, financial reporting, ...)

Trading, real payments, auctions, price negotiation, automated card scanning, grading submissions, sealed products,
and international shipping are explicitly out of scope for the MVP described in the product specification.

## Guiding principles

A few rules carry through every module, whether it exists yet or not:

- Register a physical card once, then reuse that registration everywhere: collection, binder, wishlist, listing,
  sale, and financial history.
- The backend is always the source of truth. The frontend never enforces a business rule on its own.
- Money is always an integer amount plus a currency code, never a floating-point number.
- Every endpoint checks object ownership, not just the caller's role — a regular user can only ever touch their own
  data.
- Catalog data (what a card *is*) and collection data (what a specific user *owns*) are strictly separate concepts,
  modeled as different entities.

## Architecture

CardNest is a **modular monolith**, not a set of microservices. Each module lives under `backend/src/<Module>` and
is internally layered the same way:

```text
backend/src/<Module>/
  Domain/          entities, value objects, and repository interfaces — no framework code
  Application/     use cases plus request/response DTOs
  Infrastructure/  Doctrine repositories, adapters, and fixtures
  Presentation/    HTTP controllers
```

Doctrine entities never cross the API boundary: controllers only ever return explicit response DTOs. Repository
interfaces live in `Domain`; their Doctrine-backed implementations live in `Infrastructure` and are wired together
in `config/services.yaml`, so persistence details can change without touching business logic.

Implemented modules today: `Identity`, `Catalog`, `Collection`, and `Shared`. Planned modules follow the same shape
(see "What's not built yet" above).

## Tech stack

| Layer | Choice |
| --- | --- |
| Backend | PHP 8.4, Symfony 7.4 (LTS), Doctrine ORM, Symfony Messenger/Security/Validator |
| Frontend | Vue 3 (Composition API, `<script setup>`), TypeScript (strict mode), Vite, Vue Router, Pinia |
| Database | PostgreSQL — relational columns for filterable/sortable data, `jsonb` only for source-specific metadata |
| Cache & queue | Redis, via Symfony Messenger |
| Testing | PHPUnit, PHPStan (level 8), PHP-CS-Fixer, Vitest, Playwright |
| Infrastructure | Docker Compose, GitHub Actions |

## Project layout

```text
cardnest/
  backend/     Symfony JSON API (see backend/src for the module breakdown above)
  frontend/    Vue 3 + TypeScript single-page app
  docker/      Dockerfiles for the backend and frontend images
  docs/        API contract and other reference documentation
  compose.yaml Local multi-service Docker Compose stack
```

## Run locally

Requirements: Docker with Docker Compose and Git.

```bash
cp .env.example .env
cp backend/.env.example backend/.env.local
cp frontend/.env.example frontend/.env.local
docker compose run --rm backend composer install
docker compose run --rm --no-deps frontend npm install
docker compose up -d postgres redis
docker compose run --rm backend php bin/console doctrine:database:create --if-not-exists
docker compose run --rm backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm backend php bin/console doctrine:fixtures:load --append
docker compose up backend frontend
```

Open the frontend at <http://localhost:5174>. The API is available at <http://localhost:8080/api/v1>; its health
endpoint is `/api/v1/health`.

## Quality checks

```bash
docker compose run --rm -e APP_ENV=test backend php bin/console doctrine:database:create --if-not-exists
docker compose run --rm -e APP_ENV=test backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm -e APP_ENV=test backend php bin/phpunit
docker compose run --rm backend vendor/bin/phpstan analyse --no-progress --memory-limit=512M
docker compose run --rm backend vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes
docker compose run --rm --no-deps frontend npm test
docker compose run --rm --no-deps frontend npm run build
docker compose run --rm --no-deps frontend npm run test:e2e
```

The frontend image includes Chromium and its operating-system dependencies for Playwright. Rebuild it after changing
the Playwright version in `frontend/package.json`.
