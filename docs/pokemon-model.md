# Pokémon species identity model

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

## External references

`PokemonSpeciesExternalReference` maps one bounded `provider + external ID` pair to at most one species. It stores a
nullable source version and explicit import/synchronization timestamps. Re-recording the same reference updates its
sync information instead of creating a duplicate. Raw provider payloads are deliberately not stored in this table.

The model is ready for a later approved registry provider, but this change performs no network import and designates
no provider as globally authoritative.

## Boundaries and current status

The following remain separate follow-up work:

- `PokemonForm` and its source references;
- importing a source-backed species/forms registry at an explicit data cutoff;
- linking catalog cards to species/forms with role, confidence, source, and display order;
- public Pokémon list, search, and detail APIs;
- Pokémon frontend pages;
- reconciling coverage and discrepancies against an approved manifest.

Card mechanics such as `ex`, `EX`, `GX`, `V`, `VMAX`, `VSTAR`, `BREAK`, `Radiant`, and `Shining` are not biological
species and are not aliases or forms. They remain card-domain concepts.

Until an approved import and reconciliation run exists, species coverage stays `not_verified`. Database row counts
show stored records, not expected global coverage:

```sql
SELECT COUNT(*) AS stored_species FROM pokemon_species;
SELECT COUNT(*) AS stored_aliases FROM pokemon_species_aliases;
SELECT provider, COUNT(*)
FROM pokemon_species_external_references
GROUP BY provider
ORDER BY provider;
```
