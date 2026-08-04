# Catalog discrepancies and coverage gates

CardNest treats catalog completeness as an evidence-backed release decision, not as an inference from row counts.
The coverage subsystem records provider problems, measures an explicitly approved scope, and publishes an immutable
snapshot that explains whether that scope may be called complete.

## Evidence contract

A coverage snapshot can only be generated from:

1. a successful `CatalogImportRun` (`completed`, `completed_with_warnings`, or an exact immutable replay marked
   `skipped`);
2. an import with a non-null source version and data cutoff;
3. an administrator-supplied manifest with an evidence source, version, reference, region, language, cutoff, and
   explicit set IDs plus expected card counts;
4. a manifest cutoff that exactly matches the import run cutoff; and
5. for a bounded import, a manifest set list that exactly matches that bounded import scope.

The manifest is canonicalized and SHA-256 checksummed. Repeating the same run and manifest returns the existing
snapshot. CardNest does not derive expected worldwide totals from the current database, provider pagination, README
counters, or set-declared totals. An approved source manifest must be supplied by an operator.

The snapshot retains the approving administrator's UUID as an immutable audit value. Its nullable user relation may
be removed later under account-retention policy without erasing who approved the evidence.

## Dimensions and statuses

Every dimension is reported separately:

- sets;
- complete sets;
- cards;
- Pokémon species;
- Pokémon forms;
- official ordering;
- artists;
- images;
- card-to-Pokémon mappings;
- prices.

Statuses are `complete`, `mostly_complete`, `partially_complete`, `missing`, `not_verified`, or
`blocked_external`. Percentages are represented as integer basis points (`10000` = 100%) so calculations do not
depend on floating-point arithmetic.

The release gate requires verified, complete sets, set completeness, cards, Pokémon species, forms, ordering,
artists, and Pokémon mapping, with zero unresolved critical discrepancies, duplicate canonical records, and orphan
cards. Images and prices remain separate quality dimensions: licensing or upstream availability may prevent full
coverage without making catalog records disappear.

The possible gate results are:

- `passed` — every core dimension is verified and complete;
- `blocked_data_gaps` — measured core data is incomplete;
- `blocked_discrepancies` — an unresolved critical discrepancy exists for the same import scope;
- `not_verified` — required evidence or a required measurement is unavailable;
- `blocked_external` — a critical dependency has an explicitly documented external block.

### Current limitation

The current CardNest schema has no `PokemonSpecies`, `PokemonForm`, or card-to-Pokémon mapping tables. It also has a
stable natural `number_sort_key`, but no source-backed official set-position field. Pokémon and official-ordering
dimensions are therefore deliberately returned as `not_verified`; they are never inferred from card names,
`primaryCollectible`, or natural number sorting. Consequently, the current implementation cannot produce a
`passed` global Pokémon catalog gate. A successful set/card import is not proof of global completeness.

## Discrepancies

`CatalogDiscrepancy` stores a stable fingerprint, provider and import scope, typed entity/reference, severity,
operator-safe message, occurrence count, latest import run, and resolution history. Import validation records typed
failures such as missing or duplicate cards. Coverage measurement records gaps such as absent manifest sets,
conflicting card totals, incomplete ordering, missing images/artists, duplicates, and orphan cards.

The fingerprint makes repeated observations idempotent. A later import increments the occurrence count. A resolved
problem that recurs is reopened automatically and that transition is appended to the audit history. Accepted,
ignored, and externally blocked findings remain explicit operator decisions; no finding can be silently dismissed.

Resolution statuses are:

- `open`;
- `accepted`;
- `resolved`;
- `ignored_with_reason`;
- `blocked_external`.

Every non-open status requires a 10–1000 character reason and a traceable administrator. Provider payloads and
secrets are not returned by the discrepancy API.

## API and authorization

The machine-readable latest gate is public:

```text
GET /api/v1/catalog/coverage?provider=tcgdex&region=international&language=en
```

When no approved snapshot exists, the endpoint returns `not_verified` with `no_verified_snapshot`; it never falls
back to an optimistic estimate.

Administrative endpoints require `ROLE_ADMIN`:

```text
GET   /api/v1/admin/catalog/discrepancies
PATCH /api/v1/admin/catalog/discrepancies/{id}
POST  /api/v1/admin/catalog/coverage-snapshots
```

The list supports `status`, `severity`, `type`, `provider`, `language`, `region`, `page`, and `perPage` filters.
Results are ordered by explicit severity rank, newest observation, and UUID. Evidence references remain visible in
the admin create response but are omitted from the public gate because an operator may use an internal audit URI.
No general admin frontend or role assignment UI is included in this increment.

## Operator procedure

1. Run a catalog import with explicit `--source-version` and `--data-cutoff` values from the approved source.
2. Confirm that the run completed and that staging/publishing counts are correct.
3. Prepare and review a versioned coverage manifest outside the application. Do not substitute provider claims that
   have not been validated.
4. Submit the manifest through the admin API.
5. Inspect each blocking reason and open critical discrepancy.
6. Resolve, accept, or mark an external block with a concrete audit reason.
7. Run a new import and generate a new immutable snapshot after source corrections. An old snapshot does not mutate
   when a discrepancy is later resolved.

Useful database checks:

```sql
SELECT resolution_status, COUNT(*)
FROM catalog_discrepancies
GROUP BY resolution_status
ORDER BY resolution_status;

SELECT provider, region, language, release_gate_status, created_at
FROM catalog_coverage_snapshots
ORDER BY created_at DESC;
```

The live release decision should use the API snapshot, not an ad-hoc query. Database queries are for audit and
incident diagnosis only.
