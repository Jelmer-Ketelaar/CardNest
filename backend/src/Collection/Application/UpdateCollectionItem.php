<?php

declare(strict_types=1);

namespace App\Collection\Application;

use App\Collection\Application\Dto\CollectionItemResponse;
use App\Collection\Application\Dto\UpdateCollectionItemRequest;
use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Entity\CollectionItemStatus;
use App\Collection\Domain\Repository\CollectionItemRepository;
use App\Identity\Domain\Entity\User;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use LogicException;

final readonly class UpdateCollectionItem
{
    public function __construct(
        private FindCollectionItems $finder,
        private CollectionItemReferenceResolver $references,
        private CollectionItemRepository $collectionItems,
    ) {
    }

    public function update(User $owner, string $id, UpdateCollectionItemRequest $request): CollectionItemResponse
    {
        $item = $this->finder->findOwnedItem($owner, $id);

        if ($request->variantId !== null) {
            $item->changeVariant($this->references->variant($item->getCatalogCard(), $request->variantId));
        }
        if ($request->language !== null) {
            $item->changeLanguage(CardLanguage::from($request->language));
        }
        if ($request->condition !== null) {
            $item->changeCondition(CardCondition::from($request->condition));
        }
        if ($request->clearPurchasePrice) {
            $item->changePurchasePrice(null);
        } elseif ($request->purchasePrice !== null && $request->purchaseCurrency !== null) {
            $item->changePurchasePrice(new Money($request->purchasePrice, $request->purchaseCurrency));
        }
        if ($request->clearPurchasedAt) {
            $item->changePurchasedAt(null);
        } elseif ($request->purchasedAt !== null) {
            $item->changePurchasedAt($this->date($request->purchasedAt));
        }
        if ($request->sellerName !== null) {
            $item->changeSellerName($request->sellerName);
        }
        if ($request->notes !== null) {
            $item->changeNotes($request->notes);
        }

        $this->collectionItems->save($item);
        $counts = $this->collectionItems->duplicateCounts($owner);
        $quantity = $item->getStatus() === CollectionItemStatus::InCollection
            ? $counts[$item->duplicateKey()] ?? 1
            : 0;

        return new CollectionItemResponse($item, $quantity);
    }

    private function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date instanceof DateTimeImmutable) {
            throw new LogicException('A validated purchase date must be parseable.');
        }

        return $date;
    }
}
