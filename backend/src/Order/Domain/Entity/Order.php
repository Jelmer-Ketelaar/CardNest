<?php

declare(strict_types=1);

namespace App\Order\Domain\Entity;

use App\Identity\Domain\Entity\User;
use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Entity\ListingStatus;
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Order\Domain\Exception\InvalidOrder;
use App\Order\Domain\Exception\InvalidOrderTransition;
use App\Order\Domain\ValueObject\ShippingAddress;
use App\Payment\Domain\Entity\Payment;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
#[ORM\Index(name: 'idx_orders_buyer_created', columns: ['buyer_id', 'created_at'])]
#[ORM\Index(name: 'idx_orders_seller_created', columns: ['seller_id', 'created_at'])]
#[ORM\Index(name: 'idx_orders_payment', columns: ['payment_id'])]
final class Order
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $items;

    /** @var Collection<int, OrderStatusHistory> */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderStatusHistory::class, cascade: ['persist'])]
    #[ORM\OrderBy(['occurredAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $statusHistory;

    #[ORM\OneToOne(mappedBy: 'order', targetEntity: OrderAddress::class, cascade: ['persist'])]
    private OrderAddress $shippingAddress;

    #[ORM\Column(name: 'paid_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $paidAt = null;

    #[ORM\Column(name: 'completed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(length: 30, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::PendingPayment;

    /** @param list<Listing> $listings */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'buyer_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly User $buyer,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'seller_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly User $seller,
        #[ORM\ManyToOne(targetEntity: Payment::class)]
        #[ORM\JoinColumn(name: 'payment_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly Payment $payment,
        array $listings,
        #[ORM\Column(name: 'shipping_method', length: 30, enumType: ShippingMethodCode::class)]
        private readonly ShippingMethodCode $shippingMethod,
        Money $shippingPrice,
        ShippingAddress $shippingAddress,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        if ($buyer->getId()->equals($seller->getId())) {
            throw new InvalidOrder('Een verkoper kan geen order bij zichzelf plaatsen.');
        }
        if ($listings === []) {
            throw new InvalidOrder('Een order vereist minimaal één listing.');
        }
        if ($shippingPrice->currency !== 'EUR') {
            throw new InvalidOrder('Orders ondersteunen momenteel alleen EUR.');
        }

        $this->id = Uuid::v7();
        $this->items = new ArrayCollection();
        $this->statusHistory = new ArrayCollection();
        $subtotal = 0;
        foreach ($listings as $listing) {
            if (!$listing->getSeller()->getId()->equals($seller->getId())) {
                throw new InvalidOrder('Alle listings in een order moeten van dezelfde verkoper zijn.');
            }
            if ($listing->getStatus() !== ListingStatus::Reserved) {
                throw new InvalidOrder('Alleen gereserveerde listings kunnen aan een order worden toegevoegd.');
            }

            $item = OrderItem::fromListing($this, $listing, $createdAt);
            $this->items->add($item);
            $subtotal += $item->getPrice()->amount;
        }

        $this->itemSubtotalAmount = $subtotal;
        $this->shippingAmount = $shippingPrice->amount;
        $this->serviceFeeAmount = 0;
        $this->totalAmount = $subtotal + $shippingPrice->amount;
        $this->currency = 'EUR';
        $this->shippingAddress = new OrderAddress($this, $shippingAddress, $createdAt);
        $this->statusHistory->add(new OrderStatusHistory(
            $this,
            null,
            OrderStatus::PendingPayment,
            $buyer,
            $createdAt,
        ));
        $this->updatedAt = $createdAt;
    }

    #[ORM\Column(name: 'item_subtotal_amount')]
    private readonly int $itemSubtotalAmount;

    #[ORM\Column(name: 'shipping_amount')]
    private readonly int $shippingAmount;

    #[ORM\Column(name: 'service_fee_amount')]
    private readonly int $serviceFeeAmount;

    #[ORM\Column(name: 'total_amount')]
    private readonly int $totalAmount;

    #[ORM\Column(length: 3)]
    private readonly string $currency;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getBuyer(): User
    {
        return $this->buyer;
    }

    public function getSeller(): User
    {
        return $this->seller;
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    /** @return Collection<int, OrderItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function getShippingAddress(): OrderAddress
    {
        return $this->shippingAddress;
    }

    /** @return Collection<int, OrderStatusHistory> */
    public function getStatusHistory(): Collection
    {
        return $this->statusHistory;
    }

    public function getShippingMethod(): ShippingMethodCode
    {
        return $this->shippingMethod;
    }

    public function getItemSubtotal(): Money
    {
        return new Money($this->itemSubtotalAmount, $this->currency);
    }

    public function getShippingPrice(): Money
    {
        return new Money($this->shippingAmount, $this->currency);
    }

    public function getServiceFee(): Money
    {
        return new Money($this->serviceFeeAmount, $this->currency);
    }

    public function getTotal(): Money
    {
        return new Money($this->totalAmount, $this->currency);
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getWorkflowStatus(): string
    {
        return $this->status->value;
    }

    public function setWorkflowStatus(string $status): void
    {
        $next = OrderStatus::from($status);
        $now = new DateTimeImmutable();
        if ($next === OrderStatus::Paid && $this->paidAt === null) {
            $this->paidAt = $now;
        }
        if ($next === OrderStatus::Completed && $this->completedAt === null) {
            $this->completedAt = $now;
        }

        $this->status = $next;
        $this->updatedAt = $now;
    }

    public function getPaidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function recordStatusTransition(
        OrderStatus $previousStatus,
        User $actor,
        DateTimeImmutable $occurredAt = new DateTimeImmutable(),
    ): void {
        if ($this->status === $previousStatus) {
            throw new InvalidOrderTransition('De orderstatus is niet gewijzigd.');
        }
        if (!$actor->getId()->equals($this->buyer->getId()) && !$actor->getId()->equals($this->seller->getId())) {
            throw new InvalidOrderTransition('Alleen een orderdeelnemer kan een status wijzigen.');
        }

        $last = $this->statusHistory->last();
        if (!$last instanceof OrderStatusHistory || $last->getToStatus() !== $previousStatus) {
            throw new InvalidOrderTransition('De orderstatusgeschiedenis sluit niet aan op de overgang.');
        }

        $this->statusHistory->add(new OrderStatusHistory(
            $this,
            $previousStatus,
            $this->status,
            $actor,
            $occurredAt,
        ));
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
