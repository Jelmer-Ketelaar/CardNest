# Privacy and data-handling notes

This document describes the CardNest MVP implementation so engineers can make informed product, security and legal
choices. It is not a privacy notice, data-processing agreement, retention policy, or legal advice. A public service
requires a jurisdiction-specific review before processing real user data.

## Data inventory

| Category | Current records | Purpose and access boundary |
| --- | --- | --- |
| Account identity | E-mail address, password hash, roles, verification timestamp and account timestamps | Used to authenticate and authorize an account. E-mail addresses and hashes are not public API data. |
| Authentication and recovery artifacts | SHA-256 hashes of bearer tokens, e-mail-verification tokens and password-reset tokens; expiry and creation timestamps | The plaintext values are issued to the caller or delivery adapter and are not stored in the database. |
| Private collection data | Collection ownership, physical card details, purchase price/date, seller name, notes, duplicate state and archival state | Only the owner can access private collection records through the API. |
| Physical storage and binder data | Location names, hierarchy, binder layout, slots, placement history and optional placeholders | These can reveal where a physical collection is stored and remain owner-scoped. |
| Wishlist data | Desired cards, conditions, variants, price caps, priority and notes | Owner-scoped preference data. |
| Marketplace data | Seller, listing description, item price, delivery options, photo references, expiration and price history | Active listing details are public; seller-only physical collection references are not serialized publicly. The MVP stores photo references, not an object-storage upload implementation. |
| Orders and delivery | Buyer/seller identifiers, immutable order item and price snapshots, shipping address snapshot, status history and shipment dates | Visible only to the relevant order participant under owner-safe lookup rules. |
| Fake payment records | Fake provider name/reference, amount, currency, status and immutable charge transaction | The MVP does not collect card, bank-account or payout data. A fake provider must never be represented as a real payment service. |
| Reviews and reputation | Reviewer/reviewed user links, five scores, optional comment and timestamp | A public reputation response deliberately excludes order and payment identifiers. |
| Operational records | Structured application logs and CI output | Production logging is configured for JSON stderr output. Logs must not intentionally contain passwords, bearer tokens, full payment data or unnecessary personal data. |

Catalog card data is not user-owned data. It may contain source metadata and image references, but not a collector's
identity, collection or order information.

## Current privacy and security boundaries

- The API is stateless and uses bearer tokens. The frontend persists the token and minimal user profile in browser
  `localStorage`; therefore any public deployment needs an explicit XSS and Content Security Policy review before
  relying on this model.
- Symfony Security requires `ROLE_USER` for private `/api/v1` endpoints. Application use cases perform owner or
  participant checks and return owner-safe not-found responses where object existence should not be revealed.
- Passwords and issued tokens are hashed before persistence. Token expiry is implemented, but expiry is not a
  substitute for a product retention policy.
- The local e-mail adapters log a delivery request for development. A real deployment must select a reviewed delivery
  adapter and avoid placing recovery links or sensitive identifiers in broadly accessible logs.
- No real object storage, payment processor, seller verification, payout provider, analytics product, or notification
  provider is configured in this repository.

## Retention and lifecycle status

The MVP has no approved retention schedule, privacy-request workflow, data-export endpoint, or account-deletion
endpoint. Some domain records deliberately preserve history: order addresses, transactions, status history,
collection placement history, price history and reviews have business and potentially legal retention implications.
Those implications must be decided with the product owner and appropriate legal advice before data is deleted,
anonymized or retained for a fixed duration.

The engineering designs for the missing workflows are documented separately:

- [Future user-data export](data-export-design.md)
- [Future account deletion and anonymization](account-deletion-design.md)
- [Backups and restore verification](backups.md)

## Before a public deployment

- Define the controller/processor roles, purpose and legal basis for every production data category.
- Publish a user-facing privacy notice and a contact route for privacy requests.
- Decide retention periods, deletion/anonymization rules and exception handling for completed marketplace records.
- Review sub-processors, hosting region, object storage, e-mail delivery and observability access.
- Verify access logs, backups and support tools follow the approved access-control and retention rules.
- Treat any new tracking, analytics, support or payment integration as a new privacy review, not as an implementation
  detail.
