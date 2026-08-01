# Product vision

## Purpose

CardNest is a collection-management and marketplace platform for trading cards. It gives a collector one reliable
place to record a physical card, understand where it is stored, decide which cards are still wanted, and—when
appropriate—offer an individual copy for sale.

The first supported card game is Pokémon. The product and its architecture use generic trading-card terminology so
additional games can be introduced without renaming the core concepts or coupling the domain to a trademark.

## MVP audience

The MVP is for private collectors who want to manage a personal collection and buy or sell individual cards. It is
not designed for professional sellers, shops, bulk inventory, or cross-border fulfilment in its first release.

## MVP outcomes

- A collector can create an account and manage their own collection of physical card instances.
- A collector can browse a generic catalog of series, sets, cards and variants.
- A collector can record storage locations and visual binder positions.
- A collector can maintain wishlists with language, variant, condition and maximum-price preferences.
- A seller can create, publish, update, cancel and expire fixed-price listings from eligible collection items.
- A buyer can add listings to a cart, complete a seller-split checkout through a fake payment provider, and track
  shipment and receipt.
- Buyers and sellers can leave one bounded review after a completed order.

## Domain language

| Term | Meaning |
| --- | --- |
| Card game | A collectible-card game supported by the catalog. |
| Card series | A high-level catalog grouping within a card game. |
| Card set | A released set within a series. |
| Catalog card | The canonical description of a printed card, independent of ownership. |
| Card variant | A printing or finish variation of a catalog card. |
| Collection item | One specific physical copy owned by a collector. |
| Storage location | A real-world place such as a box, shelf or cabinet. |
| Binder slot | One position on a visual binder page. |
| Listing | A fixed-price offer for one eligible collection item. |
| Order | A seller-specific purchase record created by checkout. |

## Scope guardrails

The MVP includes a JSON API, a Vue single-page application, local Docker Compose development, continuous
integration, a fake payment-provider interface, and a modular monolith. Money is represented with integer minor
units and an ISO currency code.

The MVP does not include real marketplace payments, seller payouts, auctions, price negotiation, card scanning,
grading submissions, sealed products, automated valuation, moderation tooling, disputes, chargebacks, or legal and
compliance certification. No production credentials are stored in the repository.

Card images and other third-party catalog media must not be used without a documented source and permission basis.
The backlog intentionally does not authorize copying copyrighted card artwork.

## Product principles

- Model a physical card once, then reference that item from collection, storage, binder, listing and order flows.
- Keep catalog data separate from an individual collector's private inventory.
- Make backend validation and authorization authoritative; frontend validation improves usability but is never the
  sole enforcement point.
- Prefer clear, reversible state transitions and durable history over destructive changes.
- Keep each MVP issue small, testable and traceable through the GitHub Project.
