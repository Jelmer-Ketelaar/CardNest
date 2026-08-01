<?php

declare(strict_types=1);

namespace App\Collection\Application;

use App\Collection\Application\Dto\CreateCollectionItemRequest;
use App\Collection\Application\Dto\CreatedCollectionItemsResponse;
use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Entity\CollectionItem;
use App\Collection\Domain\Repository\CollectionItemRepository;
use App\Collection\Domain\Repository\CollectionRepository;
use App\Identity\Domain\Entity\User;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use LogicException;

final readonly class CreateCollectionItems
{
    public function __construct(
        private CollectionItemReferenceResolver $references,
        private CollectionRepository $collections,
        private CollectionItemRepository $collectionItems,
        private RegisteredCollectionItemsProcessor $registeredItemsProcessor,
    ) {
    }

    public function create(User $owner, CreateCollectionItemRequest $request): CreatedCollectionItemsResponse
    {
        $card = $this->references->card($request->catalogCardId);
        $variant = $this->references->variant($card, $request->variantId);
        $collection = $this->collections->getOrCreateForOwner($owner);
        $price = $request->purchasePrice !== null && $request->purchaseCurrency !== null
            ? new Money($request->purchasePrice, $request->purchaseCurrency)
            : null;
        $purchasedAt = $this->date($request->purchasedAt);

        $items = [];
        for ($index = 0; $index < $request->quantity; ++$index) {
            $items[] = new CollectionItem(
                collection: $collection,
                catalogCard: $card,
                variant: $variant,
                language: CardLanguage::from($request->language),
                condition: CardCondition::from($request->condition),
                purchasePrice: $price,
                purchasedAt: $purchasedAt,
                sellerName: $request->sellerName,
                notes: $request->notes,
            );
        }

        $this->registeredItemsProcessor->process($owner, $items);
        $this->collectionItems->saveMany($items);

        return new CreatedCollectionItemsResponse($items, $this->collectionItems->duplicateCounts($owner));
    }

    private function date(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date instanceof DateTimeImmutable) {
            throw new LogicException('A validated purchase date must be parseable.');
        }

        return $date;
    }
}
