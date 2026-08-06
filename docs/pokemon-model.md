# Pokémon species and form identity model

CardNest models a Pokémon species as a catalog identity, independent from a card name, card mechanic, physical
collection item, or marketplace listing. The technical names remain `PokemonSpecies` and related English domain
terms. This foundation does not itself prove that the registry is complete.

## Species

`PokemonSpecies` stores:

- a UUIDv7 public identifier;
- a unique positive National Dex number;
- canonical and Unicode-normalized names;
- a stable slug;
- a positive generation number;
- an optional official introduction date;
- creation and update timestamps.

Canonical names retain their display diacritics. Input is normalized to Unicode NFC, surrounding whitespace is
trimmed, repeated internal whitespace is collapsed, and duplicate matching uses Unicode-aware lowercase text.
Slugs are transliterated for readable URLs. The gender symbols in `Nidoran♀` and `Nidoran♂` are explicitly rendered
as `female` and `male` so those two official species cannot collide.

## Searchable aliases

`PokemonSpeciesAlias` stores alternative identity names relationally because these values will be searched and
deduplicated. An alias records:

- its canonical and normalized value;
- a closed type: localized name, alternate spelling, transliteration, or former official name;
- an optional normalized language tag;
- a bounded source and optional source reference;
- its creation timestamp.

One species cannot contain the same normalized alias twice. An alias equal to the canonical species name is rejected
as redundant. The current issue adds exact identity lookup only; prefix, partial, Pokédex, and accent-insensitive
public search belong to the later Pokémon API issue.

## Official forms

`PokemonForm` belongs to exactly one species and stores a canonical and normalized form name, a species-scoped slug,
a closed form type, a default flag, and timestamps. Supported classifications are default, regional, Mega Evolution,
Primal Reversion, Gigantamax, and other official alternate forms. The generic alternate classification accommodates
official identities such as Origin Forme without inventing a new type for every franchise-specific label.

One species can have at most one default form. Both the aggregate and a PostgreSQL partial unique index enforce this;
the database index also protects concurrent imports. The `default` type and `isDefault` flag must agree. Normalized
names and slugs are unique within a species, while the same generic form label may legitimately occur for different
species.

Known card mechanics (`ex`, `EX`, `GX`, `V`, `VMAX`, `VSTAR`, `BREAK`, `Radiant`, and `Shining`) are rejected when
they appear as standalone form-name tokens. These concepts describe a card printing or mechanic, not a biological
Pokémon identity. CardNest does not infer forms from current card names or `primaryCollectible` text.

## External references

`PokemonSpeciesExternalReference` and `PokemonFormExternalReference` each map one bounded
`provider + external ID` pair to at most one identity of their respective type. They store a nullable source version
and explicit import/synchronization timestamps. Re-recording the same reference updates its sync information instead
of creating a duplicate. Raw provider payloads are deliberately not stored in these tables.

The model is ready for a later approved registry provider, but this change performs no network import and designates
no provider as globally authoritative.

## Boundaries and current status

The following remain separate follow-up work:

- importing a source-backed species/forms registry at an explicit data cutoff;
- linking catalog cards to species/forms with role, confidence, source, and display order;
- public Pokémon list, search, and detail APIs;
- Pokémon frontend pages;
- reconciling coverage and discrepancies against an approved manifest.

Until an approved import and reconciliation run exists, species and form coverage stay `not_verified`. Database row
counts show stored records, not expected global coverage:

```sql
SELECT COUNT(*) AS stored_species FROM pokemon_species;
SELECT COUNT(*) AS stored_aliases FROM pokemon_species_aliases;
SELECT COUNT(*) AS stored_forms FROM pokemon_forms;
SELECT provider, COUNT(*)
FROM pokemon_species_external_references
GROUP BY provider
ORDER BY provider;
SELECT provider, COUNT(*)
FROM pokemon_form_external_references
GROUP BY provider
ORDER BY provider;
```
