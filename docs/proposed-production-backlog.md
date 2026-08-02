# CardNest proposed production backlog

Status: proposal only

Audit date: 2026-08-02

Repository baseline: `develop` at `c6935a2`

Scope: production readiness; no GitHub issues were created

## 1. Backlog contract

This backlog translates the production-readiness audit into small, testable work items. It is based on repository evidence, not on the product specification alone. Priorities mean:

- **P0** — blocks safe production or can cause money/data loss.
- **P1** — required for the first public production release.
- **P2** — important shortly after launch.
- **P3** — future improvement.

Sizes are deliberately bounded: **XS** is a few hours, **S** about one day, and **M** two to three engineering days. External decision/provider lead time is not included. No proposed issue is larger than M; larger outcomes are represented as epics. In each detailed proposal, the `###` heading is the **Title** metadata field and the following metadata line supplies the remaining project fields.

### Exact proposal totals

| Item | Count |
|---|---:|
| Epics | 17 |
| Concrete issues | 66 |
| P0 | 14 |
| P1 | 40 |
| P2 | 9 |
| P3 | 3 |

The 66 issue proposals are new work items. Existing issue [#198, “Manage focus in modal dialogs”](https://github.com/Jelmer-Ketelaar/CardNest/issues/198), is not duplicated; `CN-PROD-035` depends on it.

## 2. Recommended GitHub Project fields

Use the existing project and its statuses `Todo`, `In Progress`, `In Review`, and `Done`. Preserve the existing fields `Priority`, `Size`, `Type`, `Module`, `Phase`, and `Target date`; extend field options only after checking for exact-name matches.

Recommended values:

| Field | Values used here |
|---|---|
| Priority | P0, P1, P2, P3 |
| Size | XS, S, M for issues; XL for epics |
| Type | Epic, Bug, Feature, Task |
| Phase | A — Local complete, B — Internal staging, C — Closed beta, D — Controlled payments, E — Public release, Post-launch |
| Milestone | M1 Internal staging, M2 Closed beta, M3 Controlled payments, M4 Public release, M5 Post-launch |

Common labels: `production-readiness`, `security`, `data-integrity`, `payments`, `compliance`, `operations`, `performance`, plus one `module:*` and one `phase:*` label per issue. Do not create duplicates where an equivalent label already exists.

The current Project fields are single-select. Apply this exact import mapping:

- The first token in `Module A/B/C` is the primary Project `Module`; add that exact option if it is absent. Represent every secondary token only as a `module:<lowercase-token>` label.
- Metadata categories `Security`, `Operations`, `Tech debt`, and `Documentation` map to Project `Type: Task`; preserve the category with a same-named lowercase label. `Epic`, `Bug`, and `Feature` map directly.
- The proposed Phase A–E and Post-launch values require a Project schema extension before issue import. Do not coerce them to unrelated existing options.
- `Priority`, `Size`, `Phase`, and `Module` each receive exactly one value; milestones and dependencies are linked separately.

## 3. Epic index

### EPIC-01 — Complete account and identity management

**Metadata:** Priority P0 · Size XL · Type Epic · Module Identity · Phase B — Internal staging · Labels `production-readiness`, `epic`, `identity`, `module:identity`, `phase:B` · Milestone M2 Closed beta · Dependencies `EPIC-13`

- **Problem:** External users cannot safely verify, recover, configure, or close their accounts.
- **Current state:** Mail is logging-only, verification is not enforced, the reset UI is absent, and bearer credentials are stored in `localStorage`.
- **Desired outcome:** Identity flows are verified, recoverable, bilingual, privacy-aware, and use a hardened credential lifecycle.
- **Scope:** Child issues `004`, `005`, `014`, `016`, `017`, `037`, `055`, `063`, and `066`.
- **Acceptance criteria:** Every child required for closed beta/public launch is accepted with API, browser, and operating evidence.
- **Technical notes:** Keep Identity authoritative; frontend guards remain convenience controls.
- **Testing:** Cover verification, recovery, session expiry, profile/locale, export, and deletion request flows.
- **Security:** Preserve anti-enumeration, token hashing, object ownership, and credential revocation rules.
- **Operational:** Name owners for mail delivery, account support, and privacy-request SLAs.
- **Out of scope:** Social login, passkeys, and two-factor authentication.
- **Dependencies:** Production artifacts/staging from `EPIC-13`; provider/legal choices remain explicit gates.

### EPIC-02 — Complete card catalog and artist data

**Metadata:** Priority P2 · Size XL · Type Epic · Module Catalog · Phase C — Closed beta · Labels `production-readiness`, `epic`, `catalog`, `module:catalog`, `phase:C` · Milestone M5 Post-launch · Dependencies none

- **Problem:** Catalog reads and artist data will not scale cleanly beyond the imported baseline.
- **Current state:** Illustrator is free text; case-folded search, offset pagination, and lazy associations cause avoidable cost.
- **Desired outcome:** Catalog data is normalized, searchable, and served through bounded indexed projections.
- **Scope:** Child issues `038`, `039`, and `040`.
- **Acceptance criteria:** All child migrations, query budgets, APIs, and compatibility tests pass.
- **Technical notes:** Preserve catalog/owned-card separation and raw source attribution.
- **Testing:** Use representative 134k-card query plans and import regression fixtures.
- **Security:** Bound public inputs and do not expose raw provider payloads unintentionally.
- **Operational:** Monitor query latency, result counts, and migration duration.
- **Out of scope:** New external provider integrations and user-owned cards.
- **Dependencies:** None; sequence projection work before cursor/index rollout.

### EPIC-03 — Implement production image storage and delivery

**Metadata:** Priority P0 · Size XL · Type Epic · Module Images · Phase B — Internal staging · Labels `production-readiness`, `epic`, `images`, `security`, `module:images`, `phase:B` · Milestone M2 Closed beta · Dependencies `EPIC-13`

- **Problem:** Seller-controlled URLs are unsafe evidence and CardNest has no owned media pipeline.
- **Current state:** URL shape is the only check; storage, scanning, derivatives, and controlled delivery are absent.
- **Desired outcome:** Authorized first-party media is quarantined, inspected, scanned, transformed, and safely delivered.
- **Scope:** Child issues `006`, `053`, `041`, and `043`.
- **Acceptance criteria:** Listing media cannot publish before validation and a clean scan; derivative and attachment flows use owned objects.
- **Technical notes:** Use immutable object keys, checksums, explicit states, and replaceable storage/scanner adapters.
- **Testing:** Include malicious fixtures, ownership, cleanup, derivative, and delivery contract tests.
- **Security:** Enforce least-privilege storage, metadata stripping, safe response headers, and tenant ownership.
- **Operational:** Alert on quarantine/scanner backlog and define storage/cleanup retention.
- **Out of scope:** Catalog image licensing approval.
- **Dependencies:** Staging secrets and object storage from `EPIC-13`.

### EPIC-04 — Complete marketplace listing lifecycle

**Metadata:** Priority P0 · Size XL · Type Epic · Module Marketplace · Phase A — Local complete · Labels `production-readiness`, `epic`, `marketplace`, `data-integrity`, `module:marketplace`, `phase:A` · Milestone M2 Closed beta · Dependencies none

- **Problem:** Listing terminal transitions and operational review are not coherent under concurrency or delayed work.
- **Current state:** Checkout locks listings, cancel/expiry do not, and reconciliation/report handling is incomplete.
- **Desired outcome:** Listings retain one serialized lifecycle with recoverable delayed transitions and review intake.
- **Scope:** Child issues `001`, `018`, `028`, and `059`.
- **Acceptance criteria:** Concurrency invariants, reconciliation, user reporting, and moderator decisions are accepted.
- **Technical notes:** Use database transactions/locks and Symfony Workflow as the state authority.
- **Testing:** Cover checkout/cancel/expiry races, delayed duplicate work, and moderator authorization.
- **Security:** Preserve owner checks and redact buyer/reservation data from conflicts.
- **Operational:** Emit transition/reconciliation metrics and a review backlog SLA.
- **Out of scope:** Negotiation, auctions, and trades.
- **Dependencies:** None; `CN-PROD-001` is the first child.

### EPIC-05 — Complete order cancellation and refunds

**Metadata:** Priority P0 · Size XL · Type Epic · Module Order · Phase A — Local complete · Labels `production-readiness`, `epic`, `orders`, `refunds`, `module:order`, `phase:A` · Milestone M3 Controlled payments · Dependencies `CN-PROD-001`, `CN-PROD-002`

- **Problem:** Paid orders lack safe cancellation/refund semantics, immutable evidence, and a formal item transfer model.
- **Current state:** Refund enum scaffolding exists without workflow/provider support; order card facts remain mutable.
- **Desired outcome:** Each order can be evidenced, refunded per seller, tracked, and transferred without corrupting siblings.
- **Scope:** Child issues `003`, `020`, `034`, `047`, and `056`.
- **Acceptance criteria:** Fake and production-provider refund paths, snapshots, transfer, and shipment evidence pass invariant tests.
- **Technical notes:** Keep money in minor units and model shared-payment allocations explicitly.
- **Testing:** Include partial/repeated/concurrent refunds and post-sale ownership lineage.
- **Security:** Apply object-level authorization and never expose prior-owner private data.
- **Operational:** Audit each transition and document manual refund/recovery procedures.
- **Out of scope:** Chargeback adjudication and payouts.
- **Dependencies:** Listing serialization and idempotent payment foundations in `001`/`002`.

### EPIC-06 — Integrate production marketplace payments

**Metadata:** Priority P0 · Size XL · Type Epic · Module Payment · Phase A — Local complete · Labels `production-readiness`, `epic`, `payments`, `data-integrity`, `module:payment`, `phase:A` · Milestone M3 Controlled payments · Dependencies `CN-PROD-001`

- **Problem:** The synchronous fake-only payment boundary cannot safely process or reconcile real money.
- **Current state:** Provider calls occur while locks are held; attempts, webhooks, refunds/voids, and reconciliation are incomplete.
- **Desired outcome:** Provider operations are idempotent, durable, signed, auditable, and recoverable.
- **Scope:** Child issues `002`, `021`, `022`, `023`, `024`, and `056`.
- **Acceptance criteria:** Fault injection proves one financial side effect, durable outcomes, webhook replay safety, and reconciliation.
- **Technical notes:** Use short transactions and provider idempotency; do not attempt distributed transactions.
- **Testing:** Add adapter contracts, webhook fixtures, timeout/retry, reconciliation, and refund/void cases.
- **Security:** Never store card/bank data; isolate secrets and verify webhook authenticity.
- **Operational:** Provide stuck-attempt, mismatch, and provider-outage runbooks/alerts.
- **Out of scope:** Seller payouts and chargebacks.
- **Dependencies:** `CN-PROD-001`; deliberately no dependency on seller onboarding, avoiding a `022`↔`025` cycle.

### EPIC-07 — Implement seller onboarding and verification

**Metadata:** Priority P1 · Size XL · Type Epic · Module Identity · Phase D — Controlled payments · Labels `production-readiness`, `epic`, `seller-verification`, `module:identity`, `module:marketplace`, `phase:D` · Milestone M3 Controlled payments · Dependencies `EPIC-06`, `EPIC-11`, `EPIC-12`

- **Problem:** Any account can approach seller functionality without verified provider state or beta limits.
- **Current state:** Seller onboarding, eligibility policy, provider status, and transaction caps are absent.
- **Desired outcome:** Verified sellers enter through explicit onboarding and enforced controlled-release limits.
- **Scope:** Child issues `025` and `057`.
- **Acceptance criteria:** Provider state sync, eligibility checks, caps, and operator overrides are accepted.
- **Technical notes:** Separate provider onboarding state from CardNest roles and policy decisions.
- **Testing:** Cover status transitions, bypass attempts, thresholds, and concurrent limit enforcement.
- **Security:** Minimize KYC data and audit privileged overrides.
- **Operational:** Define manual review, provider outage, and limit-breach procedures.
- **Out of scope:** Direct storage of identity documents and seller payouts.
- **Dependencies:** Payment adapter, legal rules, and administration RBAC; `CN-PROD-022` does not depend on this epic.

### EPIC-08 — Implement notifications

**Metadata:** Priority P1 · Size XL · Type Epic · Module Notification · Phase C — Closed beta · Labels `production-readiness`, `epic`, `notifications`, `module:notification`, `phase:C` · Milestone M2 Closed beta · Dependencies `CN-PROD-004`, `CN-PROD-033`

- **Problem:** Product events have no durable user notification model, channels, or preferences.
- **Current state:** Only logging-only auth messages use Messenger; notification persistence/UI is absent.
- **Desired outcome:** Required events create durable intents delivered through controlled in-app/email channels.
- **Scope:** Child issues `026` and `058`.
- **Acceptance criteria:** Intent persistence, deduplication, delivery, preferences, locale, and retry evidence are accepted.
- **Technical notes:** Keep domain events separate from channel rendering/delivery.
- **Testing:** Cover duplicate events, channel failures, preferences, locale, and unread state.
- **Security:** Prevent cross-user delivery and sensitive content in subject lines/logs.
- **Operational:** Monitor failed transport, delivery age, bounce, and backlog.
- **Out of scope:** Push notifications and marketing campaigns.
- **Dependencies:** Production mail and Messenger reliability from `004`/`033`.

### EPIC-09 — Complete pricing and price history

**Metadata:** Priority P1 · Size XL · Type Epic · Module Pricing · Phase C — Closed beta · Labels `production-readiness`, `epic`, `pricing`, `performance`, `module:pricing`, `phase:C` · Milestone M2 Closed beta · Dependencies `CN-PROD-015`, `CN-PROD-033`

- **Problem:** Public reads can trigger provider writes and displayed history lacks CardNest completed-sale evidence.
- **Current state:** Cache misses refresh synchronously; current data is external asking-price information.
- **Desired outcome:** Refresh is bounded/asynchronous and price types, source, freshness, and samples are explicit.
- **Scope:** Child issues `027`, `042`, and `051`.
- **Acceptance criteria:** Public reads are side-effect free and internal sold observations exclude ineligible transactions.
- **Technical notes:** Keep provider adapters replaceable and money exact.
- **Testing:** Cover stale/failure cooldown, scheduler locks, eligibility, periods, and accessibility.
- **Security:** Rate-limit reads and prevent low-sample de-anonymization.
- **Operational:** Track freshness, provider failures, queue lag, and cache hit rate.
- **Out of scope:** Price prediction and investment advice.
- **Dependencies:** Global abuse controls and reliable queues.

### EPIC-10 — Implement moderation and disputes

**Metadata:** Priority P1 · Size XL · Type Epic · Module Moderation · Phase C — Closed beta · Labels `production-readiness`, `epic`, `moderation`, `module:moderation`, `module:disputes`, `phase:C` · Milestone M3 Controlled payments · Dependencies `EPIC-03`, `EPIC-08`, `EPIC-12`

- **Problem:** Users cannot report harm or resolve order disputes through reviewable operator workflows.
- **Current state:** Reports, disputes, evidence/messages, review queues, and risk signals are absent.
- **Desired outcome:** Intake, evidence, decisions, and later transparent risk signals are authorized and auditable.
- **Scope:** Child issues `028`, `029`, `052`, `059`, and `060`.
- **Acceptance criteria:** User and moderator flows, SLAs, evidence access, state transitions, and audit references pass.
- **Technical notes:** Separate report and financial-dispute aggregates; reuse secure media references.
- **Testing:** Cover ownership, role boundaries, evidence, concurrency, decisions, and false-positive review.
- **Security:** Scan attachments, minimize exposed personal data, and protect moderator actions.
- **Operational:** Define review/dispute queues, escalation, retention, and response targets.
- **Out of scope:** Automated permanent sanctions and machine-learning models.
- **Dependencies:** Secure media, notifications, and administration RBAC/audit.

### EPIC-11 — Implement privacy and GDPR workflows

**Metadata:** Priority P0 · Size XL · Type Epic · Module Privacy · Phase B — Internal staging · Labels `production-readiness`, `epic`, `privacy`, `compliance`, `module:privacy`, `phase:B` · Milestone M4 Public release · Dependencies none

- **Problem:** Privacy/legal documents are design notes without enforceable user or operator workflows.
- **Current state:** There is no approved data matrix, policy acceptance, request execution, consent ledger, retention job, or legal hold.
- **Desired outcome:** Legal gates and data lifecycles are versioned, user-visible, auditable, and operationally executable.
- **Scope:** Child issues `012`, `017`, `048`, `054`, `055`, `064`, and `065`.
- **Acceptance criteria:** Counsel-approved policy versions, request SLAs, consent evidence, lifecycle rules, cleanup, and holds are accepted.
- **Technical notes:** Prefer module-owned handlers coordinated by Privacy; preserve financial/legal records.
- **Testing:** Cover policy versions, export, deletion/anonymization, withdrawal, boundaries, holds, and retries.
- **Security:** Restrict operator access and prevent re-identification or export data leakage.
- **Operational:** Assign data controller/processor owners, review cadence, and request/hold runbooks.
- **Out of scope:** Legal advice generated by engineering.
- **Dependencies:** None; counsel decisions are explicit external gates.

### EPIC-12 — Build administration capabilities

**Metadata:** Priority P1 · Size XL · Type Epic · Module Administration · Phase B — Internal staging · Labels `production-readiness`, `epic`, `administration`, `authorization`, `module:administration`, `phase:B` · Milestone M2 Closed beta · Dependencies `CN-PROD-012`

- **Problem:** Privileged actions lack managed roles, operator UI boundaries, and immutable evidence.
- **Current state:** Roles are stored, but role management, administration routes, and audit persistence are absent.
- **Desired outcome:** Least-privilege administration and moderation actions are authenticated, authorized, and auditable.
- **Scope:** Child issues `028`, `029`, `030`, `059`, `060`, and `061`.
- **Acceptance criteria:** RBAC, privileged queues/actions, and immutable audit records pass misuse and tamper tests.
- **Technical notes:** Keep role policy centralized and audit writes append-only.
- **Testing:** Cover every role/object boundary, audit failure path, and operator action.
- **Security:** Require strong operator sessions and prohibit self-escalation/audit mutation.
- **Operational:** Define access reviews, break-glass controls, and audit retention/export.
- **Out of scope:** Full business-intelligence dashboards.
- **Dependencies:** Approved responsibilities/privacy rules from `CN-PROD-012`.

### EPIC-13 — Prepare production infrastructure

**Metadata:** Priority P0 · Size XL · Type Epic · Module Infrastructure · Phase B — Internal staging · Labels `production-readiness`, `epic`, `infrastructure`, `operations`, `module:infrastructure`, `phase:B` · Milestone M1 Internal staging · Dependencies none

- **Problem:** Development Compose cannot produce, deploy, protect, back up, or roll back production artifacts.
- **Current state:** PHP/Vite dev servers run mutable bind mounts and migrations execute during startup.
- **Desired outcome:** Immutable non-root artifacts deploy to isolated staging with TLS, secrets, backups, and release gates.
- **Scope:** Child issues `007`, `008`, `009`, `010`, and `031`.
- **Acceptance criteria:** Reproducible artifacts, staging deployment/rollback, restore evidence, and CI gates are accepted.
- **Technical notes:** Separate build, migration, deployment, and application startup responsibilities.
- **Testing:** Build/smoke/scan artifacts and execute network, migration, rollback, and restore checks.
- **Security:** Use workload identity, private services, TLS, secret isolation, and SBOM/scanning.
- **Operational:** Assign environment, backup, cost, deployment, and rollback owners.
- **Out of scope:** Multi-region active-active architecture.
- **Dependencies:** None.

### EPIC-14 — Implement monitoring and incident response

**Metadata:** Priority P0 · Size XL · Type Epic · Module Operations · Phase B — Internal staging · Labels `production-readiness`, `epic`, `observability`, `operations`, `module:operations`, `phase:B` · Milestone M1 Internal staging · Dependencies `CN-PROD-009`

- **Problem:** CardNest can appear healthy during dependency failure and has no actionable application/queue alerts.
- **Current state:** Health is static, logs stop at stderr, and Messenger has no failed transport or worker health.
- **Desired outcome:** Readiness, errors, queue failures, and incident response are observable and owned.
- **Scope:** Child issues `011` and `033`.
- **Acceptance criteria:** Dependency failures alter readiness, alerts reach an owner, and failed messages are recoverable.
- **Technical notes:** Use low-cardinality metrics and correlation IDs without personal data.
- **Testing:** Inject database, Redis, worker, and application failures in staging.
- **Security:** Redact credentials, tokens, addresses, and request bodies.
- **Operational:** Publish severity, on-call, escalation, retry, and incident review procedures.
- **Out of scope:** Full distributed tracing before service decomposition.
- **Dependencies:** Isolated staging from `CN-PROD-009`.

### EPIC-15 — Harden application security

**Metadata:** Priority P0 · Size XL · Type Epic · Module Shared · Phase B — Internal staging · Labels `production-readiness`, `epic`, `security`, `module:shared`, `phase:B` · Milestone M2 Closed beta · Dependencies `EPIC-13`

- **Problem:** Browser, credential, upload, abuse, supply-chain, and privileged-action controls are incomplete.
- **Current state:** Security headers are absent, credentials are long-lived in browser storage, uploads are external URLs, and limits focus on auth.
- **Desired outcome:** Layered controls cover browser, API, media, auth, CI, and privileged evidence.
- **Scope:** Child issues `005`, `006`, `013`, `014`, `015`, `031`, `053`, `061`, and `066`.
- **Acceptance criteria:** Threat-driven security tests and release scans pass with documented exceptions.
- **Technical notes:** Backend authorization is authoritative; keep controls centrally configurable.
- **Testing:** Include XSS/session, CSP, abuse, upload, dependency/container, and role-boundary tests.
- **Security:** Apply least privilege, secure defaults, secret redaction, and immutable evidence.
- **Operational:** Define vulnerability, secret rotation, abuse, and media incident response.
- **Out of scope:** Formal external penetration testing execution.
- **Dependencies:** Hardened runtime and staging from `EPIC-13`.

### EPIC-16 — Complete production test coverage

**Metadata:** Priority P0 · Size XL · Type Epic · Module Testing · Phase A — Local complete · Labels `production-readiness`, `epic`, `testing`, `module:testing`, `phase:A` · Milestone M2 Closed beta · Dependencies `CN-PROD-009`

- **Problem:** Strong unit/API coverage does not prove real browser-to-database, failure, accessibility, or release behavior.
- **Current state:** Playwright mocks every API route and key concurrency/accessibility scenarios are absent.
- **Desired outcome:** Critical flows and data invariants have deterministic full-stack and human-verifiable evidence.
- **Scope:** Child issues `001`, `003`, `019`, `032`, `035`, `036`, and `062`.
- **Acceptance criteria:** Required suites run in CI/staging and retain artifacts for every release candidate.
- **Technical notes:** Keep mocked UI tests fast while adding a separate real-stack lane.
- **Testing:** This epic owns concurrency, migration, browser, accessibility, and cross-browser evidence.
- **Security:** Test authorization and error redaction without production personal data.
- **Operational:** Define flake ownership, quarantine policy, artifact retention, and go/no-go use.
- **Out of scope:** Exhaustive device/browser combinations.
- **Dependencies:** Representative staging for `CN-PROD-032`; local invariant work can start earlier.

### EPIC-17 — Prepare public launch

**Metadata:** Priority P0 · Size XL · Type Epic · Module Shared · Phase E — Public release · Labels `production-readiness`, `epic`, `launch`, `module:shared`, `phase:E` · Milestone M4 Public release · Dependencies `EPIC-01`, `EPIC-03`, `EPIC-04`, `EPIC-05`, `EPIC-06`, `EPIC-07`, `EPIC-08`, `EPIC-09`, `EPIC-10`, `EPIC-11`, `EPIC-12`, `EPIC-13`, `EPIC-14`, `EPIC-15`, `EPIC-16`

- **Problem:** There is no evidence-based gate joining legal, seller, payments, accessibility, localization, privacy, and operations readiness.
- **Current state:** Local MVP evidence exists, but external-user and public-release controls remain distributed proposals.
- **Desired outcome:** One signed go/no-go record proves every P0/P1 launch gate or records an owned, approved exception.
- **Scope:** Launch-specific children `012`, `025`, `035`, `037`, `044`, `048`, `049`, `054`, `057`, and `062`–`065`, plus acceptance evidence from dependency epics.
- **Acceptance criteria:** All P0/P1 issues are done, or an authorized time-bounded exception names risk, owner, mitigation, and expiry.
- **Technical notes:** Generate the release evidence from immutable issue, CI, deploy, restore, and policy artifacts.
- **Testing:** Execute the release candidate matrix on production-like staging and retain results.
- **Security:** Require security/privacy approval and no unresolved critical/high launch vulnerability.
- **Operational:** Confirm owners, alerts, backups, incident/support/moderation/refund/privacy runbooks, and rollback.
- **Out of scope:** Deferred P2/P3 product improvements with documented non-blocking rationale.
- **Dependencies:** All listed launch-gating epics; EPIC-02 remains post-launch unless query budgets fail the release threshold.

### Epic completion rule

Create every epic with the exact metadata above and status `Todo`. An epic closes only when all launch-gating child issues are accepted with their required test and operational evidence; a deferred post-launch child must have an owner, milestone, and explicit non-blocking rationale.

## 4. Priority summary and recommended order

### P0 blockers — exact order

1. `CN-PROD-001` — Serialize listing cancellation and expiry with checkout.
2. `CN-PROD-002` — Make payment initiation idempotent and commit-safe.
3. `CN-PROD-003` — Implement per-order cancellation and refund state transitions.
4. `CN-PROD-007` — Build a hardened immutable backend runtime image.
5. `CN-PROD-008` — Build a hardened frontend and TLS reverse-proxy runtime.
6. `CN-PROD-009` — Provision an isolated staging environment with managed secrets.
7. `CN-PROD-004` — Replace logging-only account mail with a production transport.
8. `CN-PROD-005` — Enforce verified-email access to authenticated product APIs.
9. `CN-PROD-006` — Replace seller photo URLs with quarantined first-party uploads.
10. `CN-PROD-053` — Scan quarantined media before publication.
11. `CN-PROD-010` — Automate PostgreSQL backups and prove restoration.
12. `CN-PROD-011` — Add dependency-aware readiness and actionable error alerts.
13. `CN-PROD-012` — Establish and enforce the privacy/legal launch gate.
14. `CN-PROD-054` — Publish versioned policies and record acceptance.

### P1 requirements — recommended order after applicable P0 dependencies

1. `CN-PROD-019` — Align CardLanguage and wishlist persistence constraints.
2. `CN-PROD-036` — Enforce binder occupied-slot and placeholder consistency.
3. `CN-PROD-044` — Paginate unbounded orders, listings, wishlists, and storage APIs.
4. `CN-PROD-030` — Add least-privilege administration RBAC.
5. `CN-PROD-061` — Persist immutable audit records for privileged actions.
6. `CN-PROD-013` — Add browser security headers and a restrictive CSP.
7. `CN-PROD-014` — Implement a hardened backend credential lifecycle.
8. `CN-PROD-066` — Migrate the frontend to the hardened auth/session lifecycle.
9. `CN-PROD-049` — Add frontend API timeouts, cancellation, and centralized 401 handling.
10. `CN-PROD-015` — Add global API abuse and payload-size controls.
11. `CN-PROD-020` — Snapshot immutable card attributes on order items.
12. `CN-PROD-031` — Add security, migration, and container release gates to CI.
13. `CN-PROD-033` — Add a Messenger failed transport and queue health alerts.
14. `CN-PROD-018` — Reconcile listing expiry and reservation invariants.
15. `CN-PROD-032` — Add real full-stack auth and marketplace E2E tests.
16. `CN-PROD-016` — Complete the password-reset browser flow.
17. `CN-PROD-026` — Persist required notification intents and in-app delivery.
18. `CN-PROD-058` — Add notification channels and user preferences.
19. `CN-PROD-027` — Make pricing refresh asynchronous and protect public reads.
20. `CN-PROD-028` — Implement user report intake.
21. `CN-PROD-059` — Add the moderator queue and decision UI.
22. `CN-PROD-034` — Decide and implement post-sale physical-item ownership transfer.
23. `CN-PROD-035` — Fix the critical keyboard and screen-reader baseline.
24. `CN-PROD-062` — Record critical-flow WCAG and cross-browser evidence.
25. `CN-PROD-021` — Persist all payment attempts and failures in an audit ledger.
26. `CN-PROD-022` — Implement production charge operations in the marketplace payment adapter.
27. `CN-PROD-023` — Verify and idempotently process payment webhooks.
28. `CN-PROD-056` — Add provider refund and void operations.
29. `CN-PROD-024` — Reconcile provider payments with local orders.
30. `CN-PROD-025` — Implement seller provider onboarding and verification state.
31. `CN-PROD-057` — Enforce seller eligibility and controlled-beta transaction limits.
32. `CN-PROD-029` — Implement dispute intake and the core state machine.
33. `CN-PROD-060` — Add dispute evidence, messages, and operator UI.
34. `CN-PROD-037` — Complete editable profile and locale settings.
35. `CN-PROD-063` — Complete the NL/EN page and message migration.
36. `CN-PROD-017` — Implement personal-data export and request intake.
37. `CN-PROD-048` — Implement the privacy lifecycle policy registry.
38. `CN-PROD-064` — Persist a versioned consent ledger.
39. `CN-PROD-055` — Orchestrate account deletion and cross-module anonymization.
40. `CN-PROD-065` — Enforce retention cleanup and legal holds.

## 5. Detailed issue proposals

### CN-PROD-001 — Serialize listing cancellation and expiry with checkout

**Metadata:** Priority P0 · Size M · Type Bug · Module Marketplace/Order · Phase A — Local complete · Labels `production-readiness`, `data-integrity`, `concurrency`, `module:marketplace`, `phase:A` · Milestone M1 Internal staging · Dependencies none

#### Problem
Checkout can race with listing cancellation or delayed expiry and leave a paid order pointing to a non-sold listing or a seller item returned to collection.

#### Current state
`CheckoutCart` uses `DoctrineListingRepository::lockForCheckout()`. `CancelListing` and `ExpireListingHandler` load and update the same listing without that lock or an optimistic version. The two terminal writers can therefore apply stale state after checkout commits.

#### Desired outcome
All listing terminal transitions serialize on the same row and preserve one coherent listing, reservation, order, and collection-item state.

#### Scope
- Add a transactional, pessimistically locked repository path for cancel and expire.
- Re-check transition preconditions only after the lock is acquired.
- Define deterministic behavior when checkout already won.

#### Acceptance criteria
- Cancel-versus-checkout and expire-versus-checkout cannot produce contradictory persisted state.
- A paid order always retains a sold listing and archived/sold seller item.
- A losing cancel/expiry reports a stable domain conflict and performs no partial write.

#### Technical notes
Reuse the checkout lock order in `backend/src/Marketplace/Infrastructure/Persistence/DoctrineListingRepository.php`; do not add a process-local mutex. Keep the transaction boundary in the application service/handler.

#### Testing requirements
Add PostgreSQL integration tests with two connections/processes for both races, plus unit tests for the losing transition.

#### Security considerations
Do not leak buyer, order, or reservation identifiers in conflict responses.

#### Operational considerations
Log transition name, listing UUID, winner, and correlation ID without personal data.

#### Out of scope
Order refunds, payment-provider integration, and listing review states.

#### Dependencies
None. This is the first recommended change.

### CN-PROD-002 — Make payment initiation idempotent and commit-safe

**Metadata:** Priority P0 · Size M · Type Tech debt · Module Payment/Order · Phase A — Local complete · Labels `production-readiness`, `payments`, `data-integrity`, `module:payment`, `phase:A` · Milestone M1 Internal staging · Dependencies `CN-PROD-001`

#### Problem
An external charge inside the checkout database transaction can succeed while the database commit fails, producing an orphan charge; retries can duplicate it.

#### Current state
`CheckoutCart` calls the synchronous `PaymentProvider` while holding database locks. `PaymentInstruction` already carries a deterministic idempotency key and the fake reference derives from it, but no intent/attempt is committed before the side effect and no real provider behavior proves durable retry semantics.

#### Desired outcome
Every checkout has a durable payment intent and stable idempotency key, and a crash at any boundary can be retried without another charge.

#### Scope
- Persist a payment intent before the provider side effect.
- Persist and reuse the existing stable `PaymentInstruction` idempotency key across retries and recovery.
- Finalize orders/payment atomically from a recorded provider result.
- Make incomplete intents safely recoverable.

#### Acceptance criteria
- Repeating checkout with the same intent never requests a second charge.
- Simulated provider success followed by finalize failure remains recoverable.
- Long external calls do not hold listing locks.
- Existing fake-provider behavior remains testable.

#### Technical notes
Use explicit payment-intent states and a short local transaction per state change. Do not implement distributed transactions; design for idempotent reconciliation.

#### Testing requirements
Add fault-injection integration tests before/after provider response and retry tests asserting one provider invocation.

#### Security considerations
Never persist card/bank details or log provider secrets and payloads.

#### Operational considerations
Expose intent age/state metrics so stuck payment attempts can be reconciled.

#### Out of scope
Choosing the production provider, webhooks, payouts, and chargebacks.

#### Dependencies
Implement after the listing race is fixed; `CN-PROD-021` later expands durable payment-attempt auditing.

### CN-PROD-003 — Implement per-order cancellation and refund state transitions

**Metadata:** Priority P0 · Size M · Type Feature · Module Order/Payment · Phase A — Local complete · Labels `production-readiness`, `payments`, `workflow`, `module:order`, `phase:A` · Milestone M1 Internal staging · Dependencies `CN-PROD-002`

#### Problem
CardNest can create paid multi-seller orders but cannot safely cancel or refund one order.

#### Current state
`OrderStatus::Refunded` and `PaymentStatus::Refunded` exist, while `workflow.yaml`, `PaymentProvider`, transaction types, persistence, and APIs have no complete refund path. `docs/adr/0001-per-order-refunds-for-shared-payments.md` is design only.

#### Desired outcome
An authorized cancellation/refund produces explicit order, payment-allocation, listing/item, and transaction history without refunding unrelated seller orders.

#### Scope
- Add allowed cancellation/refund transitions and authorization rules.
- Model a per-order refund amount against the shared payment.
- Extend the fake provider with idempotent refund behavior.
- Add request/response DTOs and seller/buyer/admin-safe endpoints as approved by policy.

#### Acceptance criteria
- Refund amount is integer minor units, positive, and cannot exceed the refundable order amount.
- A repeated command is idempotent.
- Shared-payment sibling orders remain unchanged.
- State/history records who/what initiated the transition.

#### Technical notes
Follow the approved ADR but close any unresolved ownership/policy questions before coding; use a database constraint for cumulative refund bounds where practical.

#### Testing requirements
Cover full, partial, repeated, over-limit, unauthorized, and concurrent refunds in domain/API/integration tests.

#### Security considerations
Apply object-level authorization and prevent client-controlled provider references or refund totals.

#### Operational considerations
Emit a structured audit event and a later notification event for every result.

#### Out of scope
Chargebacks, seller payouts, dispute adjudication, and real-provider implementation.

#### Dependencies
Requires the idempotent payment boundary from `CN-PROD-002`; production-provider support is added in `CN-PROD-022`.

### CN-PROD-004 — Replace logging-only account mail with a production transport

**Metadata:** Priority P0 · Size S · Type Feature · Module Identity · Phase B — Internal staging · Labels `production-readiness`, `identity`, `email`, `module:identity`, `phase:B` · Milestone M1 Internal staging · Dependencies `CN-PROD-009`

#### Problem
Users cannot receive verification or password-reset links outside developer logs.

#### Current state
Service aliases select `LoggingEmailVerificationSender` and `LoggingPasswordResetSender` unconditionally. The logging verification sender includes a raw email address and token hash in application logs.

#### Desired outcome
Non-development environments send expiring links through a configured provider; development retains a safe non-delivery adapter.

#### Scope
- Add a Symfony Mailer/provider-backed implementation for both interfaces.
- Select adapters by environment.
- Create NL templates with plain-text fallback and absolute frontend URLs.
- Remove raw email/token material from logs.

#### Acceptance criteria
- Staging receives both message types end-to-end.
- Production fails configuration validation when provider/URL settings are absent.
- Delivery failures retry through Messenger and do not expose tokens in logs.

#### Technical notes
Keep provider details behind the existing sender interfaces. Store secrets outside `.env` files committed to Git.

#### Testing requirements
Use an in-memory mailer for integration tests; test URL expiry/encoding and failed delivery retry.

#### Security considerations
Links remain single-use and hashed at rest; redact recipient/token data in logs and monitoring.

#### Operational considerations
Document bounce, suppression, rate-limit, and provider-outage handling.

#### Out of scope
Marketing mail, newsletters, and push notifications.

#### Dependencies
Requires staging secrets/provider decision from `CN-PROD-009`.

### CN-PROD-005 — Enforce verified-email access to authenticated product APIs

**Metadata:** Priority P0 · Size S · Type Security · Module Identity/Shared · Phase B — Internal staging · Labels `production-readiness`, `security`, `authorization`, `module:identity`, `phase:B` · Milestone M1 Internal staging · Dependencies `CN-PROD-004`

#### Problem
An unverified account can log in and use authenticated features, defeating ownership/account-recovery controls and increasing abuse risk.

#### Current state
The real browser smoke test registered an account, logged in before verification, and accessed private navigation. `LoginUser` and API authorization do not enforce `emailVerifiedAt`.

#### Desired outcome
Unverified users can only authenticate for verification/account-recovery actions and receive a stable machine-readable denial elsewhere.

#### Scope
- Define the allowlist for unverified principals.
- Enforce the rule centrally in backend authorization/authentication.
- Add frontend handling and a resend-verification path.

#### Acceptance criteria
- Private collection, binder, wishlist, listing, cart, order, and account APIs reject unverified users.
- Verification/resend/logout remain available and rate-limited.
- A verified user immediately gains normal access without re-registration.

#### Technical notes
Backend enforcement is authoritative; route guards are convenience only. Return the existing structured error envelope with a stable code.

#### Testing requirements
Add functional tests for every firewall/access-control category and one real browser verification-gate flow.

#### Security considerations
Avoid account-enumeration differences and revoke/rotate credentials if policy requires it on verification.

#### Operational considerations
Track aggregate denial/resend rates without storing addresses.

#### Out of scope
Two-factor authentication and seller KYC.

#### Dependencies
Do not enforce outside development until `CN-PROD-004` delivers usable mail.

### CN-PROD-006 — Replace seller photo URLs with quarantined first-party uploads

**Metadata:** Priority P0 · Size M · Type Security · Module Images/Marketplace · Phase B — Internal staging · Labels `production-readiness`, `security`, `uploads`, `module:images`, `phase:B` · Milestone M2 Closed beta · Dependencies `CN-PROD-009`

#### Problem
Arbitrary HTTPS seller URLs expose buyers to tracking, replaced/inappropriate content, decompression payloads, and unverifiable evidence.

#### Current state
`ListingPhotoReference` checks only URL shape/scheme. There is no owned object storage, content inspection, malware scan, immutable object key, or upload authorization.

#### Desired outcome
Listing photos are private while quarantined and become displayable only after server-side validation and a clean scan.

#### Scope
- Add an object-storage interface and per-user upload authorization.
- Validate magic bytes, MIME, byte/pixel limits, and supported formats.
- Strip metadata, scan files, and persist immutable storage references/status.
- Require approved front/back objects before listing publication.

#### Acceptance criteria
- Remote arbitrary URLs cannot be attached to new listings.
- Invalid, oversized, polyglot, or scan-failed objects never become public.
- Users cannot attach another user's upload.
- Failed/orphaned quarantine objects have a cleanup path.

#### Technical notes
Use S3-compatible keys, checksums, short-lived signed operations, and explicit states (`pending`, `clean`, `rejected`). Never trust browser MIME.

#### Testing requirements
Add adapter contract tests, authorization/API tests, representative malicious-file fixtures, and cleanup tests.

#### Security considerations
Apply least-privilege bucket policy, encryption at rest, metadata stripping, and safe `Content-Disposition`/`Content-Type` delivery.

#### Operational considerations
Alert on scanner backlog/failure and storage errors; define quarantine retention.

#### Out of scope
Catalog image licensing and responsive derivatives (`CN-PROD-041`).

#### Dependencies
Requires an object-storage/scanner decision and secrets supplied through `CN-PROD-009`.

### CN-PROD-007 — Build a hardened immutable backend runtime image

**Metadata:** Priority P0 · Size M · Type Operations · Module Infrastructure/Backend · Phase B — Internal staging · Labels `production-readiness`, `infrastructure`, `containers`, `module:shared`, `phase:B` · Milestone M1 Internal staging · Dependencies none

#### Problem
The current backend container is a mutable developer runtime, unsuitable for reproducible deployment.

#### Current state
`docker/backend/Dockerfile` uses a CLI Alpine image, bind-mounted source, root execution, and the PHP built-in server; Compose applies migrations during application startup.

#### Desired outcome
CI builds a pinned, non-root, immutable PHP-FPM runtime containing only production dependencies and no automatic migration side effect.

#### Scope
- Add multi-stage dependency/build/runtime targets.
- Pin base-image digest and required extensions.
- Run as a non-root user with read-only application files.
- Add an explicit entrypoint and separate migration command/job.

#### Acceptance criteria
- Image starts without source bind mounts or network dependency installation.
- `composer install --no-dev --classmap-authoritative` is represented in the build.
- Runtime is non-root and exposes a process health signal.
- Rebuilding the same revision is reproducible and scanable.

#### Technical notes
Keep the development target/Compose workflow intact; do not put secrets in layers.

#### Testing requirements
CI builds and smoke-tests the image, runs container structure assertions, and scans high/critical CVEs.

#### Security considerations
Minimize packages/capabilities, set a read-only filesystem where possible, and generate an SBOM.

#### Operational considerations
Tag images by commit digest and retain the previous deployable revision for rollback.

#### Out of scope
Cloud provider provisioning and frontend runtime.

#### Dependencies
None; feeds `CN-PROD-009` and `CN-PROD-031`.

### CN-PROD-008 — Build a hardened frontend and TLS reverse-proxy runtime

**Metadata:** Priority P0 · Size M · Type Operations · Module Infrastructure/Frontend · Phase B — Internal staging · Labels `production-readiness`, `infrastructure`, `tls`, `module:shared`, `phase:B` · Milestone M1 Internal staging · Dependencies `CN-PROD-007`

#### Problem
Vite's development server and separate backend origin do not provide a production asset/API boundary, TLS, or controlled headers.

#### Current state
`docker/frontend/Dockerfile` installs a development/test toolchain and Compose runs Vite from a bind mount. No Nginx/Caddy production configuration exists.

#### Desired outcome
A non-root immutable image serves hashed static assets and proxies same-origin `/api` traffic over an HTTPS-terminated production boundary.

#### Scope
- Add a production Vite build stage and minimal web-server runtime.
- Configure SPA fallback, immutable asset caching, API proxying, upload/body/time limits, and forwarded-header trust.
- Define TLS termination and HTTP-to-HTTPS redirect behavior.

#### Acceptance criteria
- No Node/Vite dev server is present in the production runtime.
- Deep links work; hashed assets receive immutable caching while `index.html` does not.
- API errors/timeouts preserve structured responses.
- Staging is reachable only over HTTPS.

#### Technical notes
Coordinate CSP/security headers with `CN-PROD-013`; avoid duplicating conflicting header ownership.

#### Testing requirements
Build/smoke-test the image and verify routes, cache headers, proxy limits, redirects, and Web API calls.

#### Security considerations
Restrict trusted proxy ranges and prevent direct backend exposure.

#### Operational considerations
Expose web-server access/error logs in structured form and a lightweight liveness endpoint.

#### Out of scope
CDN selection and responsive media generation.

#### Dependencies
Align runtime conventions with `CN-PROD-007`.

### CN-PROD-009 — Provision an isolated staging environment with managed secrets

**Metadata:** Priority P0 · Size M · Type Operations · Module Infrastructure · Phase B — Internal staging · Labels `production-readiness`, `infrastructure`, `secrets`, `module:shared`, `phase:B` · Milestone M1 Internal staging · Dependencies `CN-PROD-007`, `CN-PROD-008`

#### Problem
There is no deployable staging environment that resembles production or safely hosts integrations/test data.

#### Current state
Only local Docker Compose exists, with development defaults and mutable volumes; `docs/production-environment.md` explicitly describes a target rather than deployed infrastructure.

#### Desired outcome
An isolated, repeatable staging environment runs production artifacts with separate database, Redis, object storage, DNS/TLS, and managed secrets.

#### Scope
- Add reviewed infrastructure definitions for staging.
- Configure environment-specific identities, networking, encryption, secrets, and least privilege.
- Deploy only immutable image digests.
- Create documented test-account/data reset rules.

#### Acceptance criteria
- No local/default secret is accepted by staging startup validation.
- Database/Redis/storage are private and encrypted in transit/at rest where supported.
- A clean deployment and rollback can be reproduced from CI artifacts.
- Production and staging credentials/data cannot cross.

#### Technical notes
Provider selection is an external decision; keep provider-specific code isolated and record it in an ADR.

#### Testing requirements
Run an automated post-deploy health/API smoke and validate network/secret policies.

#### Security considerations
Use workload identity or short-lived credentials; forbid secrets in Git, images, build logs, or frontend variables.

#### Operational considerations
Assign environment owner, cost alerts, access-review cadence, and change log.

#### Out of scope
Production tenant provisioning and public DNS cutover.

#### Dependencies
Requires immutable artifacts from `CN-PROD-007` and `CN-PROD-008`; unblocks mail, storage, backups, monitoring, and E2E.

### CN-PROD-010 — Automate PostgreSQL backups and prove restoration

**Metadata:** Priority P0 · Size M · Type Operations · Module Infrastructure/Database · Phase B — Internal staging · Labels `production-readiness`, `backup`, `disaster-recovery`, `module:shared`, `phase:B` · Milestone M1 Internal staging · Dependencies `CN-PROD-009`

#### Problem
CardNest has no executed backup job, defined RPO/RTO, or restore evidence; collection/order history could be irrecoverably lost.

#### Current state
`docs/backups.md` describes expectations only. Local Postgres has a volume, while no managed snapshots, WAL/PITR, off-site retention, encryption, or restore drill is configured.

#### Desired outcome
Automated encrypted backups meet approved RPO/RTO and a clean staging restore proves recoverability.

#### Scope
- Agree and record RPO, RTO, retention, ownership, and restore authority.
- Configure snapshots plus point-in-time recovery where supported.
- Add backup success/freshness monitoring.
- Execute and document a restore drill with integrity checks.

#### Acceptance criteria
- Backup jobs run automatically and failures alert an owner.
- A fresh environment restores within RTO to a point within RPO.
- Restored migrations, row counts, constraints, and critical read flows pass.
- Restore credentials and backups are access-controlled and encrypted.

#### Technical notes
Include catalog, transactional, and identity data; define object-storage backup/versioning separately with `CN-PROD-006`.

#### Testing requirements
Automate restore smoke checks and schedule recurring manual disaster-recovery exercises.

#### Security considerations
Separate backup deletion authority, rotate keys, and test access without exposing personal data.

#### Operational considerations
Record evidence date, restore duration, recovery point, owner, and exceptions after every drill.

#### Out of scope
Cross-region active-active failover.

#### Dependencies
Requires the staging data services from `CN-PROD-009`.

### CN-PROD-011 — Add dependency-aware readiness and actionable error alerts

**Metadata:** Priority P0 · Size M · Type Operations · Module Observability/Shared · Phase B — Internal staging · Labels `production-readiness`, `observability`, `alerts`, `module:shared`, `phase:B` · Milestone M1 Internal staging · Dependencies `CN-PROD-009`

#### Problem
The platform can report healthy while PostgreSQL, Redis, Messenger, or critical configuration is unusable, and application failures have no centralized alert.

#### Current state
`/api/v1/health` returns static JSON. Monolog emits JSON to stderr, but there is no aggregation, exception service, metrics, trace/correlation ID, SLO, or alert routing.

#### Desired outcome
Orchestration receives separate liveness/readiness signals and operators receive low-noise alerts with enough context to act.

#### Scope
- Add shallow liveness and bounded dependency-aware readiness endpoints.
- Add request/correlation IDs to responses, logs, Messenger envelopes, and errors.
- Integrate a selected error/metrics provider and alerts for availability, error rate, latency, and dependency failure.

#### Acceptance criteria
- Readiness fails quickly for unavailable required dependencies without leaking internals.
- Exceptions group in the monitoring provider with release/environment metadata.
- A synthetic staging failure reaches the documented on-call/support target.
- Health endpoints do not mutate state or expose secrets.

#### Technical notes
Use strict timeouts and distinguish optional integrations from required request-path dependencies.

#### Testing requirements
Add endpoint tests and a staging game-day for database, Redis, and worker failure.

#### Security considerations
Scrub authorization headers, tokens, email/address fields, and provider payloads before export.

#### Operational considerations
Define initial SLI thresholds and an alert owner; refine after beta traffic.

#### Out of scope
Full distributed tracing and public status page.

#### Dependencies
Requires staging and an approved monitoring provider through `CN-PROD-009`.

### CN-PROD-012 — Establish and enforce the privacy/legal launch gate

**Metadata:** Priority P0 · Size M · Type Documentation · Module Privacy/Legal · Phase B — Internal staging · Labels `production-readiness`, `compliance`, `privacy`, `module:privacy`, `phase:B` · Milestone M2 Closed beta · Dependencies none

#### Problem
External users, marketplace content, and personal data cannot be accepted safely without approved legal bases, policies, ownership, and visible terms.

#### Current state
Repository privacy/production documents are design notes. There is no approved public privacy notice, terms, cookie policy/choice, marketplace rules, prohibited-content/takedown process, seller/tax decision, image/trademark decision, or policy-version acceptance evidence.

#### Desired outcome
Named legal/product owners approve a minimum closed-beta/public policy set, and the application presents and records the required versions before access is opened.

#### Scope
- Resolve legal entity/controller, lawful bases, processors, seller/tax duties, support channel, image licensing, trademark use, moderation, refund, and retention decisions.
- Publish versioned policy pages and capture required acceptance/consent separately.
- Add a go/no-go checklist that blocks unsupported release phases.

#### Acceptance criteria
- Counsel/product owners approve dated policy versions and processor list.
- Registration/marketplace entry exposes required notices and records only legally required acceptance/consent.
- Withdrawal/change behavior and policy-version history are defined.
- Closed-beta and public-launch gates name every unresolved blocker and owner.

#### Technical notes
Do not encode legal assumptions. Store policy identifiers/timestamps rather than document bodies in user records.

#### Testing requirements
Add functional tests for current-policy display, required acceptance, optional consent, and version changes.

#### Security considerations
Collect the minimum evidence; protect acceptance records as personal data and never use pre-ticked optional consent.

#### Operational considerations
Define review cadence, complaints/takedown route, legal escalation, and policy-change communication.

#### Out of scope
Giving legal advice or completing the later export/deletion implementation.

#### Dependencies
Requires decisions by product, privacy/legal counsel, finance/tax, and card-image rights owners; it blocks any external beta.

### CN-PROD-013 — Add browser security headers and a restrictive CSP

**Metadata:** Priority P1 · Size S · Type Security · Module Shared/Frontend · Phase B — Internal staging · Labels `production-readiness`, `security`, `csp`, `module:shared`, `phase:B` · Milestone M2 Closed beta · Dependencies `CN-PROD-008`

#### Problem
Browser responses lack baseline protections against script injection, framing, MIME confusion, and referrer leakage.

#### Current state
Backend and Vite smoke responses did not include CSP, HSTS, `X-Content-Type-Options`, frame protection, `Referrer-Policy`, or `Permissions-Policy`; backend also exposed `X-Powered-By`.

#### Desired outcome
One production edge owns a tested, least-permissive header policy compatible with Vue and approved external media/services.

#### Scope
- Add CSP in report-only mode, resolve violations, then enforce it.
- Add HSTS, MIME, framing, referrer, permissions, and cache controls by route class.
- Remove unnecessary technology disclosure.

#### Acceptance criteria
- Automated assertions cover HTML, API, static assets, downloads, and errors.
- CSP has no broad `unsafe-eval`; any exception is documented and scoped.
- HSTS is enabled only behind confirmed HTTPS.
- Sensitive API/account responses are not cached publicly.

#### Technical notes
Configure headers at the reverse proxy from `CN-PROD-008`, with application overrides only when route-specific.

#### Testing requirements
Run header integration tests and browser smoke under enforced CSP.

#### Security considerations
Include `frame-ancestors`, `object-src`, `base-uri`, and explicit `connect-src`/`img-src`; coordinate third-party image removal.

#### Operational considerations
Collect CSP reports with sampling and personal-data scrubbing.

#### Out of scope
Authentication credential redesign.

#### Dependencies
Requires the production edge from `CN-PROD-008`; becomes simpler after `CN-PROD-006` removes arbitrary image origins.

### CN-PROD-014 — Replace long-lived localStorage credentials with a hardened lifecycle

**Metadata:** Priority P1 · Size M · Type Security · Module Identity/Frontend · Phase B — Internal staging · Labels `production-readiness`, `security`, `authentication`, `module:identity`, `phase:B` · Milestone M2 Closed beta · Dependencies `CN-PROD-008`, `CN-PROD-013`

#### Problem
Any successful XSS can extract the current 30-day bearer token from `localStorage` and retain account access.

#### Current state
`frontend/src/api/client.ts` and auth state persist the bearer credential in browser storage; the route guard checks token presence, not server validity.

#### Desired outcome
Browser credentials are short-lived, revocable/rotatable, unavailable to JavaScript where feasible, and have an explicit CSRF model.

#### Scope
- Record an ADR choosing same-origin secure cookie/session or access-plus-rotating-refresh design.
- Implement expiry, rotation/revocation, logout invalidation, and safe tab/reload behavior.
- Remove long-lived credential storage and centralize unauthorized handling.

#### Acceptance criteria
- No long-lived authentication secret exists in `localStorage`/`sessionStorage`.
- Stolen/replayed rotated credentials are rejected.
- Logout/revocation prevents subsequent API access.
- Cookie-based state-changing requests are CSRF-protected.

#### Technical notes
Use the same-origin proxy from `CN-PROD-008`; do not store refresh tokens in the user table in plaintext.

#### Testing requirements
Add API/browser tests for expiry, refresh/rotation, CSRF, replay, logout, and concurrent tabs.

#### Security considerations
Use `Secure`, `HttpOnly`, appropriate `SameSite`, narrow path/domain, and hashed server-side token material.

#### Operational considerations
Expose aggregate refresh/replay failures and document emergency session revocation.

#### Out of scope
2FA, passkeys, and social login.

#### Dependencies
Requires same-origin HTTPS and compatible CSP from `CN-PROD-008`/`013`.

### CN-PROD-015 — Add global API abuse and payload-size controls

**Metadata:** Priority P1 · Size S · Type Security · Module Shared/API · Phase B — Internal staging · Labels `production-readiness`, `security`, `rate-limit`, `module:shared`, `phase:B` · Milestone M2 Closed beta · Dependencies `CN-PROD-009`

#### Problem
Catalog, listing, pricing, and authenticated business endpoints can be scraped or flooded without consistent limits.

#### Current state
Redis-backed limiters cover login, verification, and password reset only. There is no edge/API-wide request quota, concurrency limit, or documented JSON body ceiling.

#### Desired outcome
Route-class limits protect capacity while preserving predictable client errors and legitimate use.

#### Scope
- Define anonymous, authenticated, search, pricing, write, and upload policies.
- Enforce body/header/time limits at edge and application where appropriate.
- Return structured `429`/`413` responses and `Retry-After`.

#### Acceptance criteria
- Limits are shared across instances and cannot be bypassed by trivial header spoofing.
- Login-specific throttles remain stricter.
- Normal E2E and import/admin jobs have documented allowances.
- Monitoring distinguishes limited traffic from server errors.

#### Technical notes
Key authenticated limits by stable user ID and anonymous limits by trusted client IP plus route class; avoid high-cardinality metrics.

#### Testing requirements
Add functional tests for threshold/reset/headers and a staging burst test.

#### Security considerations
Do not trust forwarded IP headers except from configured proxies; keep responses account-neutral.

#### Operational considerations
Make thresholds configurable and review them after closed-beta traffic.

#### Out of scope
Advanced bot detection and fraud scoring.

#### Dependencies
Requires shared Redis and trusted edge/networking in `CN-PROD-009`.

### CN-PROD-016 — Complete the password-reset browser flow

**Metadata:** Priority P1 · Size S · Type Feature · Module Identity/Frontend · Phase C — Closed beta · Labels `production-readiness`, `identity`, `account-recovery`, `module:identity`, `phase:C` · Milestone M2 Closed beta · Dependencies `CN-PROD-004`, `CN-PROD-014`

#### Problem
The backend reset capability has no usable frontend route/form, leaving locked-out users without self-service recovery.

#### Current state
Identity contains reset sender/messages/rate limiting, but `frontend/src/router/index.ts` has no complete request/reset pages.

#### Desired outcome
Users can request a neutral reset message, choose a new strong password through an expiring link, and return to login safely.

#### Scope
- Add request and reset pages using shared form/error components.
- Add route/token parsing and accessible success/expired states.
- Revoke relevant existing credentials after a successful reset.

#### Acceptance criteria
- Request response does not reveal whether an email exists.
- Invalid, expired, and reused tokens fail safely.
- Successful reset invalidates the token and old password.
- Forms work keyboard-only and expose field errors accessibly.

#### Technical notes
Reuse existing DTO/error envelopes and do not put tokens into analytics/logs/referrers.

#### Testing requirements
Add Vitest form tests and a real full-stack Playwright reset flow using a test mail sink.

#### Security considerations
Preserve rate limiting, strong hash configuration, and session/token revocation policy.

#### Operational considerations
Document recovery support when mail delivery is suppressed or delayed.

#### Out of scope
Administrator-assisted account takeover/recovery and 2FA recovery.

#### Dependencies
Requires production-capable mail and the selected credential lifecycle.

### CN-PROD-017 — Implement personal-data export and deletion requests

**Metadata:** Priority P1 · Size M · Type Feature · Module Privacy/Identity · Phase E — Public release · Labels `production-readiness`, `privacy`, `gdpr`, `module:privacy`, `phase:E` · Milestone M4 Public release · Dependencies `CN-PROD-012`, `CN-PROD-026`

#### Problem
Users cannot exercise access/portability/deletion rights through CardNest, and operators have no traceable workflow.

#### Current state
No privacy module, export artifact, deletion-request entity, admin queue, or user UI exists; transaction-retention constraints are only documented conceptually.

#### Desired outcome
Authenticated users can request an export or account deletion, verify the request, track status, and receive a safe outcome under approved retention rules.

#### Scope
- Model request type/status/verification/deadline and operator notes.
- Generate a structured export asynchronously with expiring authenticated download.
- Implement deletion/anonymization orchestration that preserves legally required records.
- Add user and authorized-admin endpoints/UI.

#### Acceptance criteria
- Export covers identity/profile, collection, marketplace, order, review, and policy evidence in a documented schema.
- Cross-user/order data is minimized and secrets/token hashes are excluded.
- Deletion is reversible until confirmation, then idempotent and audited.
- Requests enforce ownership and approved retention exceptions.

#### Technical notes
Use module-owned exporters behind an interface so future modules must register their data; avoid one giant Doctrine serialization graph.

#### Testing requirements
Add authorization, completeness, expiry, repeated-request, retained-transaction, and anonymization integration tests.

#### Security considerations
Step-up/recent authentication, short-lived signed download, encryption, and access audit are required.

#### Operational considerations
Alert on statutory-deadline risk and provide an operator runbook/escalation owner.

#### Out of scope
Automated retention scheduling (`CN-PROD-048`) and legal interpretation.

#### Dependencies
Requires approved policy/retention decisions and notification delivery.

### CN-PROD-018 — Reconcile listing expiry and reservation invariants

**Metadata:** Priority P1 · Size M · Type Operations · Module Marketplace · Phase B — Internal staging · Labels `production-readiness`, `marketplace`, `reconciliation`, `module:marketplace`, `phase:B` · Milestone M2 Closed beta · Dependencies `CN-PROD-001`, `CN-PROD-033`

#### Problem
Delayed Messenger delivery or worker downtime can leave expired listings/reservations and collection-item state inconsistent indefinitely.

#### Current state
Listing expiry relies on `ExpireListing` delayed messages. There is no periodic reconciliation scan or operator repair command, and the worker has no failed transport.

#### Desired outcome
A repeatable scheduled reconciliation corrects overdue states through the same locked domain transitions and reports anomalies.

#### Scope
- Add bounded scans for overdue active listings/reservations and contradictory item states.
- Process each candidate idempotently with row locks.
- Add dry-run/repair command output and scheduled execution.

#### Acceptance criteria
- Missed delayed messages converge to the correct state.
- Repeated/parallel runs are safe and bounded.
- Active checkout/payment windows are never prematurely released.
- Anomalies are counted and traceable without personal data.

#### Technical notes
Use keyset batches and reuse transition services; do not bulk-update around domain invariants.

#### Testing requirements
Cover overdue, in-flight, already-completed, duplicate-worker, and crash/retry cases.

#### Security considerations
Restrict repair commands to worker/operator identities and avoid exposing seller/buyer data.

#### Operational considerations
Define schedule, batch size, runtime SLO, alert threshold, and remediation playbook.

#### Out of scope
Refund reconciliation and listing moderation decisions.

#### Dependencies
Requires locked transitions (`CN-PROD-001`) and queue failure handling (`CN-PROD-033`).

### CN-PROD-019 — Align CardLanguage and wishlist persistence constraints

**Metadata:** Priority P1 · Size S · Type Bug · Module Catalog/Wishlist · Phase A — Local complete · Labels `production-readiness`, `data-integrity`, `database`, `module:wishlist`, `phase:A` · Milestone M1 Internal staging · Dependencies none

#### Problem
The application language value set and the wishlist database constraint can diverge, causing valid domain input to fail at persistence or unsupported values to survive in one layer.

#### Current state
`CardLanguage` contains 19 codes, including regional Spanish/Portuguese/Chinese plus Polish, Russian, Indonesian, and Thai. Migration `Version20260731230000` constrains `wishlist_items.desired_language` to only ten base codes, so valid enum values can fail at persistence.

#### Desired outcome
One canonical language code set is accepted consistently by DTO validation, domain objects, Doctrine mapping, database constraints, API responses, and filters.

#### Scope
- Inventory and compare all language values used by Catalog, Collection, Wishlist, Marketplace, fixtures, and migrations.
- Correct the wishlist constraint/data with a safe migration.
- Add a shared contract test that fails when enum/config and database values drift.

#### Acceptance criteria
- Every supported `CardLanguage` persists and round-trips in wishlist and collection flows.
- Unknown/legacy values are rejected or explicitly migrated.
- Existing rows are validated before tightening the constraint.
- API language codes remain backwards-compatible or have documented migration behavior.

#### Technical notes
Prefer one canonical PHP enum/value object plus explicitly generated/verified migration values; do not rely on frontend option lists as authority.

#### Testing requirements
Add parameterized domain, repository, migration, and API tests across every supported language code.

#### Security considerations
No direct security change; keep validation server-side to prevent malformed persisted filters.

#### Operational considerations
Run a pre-migration query for unexpected production values and document remediation/rollback.

#### Out of scope
Full interface translation and catalog source-language licensing.

#### Dependencies
None; resolve before importing external user wishlist data.

### CN-PROD-020 — Snapshot immutable card attributes on order items

**Metadata:** Priority P1 · Size M · Type Tech debt · Module Order · Phase B — Internal staging · Labels `production-readiness`, `data-integrity`, `orders`, `module:order`, `phase:B` · Milestone M2 Closed beta · Dependencies none

#### Problem
Historical order display can change when catalog or collection-item data changes, weakening customer evidence and financial auditability.

#### Current state
Order totals/addresses are snapshotted, but `OrderResponse` derives card name/set/language/variant/condition through the current `Listing` and related entities.

#### Desired outcome
Each order item contains the immutable display/identification facts agreed at checkout and responses read those snapshots.

#### Scope
- Add snapshot fields for catalog ID/name/set/number, language, variant, condition, and listing ID/reference.
- Populate them during checkout and backfill existing rows deterministically.
- Switch response DTO mapping to snapshot data.

#### Acceptance criteria
- Later catalog/item/listing edits do not change order history.
- Existing orders are backfilled and migration/schema validation passes.
- Snapshot creation is required and atomic with order creation.
- Public IDs remain UUIDs and money remains minor-unit integers.

#### Technical notes
Use an expand/backfill/read-switch migration sequence suitable for production data volume; retain foreign keys for traceability where policy permits.

#### Testing requirements
Add tests that mutate related records after checkout and assert unchanged responses, plus migration/backfill tests.

#### Security considerations
Snapshot only transaction-relevant facts; do not duplicate unnecessary seller/buyer personal data.

#### Operational considerations
Measure backfill duration/locks on a production-sized copy and include rollback/read compatibility.

#### Out of scope
Invoice generation, tax documents, and sold-price aggregation.

#### Dependencies
None, but complete before external marketplace transactions.

### CN-PROD-021 — Persist all payment attempts and failures in an audit ledger

**Metadata:** Priority P1 · Size M · Type Feature · Module Payment · Phase D — Controlled payments · Labels `production-readiness`, `payments`, `audit`, `module:payment`, `phase:D` · Milestone M3 Controlled payments · Dependencies `CN-PROD-002`

#### Problem
Failed or interrupted charges can roll back without durable evidence, preventing reconciliation, support, and fraud analysis.

#### Current state
Payment/transaction entities primarily represent the successful fake checkout path. A fake-provider failure throws inside the checkout transaction, so the attempted result is not reliably retained.

#### Desired outcome
Every provider attempt has an immutable local record with intent, status, timestamps, safe provider reference, error category, and idempotency key.

#### Scope
- Extend the payment transaction model for attempt lifecycle and failure categories.
- Persist state before and after provider calls in short transactions.
- Add authorized read support for operations without raw provider payload exposure.

#### Acceptance criteria
- Success, decline, timeout, unknown result, retry, and refund attempts are distinguishable.
- Attempts survive order-finalization rollback.
- Idempotency/reference uniqueness is database-enforced.
- Secrets, account data, and raw card/bank data are never stored.

#### Technical notes
Use append-only transaction records and sanitized metadata; updates should be restricted to a known pending-to-terminal transition.

#### Testing requirements
Add provider fault matrix, crash/retry, duplicate-reference, and authorization integration tests.

#### Security considerations
Encrypt sensitive allowed metadata, minimize retention, and restrict operations access.

#### Operational considerations
Define searchable fields, retention, unknown-result alerting, and reconciliation linkage.

#### Out of scope
Provider dashboard mirroring and financial accounting general ledger.

#### Dependencies
Build on the durable intent/idempotency design in `CN-PROD-002`.

### CN-PROD-022 — Implement a production marketplace payment adapter

**Metadata:** Priority P1 · Size M · Type Feature · Module Payment · Phase D — Controlled payments · Labels `production-readiness`, `payments`, `provider`, `module:payment`, `phase:D` · Milestone M3 Controlled payments · Dependencies `CN-PROD-002`, `CN-PROD-003`, `CN-PROD-021`

#### Problem
Only fake payment providers exist, so CardNest cannot process controlled real-money transactions.

#### Current state
`PaymentProvider` exposes a fake synchronous charge path and no marketplace account, refund, webhook, payout, or provider-status abstractions.

#### Desired outcome
An approved EU marketplace provider adapter supports idempotent payment creation/status and per-order refunds without exposing provider concepts to controllers.

#### Scope
- Record provider/currency/marketplace flow decisions in an ADR.
- Extend provider ports only for approved controlled-beta operations.
- Implement authenticated API client, timeout/error mapping, idempotency, and fake parity.
- Store provider IDs safely through the payment ledger.

#### Acceptance criteria
- Sandbox charge and refund pass end-to-end for multi-seller checkout.
- Provider declines/timeouts map to stable domain outcomes.
- No real secret or financial credential enters source, logs, frontend, or database.
- Fake provider remains the default outside explicitly enabled environments.

#### Technical notes
Prefer hosted/provider UI for payment details; use integer EUR minor units and validate totals server-side.

#### Testing requirements
Add adapter contract tests shared by fake/sandbox adapters and recorded provider sandbox integration tests where policy permits.

#### Security considerations
Verify TLS, rotate scoped API keys, minimize PCI scope, and threat-model redirect/callback flows.

#### Operational considerations
Document provider outage/degraded behavior, limits, dashboards, escalation, and key rotation.

#### Out of scope
Automatic payouts, additional currencies, chargeback automation, and international sales.

#### Dependencies
Requires payment safety issues plus an approved provider/legal model. `CN-PROD-025` may then build seller onboarding on this adapter; the adapter does not depend on that issue, avoiding a circular dependency.

### CN-PROD-023 — Verify and idempotently process payment webhooks

**Metadata:** Priority P1 · Size M · Type Security · Module Payment · Phase D — Controlled payments · Labels `production-readiness`, `payments`, `webhook`, `module:payment`, `phase:D` · Milestone M3 Controlled payments · Dependencies `CN-PROD-022`, `CN-PROD-021`

#### Problem
Asynchronous provider state cannot be trusted or safely applied without signature verification, replay protection, and ordered/idempotent handling.

#### Current state
No payment webhook endpoint, event store, signature secret, replay protection, or handler exists.

#### Desired outcome
Authentic provider events are durably acknowledged once, safely reprocessed, and converge local payment/order state without trusting event order.

#### Scope
- Add raw-body signature and timestamp verification.
- Persist unique provider event ID/type/received/processed status and safe hash.
- Dispatch processing asynchronously and re-fetch provider state when authoritative confirmation is required.

#### Acceptance criteria
- Invalid, stale, or malformed signatures are rejected before mutation.
- Duplicate/out-of-order delivery has one correct state effect.
- Endpoint acknowledges accepted events quickly and retries handlers safely.
- Unsupported event types are retained/visible without breaking delivery.

#### Technical notes
Keep raw payload retention minimal and policy-controlled; lock payment/order rows in a deterministic order.

#### Testing requirements
Use provider fixtures for signature, timestamp, duplicate, reordered, malformed, and concurrent event cases.

#### Security considerations
Rate-limit separately, compare signatures safely, rotate secrets, and do not expose verification reasons publicly.

#### Operational considerations
Alert on verification-failure spikes, processing lag, failed events, and unknown event types.

#### Out of scope
Chargeback/dispute business policy and payout webhooks.

#### Dependencies
Requires the provider adapter and durable attempt/event ledger.

### CN-PROD-024 — Reconcile provider payments with local orders

**Metadata:** Priority P1 · Size M · Type Operations · Module Payment/Order · Phase D — Controlled payments · Labels `production-readiness`, `payments`, `reconciliation`, `module:payment`, `phase:D` · Milestone M3 Controlled payments · Dependencies `CN-PROD-022`, `CN-PROD-023`, `CN-PROD-033`

#### Problem
Lost callbacks, crashes, and provider ambiguity can leave local payment/order state different from the provider's authoritative state.

#### Current state
There is no reconciliation command, schedule, discrepancy model, or operator workflow.

#### Desired outcome
A bounded idempotent job compares unsettled/recent records, repairs safe discrepancies, and queues unsafe ones for human review.

#### Scope
- Add provider status lookup through the port.
- Scan candidate payments by keyset/status/age.
- Classify, record, auto-repair approved cases, and expose a dry-run/operator summary.

#### Acceptance criteria
- Missed webhook and finalize-after-charge scenarios converge.
- Unknown/financially risky mismatches are never silently overwritten.
- Parallel/repeated jobs are safe and provider-rate-limit aware.
- Every repair records before/after/reason and correlation data.

#### Technical notes
Use exponential backoff and provider-request budgets; reuse domain transitions and locks.

#### Testing requirements
Cover every provider/local status matrix branch, rate limits, partial batch failure, and retry.

#### Security considerations
Restrict command/API visibility and redact provider/customer data.

#### Operational considerations
Define schedule, mismatch alerts, manual owner, response SLA, and ledger export.

#### Out of scope
Payout/bank reconciliation and accounting exports.

#### Dependencies
Requires real-provider status, webhooks, payment ledger, and working failed transport.

### CN-PROD-025 — Implement seller onboarding, eligibility, and beta limits

**Metadata:** Priority P1 · Size M · Type Feature · Module Identity/Marketplace/Payment · Phase D — Controlled payments · Labels `production-readiness`, `seller-verification`, `payments`, `module:identity`, `phase:D` · Milestone M3 Controlled payments · Dependencies `CN-PROD-012`, `CN-PROD-022`, `CN-PROD-030`

#### Problem
Any registered user can act as a seller without provider onboarding, verified eligibility, policy acceptance, or controlled-beta exposure limits.

#### Current state
No `seller_accounts` implementation/workflow, KYC adapter, capability status, or listing/volume limit enforcement exists.

#### Desired outcome
Seller state mirrors approved provider capabilities and policy; only eligible sellers can publish/transact within configured beta limits.

#### Scope
- Model onboarding/capability/restriction state without storing raw identity documents.
- Add provider onboarding/status adapter and return flow.
- Gate publish/checkout and enforce per-item/order/period limits server-side.
- Add seller-facing status/remediation display.

#### Acceptance criteria
- Unverified/restricted sellers cannot publish or receive new paid orders.
- Provider status changes/webhooks are idempotent and audited.
- Limits are atomic under concurrent checkouts.
- Existing listings are handled predictably when capability is lost.

#### Technical notes
Keep KYC evidence at the provider; persist only identifiers, capability flags, reason categories, and timestamps needed for operation.

#### Testing requirements
Add state-machine, authorization, concurrent-limit, provider-status, and end-to-end sandbox tests.

#### Security considerations
Least privilege, no identity-document upload, careful status messaging, and administrator override audit.

#### Operational considerations
Define manual review, seller support, sanctions/appeal, tax reporting, and provider escalation owners.

#### Out of scope
Automatic payouts, international sellers, and business/store accounts.

#### Dependencies
Requires legal/tax/provider decisions, payment adapter, and admin audit/RBAC.

### CN-PROD-026 — Persist and deliver required notifications asynchronously

**Metadata:** Priority P1 · Size M · Type Feature · Module Notification · Phase C — Closed beta · Labels `production-readiness`, `notifications`, `messenger`, `module:notification`, `phase:C` · Milestone M2 Closed beta · Dependencies `CN-PROD-004`, `CN-PROD-033`

#### Problem
Users receive no durable order, listing, review, pricing, dispute, or security notifications and cannot manage channel preferences.

#### Current state
There is no Notification module/table/inbox/preferences; Messenger handlers only cover logging auth mail, listing expiry, and pricing refresh.

#### Desired outcome
Domain events create deduplicated in-app notifications and optional transactional email through retryable background delivery.

#### Scope
- Add notification/preference entities, repository, event-to-notification handlers, inbox/read APIs, and minimal UI.
- Cover new order, paid, shipped, received/completed, refund, review, listing expiry/suspension, security, and required privacy events.
- Implement per-type/channel defaults and mandatory-security exceptions.

#### Acceptance criteria
- Event retries never create duplicate user notifications.
- Object ownership controls all links/content.
- Users can list/paginate/read and update allowed preferences.
- Mail failure does not roll back the business transaction.

#### Technical notes
Store translation keys/structured context where useful; resolve links through public UUIDs and avoid serializing entities into Messenger messages.

#### Testing requirements
Add event-handler idempotency, preference, authorization, pagination, retry, and browser inbox tests.

#### Security considerations
Minimize sensitive content in subject/body and prevent cross-user object links.

#### Operational considerations
Measure queue lag, delivery outcomes, bounce/suppression state, and notification volume by type.

#### Out of scope
Push notifications, marketing campaigns, and rich preference analytics.

#### Dependencies
Requires production email and failed-transport/worker health.

### CN-PROD-027 — Make pricing refresh asynchronous and protect public reads

**Metadata:** Priority P1 · Size M · Type Tech debt · Module Pricing · Phase C — Closed beta · Labels `production-readiness`, `pricing`, `performance`, `module:pricing`, `phase:C` · Milestone M2 Closed beta · Dependencies `CN-PROD-015`, `CN-PROD-033`

#### Problem
A public price read can synchronously call TCGdex/Cardmarket and write a snapshot, tying up PHP workers and allowing cheap upstream/cost amplification.

#### Current state
`CardPriceController`/pricing application services refresh on a missing daily snapshot. Daily Messenger refresh exists, but public cache misses can still execute provider work; limits are per-card failure cooldown, not a bounded public workload.

#### Desired outcome
Public reads are fast and read-only, serving fresh/stale data with explicit timestamps while one deduplicated bounded background refresh updates missing/stale keys.

#### Scope
- Remove provider calls and writes from the public request path.
- Implement stale-while-revalidate semantics with maximum accepted age.
- Deduplicate queued refreshes by card/market/variant/day and cap batch/concurrency/provider rates.
- Return transparent source/as-of/stale status.

#### Acceptance criteria
- A public GET never contacts the provider or writes PostgreSQL.
- Concurrent cold reads enqueue at most one refresh key.
- Stale data is bounded and labelled; absent data returns a stable pending/unavailable response.
- Provider outage cannot exhaust the web worker pool.

#### Technical notes
Reuse Redis locks/cache and `RefreshCardPrice` messages; enforce idempotency with the existing daily uniqueness constraint.

#### Testing requirements
Add request-path provider-spy tests, cold-read concurrency, stale/absent/outage, queue-bound, and scheduler tests.

#### Security considerations
Apply public rate limits and do not expose raw provider errors or credentials.

#### Operational considerations
Track freshness, queue depth/age, provider latency/errors, budget consumption, and cards without data.

#### Out of scope
Internal sold-price statistics and predictive pricing.

#### Dependencies
P1 rather than P0 because the demonstrated risk is availability/cost abuse, not direct money/data loss; it still gates first production and requires rate limiting and queue reliability.

### CN-PROD-028 — Implement user reports and moderator review

**Metadata:** Priority P1 · Size M · Type Feature · Module Moderation/Marketplace · Phase C — Closed beta · Labels `production-readiness`, `moderation`, `marketplace`, `module:moderation`, `phase:C` · Milestone M2 Closed beta · Dependencies `CN-PROD-006`, `CN-PROD-030`, `CN-PROD-026`

#### Problem
Users cannot report suspicious listings/users/reviews and moderators cannot remove marketplace exposure through a traceable workflow.

#### Current state
No reports module/table/endpoints/UI or moderator queue exists; listing `pending_review`/`suspended` places are scaffolding without authorized application transitions.

#### Desired outcome
Users submit categorized reports with safe evidence references; moderators triage, assign, suspend/approve/reinstate content, and close with an auditable outcome.

#### Scope
- Model report subject/type/status/priority/assignment/reason/outcome.
- Add reporter and moderator DTO/API/minimal queue UI.
- Implement locked listing review/suspension/reinstatement transitions and public-search/checkout effects.
- Notify affected users without revealing reporter identity.

#### Acceptance criteria
- Reporters can only view their own report status; moderators require explicit permission.
- Duplicate/spam submissions are bounded.
- Suspended/pending listings are atomically unavailable to search/checkout.
- Every moderator action records actor, reason, prior/new state, and timestamp.

#### Technical notes
Reuse secure upload references for allowed evidence and the listing locks from `CN-PROD-001`.

#### Testing requirements
Add ownership, moderation transition matrix, checkout race, duplicate report, attachment, audit, and notification tests.

#### Security considerations
Sanitize text, scan attachments, conceal reporter identity, and separate internal from user-visible notes.

#### Operational considerations
Define categories, SLA, escalation, takedown policy, abuse controls, and moderator staffing.

#### Out of scope
Buyer-seller financial disputes (`CN-PROD-029`) and automatic sanctions/fraud models.

#### Dependencies
Requires secure evidence storage, admin RBAC/audit, notifications, and approved moderation policy.

### CN-PROD-029 — Implement the evidence-backed dispute workflow

**Metadata:** Priority P1 · Size M · Type Feature · Module Disputes/Order/Payment · Phase D — Controlled payments · Labels `production-readiness`, `disputes`, `payments`, `module:disputes`, `phase:D` · Milestone M3 Controlled payments · Dependencies `CN-PROD-003`, `CN-PROD-006`, `CN-PROD-026`, `CN-PROD-030`

#### Problem
Buyers and sellers have no structured route to pause completion, exchange evidence, obtain a decision, or trigger a controlled refund.

#### Current state
No dispute, message, attachment, moderator assignment, or resolution model/API/UI exists despite disputed statuses in the product specification.

#### Desired outcome
Eligible participants can open one scoped dispute within policy time, submit safe evidence/messages, and receive an auditable moderator resolution linked to refund actions.

#### Scope
- Model reason/status/deadline/participants/assignment/messages/attachments/outcome.
- Add participant and moderator DTO/API/minimal UI.
- Lock applicable order completion/payout progression while open.
- Invoke approved refund commands from resolutions.

#### Acceptance criteria
- Only order participants can open within the configured window and view participant-visible content.
- State transitions follow the approved matrix and are concurrency-safe.
- Evidence uses scanned owned storage and cannot be replaced after submission.
- Resolution/refund is idempotent and audit-linked.

#### Technical notes
Separate internal notes from participant messages; snapshot policy deadlines and reason codes at opening.

#### Testing requirements
Add status-matrix, ownership, expiry, attachment, concurrent resolution/completion, refund, and notification tests.

#### Security considerations
Protect sensitive evidence, scan/sanitize content, apply retention/access rules, and prevent moderator self-conflict.

#### Operational considerations
Define dispute SLA, escalation, evidence review safety, provider deadline, and appeal policy.

#### Out of scope
Provider chargeback automation and law-enforcement workflows.

#### Dependencies
Requires refunds, secure uploads, notifications, RBAC/audit, and approved dispute policy.

### CN-PROD-030 — Add administration RBAC and immutable audit logs

**Metadata:** Priority P1 · Size M · Type Security · Module Administration/Shared · Phase B — Internal staging · Labels `production-readiness`, `authorization`, `audit`, `module:administration`, `phase:B` · Milestone M2 Closed beta · Dependencies `CN-PROD-012`

#### Problem
Sensitive moderation, role, refund, and account actions cannot be safely delegated or reconstructed.

#### Current state
Users store roles, but no admin/moderator application flows, granular permissions, admin frontend, role-assignment policy, or `audit_logs` table exists.

#### Desired outcome
Named least-privilege permissions protect an isolated administration surface and every sensitive action produces append-only audit evidence.

#### Scope
- Define administrator/moderator/support permission matrix and voters/policies.
- Add audit event model/writer with actor, action, subject, timestamp, reason, correlation ID, and safe diff.
- Add minimal protected admin shell and role-management bootstrap procedure.

#### Acceptance criteria
- User roles alone never bypass object-level checks unintentionally.
- Role/refund/moderation/account restriction actions require reason and audit event.
- Audit events are not editable through product APIs.
- Privileged routes are absent from normal navigation and denied by default.

#### Technical notes
Centralize policy decisions while keeping module-level voters; avoid logging serialized entities or secrets.

#### Testing requirements
Add permission-matrix, privilege-escalation, cross-object, audit atomicity, and admin-route browser tests.

#### Security considerations
Require recent/strong authentication for high-impact actions, review bootstrap access, and alert on role changes.

#### Operational considerations
Define access review, break-glass, audit retention/export, and privileged-action alert procedures.

#### Out of scope
Complete catalog/content management dashboards and organization SSO.

#### Dependencies
Requires approved roles/responsibilities and privacy retention decisions from `CN-PROD-012`.

### CN-PROD-031 — Add security, migration, and container release gates to CI

**Metadata:** Priority P1 · Size M · Type Operations · Module CI/Infrastructure · Phase B — Internal staging · Labels `production-readiness`, `ci`, `supply-chain`, `module:shared`, `phase:B` · Milestone M1 Internal staging · Dependencies `CN-PROD-007`, `CN-PROD-008`

#### Problem
CI tests code quality but does not prove immutable artifacts, clean database evolution, or dependency/container security before release.

#### Current state
`.github/workflows/ci.yml` runs backend/frontend tests, static/style/lint/build, and mocked Playwright. It omits Composer/npm audit gates, fresh schema/migration validation, production image builds/scans, SBOM, secret scan, and full-stack E2E. Existing test database naming is correct: Symfony's `when@test` suffix resolves `cardnest` to `cardnest_test`, as confirmed by CI migrations.

#### Desired outcome
Every candidate revision produces tested immutable artifacts and is blocked by known high-risk dependency, migration, secret, or container failures.

#### Scope
- Add `composer validate/audit`, `npm audit` under an explicit severity policy, and secret scanning.
- Apply all migrations to a fresh PostgreSQL database, validate schema, and dry-run/no-pending checks.
- Build backend/frontend production images, generate SBOMs, scan them, and publish only after success.
- Pin third-party Actions to reviewed immutable SHAs.

#### Acceptance criteria
- A broken migration, vulnerable disallowed dependency, leaked test secret, or high/critical image CVE fails CI.
- Successful runs identify artifact digests and source revision.
- Audit exceptions require owner, rationale, and expiry.
- No deploy job can consume an artifact from a failed workflow.

#### Technical notes
Cache package downloads without caching generated vendor state across incompatible lockfiles/runtimes.

#### Testing requirements
Exercise each gate with a safe fixture/test branch and document expected failure output.

#### Security considerations
Use minimal workflow permissions, no secrets on untrusted pull requests, and provenance/attestation where available.

#### Operational considerations
Set gate ownership, update cadence, exception SLA, and artifact retention.

#### Out of scope
Changing the valid Symfony test database suffix behavior and automatic production deployment.

#### Dependencies
Requires buildable production images; real E2E is added separately in `CN-PROD-032`.

### CN-PROD-032 — Add real full-stack auth and marketplace E2E tests

**Metadata:** Priority P1 · Size M · Type Tech debt · Module Testing/All · Phase B — Internal staging · Labels `production-readiness`, `testing`, `e2e`, `module:shared`, `phase:B` · Milestone M2 Closed beta · Dependencies `CN-PROD-009`, `CN-PROD-004`, `CN-PROD-003`

#### Problem
Current browser tests can pass while routing, auth, Doctrine, Messenger, Redis, mail, or transactional flows are broken.

#### Current state
All 11 Playwright specs intercept and fulfill API requests in the page. They validate Vue behavior but not browser-to-API-to-PostgreSQL integration.

#### Desired outcome
A small independent suite runs against deployed production-like services and proves the critical account and multi-user marketplace flows.

#### Scope
- Add deterministic environment setup/cleanup and test mail access.
- Cover register → verify → login → logout.
- Cover seller item/listing → buyer cart/fake checkout → ship → receive → collection action → review.
- Keep mocked UI specs for fast component behavior.

#### Acceptance criteria
- Full-stack specs contain no `page.route().fulfill()` for application APIs.
- Tests use isolated unique users/data and leave the environment clean.
- Failures retain safe screenshots/traces/log correlation IDs.
- CI/staging gate fails on either critical flow failure.

#### Technical notes
Use seeded catalog records and stable provider/mail test adapters; never depend on public TCGdex during E2E.

#### Testing requirements
The issue itself adds the suite; run it repeatedly and in parallel to prove isolation and flake threshold.

#### Security considerations
Use non-production secrets and synthetic personal data; redact traces/artifacts.

#### Operational considerations
Publish runtime/flakiness, artifact retention, triage owner, and test-environment reset procedure.

#### Out of scope
Replacing Vitest/API/domain tests and exhaustive browser coverage.

#### Dependencies
Requires staging, mail, refund-safe flow/fake provider, and production-like artifacts.

### CN-PROD-033 — Add a Messenger failed transport and queue health alerts

**Metadata:** Priority P1 · Size S · Type Operations · Module Shared/Messenger · Phase B — Internal staging · Labels `production-readiness`, `messenger`, `reliability`, `module:shared`, `phase:B` · Milestone M1 Internal staging · Dependencies `CN-PROD-009`, `CN-PROD-011`

#### Problem
Exhausted messages can disappear from the operational workflow, and worker/scheduler failure is not visible.

#### Current state
`backend/config/packages/messenger.yaml` has the failed transport commented out. Worker/scheduler containers lack health checks and queue lag/failure alerting.

#### Desired outcome
Messages exhaust into a durable failed transport, workers expose health, and operators can inspect/retry/remove safely.

#### Scope
- Configure an environment-isolated failed transport and retry policy by message class.
- Add worker heartbeat, queue depth/age/failure metrics, and alerts.
- Document authorized inspection/retry commands and poison-message handling.

#### Acceptance criteria
- A deterministic failing message lands in the failed transport after configured retries.
- Worker/scheduler outage and queue-age breach alert an owner.
- Retry is idempotent and retains correlation/message identity.
- Queue data survives routine container replacement.

#### Technical notes
Use dedicated Redis namespaces/credentials or an approved durable transport; avoid serializing Doctrine entities.

#### Testing requirements
Add integration test for retry→failed→successful replay and staging worker-kill exercise.

#### Security considerations
Restrict failed-message access and scrub message payloads/logs of secrets/personal data.

#### Operational considerations
Set per-message retry/dead-letter SLA, dashboard, storage retention, and runbook owner.

#### Out of scope
Changing business scheduling cadence and multi-region queue replication.

#### Dependencies
Requires staging and monitoring from `CN-PROD-009`/`011`.

### CN-PROD-034 — Decide and implement post-sale physical-item ownership transfer

**Metadata:** Priority P1 · Size M · Type Feature · Module Order/Collection · Phase C — Closed beta · Labels `production-readiness`, `data-integrity`, `ownership`, `module:order`, `phase:C` · Milestone M2 Closed beta · Dependencies `CN-PROD-020`, `CN-PROD-003`

#### Problem
The product principle says one physical card is one digital object, but the completed-sale flow archives the seller item and later creates a second buyer `CollectionItem`.

#### Current state
`ImportOrderItemToCollection` reads the sold listing's item and constructs a new item for the buyer, linked as `importedCollectionItem`; the seller's archived record remains. This may be intentional historical modeling, but ownership semantics are not explicitly decided or enforced.

#### Desired outcome
Product/domain owners choose transfer-versus-copy semantics and the implementation guarantees one unambiguous current physical ownership record while preserving immutable sale history.

#### Scope
- Record an ADR defining physical identity, history, buyer condition/language correction, refusal/non-import, refund, and privacy behavior.
- Implement the chosen transactional transfer/lineage model.
- Ensure seller history remains visible without granting current ownership/actions.

#### Acceptance criteria
- A completed/imported card has exactly one current owner according to the chosen model.
- Seller cannot list/move/edit it after sale; buyer cannot import twice.
- Refund/cancellation behavior is deterministic and does not duplicate ownership.
- Order history remains immutable and traceable across anonymization rules.

#### Technical notes
If copying is retained, introduce explicit physical-lineage/current-owner constraints rather than relying on two unrelated items. Lock order/order-item/item rows deterministically.

#### Testing requirements
Add sale→receipt→import/transfer, repeated, concurrent, refund-before/after-import, authorization, and lineage constraint tests.

#### Security considerations
Never expose prior owner's private notes, purchase source, storage location, or photos without approved transfer rules.

#### Operational considerations
Define repair tooling/report for any existing ambiguous lineage before external beta data.

#### Out of scope
Trade proposals and automatic binder placement.

#### Dependencies
Requires immutable order snapshots and cancellation/refund semantics.

### CN-PROD-035 — Meet the keyboard and screen-reader accessibility baseline

**Metadata:** Priority P1 · Size M · Type Tech debt · Module Frontend · Phase C — Closed beta · Labels `production-readiness`, `accessibility`, `frontend`, `module:shared`, `phase:C` · Milestone M4 Public release · Dependencies GitHub issue `#198`

#### Problem
Critical account/marketplace/dialog flows have no complete WCAG-oriented keyboard, focus, screen-reader, contrast, or zoom evidence.

#### Current state
Semantic labels/errors exist in parts of the Vue UI, but dialogs are panel markup and open issue #198 tracks modal focus. No automated axe gate or documented manual matrix exists.

#### Desired outcome
Critical flows meet an agreed WCAG 2.2 AA baseline with automated checks and documented manual keyboard/screen-reader validation.

#### Scope
- Complete #198 rather than duplicate its modal-focus work.
- Audit/fix landmarks, headings, labels, error announcement, keyboard order, escape/return focus, contrast, target size, reflow/zoom.
- Add automated checks to critical component/E2E flows.

#### Acceptance criteria
- Register/login/catalog/listing/cart/checkout/order/binder/report flows complete keyboard-only.
- Dialog focus is trapped/restored and errors/status changes are announced.
- No serious/critical automated accessibility violations exist in scoped pages.
- 200% zoom and mobile reflow retain content/actions.

#### Technical notes
Prefer shared components and native controls; document justified exceptions with owner/expiry.

#### Testing requirements
Add axe-style automated assertions and a manual NVDA/VoiceOver + keyboard checklist with evidence.

#### Security considerations
Accessible error text must not expose account existence or authorization details.

#### Operational considerations
Make accessibility regression part of release sign-off and assign an issue-intake owner.

#### Out of scope
A formal third-party certification and redesign unrelated to confirmed barriers.

#### Dependencies
Depends on completing existing issue #198; do not create a duplicate issue for modal focus.

### CN-PROD-036 — Enforce binder occupied-slot and placeholder consistency

**Metadata:** Priority P1 · Size M · Type Bug · Module Binder/Storage · Phase A — Local complete · Labels `production-readiness`, `data-integrity`, `binder`, `module:binder`, `phase:A` · Milestone M1 Internal staging · Dependencies none

#### Problem
A binder slot can retain placeholder metadata while also being occupied, so API state can describe both a real card and a missing-card placeholder.

#### Current state
`AssignBinderSlot` validates a reserved card match then calls `placeInBinderSlot()` but does not clear the reservation/label. `BinderDetailResponse` can serialize both `collectionItem` and `placeholder`; the frontend merely hides the latter with `v-if`. No database constraint spans current binder placement and placeholder columns.

#### Desired outcome
Slot commands have explicit semantics and persisted/API state cannot represent an occupied slot as missing simultaneously.

#### Scope
- Decide whether matching placeholder is consumed on assignment and restored on unassignment; document the rule.
- Apply assignment/unassignment/configuration under compatible locks.
- Enforce the invariant in domain/repository/database design where feasible and normalize existing rows.

#### Acceptance criteria
- Assignment produces exactly one visible slot-content state.
- Placeholder configuration on an occupied slot is rejected or follows one documented atomic replacement command.
- Concurrent assignment/configuration cannot violate the invariant.
- `occupiedSlots` and API placeholder fields agree after every transition.

#### Technical notes
Because placement is represented in location history, a trigger or transactionally locked cross-table check may be required; avoid a check constraint that cannot see the placement table.

#### Testing requirements
Add matching/mismatching placeholder, manual label, assign/unassign, concurrent configure/assign, response, count, and migration tests.

#### Security considerations
Preserve binder ownership checks for both item and slot.

#### Operational considerations
Add a pre-migration audit/repair query for occupied slots with placeholder metadata.

#### Out of scope
Automatic layout algorithms and wishlist auto-placement.

#### Dependencies
None; fix the current invariant before automatic binder layouts.

### CN-PROD-037 — Complete profile and NL/EN locale settings

**Metadata:** Priority P2 · Size M · Type Feature · Module Identity/Frontend · Phase E — Public release · Labels `production-readiness`, `identity`, `i18n`, `module:identity`, `phase:E` · Milestone M4 Public release · Dependencies `CN-PROD-014`

#### Problem
The account screen is effectively read-only and the interface is Dutch-only despite the intended NL/EN market.

#### Current state
There is no editable profile/settings model for display data or locale, no locale API, and UI text is embedded directly in Vue/PHP responses.

#### Desired outcome
Users can edit the approved minimal profile and choose Dutch or English consistently across frontend and user-facing backend messages.

#### Scope
- Define minimal profile fields and privacy visibility with product approval.
- Persist/validate locale and expose owner-only DTO APIs.
- Add frontend message catalogs, fallback, locale switch, and translate critical release flows.

#### Acceptance criteria
- Profile/locale updates enforce ownership and validation.
- Locale persists across sessions and unsupported values fall back safely.
- Auth, catalog, collection, marketplace, checkout, order, privacy, and error chrome have NL/EN coverage.
- Public profile fields never expose private account/address data.

#### Technical notes
Use stable translation keys for notification/backend messages and keep domain enums language-neutral.

#### Testing requirements
Add API ownership/validation tests, missing-key checks, and NL/EN browser smoke tests.

#### Security considerations
Escape user-visible fields, prevent email enumeration, and define public/private field boundaries.

#### Operational considerations
Add translation review ownership and missing-key logging without user content.

#### Out of scope
Additional locales, automatic translation, and full profile social features.

#### Dependencies
Coordinate credential/account-state behavior with `CN-PROD-014`.

### CN-PROD-038 — Normalize artist data and expose search/autocomplete

**Metadata:** Priority P2 · Size M · Type Feature · Module Catalog · Phase Post-launch · Labels `production-readiness`, `catalog`, `artists`, `module:catalog`, `phase:post-launch` · Milestone M5 Post-launch · Dependencies none

#### Problem
Illustrators are free text, so spelling/source variants cannot be managed, linked, or searched reliably as artists.

#### Current state
`CatalogCard` stores an `illustrator` string; there is no Artist entity/repository/API/page or import correction/alias model.

#### Desired outcome
Canonical artist records with aliases/source references support filtering and autocomplete while retaining raw source attribution.

#### Scope
- Add Artist UUID entity, normalized name/slug, aliases/source provenance, and card relation.
- Backfill distinct illustrator values without silently merging uncertain identities.
- Add paginated read/search endpoints and catalog filter/autocomplete.

#### Acceptance criteria
- Cards can link to a canonical artist and retain raw imported value.
- Exact/alias search is case-insensitive and paginated.
- Ambiguous names remain separate/reviewable.
- Import does not overwrite manual corrections silently.

#### Technical notes
Use an explicit correction/provenance rule and safe expand/backfill migration.

#### Testing requirements
Add entity/repository/API/import-backfill/search and ambiguous-alias tests.

#### Security considerations
Escape imported/user-visible names and bound autocomplete queries.

#### Operational considerations
Provide counts of unlinked/ambiguous artists for catalog administration.

#### Out of scope
Artist biographies, copyrighted portraits, and automatic identity matching.

#### Dependencies
Provider/legal source decisions may affect provenance display but do not block the schema proposal.

### CN-PROD-039 — Replace catalog lazy hydration with a read projection

**Metadata:** Priority P2 · Size M · Type Tech debt · Module Catalog · Phase C — Closed beta · Labels `production-readiness`, `performance`, `doctrine`, `module:catalog`, `phase:C` · Milestone M2 Closed beta · Dependencies none

#### Problem
Catalog list serialization can trigger lazy queries for variants and external references per card, multiplying database round trips by page size.

#### Current state
The repository joins card/set/series, while `CatalogCardCollectionResponse` traverses variants/external references. With 20–25 cards this can approach two extra queries per card.

#### Desired outcome
Catalog list/search uses a bounded DTO projection or explicitly batched associations with a stable query budget.

#### Scope
- Measure query counts for representative list/filter pages.
- Add a repository read model/projection for list fields.
- Keep detailed associations on the card-detail query only where needed.

#### Acceptance criteria
- Query count does not grow linearly with result count.
- API shape/backwards compatibility is preserved or versioned.
- Pagination totals/filter semantics remain correct.
- Benchmark evidence records before/after latency and buffers.

#### Technical notes
Do not expose Doctrine entities; prefer DBAL/scalar DTO mapping when it materially simplifies the read path.

#### Testing requirements
Add query-count regression and API contract/filter/pagination tests.

#### Security considerations
Projection must not bypass visibility rules if private catalog corrections are added later.

#### Operational considerations
Add slow-query visibility and a documented query budget.

#### Out of scope
Search engine migration and artist normalization.

#### Dependencies
None; coordinate index work with `CN-PROD-040`.

### CN-PROD-040 — Add case-insensitive catalog indexes and guarded cursor pagination

**Metadata:** Priority P2 · Size M · Type Tech debt · Module Catalog/Database · Phase C — Closed beta · Labels `production-readiness`, `performance`, `postgresql`, `module:catalog`, `phase:C` · Milestone M2 Closed beta · Dependencies `CN-PROD-039`

#### Problem
Case-folded contains/equality filters and deep offsets scan/sort large portions of the 134k-card catalog and can spill to disk.

#### Current state
Audit plans showed sequential/ineffective-index scans for `lower(name) LIKE '%…%'`, `lower(rarity)=…`, and an external merge near offset 100,000. Raw column indexes do not support those expressions.

#### Desired outcome
Common filters use measured PostgreSQL indexes and clients cannot request pathological offsets.

#### Scope
- Add `pg_trgm`/expression indexes only where query evidence supports them.
- Normalize query expressions to match index definitions.
- Add stable keyset cursor pagination or an explicit maximum offset with a migration path.

#### Acceptance criteria
- Representative name/rarity/set/number plans use intended indexes and meet an approved latency/buffer budget.
- Sort order is deterministic across equal values.
- Invalid/deep cursors return bounded structured errors.
- Index build/deploy plan avoids unacceptable table locking.

#### Technical notes
Use `CREATE INDEX CONCURRENTLY` via deployment-safe migration procedure where needed; PostgreSQL extension availability must be explicit.

#### Testing requirements
Add filter/pagination correctness tests and production-sized `EXPLAIN (ANALYZE, BUFFERS)` evidence outside normal CI.

#### Security considerations
Bound query length/wildcards and apply public rate limits.

#### Operational considerations
Monitor slow queries, index size/usage, and cursor error rates.

#### Out of scope
Meilisearch/OpenSearch adoption.

#### Dependencies
Coordinate response/read-model work with `CN-PROD-039` and deploy through migration gates.

### CN-PROD-041 — Generate responsive media derivatives and controlled delivery

**Metadata:** Priority P2 · Size M · Type Feature · Module Images/Frontend · Phase D — Controlled payments · Labels `production-readiness`, `images`, `performance`, `module:images`, `phase:D` · Milestone M3 Controlled payments · Dependencies `CN-PROD-006`

#### Problem
Even approved original uploads are too large/unpredictable for list/grid delivery and can cause bandwidth waste and layout shift.

#### Current state
There is no derivative/thumbnail worker, explicit dimensions, responsive source metadata, content-addressed cache policy, or first-party image error fallback.

#### Desired outcome
Clean originals produce deterministic thumbnails/display sizes and frontend views request the smallest appropriate immutable asset.

#### Scope
- Add async derivative generation with orientation normalization and metadata stripping.
- Persist dimensions/checksum/format/status.
- Serve signed/private or public immutable variants according to policy.
- Add `srcset`/`sizes`, dimensions, lazy/async decoding, and fallbacks.

#### Acceptance criteria
- Original upload never blocks listing request processing.
- Required variants are idempotent and checksum-addressed.
- UI prevents layout shift and handles processing/failure states.
- Unauthorized originals remain inaccessible.

#### Technical notes
Use bounded pixel/memory processing and retain a safe JPEG/WebP fallback; AVIF is optional after measurement.

#### Testing requirements
Add orientation/size/transparency/malformed fixture tests and visual/browser delivery checks.

#### Security considerations
Run processors isolated with strict resource limits; never pass untrusted filenames to shell commands.

#### Operational considerations
Monitor processing lag/failures, cache hit ratio, storage growth, and cleanup.

#### Out of scope
Catalog image licensing/caching until rights are approved.

#### Dependencies
Requires secure first-party storage/status from `CN-PROD-006`.

### CN-PROD-042 — Persist completed CardNest sale-price statistics

**Metadata:** Priority P2 · Size M · Type Feature · Module Pricing/Order · Phase E — Public release · Labels `production-readiness`, `pricing`, `orders`, `module:pricing`, `phase:E` · Milestone M4 Public release · Dependencies `CN-PROD-003`, `CN-PROD-020`

#### Problem
External asking-price indications cannot support official CardNest sale statistics, and active asks must not be presented as market value.

#### Current state
`price_snapshots` stores external TCGdex/Cardmarket-derived observations; there is no `sold_prices` model or aggregation from completed CardNest orders.

#### Desired outcome
Only eligible completed, non-refunded platform transactions contribute anonymized sold-price observations with clear source/sample labels.

#### Scope
- Model immutable sold-price observation from order snapshots.
- Define eligibility/exclusion for refunds, cancellations, disputes, internal/test data, and outliers.
- Add aggregated API response with count, period, language/variant/condition dimensions as available.

#### Acceptance criteria
- Observation is emitted once per eligible order item.
- Refund/correction policy updates or excludes affected statistics deterministically.
- Values use integer minor units and never expose parties/order IDs publicly.
- UI labels external asks and completed CardNest sales distinctly.

#### Technical notes
Prefer event-driven idempotent aggregation keyed by order item; store raw internal observation separately from published aggregates.

#### Testing requirements
Add lifecycle eligibility, duplicate event, refund, privacy, aggregation, and API label tests.

#### Security considerations
Apply minimum sample thresholds and prevent re-identification for rare cards/periods.

#### Operational considerations
Document correction/rebuild procedure and data freshness.

#### Out of scope
Predictive valuations and importing third-party sold transactions.

#### Dependencies
Requires immutable order snapshots and finalized refund semantics.

### CN-PROD-043 — Add collection-item photo attachments through the secure upload pipeline

**Metadata:** Priority P2 · Size M · Type Feature · Module Collection/Images · Phase E — Public release · Labels `production-readiness`, `collection`, `uploads`, `module:collection`, `phase:E` · Milestone M4 Public release · Dependencies `CN-PROD-006`, `CN-PROD-041`

#### Problem
Collectors cannot retain owned condition evidence, while reusing listing media without explicit ownership/privacy rules would be unsafe.

#### Current state
There is no `collection_item_images` implementation or owner-only upload API; marketplace images are arbitrary URL records until `CN-PROD-006`.

#### Desired outcome
Owners can attach private front/back/detail images to a physical item and explicitly copy approved versions into a listing.

#### Scope
- Add owner-scoped attachment model/order/side/caption/status.
- Reuse quarantined uploads and derivatives.
- Add attach/remove/list APIs/UI and explicit listing-copy command.

#### Acceptance criteria
- Only current owner can view/manage private collection originals.
- Listing copy creates a policy-compliant immutable reference, not accidental public access to private storage.
- Archive/sale/deletion/transfer rules are explicit and tested.
- Duplicate/reordered attachments behave deterministically.

#### Technical notes
Store object IDs/checksums, never binary data or arbitrary URLs in PostgreSQL.

#### Testing requirements
Add ownership, transfer/sale, listing-copy, deletion, ordering, failed-scan, and browser tests.

#### Security considerations
Strip EXIF/location data and use short-lived authorized delivery.

#### Operational considerations
Define quotas, retention, abuse handling, and storage-cost metrics.

#### Out of scope
AI condition grading and public photo galleries.

#### Dependencies
Requires secure uploads and derivative delivery.

### CN-PROD-044 — Paginate unbounded orders, listings, wishlists, and storage APIs

**Metadata:** Priority P2 · Size M · Type Tech debt · Module Order/Marketplace/Wishlist/Storage · Phase C — Closed beta · Labels `production-readiness`, `performance`, `pagination`, `module:shared`, `phase:C` · Milestone M2 Closed beta · Dependencies none

#### Problem
Several owner list endpoints load all matching rows, so established users can produce large queries/responses and memory pressure.

#### Current state
Participant orders, seller listings, wishlists/items, and storage locations use unbounded repository methods; meanwhile listing/binder frontend selectors fetch only the first 100 collection items, hiding later items.

#### Desired outcome
Every growing list has deterministic server pagination, explicit limits, totals/cursors as appropriate, and frontend traversal/search beyond the first page.

#### Scope
- Inventory all unbounded endpoints/repositories and define defaults/maxima.
- Add paginated request/response DTOs and stable indexed sort.
- Update frontend lists/selectors to page, search, or incrementally load all eligible data.

#### Acceptance criteria
- No audited growing list endpoint returns unbounded results.
- Users with more than 100 collection items can select any eligible item for a listing/binder.
- Invalid/oversized page requests return structured validation errors.
- Query plans use owner/status/sort indexes without N+1 growth.

#### Technical notes
Preserve endpoint compatibility where practical; otherwise version response changes. Prefer cursor pagination for highly mutable streams such as orders.

#### Testing requirements
Add >100-item API/frontend tests, stable-page mutation cases, query-count tests, and authorization checks.

#### Security considerations
Always scope in SQL by authenticated owner/participant before pagination.

#### Operational considerations
Track response size/latency and cap export-like operations separately.

#### Out of scope
Public catalog-specific search indexes/cursors (`CN-PROD-040`).

#### Dependencies
None; coordinate shared pagination DTO conventions with Catalog.

### CN-PROD-045 — Match wishlists to eligible marketplace listings

**Metadata:** Priority P2 · Size M · Type Feature · Module Wishlist/Marketplace · Phase Post-launch · Labels `production-readiness`, `wishlist`, `matching`, `module:wishlist`, `phase:post-launch` · Milestone M5 Post-launch · Dependencies `CN-PROD-019`, `CN-PROD-026`

#### Problem
Wishlists track missing criteria but do not identify available marketplace listings or notify users when a valid match appears.

#### Current state
Wishlist fulfillment reacts to registered collection items. There is no listing matcher, match persistence, deduplication, or price-alert notification.

#### Desired outcome
Active listings are matched against explicit language, variant, minimum condition, maximum price, quantity, and availability criteria without auto-buying.

#### Scope
- Define deterministic match eligibility and condition ordering.
- Create idempotent match records on listing/wishlist changes.
- Add paginated match API/UI and opt-in notifications.

#### Acceptance criteria
- Ineligible state/language/variant/condition/price never matches.
- Recalculation is idempotent and removes/invalidates stale matches.
- Users see total cost/source timestamp and must explicitly purchase.
- Ownership/privacy boundaries prevent wishlist leakage.

#### Technical notes
Use bounded event-driven candidate queries and exact minor-unit price comparison; do not build an optimization engine.

#### Testing requirements
Add criteria matrix, listing lifecycle, changed wishlist, duplicate event, authorization, and notification tests.

#### Security considerations
Rate-limit recalculation triggers and do not expose private wishlist notes.

#### Operational considerations
Measure match lag, fan-out, queue load, and notification volume.

#### Out of scope
Automatic buying, multi-seller optimization, and buy orders.

#### Dependencies
Requires canonical language constraints and notification delivery.

### CN-PROD-046 — Execute automatic binder layout strategies

**Metadata:** Priority P2 · Size M · Type Feature · Module Binder · Phase Post-launch · Labels `production-readiness`, `binder`, `layout`, `module:binder`, `phase:post-launch` · Milestone M5 Post-launch · Dependencies `CN-PROD-036`

#### Problem
Binder layout strategy values are stored/displayed but do not arrange cards or placeholders, creating misleading scaffolding.

#### Current state
`BinderLayoutStrategy` represents multiple strategies, while no application use case computes or previews an arrangement.

#### Desired outcome
Users can preview and atomically apply a deterministic set-number strategy; unsupported strategies are clearly unavailable until implemented.

#### Scope
- Implement preview/apply for set-number ordering only.
- Define occupied/manual-placeholder preservation and overflow behavior.
- Remove/disable claims for unimplemented strategies.

#### Acceptance criteria
- Preview is side-effect free and matches applied result.
- Apply locks affected slots/items and is all-or-nothing.
- Manual placements/placeholders follow documented preservation choices.
- Re-running is deterministic and idempotent.

#### Technical notes
Use command DTOs and backend authority; cap binder/item counts and avoid one transaction per slot.

#### Testing requirements
Add sparse/full/overflow/placeholder/concurrent/manual placement and API/UI tests.

#### Security considerations
Validate ownership of binder and every assigned item.

#### Operational considerations
Log aggregate move count/runtime, not card/user details.

#### Out of scope
Pokédex/type/value/custom strategies until individually specified.

#### Dependencies
Requires the slot-content invariant from `CN-PROD-036`.

### CN-PROD-047 — Persist shipment tracking events and delivery evidence

**Metadata:** Priority P2 · Size M · Type Feature · Module Shipping/Order · Phase E — Public release · Labels `production-readiness`, `shipping`, `orders`, `module:shipping`, `phase:E` · Milestone M4 Public release · Dependencies `CN-PROD-026`, `CN-PROD-029`

#### Problem
A single manual shipped/received status does not retain carrier progression or reliable evidence for support/disputes.

#### Current state
Shipping quotes and mark-shipped/confirm-received flows exist, but there is no shipment/tracking-event history or carrier adapter.

#### Desired outcome
Orders retain immutable shipment method/reference and normalized event history, with manual confirmation remaining authoritative under policy.

#### Scope
- Model shipment and append-only tracking events.
- Validate/normalize user-entered carrier/reference and expose participant-safe timeline.
- Add one replaceable carrier lookup adapter or an explicit manual-only implementation.

#### Acceptance criteria
- Events are ordered, deduplicated, participant-authorized, and cannot regress terminal order state.
- Tracking references are not public.
- Manual/no-tracking/pickup paths remain supported.
- Dispute workflow can reference immutable evidence.

#### Technical notes
Do not equate a carrier “delivered” event automatically with buyer confirmation without an approved rule.

#### Testing requirements
Add carrier/manual/pickup, duplicate/out-of-order event, ownership, status interaction, and API tests.

#### Security considerations
Validate external URLs/data, minimize address exposure, and protect webhook/adapter credentials.

#### Operational considerations
Monitor carrier errors/lag and document manual fallback.

#### Out of scope
International shipping, label purchase, and carrier insurance claims.

#### Dependencies
Requires notifications and dispute evidence semantics.

### CN-PROD-048 — Enforce retention, anonymization, and consent lifecycle rules

**Metadata:** Priority P2 · Size M · Type Feature · Module Privacy/Shared · Phase E — Public release · Labels `production-readiness`, `privacy`, `retention`, `module:privacy`, `phase:E` · Milestone M4 Public release · Dependencies `CN-PROD-012`, `CN-PROD-017`, `CN-PROD-030`

#### Problem
Documented retention intentions are not executable, so personal data may be kept forever or deleted despite financial/legal obligations.

#### Current state
No retention schedule, consent ledger, anonymization service, cleanup jobs, legal hold, or deletion evidence exists across modules.

#### Desired outcome
Approved per-data-class rules are machine-enforced, dry-runnable, auditable, and compatible with transaction retention and legal holds.

#### Scope
- Map data classes/owners/legal basis/retention/action across all existing modules.
- Add versioned consent evidence where required and withdrawal handling.
- Implement bounded scheduled cleanup/anonymization with dry run and legal holds.

#### Acceptance criteria
- Each personal-data table has an approved rule or explicit justified exception.
- Jobs are idempotent, batched, observable, and preserve referential/financial integrity.
- Legal hold prevents affected deletion and is privileged/audited.
- Dry-run counts and executed outcomes are reviewable without exposing personal data.

#### Technical notes
Prefer module-owned erasure/anonymization handlers orchestrated by Privacy; avoid broad cascading deletes.

#### Testing requirements
Add boundary-date, legal-hold, repeated-run, cross-module FK, consent withdrawal, and restore/backup-policy tests.

#### Security considerations
Restrict hold/override actions and ensure anonymized identifiers cannot be trivially reversed.

#### Operational considerations
Set schedule, owner, alert thresholds, backup-retention alignment, and periodic privacy review.

#### Out of scope
Determining legal retention periods without counsel.

#### Dependencies
Requires approved policies, privacy requests, and immutable audit support.

### CN-PROD-049 — Add frontend API timeouts, cancellation, and centralized 401 handling

**Metadata:** Priority P3 · Size S · Type Tech debt · Module Frontend · Phase Post-launch · Labels `production-readiness`, `frontend`, `reliability`, `module:shared`, `phase:post-launch` · Milestone M5 Post-launch · Dependencies `CN-PROD-014`

#### Problem
Slow/obsolete requests can continue consuming resources and authentication failures are handled inconsistently across pages.

#### Current state
`frontend/src/api/client.ts` wraps `fetch` without a default timeout, `AbortController`, safe retry policy, or centralized unauthorized/session-expired action. Some pages ignore stale responses using counters but do not cancel them.

#### Desired outcome
The shared client cancels obsolete work, times out predictably, retries only safe requests, and handles expired authentication once.

#### Scope
- Add caller cancellation and configurable deadline support.
- Centralize `401`/session-expired behavior.
- Retry only explicitly idempotent transient reads with bounded jitter.
- Update search/filter pages to cancel superseded requests.

#### Acceptance criteria
- Superseded search requests are aborted and cannot overwrite latest results.
- Timeouts/errors map to accessible stable UI messages.
- Mutations are never automatically retried without an idempotency contract.
- Concurrent `401`s trigger one auth-state transition.

#### Technical notes
Accept an `AbortSignal` in the reusable client and distinguish timeout from caller cancellation.

#### Testing requirements
Add fake-timer/client tests and browser tests for slow search, cancellation, timeout, retry, and multiple `401`s.

#### Security considerations
Do not log request bodies/tokens and preserve CSRF/credential behavior from `CN-PROD-014`.

#### Operational considerations
Measure client timeout/network-error rate without high-cardinality URLs.

#### Out of scope
Offline mode and service workers.

#### Dependencies
Implement after the final credential lifecycle is selected.

### CN-PROD-050 — Add route code splitting and Core Web Vitals budgets

**Metadata:** Priority P3 · Size S · Type Tech debt · Module Frontend · Phase Post-launch · Labels `production-readiness`, `performance`, `frontend`, `module:shared`, `phase:post-launch` · Milestone M5 Post-launch · Dependencies `CN-PROD-008`, `CN-PROD-041`

#### Problem
Every route is eagerly imported into one JavaScript bundle and there is no objective browser performance budget.

#### Current state
The production build emits one approximately 244 kB JavaScript bundle (about 76 kB gzip). `frontend/src/router/index.ts` eagerly imports pages; no Lighthouse/Core Web Vitals gate exists.

#### Desired outcome
Route-level chunks, media sizing, and measured budgets keep critical mobile flows responsive as features grow.

#### Scope
- Lazy-load non-entry routes and name stable chunks where helpful.
- Add staging Lighthouse/CWV collection for critical pages.
- Define initial JS/CSS/image, LCP, CLS, and INP budgets from measured baselines.

#### Acceptance criteria
- Login/catalog entry does not download administration/order/binder page code upfront.
- Chunk loading has accessible failure/retry behavior.
- CI/reporting flags agreed budget regressions without flaky hard gates initially.
- Baselines record device/network/test conditions.

#### Technical notes
Prefer route boundaries over tiny component chunks; keep shared design components deduplicated.

#### Testing requirements
Run build/chunk assertions and repeated Lighthouse measurements on staging.

#### Security considerations
Source maps follow an explicit private error-monitoring policy and are not publicly exposed accidentally.

#### Operational considerations
Assign a budget owner and trend reports by release.

#### Out of scope
SSR, native applications, and premature CDN/provider migration.

#### Dependencies
Requires production asset serving and representative media delivery.

### CN-PROD-051 — Add pricing periods, filters, and accessible charts

**Metadata:** Priority P3 · Size M · Type Feature · Module Pricing/Frontend · Phase Post-launch · Labels `production-readiness`, `pricing`, `accessibility`, `module:pricing`, `phase:post-launch` · Milestone M5 Post-launch · Dependencies `CN-PROD-027`, `CN-PROD-042`

#### Problem
The pricing page offers a fixed limited history and cannot compare source, period, language, variant, condition, or internal sold observations clearly.

#### Current state
History API supports a bounded day range and UI uses a fixed 30-day presentation; there is no chart or CardNest sold-price series.

#### Desired outcome
Users can select supported periods/dimensions and understand source, sample size, freshness, and the difference between asking and sold prices.

#### Scope
- Add 7/30/90/365/all period controls within retention/performance limits.
- Add source/variant/language/condition filters only where underlying data supports them.
- Add accessible chart plus equivalent data table and empty/stale states.

#### Acceptance criteria
- Labels never call active asks guaranteed market value.
- Unsupported dimensions are unavailable rather than inferred.
- Chart and table expose equal values, units, source, sample size, and timestamps.
- Queries remain bounded/cached and use minor-unit money.

#### Technical notes
Aggregate server-side to a bounded number of points and retain raw observations per policy.

#### Testing requirements
Add aggregation/filter/API tests, chart accessibility tests, and timezone/empty/sparse period cases.

#### Security considerations
Apply sample thresholds to CardNest sales and public rate limits.

#### Operational considerations
Track cache hit, query latency, provider freshness, and aggregation rebuild failures.

#### Out of scope
Forecasts, investment advice, and automatic purchase recommendations.

#### Dependencies
Requires safe async external pricing and internal sold-price observations.

### CN-PROD-052 — Add reviewable fraud-risk signals

**Metadata:** Priority P3 · Size M · Type Feature · Module Moderation/Reporting · Phase Post-launch · Labels `production-readiness`, `fraud`, `moderation`, `module:moderation`, `phase:post-launch` · Milestone M5 Post-launch · Dependencies `CN-PROD-021`, `CN-PROD-025`, `CN-PROD-028`, `CN-PROD-029`, `CN-PROD-030`

#### Problem
Operators cannot consistently identify repeated payment failures, rapid expensive listings, dispute concentration, or reused media for review.

#### Current state
No risk-event/rule model, review queue, explanation, or false-positive feedback exists. Automatic sanctions would therefore be opaque and unsafe.

#### Desired outcome
A small transparent ruleset creates reviewable signals with evidence and never imposes a final sanction without authorized human review except explicit immediate security containment.

#### Scope
- Define three measured signals using existing reliable events (for example payment failures, high-value listing velocity, dispute rate).
- Persist rule version/evidence score/status/reviewer outcome.
- Add moderator queue/filter and feedback reason.

#### Acceptance criteria
- Every signal explains rule/version/input window and is reproducible.
- Rules are configurable, bounded, and tested against synthetic data.
- No signal alone permanently suspends a user/listing.
- Reviewer actions use RBAC and immutable audit.

#### Technical notes
Start with deterministic rules, not machine learning; minimize IP/device fingerprint data pending privacy approval.

#### Testing requirements
Add threshold/boundary, duplicate event, time-window, false-positive review, authorization, and load tests.

#### Security considerations
Threat-model evasion, poisoning, reviewer abuse, and protected personal/device data.

#### Operational considerations
Track signal volume, precision proxy, review backlog/SLA, overrides, and rule-change approval.

#### Out of scope
Automated permanent sanctions, biometric/device fingerprint vendors, and machine-learning models.

#### Dependencies
Requires trustworthy payment/seller/report/dispute events plus RBAC/audit and privacy approval.

## 6. Creation and dependency rules

If issue creation is authorized later:

1. Search open and closed issues by exact title and module before creating anything.
2. Reuse existing #198 for modal focus and link it as a dependency of `CN-PROD-035`.
3. Create the 17 epics first, then the 52 issues in the order in section 4 and by phase for P2/P3.
4. Add each issue to the existing CardNest project with status `Todo`; set all metadata fields from this document.
5. Link dependencies explicitly; do not start a dependent issue merely because its epic exists.
6. Do not assign target dates until an owner/capacity plan and external provider/legal decisions exist.

The critical path is:

`001 → 002 → 003 → 020/021 → 022 → 023/024 → controlled payments`, in parallel with `007/008 → 009 → 004/006/010/011/033 → staging/closed beta`, and with `012 → 017/025/028/029/030/048 → public release`.

## 7. Explicitly deferred product scope

This readiness backlog does not propose auctions, negotiation, trades, automatic buying, sealed products, other TCGs, international shipping, mobile apps, AI condition grading, grading submissions, complex optimization, or predictive prices. Those are outside the requested production-readiness path and should not displace P0/P1 work.
