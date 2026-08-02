# MVP regression verification

This is an engineering regression record for the CardNest MVP. It records automated evidence only; it is not a
production deployment approval, security certification, accessibility certification, privacy approval or legal
approval.

## Run record

- **Date:** 2026-08-01 UTC
- **Verified commit:** `62991dd786dbeaebd609c6e7e115f420db64a3e8`
- **Environment:** local Docker Compose services with PHP 8.4, PostgreSQL 16, Redis and the repository frontend
  container.
- **Database:** migrations were applied to a new, isolated PostgreSQL test database before verification. The
  temporary database contained no user or production data and was removed after the run.

## Automated results

| Check | Result |
| --- | --- |
| Doctrine migrations on a fresh database | Passed — 15 migrations applied |
| Doctrine mapping and schema validation | Passed — mappings valid and schema in sync |
| Backend PHPUnit suite | Passed — 139 tests, 1153 assertions |
| PHPStan | Passed |
| PHP-CS-Fixer dry run | Passed — no files require changes |
| Frontend lint and type check | Passed |
| Frontend Vitest suite | Passed — 23 files, 68 tests |
| Frontend production build | Passed |
| Playwright browser suite | Passed — 16 Chromium flows |

The browser suite covers registration, e-mail verification, account logout, collection, storage, binders, wishlists,
marketplace listings, cart checkout, shipment, receipt confirmation, collection import and reviews.

## Follow-up and release inputs

- No automated-regression failures remain from this run.
- [#198](https://github.com/Jelmer-Ketelaar/CardNest/issues/198) tracks modal focus management identified by the
  accessibility baseline. It remains a Future-phase item and must be assessed by the accountable release owner for
  any target environment.
- Review the [MVP release checklist](release-checklist.md), including security, accessibility, privacy, backup and
  deployment evidence, before making an environment-specific release decision.
