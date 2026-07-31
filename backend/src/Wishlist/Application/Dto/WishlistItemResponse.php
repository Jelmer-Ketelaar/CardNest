<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Dto;

use App\Wishlist\Domain\Entity\WishlistItem;

final readonly class WishlistItemResponse
{
    public function __construct(private WishlistItem $item)
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $card = $this->item->getCatalogCard();
        $variant = $this->item->getDesiredVariant();

        return [
            'id' => $this->item->getId()->toRfc4122(),
            'card' => [
                'id' => $card->getId()->toRfc4122(),
                'name' => $card->getName(),
                'number' => $card->getNumber(),
                'thumbnailUrl' => $card->getThumbnailUrl(),
                'set' => [
                    'id' => $card->getSet()->getId()->toRfc4122(),
                    'name' => $card->getSet()->getName(),
                ],
            ],
            'language' => $this->item->getDesiredLanguage()?->value,
            'variant' => $variant !== null ? [
                'id' => $variant->getId()->toRfc4122(),
                'code' => $variant->getCode()->value,
            ] : null,
            'minimumCondition' => $this->item->getMinimumCondition()?->value,
            'maxPrice' => $this->item->getMaxPrice()?->toArray(),
            'desiredQuantity' => $this->item->getDesiredQuantity(),
            'fulfilledQuantity' => $this->item->getFulfilledQuantity(),
            'remainingQuantity' => $this->item->getRemainingQuantity(),
            'completed' => $this->item->isCompleted(),
            'completedAt' => $this->item->getCompletedAt()?->format(DATE_ATOM),
            'priority' => $this->item->getPriority()->value,
            'notes' => $this->item->getNotes(),
            'createdAt' => $this->item->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $this->item->getUpdatedAt()->format(DATE_ATOM),
        ];
    }
}
