# CardNest MVP release notes

**Status:** Draft engineering release notes for the current MVP scope. They do not announce a public launch or claim
that a production environment, real payment provider, privacy review or legal approval is in place.

## What the MVP delivers

- Account registration, login/logout, password recovery and e-mail-verification abstractions using a Symfony JSON
  API and Vue 3 frontend.
- A public, read-only trading-card catalog with sets, cards, variants, filtering, pagination and local fixtures.
- Private collection management for individual physical cards, including integer-minor-unit purchase prices,
  conditions, duplicate detection and archival.
- Owner-scoped physical storage, visual binders, slots, placeholders and historical placements.
- Private wishlists that track desired/fulfilled quantities against collected cards.
- Fixed-price marketplace listings created from a physical collection item, public search, delivery options, price
  history, workflow states and expiration handling.
- Multi-seller cart and checkout flows that create one immutable seller order per group under a shared fake payment,
  with PostgreSQL locking that prevents two buyers from purchasing the same listing.
- Seller shipment confirmation, buyer receipt confirmation, collection archival/placement closure and one-time buyer
  collection import after completion.
- One-per-participant reviews on completed orders and a public, paginated reputation summary.
- Daily Cardmarket EUR price indications and history on catalog cards, with tracked wishlist/collection/listing
  refreshes through Symfony Scheduler, Redis Messenger, caching and distributed locks.

## Engineering foundations

- PHP 8.4, Symfony 7.4 LTS, Doctrine ORM, PostgreSQL, Redis/Messenger and a modular-monolith module layout.
- Vue 3, TypeScript, Vite, Pinia and Vue Router with Vitest and Playwright coverage.
- Docker Compose for local development and GitHub Actions for backend tests, static analysis, code style, frontend
  lint/type/build checks and browser tests.
- UUIDv7 public identifiers, explicit API DTOs, integer minor-unit money, structured JSON errors, object-level
  authorization and transaction/locking for stateful workflows.

## Known limitations and non-goals

- The payment adapter is fake. There are no real payments, seller payouts, payment credentials or verification flows.
- Disputes, chargebacks, moderation and fraud operations are not implemented.
- There are no partial refunds, partial item/quantity refunds or refund fees. Order cancellation and refunds are not
  released from the current `develop` branch.
- Notifications, reporting, trading, auctions, negotiation, card scanning, grading, sealed products, other
  TCGs and international shipping remain outside the MVP.
- External pricing remains an indication rather than guaranteed market value; production display requires a suitable
  provider agreement and legal approval.
- Data export and account deletion are documented designs, not product endpoints.
- This release record is not an accessibility or privacy certification; see the linked checklists for outstanding
  evidence and decisions.

## Release references

- [MVP release checklist](../release-checklist.md)
- [Security review](../security-review.md)
- [Accessibility review](../accessibility-review.md)
- [Privacy and data handling](../privacy.md)
- [Production-environment expectations](../production-environment.md)
- [Deployment checklist](../deployment-checklist.md)
