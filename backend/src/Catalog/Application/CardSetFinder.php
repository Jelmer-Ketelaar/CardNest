<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Application\Dto\CardSetCollectionResponse;
use App\Catalog\Application\Dto\CardSetResponse;
use App\Catalog\Application\Dto\ListCardSetsRequest;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Repository\CardSetRepository;

final readonly class CardSetFinder
{
    public function __construct(private CardSetRepository $cardSets)
    {
    }

    public function list(ListCardSetsRequest $request): CardSetCollectionResponse
    {
        $result = $this->cardSets->list($request->page, $request->perPage);

        return new CardSetCollectionResponse($result->items, $result->total, $result->page, $result->perPage);
    }

    public function get(string $id): ?CardSetResponse
    {
        $set = $this->cardSets->get($id);

        return $set instanceof CardSet ? new CardSetResponse($set) : null;
    }
}
