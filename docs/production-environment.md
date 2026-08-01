# Production-environment expectations

CardNest currently provides Docker Compose for local development and CI configuration for automated checks. This
document is a production readiness guide, not a deployment manifest or proof that a production environment exists.
It intentionally contains no real hostnames, tokens, credentials or provider configuration.

## Runtime topology

The MVP expects the following separately managed concerns:

- a TLS-terminating edge/reverse proxy and the Vue static build;
- the Symfony API runtime and a separately supervised Symfony Messenger worker;
- PostgreSQL as the durable system of record;
- Redis for Messenger transport and cache/queue responsibilities;
- managed secret injection; and
- monitoring, log aggregation and a backup/restore capability.

The fake payment adapter is development/test functionality only. It must not be presented as a production payment
integration, and real payment, payout and seller-verification work require a separate reviewed module.

## Configuration categories

| Category | Existing examples | Production expectation |
| --- | --- | --- |
| Application | `APP_ENV`, `APP_SECRET`, `DEFAULT_URI` | Use `APP_ENV=prod`; generate and inject a unique secret through a managed secret mechanism; set the canonical HTTPS URI deliberately. |
| Database | `DATABASE_URL` | Use a restricted, encrypted-in-transit PostgreSQL connection with separate runtime and migration credentials where operations requires it. |
| Queue/cache | `MESSENGER_TRANSPORT_DSN` | Use a protected Redis endpoint, capacity/availability monitoring and a supervised worker restart policy. |
| Mail | `MAILER_DSN` | Select a reviewed real delivery adapter only after its secret, privacy and failure-handling requirements are defined. |
| Checkout | `CART_RESERVATION_MINUTES` | Keep the configured value within the application's supported 1–60 minute range and review its operational impact. |
| Frontend | `VITE_API_BASE_URL` | Use a deliberate same-origin/proxy design or configure a restrictive CORS policy before separate origins are introduced. |

Examples in `.env.example` and `compose.yaml` are development defaults, not production secrets or values.

## Network and platform controls

- Terminate HTTPS, redirect insecure traffic, renew certificates and set reverse-proxy trust deliberately.
- Restrict direct network access to PostgreSQL, Redis, queues, object storage and observability systems.
- Define Content Security Policy, referrer policy, clickjacking protection, MIME sniffing protection and HSTS where
  appropriate for the deployed domain.
- Build immutable backend/frontend artifacts from a reviewed commit; do not mount a mutable source tree in production.
- Run migrations as an explicit deployment step, not as an unreviewed side effect of a public traffic restart.
- Use least-privilege service accounts and rotate secrets through the deployment platform rather than committing them.

## Observability and response

- Production Monolog is configured to emit JSON to stderr. Route those logs to controlled storage with a documented
  access policy.
- Monitor API availability through `/api/v1/health`, worker availability, PostgreSQL health/capacity, Redis health,
  queue depth and failed-message handling.
- Alert on failed migrations, repeated authentication/rate-limit events, unhandled API exceptions, queue failure and
  backup/restore failures without logging secrets or unnecessary personal data.
- Keep a tested rollback and incident-response path; a database migration rollback is not automatically safe.

## Release boundary

Before exposing CardNest publicly, complete the [security review](security-review.md),
[accessibility review](accessibility-review.md), [backup expectations](backups.md),
[deployment checklist](deployment-checklist.md) and [release checklist](release-checklist.md). Record unresolved
risks as tracked issues rather than treating this document as approval.
