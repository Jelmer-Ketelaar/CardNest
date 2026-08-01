# Deployment checklist

Use this checklist for a controlled environment deployment. It is intentionally environment-neutral: fill the release
record with the target environment, commit and accountable operator without recording secrets or personal data.

## Pre-deploy

- [ ] Identify the immutable commit/image and the target environment.
- [ ] Confirm the target is not using development credentials, development `APP_SECRET` values or the fake payment
  provider as a real payment integration.
- [ ] Review the deployment diff, migrations and known limitations.
- [ ] Confirm the production configuration is injected from the approved secret-management mechanism; do not copy
  secrets into source control, issue comments or release notes.
- [ ] Confirm PostgreSQL and Redis capacity/health and the worker supervision plan.
- [ ] Confirm a recent, verified backup and a documented restore owner; see [backups.md](backups.md).
- [ ] Confirm the release passed the [security](security-review.md) and
  [accessibility](accessibility-review.md) gates or has an explicitly accepted, tracked exception.
- [ ] Prepare a rollback decision owner and a user-impact communication path.

## Deploy

- [ ] Put the reviewed backend and frontend artifacts in the target environment.
- [ ] Apply migrations explicitly: `php bin/console doctrine:migrations:migrate --no-interaction`.
- [ ] Clear/rebuild application cache as appropriate for the artifact: `php bin/console cache:clear --env=prod`.
- [ ] Start or restart the API and Messenger worker under the target process supervisor.
- [ ] Confirm no migration or worker startup error is present in the controlled logs.
- [ ] Do not manually alter data to bypass a failed migration; stop and use the approved recovery path.

## Post-deploy smoke checks

- [ ] `GET /api/v1/health` returns success through the deployed edge.
- [ ] A public catalog/listing read works without exposing private data.
- [ ] A non-production test account can register/login or an approved equivalent authentication smoke test succeeds.
- [ ] An authenticated user sees only their own collection, storage, wishlist, cart and order data.
- [ ] A controlled fake-payment/MVP checkout test is run only where the environment policy permits it; do not use it to
  imply a real payment capability.
- [ ] The Messenger worker consumes a safe test message or health signal appropriate to the environment.
- [ ] Monitoring, error reporting and backup alerts are receiving expected signals.

## Rollback and follow-up

- [ ] If smoke checks fail, stop the rollout and assess whether traffic can return to the previous compatible artifact.
- [ ] Do not reverse a database migration automatically. Use the reviewed migration/restore plan and preserve evidence.
- [ ] Record the deployment result, observed version, migrations applied, smoke results and follow-up issue links.
- [ ] Close the release activity only after the [release checklist](release-checklist.md) decision is recorded.
