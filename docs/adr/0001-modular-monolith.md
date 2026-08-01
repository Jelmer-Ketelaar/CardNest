# ADR 0001: Use a modular monolith

- Status: Accepted
- Date: 2026-08-01

## Context

CardNest combines catalog data, personal collections, physical storage, binders, wishlists, marketplace listings, carts, orders, payments, shipping and reviews. These are distinct business capabilities, but the MVP team needs a small operational footprint, reliable transactions across related data, fast local development and a simple deployment model.

Splitting the MVP into networked services would introduce distributed authentication, versioned APIs, deployment coordination, observability and cross-service transaction concerns before the product has a demonstrated scale need. The highest-risk MVP flows — reserving a physical listing, charging the fake provider and creating per-seller orders — require strong consistency.

## Decision

Build CardNest as one deployable Symfony JSON API and one Vue 3 frontend in the same repository. The backend is a modular monolith:

- Each business module owns its domain types, use cases, persistence adapters and HTTP presentation code.
- Modules communicate through explicit PHP interfaces and application contracts, not internal HTTP calls.
- PostgreSQL is shared operationally but tables, Doctrine mappings and repository interfaces are owned by the module that defines them.
- Cross-module state changes use explicit application services, database transactions and locks where required.
- Shared code is limited to genuinely cross-cutting concepts such as `Money`, transaction abstraction and HTTP error mapping; it must not depend on a business module.

The normal internal layout is:

```text
backend/src/<Module>/
  Domain/
  Application/
  Infrastructure/
  Presentation/
```

## Consequences

### Positive

- Local setup, CI and deployment remain simple for the MVP.
- Checkout and physical-inventory transitions can use a single PostgreSQL transaction and deterministic locks.
- Refactoring a module boundary is cheaper while product understanding is still evolving.
- A shared codebase makes API contracts and frontend changes easy to review together.

### Negative

- Module boundaries must be actively protected in code review; a monolith does not enforce them automatically.
- One deployable means a bad dependency or runtime failure can affect all modules.
- Scaling individual capabilities independently is not an MVP optimization.

### Guardrails

- Do not introduce direct database access from one module into another module's owned persistence concerns.
- Do not return Doctrine entities from the HTTP boundary.
- Do not add shared helpers merely to avoid a small amount of duplication when they encode a module-specific rule.
- Treat external integrations as ports with deterministic fake adapters for local development and tests.

## Alternatives considered

### Microservices from the start

Rejected for the MVP. The operational and distributed-consistency cost exceeds the expected benefit before CardNest has stable traffic, team boundaries or independently scalable workloads.

### A single unlayered Symfony application

Rejected. It lowers short-term file-creation cost but makes ownership, testability and later extraction much harder.

### Event-only asynchronous integration between modules

Rejected as a default. Messenger is appropriate for background or retryable work, but synchronous transactional application calls are safer for immediate inventory and order invariants. A future external payment provider may require an outbox or saga increment; that is not required for the fake-provider MVP.
