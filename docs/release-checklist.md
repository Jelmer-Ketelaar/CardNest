# MVP release checklist

This is an engineering release gate for the assembled CardNest MVP. Completing every checkbox does not create legal,
security, accessibility, privacy, financial or product approval by itself; the accountable release owner records that
decision for the target environment.

## Release record

```text
Release candidate / commit:
Target environment:
Date and accountable release owner:
Automated results:
Deployment/restore evidence:
Known limitations and linked issues:
Decision: ready for the defined environment / blocked
```

Do not put passwords, tokens, personal data, provider references or secret configuration in this record.

## Quality evidence

- [ ] Run backend migrations against a fresh PostgreSQL database.
- [ ] Run `docker compose run --rm -e APP_ENV=test backend php bin/phpunit`.
- [ ] Run `docker compose run --rm backend vendor/bin/phpstan analyse --no-progress --memory-limit=512M`.
- [ ] Run `docker compose run --rm backend vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes`.
- [ ] Run `docker compose run --rm --no-deps frontend npm run lint`.
- [ ] Run `docker compose run --rm --no-deps frontend npm test`.
- [ ] Run `docker compose run --rm --no-deps frontend npm run build`.
- [ ] Run `docker compose run --rm --no-deps frontend npm run test:e2e`.
- [ ] Record the environment, date and results in the
  [MVP regression issue](https://github.com/Jelmer-Ketelaar/CardNest/issues/161) and
  [regression verification record](release-verification.md).

## Release reviews

- [ ] Complete the [security review checklist](security-review.md).
- [ ] Complete the [accessibility review](accessibility-review.md), including any release-blocking keyboard, focus,
  contrast and assistive-technology findings.
- [ ] Confirm the [privacy notes](privacy.md), [data-export design](data-export-design.md) and
  [account-deletion design](account-deletion-design.md) are still accurate for the target scope.
- [ ] Confirm [backup restore evidence](backups.md) and production-environment prerequisites are available.
- [ ] Execute the [deployment checklist](deployment-checklist.md) for the target environment.
- [ ] Review external dependencies, image/data licensing and third-party provider decisions separately; no approval is
  implied by repository documentation.

## Known MVP limitations

- Fake checkout is for development and test flows only. There is no real payment-provider integration, seller
  verification, payout or handling of payment credentials.
- There is no dispute, chargeback, moderation or fraud-response workflow.
- There are no partial item refunds, partial quantity refunds or refund-fee calculations. The current `develop` branch
  also has no released order-cancellation/refund flow.
- Notifications, price history/analytics, trading, auctions, negotiation, card scanning, grading workflows, sealed
  products, other TCGs and international delivery are outside this MVP.
- There is no production object-storage upload implementation, privacy export endpoint or account-deletion endpoint.
- The accessibility review is a baseline only; modal focus management is tracked in
  [#198](https://github.com/Jelmer-Ketelaar/CardNest/issues/198), and contrast/screen-reader evidence remains a
  release input.

## Final decision

- [ ] Verify that every blocking issue is closed or has an explicitly accepted, documented exception.
- [ ] Confirm release notes accurately state delivered functionality and the limitations above.
- [ ] Record the accountable decision. Do not infer approval from a green CI run alone.
