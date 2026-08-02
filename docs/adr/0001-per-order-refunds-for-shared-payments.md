# ADR 0001: Model refunds per seller order for shared payments

## Context

Checkout creates one `Payment` for the buyer and one immutable `Order` for each seller. A buyer can therefore pay
for several independent seller orders in one charge. Refunding the whole payment when just one seller order is
cancelled would incorrectly refund items that remain paid and fulfilable.

## Decision

Create one `Refund` per cancelled paid order. The refund amount is the order's immutable `total` snapshot, and the
refund stores the payment, order, requesting participant, reason, provider reference, lifecycle status, and
timestamps. A successful refund also creates an immutable `PaymentTransaction` of type `refund` linked to the same
order.

The order moves through `paid → cancelled → refunded`; a `pending_payment` order stops at `cancelled` without a
refund. The shared payment remains `succeeded` while any paid order it funds is not refunded. It changes to
`refunded` only after every paid order funded by it is refunded; a paid or completed order keeps it `succeeded`.

The cancellation use case locks the order and shared payment, and PostgreSQL enforces one refund row and one
successful refund transaction per order. The fake provider receives the stable key `refund-order-{orderUuid}`.

## Alternatives considered

- Refund the full payment for any order cancellation: rejected because it affects other sellers' orders.
- Store only a payment-level refunded amount: rejected because it loses the order-level financial audit trail.
- Re-open the original listing: rejected because stale price, description, and condition must not become public.
- Add a saga/outbox now: rejected because the current provider is deterministic and local; it would add unused
  infrastructure. A remote provider needs an asynchronous, idempotent outbox/callback design later.

## Consequences

Refund reporting can trace every refund to a seller order, while payment-level status remains meaningful for
multi-seller checkouts. The database transaction rolls back the cancellation, collection-item release, and refund
records if the fake provider refuses a refund, leaving the order paid and retryable. Partial refunds, external
provider retries, chargebacks, and disputes remain out of scope.
