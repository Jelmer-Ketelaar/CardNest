<?php

declare(strict_types=1);

namespace App\Payment\Domain\Entity;

use App\Identity\Domain\Entity\User;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\ShoppingCart;
use App\Payment\Domain\Exception\InvalidPayment;
use App\Payment\Domain\Provider\PaymentResult;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'payments')]
#[ORM\Index(name: 'idx_payments_buyer_created', columns: ['buyer_id', 'created_at'])]
#[ORM\Index(name: 'idx_payments_finalization', columns: ['finalization_status', 'created_at'])]
#[ORM\UniqueConstraint(name: 'uniq_payment_idempotency_key', columns: ['idempotency_key'])]
#[ORM\UniqueConstraint(
    name: 'uniq_payment_provider_reference',
    columns: ['provider_name', 'provider_reference'],
    options: ['where' => '(provider_reference IS NOT NULL)'],
)]
final class Payment
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /** @var Collection<int, PaymentTransaction> */
    #[ORM\OneToMany(mappedBy: 'payment', targetEntity: PaymentTransaction::class, cascade: ['persist'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $transactions;

    #[ORM\ManyToOne(targetEntity: ShoppingCart::class)]
    #[ORM\JoinColumn(name: 'checkout_cart_id', nullable: true, onDelete: 'SET NULL')]
    private readonly ?ShoppingCart $checkoutCart;

    #[ORM\Column(name: 'idempotency_key', length: 255)]
    private readonly string $idempotencyKey;

    #[ORM\Column(name: 'request_fingerprint', length: 64, nullable: true)]
    private readonly ?string $requestFingerprint;

    #[ORM\Column(name: 'cart_fingerprint', length: 64, nullable: true)]
    private readonly ?string $cartFingerprint;

    #[ORM\Column(name: 'finalization_status', length: 30, enumType: PaymentFinalizationStatus::class)]
    private PaymentFinalizationStatus $finalizationStatus;

    #[ORM\Column(name: 'provider_reference', length: 180, nullable: true)]
    private ?string $providerReference = null;

    #[ORM\Column(name: 'completed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'provider_result_recorded_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $providerResultRecordedAt = null;

    #[ORM\Column(name: 'finalization_attempted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $finalizationAttemptedAt = null;

    #[ORM\Column(name: 'finalized_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $finalizedAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'buyer_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly User $buyer,
        Money $amount,
        #[ORM\Column(name: 'provider_name', length: 50)]
        private readonly string $providerName,
        ?string $idempotencyKey = null,
        ?ShoppingCart $checkoutCart = null,
        ?string $requestFingerprint = null,
        ?string $cartFingerprint = null,
        #[ORM\Column(length: 20, enumType: PaymentStatus::class)]
        private PaymentStatus $status = PaymentStatus::Pending,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        if ($amount->amount <= 0 || $amount->currency !== 'EUR') {
            throw new InvalidPayment('Een betaling moet een positief bedrag in EUR hebben.');
        }
        if (trim($providerName) === '') {
            throw new InvalidPayment('Een betaling vereist een providernaam.');
        }

        $this->id = Uuid::v7();
        $idempotencyKey = trim($idempotencyKey ?? 'payment_'.$this->id->toRfc4122());
        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 255) {
            throw new InvalidPayment('Een betaling vereist een idempotentiesleutel van maximaal 255 tekens.');
        }
        if ($checkoutCart instanceof ShoppingCart) {
            if (!self::isFingerprint($requestFingerprint) || !self::isFingerprint($cartFingerprint)) {
                throw new InvalidPayment('Een checkoutbetaling vereist geldige aanvraag- en winkelmandvingerafdrukken.');
            }
        } elseif ($requestFingerprint !== null || $cartFingerprint !== null) {
            throw new InvalidPayment('Checkoutvingerafdrukken vereisen een gekoppelde winkelmand.');
        }

        $this->amount = $amount->amount;
        $this->currency = $amount->currency;
        $this->checkoutCart = $checkoutCart;
        $this->idempotencyKey = $idempotencyKey;
        $this->requestFingerprint = $requestFingerprint;
        $this->cartFingerprint = $cartFingerprint;
        $this->finalizationStatus = $checkoutCart instanceof ShoppingCart
            ? PaymentFinalizationStatus::Pending
            : PaymentFinalizationStatus::NotRequired;
        $this->transactions = new ArrayCollection();
        $this->updatedAt = $createdAt;
    }

    #[ORM\Column]
    private readonly int $amount;

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

    public function getAmount(): Money
    {
        return new Money($this->amount, $this->currency);
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function getProviderReference(): ?string
    {
        return $this->providerReference;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function getCheckoutCart(): ?ShoppingCart
    {
        return $this->checkoutCart;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getRequestFingerprint(): ?string
    {
        return $this->requestFingerprint;
    }

    public function getCartFingerprint(): ?string
    {
        return $this->cartFingerprint;
    }

    public function getFinalizationStatus(): PaymentFinalizationStatus
    {
        return $this->finalizationStatus;
    }

    /** @return Collection<int, PaymentTransaction> */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function recordResult(PaymentResult $result): void
    {
        if ($this->status !== PaymentStatus::Pending) {
            throw new InvalidPayment('Alleen een openstaande betaling kan worden verwerkt.');
        }
        if ($result->amount->amount !== $this->amount || $result->amount->currency !== $this->currency) {
            throw new InvalidPayment('Het betaalproviderresultaat bevat een afwijkend bedrag of valuta.');
        }

        $now = new DateTimeImmutable();
        if ($result->succeeded) {
            if ($result->providerReference === null || trim($result->providerReference) === '') {
                throw new InvalidPayment('Een geslaagde betaling vereist een providerreferentie.');
            }
            $this->status = PaymentStatus::Succeeded;
            $this->providerReference = $result->providerReference;
            $this->transactions->add(new PaymentTransaction(
                $this,
                PaymentTransactionType::Charge,
                PaymentTransactionStatus::Succeeded,
                $this->amount,
                $this->currency,
                $result->providerReference,
                createdAt: $now,
            ));
        } else {
            $this->status = PaymentStatus::Failed;
            $this->transactions->add(new PaymentTransaction(
                $this,
                PaymentTransactionType::Charge,
                PaymentTransactionStatus::Failed,
                $this->amount,
                $this->currency,
                failureReason: $result->failureReason,
                createdAt: $now,
            ));
        }

        $this->completedAt = $now;
        $this->providerResultRecordedAt = $now;
        $this->updatedAt = $now;
    }

    public function markFinalizationAttempted(DateTimeImmutable $attemptedAt = new DateTimeImmutable()): void
    {
        if ($this->status !== PaymentStatus::Succeeded
            || $this->finalizationStatus !== PaymentFinalizationStatus::Pending) {
            throw new InvalidPayment('Alleen een geslaagde, nog niet afgeronde checkout kan worden gefinaliseerd.');
        }

        $this->finalizationAttemptedAt = $attemptedAt;
        $this->updatedAt = $attemptedAt;
    }

    public function markFinalized(DateTimeImmutable $finalizedAt = new DateTimeImmutable()): void
    {
        if ($this->status !== PaymentStatus::Succeeded
            || $this->finalizationStatus !== PaymentFinalizationStatus::Pending) {
            throw new InvalidPayment('Alleen een geslaagde, nog niet afgeronde checkout kan worden voltooid.');
        }

        $this->finalizationStatus = PaymentFinalizationStatus::Finalized;
        $this->finalizedAt = $finalizedAt;
        $this->updatedAt = $finalizedAt;
    }

    public function markReservationsReleased(DateTimeImmutable $releasedAt = new DateTimeImmutable()): void
    {
        if ($this->status !== PaymentStatus::Failed
            || $this->finalizationStatus !== PaymentFinalizationStatus::Pending) {
            throw new InvalidPayment('Alleen een afgewezen checkout kan worden vrijgegeven.');
        }

        $this->finalizationStatus = PaymentFinalizationStatus::Released;
        $this->finalizedAt = $releasedAt;
        $this->updatedAt = $releasedAt;
    }

    public function getFailureReason(): ?string
    {
        foreach ($this->transactions as $transaction) {
            if ($transaction->getType() === PaymentTransactionType::Charge
                && $transaction->getStatus() === PaymentTransactionStatus::Failed) {
                return $transaction->getFailureReason();
            }
        }

        return null;
    }

    public function recordSuccessfulRefund(Refund $refund, DateTimeImmutable $processedAt = new DateTimeImmutable()): void
    {
        if ($this->status !== PaymentStatus::Succeeded) {
            throw new InvalidPayment('Alleen een geslaagde betaling kan worden terugbetaald.');
        }
        if (!$refund->getPayment()->getId()->equals($this->id)) {
            throw new InvalidPayment('De refund hoort niet bij deze betaling.');
        }
        if ($refund->getStatus() !== RefundStatus::Succeeded) {
            throw new InvalidPayment('Alleen een geslaagde refund kan als betaaltransactie worden vastgelegd.');
        }
        if ($refund->getAmount()->currency !== $this->currency) {
            throw new InvalidPayment('De refundvaluta moet overeenkomen met de betaling.');
        }

        $refundedAmount = 0;
        foreach ($this->transactions as $transaction) {
            if ($transaction->getType() !== PaymentTransactionType::Refund
                || $transaction->getStatus() !== PaymentTransactionStatus::Succeeded) {
                continue;
            }
            $relatedOrder = $transaction->getRelatedOrder();
            if ($relatedOrder instanceof Order
                && $relatedOrder->getId()->equals($refund->getOrder()->getId())) {
                throw new InvalidPayment('Deze order heeft al een refundtransactie.');
            }
            $refundedAmount += $transaction->getAmount()->amount;
        }
        if ($refundedAmount + $refund->getAmount()->amount > $this->amount) {
            throw new InvalidPayment('Refunds mogen het betaalde bedrag niet overschrijden.');
        }

        $providerReference = $refund->getProviderReference();
        if ($providerReference === null) {
            throw new InvalidPayment('Een geslaagde refund vereist een providerreferentie.');
        }

        $this->transactions->add(new PaymentTransaction(
            payment: $this,
            type: PaymentTransactionType::Refund,
            status: PaymentTransactionStatus::Succeeded,
            amount: $refund->getAmount()->amount,
            currency: $refund->getAmount()->currency,
            providerReference: $providerReference,
            createdAt: $processedAt,
            relatedOrder: $refund->getOrder(),
        ));
        $this->updatedAt = $processedAt;
    }

    public function markFullyRefunded(DateTimeImmutable $refundedAt = new DateTimeImmutable()): void
    {
        if ($this->status !== PaymentStatus::Succeeded) {
            throw new InvalidPayment('Alleen een geslaagde betaling kan volledig zijn terugbetaald.');
        }

        $this->status = PaymentStatus::Refunded;
        $this->updatedAt = $refundedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getProviderResultRecordedAt(): ?DateTimeImmutable
    {
        return $this->providerResultRecordedAt;
    }

    public function getFinalizationAttemptedAt(): ?DateTimeImmutable
    {
        return $this->finalizationAttemptedAt;
    }

    public function getFinalizedAt(): ?DateTimeImmutable
    {
        return $this->finalizedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function isFingerprint(?string $fingerprint): bool
    {
        return $fingerprint !== null && preg_match('/\A[a-f0-9]{64}\z/', $fingerprint) === 1;
    }
}
