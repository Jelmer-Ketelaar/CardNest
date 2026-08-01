<?php

declare(strict_types=1);

namespace App\Order\Application;

use App\Collection\Application\RegisteredCollectionItemsProcessor;
use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Entity\CollectionItem;
use App\Collection\Domain\Repository\CollectionItemRepository;
use App\Collection\Domain\Repository\CollectionRepository;
use App\Identity\Domain\Entity\User;
use App\Order\Application\Dto\ImportOrderItemRequest;
use App\Order\Application\Dto\OrderResponse;
use App\Order\Application\Exception\OrderItemNotFound;
use App\Order\Application\Exception\OrderNotFound;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderItem;
use App\Order\Domain\Exception\InvalidCollectionImport;
use App\Order\Domain\Repository\OrderRepository;
use App\Shared\Domain\Transaction\TransactionManager;
use App\Storage\Application\StorageLocationResolver;
use App\Storage\Domain\Repository\CollectionItemLocationRepository;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class ImportOrderItemToCollection
{
    public function __construct(
        private OrderRepository $orders,
        private CollectionRepository $collections,
        private CollectionItemRepository $collectionItems,
        private RegisteredCollectionItemsProcessor $registeredItemsProcessor,
        private StorageLocationResolver $storageLocations,
        private CollectionItemLocationRepository $placements,
        private TransactionManager $transactions,
    ) {
    }

    public function import(
        User $buyer,
        string $orderId,
        string $orderItemId,
        ImportOrderItemRequest $request,
    ): OrderResponse {
        $order = $this->transactions->run(function () use ($buyer, $orderId, $orderItemId, $request): Order {
            $order = $this->orders->lockForBuyer($orderId, $buyer);
            if (!$order instanceof Order) {
                throw new OrderNotFound('Order niet gevonden.');
            }

            $orderItem = $this->findItem($order, $orderItemId);
            if (!$orderItem instanceof OrderItem) {
                throw new OrderItemNotFound('Orderitem niet gevonden.');
            }
            if (!$orderItem->canBeImportedBy($buyer)) {
                throw new InvalidCollectionImport('Dit orderitem kan niet aan de collectie worden toegevoegd.');
            }

            $location = $this->storageLocations->optionalOwned($request->storageLocationId, $buyer);
            $source = $orderItem->getListing()->getCollectionItem();
            $importedAt = new DateTimeImmutable();
            $collection = $this->collections->getOrCreateForOwner($buyer);
            $collectionItem = new CollectionItem(
                collection: $collection,
                catalogCard: $source->getCatalogCard(),
                variant: $source->getVariant(),
                language: CardLanguage::from($request->language),
                condition: CardCondition::from($request->condition),
                purchasePrice: $orderItem->getPrice(),
                purchasedAt: $order->getPaidAt(),
                notes: $request->notes,
                createdAt: $importedAt,
            );
            $orderItem->recordCollectionImport($collectionItem, $buyer, $importedAt);
            $this->registeredItemsProcessor->process($buyer, [$collectionItem]);
            $this->collectionItems->saveMany([$collectionItem]);
            if ($location !== null) {
                $this->placements->place($collectionItem, $location);
            }

            return $order;
        });

        return new OrderResponse($order, $buyer);
    }

    private function findItem(Order $order, string $itemId): ?OrderItem
    {
        try {
            $uuid = Uuid::fromString($itemId);
        } catch (Throwable) {
            return null;
        }

        foreach ($order->getItems() as $item) {
            if ($item->getId()->equals($uuid)) {
                return $item;
            }
        }

        return null;
    }
}
