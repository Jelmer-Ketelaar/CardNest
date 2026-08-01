<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence;

use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Repository\PaymentRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePaymentRepository implements PaymentRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Payment $payment): void
    {
        $this->entityManager->persist($payment);
    }

    public function lock(Payment $payment): void
    {
        $this->entityManager->lock($payment, LockMode::PESSIMISTIC_WRITE);
    }
}
