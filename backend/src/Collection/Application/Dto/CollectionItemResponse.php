<?php

declare(strict_types=1);

namespace App\Collection\Application\Dto;

use App\Collection\Domain\Entity\CollectionItem;

final readonly class CollectionItemResponse
{
    public function __construct(
        private CollectionItem $item,
        private int $ownedQuantity,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $card = $this->item->getCatalogCard();
        $set = $card->getSet();

        return [
            'id' => $this->item->getId()->toRfc4122(),
            'card' => [
                'id' => $card->getId()->toRfc4122(),
                'name' => $card->getName(),
                'number' => $card->getNumber(),
                'imageUrl' => $card->getImageUrl(),
                'thumbnailUrl' => $card->getThumbnailUrl(),
                'set' => [
                    'id' => $set->getId()->toRfc4122(),
                    'name' => $set->getName(),
                ],
            ],
            'language' => $this->item->getLanguage()->value,
            'variant' => [
                'id' => $this->item->getVariant()->getId()->toRfc4122(),
                'code' => $this->item->getVariant()->getCode()->value,
            ],
            'condition' => $this->item->getCondition()->value,
            'status' => $this->item->getStatus()->value,
            'purchase' => [
                'price' => $this->item->getPurchasePrice()?->toArray(),
                'purchasedAt' => $this->item->getPurchasedAt()?->format('Y-m-d'),
                'sellerName' => $this->item->getSellerName(),
            ],
            'notes' => $this->item->getNotes(),
            'duplicate' => [
                'ownedQuantity' => $this->ownedQuantity,
                'isDuplicate' => $this->ownedQuantity > 1,
            ],
            'createdAt' => $this->item->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $this->item->getUpdatedAt()->format(DATE_ATOM),
        ];
    }
}
