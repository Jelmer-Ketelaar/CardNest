# Catalog import lifecycle

Catalog imports enter CardNest through the replaceable `CardCatalogProvider` port. Provider-specific HTTP and
payload parsing stays in Infrastructure; the application use case receives normalized set and card records. TCGdex
is the current adapter, but it is not treated as an infallible or globally complete source.

## Run audit trail

Every invocation creates a `CatalogImportRun` before contacting the provider. The durable audit record contains:

- provider and canonical scope checksum;
- normalized languages, requested set IDs, and detail level;
- optional immutable source version and source data cutoff;
- content checksum and staged/published set and card counts;
- warning and error counts, safe failure summary, and lifecycle timestamps;
- a link to the completed run when an immutable source release is replayed.

Run statuses are `running`, `validating`, `publishing`, `completed`, `completed_with_warnings`, `skipped`, and
`failed`. Database constraints prevent invalid terminal timestamps, negative or over-published counts, failure rows
without an error, and skipped rows without an original completed run.

TCGdex currently supplies neither an immutable dataset version nor a source cutoff. Operators must leave these
fields empty for ordinary live TCGdex calls instead of substituting CardNest's execution time. Approved snapshot
imports can provide both explicitly:

```bash
docker compose exec backend php bin/console catalog:import:tcgdex \
  --language=en \
  --set=base1 \
  --source-version=approved-snapshot-2026-08-03 \
  --data-cutoff=2026-08-03T22:00:00+00:00
```

## Stage, validate, publish

The import flow is deliberately split into phases:

1. Acquire a non-blocking lock for the provider and normalized request scope.
2. Stream normalized sets and cards into PostgreSQL staging tables.
3. Enforce record lengths, set/card ownership, unique references, declared totals, variants, and requested scope.
4. Compute a deterministic SHA-256 checksum over canonical staged JSON.
5. Check immutable source-version replay safety.
6. Publish each validated set and its progress counters in one database transaction.
7. Mark the run terminal and remove its temporary staging rows.

No live catalog row is changed while provider data is being fetched or while the requested scope is being
validated. A provider or validation failure therefore leaves the live catalog untouched. Staging uses durable
`jsonb` rows rather than process memory; each normalized set or card payload is limited to 4 MiB.

Publication is atomic **per set**, not across a potentially global multi-language import. If set 12 fails after 11
sets were committed, both set 12 and its progress update roll back as a unit. The run becomes `failed`, and its
published counts identify the partial result. This avoids one unbounded global transaction while ensuring a partial
publish is never silent.
Rerunning the failed scope safely synchronizes existing records and continues toward the desired source state.

## Idempotency and concurrency

An exact replay with the same non-null source version and checksum is recorded as `skipped` and linked to the
original completed run. Reusing that source version with different content fails validation before publication.
When no immutable source version exists, CardNest publishes the validated data again through idempotent natural
references; this allows upstream corrections to be synchronized instead of incorrectly skipping an older checksum.

Only one invocation can hold the same provider/scope lock. A concurrent duplicate is rejected and audited as a
failed run. Database unique constraints remain the final protection for overlapping but differently expressed
scopes.

## Manual corrections and source data

Provider raw data can be refreshed, but `CardExternalReference.correctedData` remains separate and is never erased
by synchronization. Detailed card imports do not overwrite an explicit correction. Typed validation failures are
persisted as catalog discrepancies. Coverage gaps and operator resolution semantics are described in
[catalog-coverage.md](catalog-coverage.md); arbitrary provider payloads remain outside API responses.

## Operations

Recent failed or partial imports can be found with:

```sql
SELECT id,
       provider,
       data_scope,
       status,
       staged_set_count,
       staged_card_count,
       published_set_count,
       published_card_count,
       failure_reason,
       started_at,
       completed_at
FROM catalog_import_runs
WHERE status = 'failed'
ORDER BY started_at DESC;
```

Run rows are audit records. Staging rows are temporary and are deleted after every terminal outcome. If a process is
terminated without executing cleanup, the foreign keys keep those rows associated with their run; a future
retention/repair command may remove abandoned staging after an operationally approved threshold.

## Source and licensing boundary

The importer stores provider metadata and external image references. It does not prove global catalog completeness
and does not grant CardNest rights to cache or redistribute card artwork. Coverage, licensing, attribution,
retention, and takedown decisions remain separate release gates.
