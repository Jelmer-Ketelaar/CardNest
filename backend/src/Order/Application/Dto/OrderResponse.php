<?php

declare(strict_types=1);

namespace App\Order\Application\Dto;

use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderItem;

final readonly class OrderResponse
{
    public function __construct(private Order $order)
    {
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
            'paidAt' => $this->order->getPaidAt()?->format(DATE_ATOM),
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
        ];
    }
}
