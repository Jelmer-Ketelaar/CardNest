<?php

declare(strict_types=1);

namespace App\Payment\Domain\Entity;

use App\Order\Domain\Entity\Order;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'payment_transactions')]
#[ORM\Index(name: 'idx_payment_transactions_payment_time', columns: ['payment_id', 'created_at'])]
#[ORM\UniqueConstraint(
    name: 'uniq_successful_refund_transaction_order',
    columns: ['order_id'],
    options: ['where' => "(((type)::text = 'refund'::text) AND ((status)::text = 'succeeded'::text))"],
)]
final class PaymentTransaction
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Payment::class, inversedBy: 'transactions')]
        #[ORM\JoinColumn(name: 'payment_id', nullable: false, onDelete: 'CASCADE')]
        private readonly Payment $payment,
        #[ORM\Column(length: 20, enumType: PaymentTransactionType::class)]
        private readonly PaymentTransactionType $type,
        #[ORM\Column(length: 20, enumType: PaymentTransactionStatus::class)]
        private readonly PaymentTransactionStatus $status,
        #[ORM\Column(name: 'amount')]
        private readonly int $amount,
        #[ORM\Column(length: 3)]
        private readonly string $currency,
        #[ORM\Column(name: 'provider_reference', length: 180, nullable: true)]
        private readonly ?string $providerReference = null,
        #[ORM\Column(name: 'failure_reason', length: 500, nullable: true)]
        private readonly ?string $failureReason = null,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
        #[ORM\ManyToOne(targetEntity: Order::class)]
        #[ORM\JoinColumn(name: 'order_id', nullable: true, onDelete: 'RESTRICT')]
        private readonly ?Order $relatedOrder = null,
    ) {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getType(): PaymentTransactionType
    {
        return $this->type;
    }

    public function getStatus(): PaymentTransactionStatus
    {
        return $this->status;
    }

    public function getAmount(): Money
    {
        return new Money($this->amount, $this->currency);
    }

    public function getProviderReference(): ?string
    {
        return $this->providerReference;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRelatedOrder(): ?Order
    {
        return $this->relatedOrder;
    }
}
