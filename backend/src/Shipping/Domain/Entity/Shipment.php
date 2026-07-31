<?php

declare(strict_types=1);

namespace App\Shipping\Domain\Entity;

use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderStatus;
use App\Shipping\Domain\Exception\InvalidShipment;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'shipments')]
#[ORM\UniqueConstraint(name: 'uniq_shipment_order', columns: ['order_id'])]
final class Shipment
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'delivered_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(length: 20, enumType: ShipmentStatus::class)]
    private ShipmentStatus $status = ShipmentStatus::Shipped;

    public function __construct(
        #[ORM\OneToOne(targetEntity: Order::class)]
        #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly Order $order,
        #[ORM\Column(name: 'shipping_method', length: 30, enumType: ShippingMethodCode::class)]
        private readonly ShippingMethodCode $shippingMethod,
        #[ORM\Column(name: 'shipped_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $shippedAt = new DateTimeImmutable(),
    ) {
        if ($order->getStatus() !== OrderStatus::Shipped) {
            throw new InvalidShipment('Een shipment kan pas worden aangemaakt nadat de order is verzonden.');
        }
        if ($shippingMethod !== $order->getShippingMethod()) {
            throw new InvalidShipment('De shipmentmethode moet overeenkomen met de order.');
        }

        $this->id = Uuid::v7();
        $this->updatedAt = $shippedAt;
    }

    public static function forOrder(Order $order, DateTimeImmutable $shippedAt = new DateTimeImmutable()): self
    {
        return new self($order, $order->getShippingMethod(), shippedAt: $shippedAt);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getShippingMethod(): ShippingMethodCode
    {
        return $this->shippingMethod;
    }

    public function getStatus(): ShipmentStatus
    {
        return $this->status;
    }

    public function getShippedAt(): DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function confirmDelivered(DateTimeImmutable $deliveredAt = new DateTimeImmutable()): void
    {
        if ($this->status !== ShipmentStatus::Shipped) {
            throw new InvalidShipment('Alleen een verzonden shipment kan als afgeleverd worden bevestigd.');
        }
        if ($this->order->getStatus() !== OrderStatus::Completed) {
            throw new InvalidShipment('De order moet voltooid zijn voordat de shipment wordt afgesloten.');
        }
        if ($deliveredAt < $this->shippedAt) {
            throw new InvalidShipment('Het aflevermoment kan niet vóór het verzendmoment liggen.');
        }

        $this->status = ShipmentStatus::Delivered;
        $this->deliveredAt = $deliveredAt;
        $this->updatedAt = $deliveredAt;
    }

    public function getDeliveredAt(): ?DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
