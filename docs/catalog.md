# Catalog and artist data

The Catalog module is the public, read-only source of truth for card series, language-specific sets, catalog cards,
variants, artists, and provider references. A `CatalogCard` describes an official catalog entry; it never represents
a physical card owned by a user.

Set and card ingestion is staged, validated, audited, and published per set. The complete lifecycle, idempotency
rules, failure semantics, and operator query are documented in [catalog-import.md](catalog-import.md).
Typed source discrepancies, evidence manifests, per-dimension measurements, and release-gate semantics are
documented in [catalog-coverage.md](catalog-coverage.md).

## Artist model

`Artist` is the canonical technical term. Each artist has a canonical display name, normalized name, stable slug,
optional descriptive fields, and provider references. `CatalogCardArtist` is the many-to-many relation between a
card and an artist. It stores a role and display order so future sources can represent multiple contributors without
changing the schema. The legacy `CatalogCard.illustrator` value remains during migration and compatibility work.

Names are trimmed, internal Unicode whitespace is collapsed, and normalized with Unicode-aware lowercase matching.
The database prevents duplicate normalized names and duplicate slugs. A slug collision between distinct normalized
names gets a deterministic hash suffix; names are never silently merged solely because their ASCII slugs match.

Public artist reads expose only catalog metadata. Biography, website, or profile imagery must not be populated until
CardNest has a trustworthy source and the necessary usage rights.

## Source-backed reconciliation

`CatalogArtistProvider` is an optional provider capability separate from the general set/card importer. The TCGdex
adapter reads the language illustrator index and its card summaries in bounded concurrent batches. It does not need
to fetch every detailed card payload.

Run all artists for every language already represented by local card sets:

```bash
docker compose exec backend php bin/console --no-debug catalog:import:tcgdex-artists
```

Run one language or retry exact source names:

```bash
docker compose exec backend php bin/console catalog:import:tcgdex-artists --language=en --artist='Mitsuhiro Arita'
```

The application service performs these operations:

1. Backfill unlinked legacy illustrator strings into canonical artists.
2. Import source artist-to-card mappings per language.
3. Match cards only through the unique `source + language:external-id` reference.
4. Add missing relations and preserve existing relations idempotently.
5. Record provider references on the artist.
6. Count and sample absent local cards and conflicting legacy attributions.

Doctrine is flushed and cleared in bounded batches. A failed command retains completed batches and can safely be
rerun. Full imports must run with Symfony debug disabled to avoid retaining query-profiler data for a long-running
operation.

## Completeness and conflict policy

CardNest does not infer an attribution from card names, artwork, search results, or unrelated marketplace data. A
provider card that is absent from the local catalog is reported as missing. A source attribution that conflicts with
a non-empty legacy attribution is retained as an explicit conflict and does not silently overwrite the stored value.
Persistent discrepancy records now retain typed import and coverage failures with an auditable resolution history.
An immutable coverage snapshot can only be created from a successful versioned import and an approved manifest;
current command totals remain diagnostics and are not themselves a completeness claim.

The command reports duration and the last processed source group. TCGdex does not currently expose a dataset
version or cutoff in these responses, so CardNest reports that cutoff as unavailable rather than substituting the
local execution time.

“All artists” means all artist records supplied by the configured provider for the selected, locally imported
languages at the time of the run. It does not establish global artist, set, card, image, or language completeness.
TCGdex language coverage differs, upstream records can change, and CardNest must continue to report coverage per
source and language.

The current schema still lacks Pokémon species, forms, card-to-Pokémon mappings, and source-backed official set
positions. Coverage reports those dimensions as `not_verified`; CardNest cannot currently pass a global Pokémon
catalog release gate. Natural card-number ordering remains available for stable UI pagination, but is not mislabeled
as official provider order.

The current relational coverage can be reproduced without relying on documentation counters:

```sql
SELECT COUNT(*) FROM artists;
SELECT COUNT(*) FROM catalog_card_artists;
SELECT COUNT(DISTINCT catalog_card_id) FROM catalog_card_artists;

SELECT card_sets.language,
       COUNT(DISTINCT catalog_cards.id) AS catalog_cards,
       COUNT(DISTINCT catalog_card_artists.catalog_card_id) AS cards_with_artist
FROM card_sets
JOIN catalog_cards ON catalog_cards.set_id = card_sets.id
LEFT JOIN catalog_card_artists ON catalog_card_artists.catalog_card_id = catalog_cards.id
GROUP BY card_sets.language
ORDER BY card_sets.language;
```

## Source and image limitations

Provider metadata and image URLs have separate legal and quality considerations. The catalog importer currently
stores TCGdex source data and external image references; it does not establish that CardNest may redistribute or
cache underlying card artwork. Production use requires an approved image-provider policy and documented attribution,
licensing, retention, and takedown decisions.
