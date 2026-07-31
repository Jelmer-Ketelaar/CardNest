<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

/**
 * Configurable, closed vocabulary of card variants (see product spec section 11.3).
 * Variants are intentionally not stored as free text so listings and searches can
 * filter and compare reliably.
 */
enum VariantCode: string
{
    case Normal = 'normal';
    case Holo = 'holo';
    case ReverseHolo = 'reverse_holo';
    case CosmosHolo = 'cosmos_holo';
    case Stamped = 'stamped';
    case Promo = 'promo';
    case FirstEdition = 'first_edition';
    case Unlimited = 'unlimited';
    case Shadowless = 'shadowless';
    case AlternateArt = 'alternate_art';
    case IllustrationRare = 'illustration_rare';
    case SpecialIllustrationRare = 'special_illustration_rare';
    case Gold = 'gold';
    case Rainbow = 'rainbow';
    case MasterBall = 'master_ball';
    case PokeBall = 'poke_ball';
    case Other = 'other';
}
