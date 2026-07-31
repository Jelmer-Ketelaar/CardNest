<?php

declare(strict_types=1);

namespace App\Wishlist\Application;

use App\Catalog\Domain\Entity\CardVariant;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Repository\CatalogCardRepository;
use App\Wishlist\Application\Exception\WishlistCardUnavailable;
use App\Wishlist\Application\Exception\WishlistVariantUnavailable;

final readonly class WishlistReferenceResolver
{
    public function __construct(private CatalogCardRepository $catalogCards)
    {
    }

    public function card(string $id): CatalogCard
    {
        $card = $this->catalogCards->get($id);
        if (!$card instanceof CatalogCard) {
            throw new WishlistCardUnavailable('De gekozen cataloguskaart bestaat niet.');
        }

        return $card;
    }

    public function optionalVariant(CatalogCard $card, ?string $id): ?CardVariant
    {
        if ($id === null) {
            return null;
        }

        foreach ($card->getVariants() as $variant) {
            if ($variant->getId()->toRfc4122() === $id) {
                return $variant;
            }
        }

        throw new WishlistVariantUnavailable('De gekozen variant is niet beschikbaar voor deze kaart.');
    }
}
