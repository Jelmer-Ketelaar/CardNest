<?php

declare(strict_types=1);

namespace App\Order\Application\Dto;

use App\Identity\Domain\Entity\User;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderItem;
use App\Order\Domain\Entity\OrderStatus;
use App\Order\Domain\Entity\OrderStatusHistory;
use App\Payment\Domain\Entity\Refund;
use DateTimeImmutable;

final readonly class OrderResponse
{
    public function __construct(
        private Order $order,
        private ?User $participant = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $address = $this->order->getShippingAddress();
        $payment = $this->order->getPayment();

        return [
            'id' => $this->order->getId()->toRfc4122(),
            'status' => $this->order->getStatus()->value,
            'buyer' => ['id' => $this->order->getBuyer()->getId()->toRfc4122()],
            'seller' => ['id' => $this->order->getSeller()->getId()->toRfc4122()],
            'items' => array_map(
                fn (OrderItem $item): array => $this->item($item),
                $this->order->getItems()->toArray(),
            ),
            'shippingMethod' => $this->order->getShippingMethod()->value,
            'costs' => [
                'items' => $this->order->getItemSubtotal()->toArray(),
                'shipping' => $this->order->getShippingPrice()->toArray(),
                'serviceFee' => $this->order->getServiceFee()->toArray(),
                'total' => $this->order->getTotal()->toArray(),
            ],
            'shippingAddress' => [
                'fullName' => $address->getFullName(),
                'addressLine1' => $address->getAddressLine1(),
                'addressLine2' => $address->getAddressLine2(),
                'postalCode' => $address->getPostalCode(),
                'city' => $address->getCity(),
                'countryCode' => $address->getCountryCode(),
            ],
            'payment' => [
                'id' => $payment->getId()->toRfc4122(),
                'status' => $payment->getStatus()->value,
                'provider' => $payment->getProviderName(),
                'providerReference' => $payment->getProviderReference(),
            ],
            'cancellation' => $this->cancellation(),
            'refund' => $this->refund(),
            'statusHistory' => array_map(
                static fn (OrderStatusHistory $history): array => [
                    'id' => $history->getId()->toRfc4122(),
                    'from' => $history->getFromStatus()?->value,
                    'to' => $history->getToStatus()->value,
                    'actor' => ['id' => $history->getActor()->getId()->toRfc4122()],
                    'occurredAt' => $history->getOccurredAt()->format(DATE_ATOM),
                ],
                $this->order->getStatusHistory()->toArray(),
            ),
            'availableActions' => [
                'cancel' => $this->canCancel(),
                'markShipped' => $this->canSeller(OrderStatus::Paid),
                'confirmReceived' => $this->canBuyer(OrderStatus::Shipped),
            ],
            'paidAt' => $this->order->getPaidAt()?->format(DATE_ATOM),
            'completedAt' => $this->order->getCompletedAt()?->format(DATE_ATOM),
            'createdAt' => $this->order->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $this->order->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function item(OrderItem $item): array
    {
        $listing = $item->getListing();
        $ownedItem = $listing->getCollectionItem();
        $card = $ownedItem->getCatalogCard();
        $importedItem = $item->getImportedCollectionItem();
        $participantOwnsImportedItem = $this->participant instanceof User
            && $importedItem !== null
            && $importedItem->getOwner()->getId()->equals($this->participant->getId());

        return [
            'id' => $item->getId()->toRfc4122(),
            'listingId' => $listing->getId()->toRfc4122(),
            'card' => [
                'id' => $card->getId()->toRfc4122(),
                'name' => $card->getName(),
                'number' => $card->getNumber(),
                'set' => [
                    'id' => $card->getSet()->getId()->toRfc4122(),
                    'name' => $card->getSet()->getName(),
                ],
            ],
            'language' => $ownedItem->getLanguage()->value,
            'variant' => $ownedItem->getVariant()->getCode()->value,
            'condition' => $ownedItem->getCondition()->value,
            'price' => $item->getPrice()->toArray(),
            'collectionImport' => [
                'available' => $this->participant instanceof User && $item->canBeImportedBy($this->participant),
                'imported' => $importedItem !== null,
                'collectionItemId' => $participantOwnsImportedItem
                    ? $importedItem->getId()->toRfc4122()
                    : null,
                'importedAt' => $item->getImportedAt()?->format(DATE_ATOM),
            ],
        ];
    }

    private function canSeller(OrderStatus $status): bool
    {
        return $this->participant instanceof User
            && $this->order->getStatus() === $status
            && $this->participant->getId()->equals($this->order->getSeller()->getId());
    }

    /** @return array{reason: string, cancelledBy: array{id: string}, cancelledAt: string}|null */
    private function cancellation(): ?array
    {
        $cancelledBy = $this->order->getCancelledBy();
        $reason = $this->order->getCancellationReason();
        $cancelledAt = $this->order->getCancelledAt();
        if (!$cancelledBy instanceof User || $reason === null || !$cancelledAt instanceof DateTimeImmutable) {
            return null;
        }

        return [
            'reason' => $reason,
            'cancelledBy' => ['id' => $cancelledBy->getId()->toRfc4122()],
            'cancelledAt' => $cancelledAt->format(DATE_ATOM),
        ];
    }

    /** @return array{status: string, amount: array{amount: int, currency: string}}|null */
    private function refund(): ?array
    {
        $refund = $this->order->getRefund();
        if (!$refund instanceof Refund) {
            return null;
        }

        return [
            'status' => $refund->getStatus()->value,
            'amount' => $refund->getAmount()->toArray(),
        ];
    }

    private function canCancel(): bool
    {
        return $this->participant instanceof User
            && in_array($this->order->getStatus(), [OrderStatus::PendingPayment, OrderStatus::Paid], true)
            && ($this->participant->getId()->equals($this->order->getBuyer()->getId())
                || $this->participant->getId()->equals($this->order->getSeller()->getId()));
    }

    private function canBuyer(OrderStatus $status): bool
    {
        return $this->participant instanceof User
            && $this->order->getStatus() === $status
            && $this->participant->getId()->equals($this->order->getBuyer()->getId());
    }
}
