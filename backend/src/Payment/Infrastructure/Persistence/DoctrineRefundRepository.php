<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence;

use App\Payment\Domain\Entity\Refund;
use App\Payment\Domain\Repository\RefundRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineRefundRepository implements RefundRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Refund $refund): void
    {
        $this->entityManager->persist($refund);
    }
}
