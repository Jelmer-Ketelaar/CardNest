<?php

declare(strict_types=1);

namespace App\Payment\Domain\Entity;

use App\Identity\Domain\Entity\User;
use App\Order\Domain\Entity\Order;
use App\Payment\Domain\Exception\InvalidPayment;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'refunds')]
#[ORM\Index(name: 'idx_refunds_payment', columns: ['payment_id'])]
#[ORM\Index(name: 'idx_refunds_status', columns: ['status'])]
#[ORM\UniqueConstraint(name: 'uniq_refund_order', columns: ['order_id'])]
final class Refund
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'provider_reference', length: 180, nullable: true)]
    private ?string $providerReference = null;

    #[ORM\Column(name: 'failure_reason', length: 500, nullable: true)]
    private ?string $failureReason = null;

    #[ORM\Column(name: 'processed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $processedAt = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Payment::class)]
        #[ORM\JoinColumn(name: 'payment_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly Payment $payment,
        #[ORM\OneToOne(targetEntity: Order::class, inversedBy: 'refund')]
        #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly Order $order,
        Money $amount,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'requested_by_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly User $requestedBy,
        string $reason,
        #[ORM\Column(length: 20, enumType: RefundStatus::class)]
        private RefundStatus $status = RefundStatus::Pending,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        if (!$payment->getId()->equals($order->getPayment()->getId())) {
            throw new InvalidPayment('Een refund moet bij de payment van de order horen.');
        }
        if (!$requestedBy->getId()->equals($order->getBuyer()->getId())
            && !$requestedBy->getId()->equals($order->getSeller()->getId())) {
            throw new InvalidPayment('Alleen een orderdeelnemer kan een refund aanvragen.');
        }
        if ($amount->amount <= 0 || $amount->currency !== $order->getTotal()->currency
            || $amount->amount !== $order->getTotal()->amount) {
            throw new InvalidPayment('Een refund moet exact gelijk zijn aan het historische ordertotaal.');
        }

        $this->id = Uuid::v7();
        $this->amount = $amount->amount;
        $this->currency = $amount->currency;
        $this->reason = self::normalizeReason($reason);
        $order->recordRefund($this);
    }

    #[ORM\Column]
    private readonly int $amount;

    #[ORM\Column(length: 3)]
    private readonly string $currency;

    #[ORM\Column(type: Types::TEXT)]
    private readonly string $reason;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getAmount(): Money
    {
        return new Money($this->amount, $this->currency);
    }

    public function getRequestedBy(): User
    {
        return $this->requestedBy;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getStatus(): RefundStatus
    {
        return $this->status;
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

    public function getProcessedAt(): ?DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function markSucceeded(
        string $providerReference,
        DateTimeImmutable $processedAt = new DateTimeImmutable(),
    ): void {
        if ($this->status !== RefundStatus::Pending) {
            throw new InvalidPayment('Alleen een openstaande refund kan slagen.');
        }
        if (trim($providerReference) === '') {
            throw new InvalidPayment('Een geslaagde refund vereist een providerreferentie.');
        }

        $this->status = RefundStatus::Succeeded;
        $this->providerReference = $providerReference;
        $this->processedAt = $processedAt;
    }

    public function markFailed(string $failureReason, DateTimeImmutable $processedAt = new DateTimeImmutable()): void
    {
        if ($this->status !== RefundStatus::Pending) {
            throw new InvalidPayment('Alleen een openstaande refund kan mislukken.');
        }
        $failureReason = trim($failureReason);
        if ($failureReason === '' || mb_strlen($failureReason) > 500) {
            throw new InvalidPayment('Een mislukte refund vereist een geldige foutreden.');
        }

        $this->status = RefundStatus::Failed;
        $this->failureReason = $failureReason;
        $this->processedAt = $processedAt;
    }

    private static function normalizeReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw new InvalidPayment('Een refund vereist een annuleringsreden van 3 tot 500 tekens.');
        }

        return $reason;
    }
}
