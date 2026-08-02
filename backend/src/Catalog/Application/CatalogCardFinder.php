<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Application\Dto\CatalogCardCollectionResponse;
use App\Catalog\Application\Dto\CatalogCardResponse;
use App\Catalog\Application\Dto\SearchCatalogCardsRequest;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use App\Catalog\Domain\Repository\CatalogCardRepository;
use App\Catalog\Domain\Repository\CatalogCardSearchCriteria;

final readonly class CatalogCardFinder
{
    public function __construct(private CatalogCardRepository $catalogCards)
    {
    }

    public function search(SearchCatalogCardsRequest $request): CatalogCardCollectionResponse
    {
        $criteria = new CatalogCardSearchCriteria(
            setId: $request->setId,
            seriesId: $request->seriesId,
            name: $request->name,
            number: $request->number,
            rarity: $request->rarity,
            variant: null !== $request->variant ? VariantCode::from($request->variant) : null,
            artistIds: $request->artistIds,
            artist: $request->artist,
            page: $request->page,
            perPage: $request->perPage,
        );

        $result = $this->catalogCards->search($criteria);

        return new CatalogCardCollectionResponse($result->items, $result->total, $result->page, $result->perPage);
    }

    public function get(string $id): ?CatalogCardResponse
    {
        $card = $this->catalogCards->get($id);

        return $card instanceof CatalogCard ? new CatalogCardResponse($card) : null;
    }
}
