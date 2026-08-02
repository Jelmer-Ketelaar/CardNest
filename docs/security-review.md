# MVP security review checklist

This checklist is a repeatable engineering release gate for CardNest. It is not a security certification, legal
approval, penetration test, or claim that a deployment is production-ready. Complete it for every MVP release
candidate and record any remaining risk in a linked GitHub issue before releasing.

The [MVP release checklist](https://github.com/Jelmer-Ketelaar/CardNest/issues/160) must reference the completed
review record for the release candidate.

## Review record

Record the following with the release work. Do not put passwords, tokens, personal data, or provider credentials in
the record.

```text
Release candidate / commit:
Environment reviewed:
Reviewer and review date:
Automated checks run:
Open risks or blocking issues:
Decision: ready for the defined environment / blocked
```

## Authentication and tokens

- [ ] Confirm that the production configuration retains Symfony's automatic password hashing; the reduced hashing
  settings in `when@test` must never be deployed.
- [ ] Run the registration, login, logout, and email-verification integration tests.
- [ ] Verify that invalid credentials produce the same public response regardless of whether the e-mail address
  exists.
- [ ] Verify the login and verification-message limits configured in
  `backend/config/packages/framework.yaml`, including their behaviour at the limit.
- [ ] Confirm that persisted authentication and e-mail-verification tokens are hashes with an expiry, and that no
  plaintext token is written to the database or logs.
- [ ] Review token lifetime, logout revocation, and account-recovery requirements for the intended environment.

CardNest currently uses a stateless bearer-token API and disables Symfony sessions. A cookie-based authentication
flow must not be added without a CSRF design. Conversely, bearer tokens stored by the frontend require an explicit
XSS and Content Security Policy review before a public deployment.

## Authorization and privacy boundaries

- [ ] Review `backend/config/packages/security.yaml`: only documented registration, verification, health, and public
  read endpoints may have `PUBLIC_ACCESS`; all other `/api/v1` routes require `ROLE_USER`.
- [ ] Exercise object-level authorization for every changed private resource: an authenticated non-owner must not
  read or mutate it.
- [ ] Verify owner-safe `404` responses for private collections, wishlists, listings, orders, storage, and binder
  resources where revealing existence would be harmful.
- [ ] Verify that a user cannot buy their own listing, review themselves, or act as another order participant.
- [ ] Confirm that public profile and reputation responses do not expose private order, address, payment, or token
  data.

## Input, business rules, and error handling

- [ ] Check every new JSON input against a request DTO with Symfony Validator constraints; do not rely on client-side
  validation.
- [ ] Confirm malformed JSON, invalid UUIDs, missing fields, invalid workflow transitions, and duplicate requests
  return structured JSON errors without stack traces.
- [ ] Check money amounts remain integer minor units plus ISO currency; reject floats and client-calculated totals.
- [ ] Verify transactional flows that reserve a listing, take a payment, ship an order, or refund an order keep their
  database locks and revalidate state after locking.
- [ ] Run regression coverage for authorization, validation, concurrency-sensitive workflows, and provider failure
  paths changed by the release.

## Secrets, configuration, and source control

- [ ] Confirm only safe examples such as `.env.example` are tracked. Production `APP_SECRET`, database credentials,
  mail credentials, payment credentials, and API tokens must be injected by the deployment environment.
- [ ] Review the diff and CI logs for accidental credentials, full authentication tokens, payment data, or personal
  data.
- [ ] Confirm development-only adapters, including the logging e-mail verification sender and fake payment provider,
  are not selected for a real production integration without an explicit adapter and secret-management review.
- [ ] Review `DEFAULT_URI`, trusted proxies, allowed origins, and external service endpoints for the target
  environment; do not copy development values into production.

## Dependencies and build integrity

- [ ] Run `docker compose run --rm --no-deps backend composer audit` and record unresolved advisories.
- [ ] Run `docker compose run --rm --no-deps frontend npm audit --omit=dev` and record unresolved advisories.
- [ ] Check GitHub Dependabot alerts and security-update pull requests.
- [ ] Confirm the GitHub Actions workflow runs backend tests, PHPStan, PHP-CS-Fixer, frontend tests, type checking,
  build, and Playwright before merge.
- [ ] Review new Composer, npm, Docker, or GitHub Action dependencies for maintenance status and provenance.

## Logging, data, and operational controls

- [ ] Review changed log statements: log stable identifiers and error classes where useful, but never passwords,
  bearer tokens, full payment data, or unnecessary personal data.
- [ ] Confirm production logging uses the structured stderr handler and that access to log storage follows the target
  environment's operational policy.
- [ ] Confirm database migrations have been reviewed for destructive operations, rollback impact, and sensitive-data
  exposure.
- [ ] Confirm backups, restore verification, retention, and incident ownership are documented for the target
  environment before launch.

## Deployment boundary

The repository does not itself provision a production edge. Before any public deployment, record evidence for:

- [ ] HTTPS termination, secure redirects, and certificate renewal.
- [ ] Content Security Policy, `X-Content-Type-Options`, clickjacking protection, referrer policy, and HSTS where
  appropriate for the deployment.
- [ ] Correct reverse-proxy trust settings and a restrictive cross-origin policy.
- [ ] Network access controls for PostgreSQL, Redis, object storage, queues, and observability tooling.
- [ ] Monitoring and a tested rollback or incident-response path.

If any item is not applicable, record why. If an item is incomplete and affects the release's threat model, create or
link a blocking issue rather than checking it off.
