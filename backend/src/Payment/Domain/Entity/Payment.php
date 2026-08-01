<?php

declare(strict_types=1);

namespace App\Payment\Domain\Entity;

use App\Identity\Domain\Entity\User;
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

    #[ORM\Column(name: 'provider_reference', length: 180, nullable: true)]
    private ?string $providerReference = null;

    #[ORM\Column(name: 'completed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'buyer_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly User $buyer,
        Money $amount,
        #[ORM\Column(name: 'provider_name', length: 50)]
        private readonly string $providerName,
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
        $this->amount = $amount->amount;
        $this->currency = $amount->currency;
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
        $this->updatedAt = $now;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
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
