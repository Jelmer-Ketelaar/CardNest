<?php

declare(strict_types=1);

namespace App\Collection\Application;

use App\Catalog\Domain\Entity\CardVariant;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Repository\CatalogCardRepository;
use App\Collection\Application\Exception\CardVariantUnavailable;
use App\Collection\Application\Exception\CatalogCardUnavailable;

final readonly class CollectionItemReferenceResolver
{
    public function __construct(private CatalogCardRepository $catalogCards)
    {
    }

    public function card(string $id): CatalogCard
    {
        $card = $this->catalogCards->get($id);
        if (!$card instanceof CatalogCard) {
            throw new CatalogCardUnavailable('De gekozen cataloguskaart bestaat niet.');
        }

        return $card;
    }

    public function variant(CatalogCard $card, string $id): CardVariant
    {
        foreach ($card->getVariants() as $variant) {
            if ($variant->getId()->toRfc4122() === $id) {
                return $variant;
            }
        }

        throw new CardVariantUnavailable('De gekozen variant is niet beschikbaar voor deze kaart.');
    }
}
