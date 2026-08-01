# Future account-deletion and anonymization design

CardNest does not implement account deletion in the MVP. Marketplace history means an account request cannot safely
be modeled as an unconditional `DELETE FROM users`. This document records the engineering decisions that must be made
before a deletion workflow is built.

## Terms that must remain distinct

| Operation | Meaning | MVP status |
| --- | --- | --- |
| Deactivation | Prevent future login and new activity while preserving the record for a defined review period. | Not implemented |
| Erasure | Remove data that has no valid retention need. | Not implemented |
| Anonymization | Replace identifying fields while retaining a transaction/history record where a valid requirement exists. | Not implemented |
| Retention hold | Preserve specific records because of an approved legal, fraud, dispute or accounting reason. | Not implemented |

No product or legal rule should use these terms interchangeably.

## Current model constraints

- A user owns private collections, physical-location history, binders, wishlists and listings.
- A user can be buyer or seller on an order; completed orders reference immutable order items, addresses, payment
  records, status history, shipments and reviews.
- Reviews intentionally point to reviewer and reviewed-user records. Deleting a user without a design would break
  reputation history and foreign-key integrity.
- Active listings, carts, pending checkout attempts and uncompleted orders need explicit handling before an account
  can be deactivated or erased.

## Proposed deletion-request workflow

1. Require an authenticated request and a recent re-authentication step. Never accept an arbitrary user id from a
   frontend request.
2. Create a private, auditable deletion request with a cooling-off period and a cancellation path.
3. Inventory active obligations: listings, reservations, carts, orders, reviews, support cases and any future disputes
   or payouts.
4. Prevent new listings, purchases and account changes while the request is being evaluated.
5. Apply product-approved cancellation/deactivation rules to active inventory before touching historical records.
6. Erase or anonymize only the categories approved for that request; preserve a minimal internal mapping only when an
   approved retention hold requires it.
7. Revoke authentication and recovery artifacts, remove browser sessions where applicable, and prevent new login.
8. Record the request outcome and reasons in an access-controlled audit trail without copying unnecessary personal
   data into the audit record.

## Decisions required before implementation

- Retention periods and legal basis for order addresses, payment records, tax/accounting records, reviews and fraud
  investigations.
- Whether a deleted seller's public listing/review name becomes a neutral label, a pseudonym, or is omitted.
- The handling of physical collection records and photos after a user confirms deletion.
- The escalation path for active orders, future disputes, chargebacks, seller payouts and moderation cases.
- How backup retention and restoration interact with deletion requests; see [backups.md](backups.md).

## Deliberately not implemented

This design does not add an endpoint, job, admin process, hard-delete migration, anonymization algorithm or claim of
compliance. It must be delivered as a dedicated, reviewed workflow after the above decisions are approved.
