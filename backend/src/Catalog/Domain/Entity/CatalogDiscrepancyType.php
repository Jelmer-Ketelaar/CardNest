<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

enum CatalogDiscrepancyType: string
{
    case MissingSet = 'missing_set';
    case MissingCard = 'missing_card';
    case MissingPokemon = 'missing_pokemon';
    case MissingForm = 'missing_form';
    case MissingArtist = 'missing_artist';
    case MissingImage = 'missing_image';
    case MissingCardNumber = 'missing_card_number';
    case DuplicateCard = 'duplicate_card';
    case DuplicateSet = 'duplicate_set';
    case ConflictingName = 'conflicting_name';
    case ConflictingNumber = 'conflicting_number';
    case ConflictingReleaseDate = 'conflicting_release_date';
    case ConflictingTotal = 'conflicting_total';
    case ConflictingSourceVersion = 'conflicting_source_version';
    case OrphanCard = 'orphan_card';
    case UnmappedPokemon = 'unmapped_pokemon';
    case LowResolutionImage = 'low_resolution_image';
    case UnknownVariant = 'unknown_variant';
    case ProviderError = 'provider_error';
}
