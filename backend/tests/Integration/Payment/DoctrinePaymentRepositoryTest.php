<?php

declare(strict_types=1);

namespace App\Tests\Integration\Payment;

use App\Identity\Domain\Entity\User;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Repository\PaymentRepository;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DoctrinePaymentRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private PaymentRepository $payments;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->payments = $container->get(PaymentRepository::class);
    }

    public function testReloadAndLockByIdRehydratesWithoutRewritingReadonlyValues(): void
    {
        // Arrange
        $createdAt = new DateTimeImmutable('2026-08-03T19:00:00+00:00');
        $buyer = new User('payment-lock-'.Uuid::v7()->toRfc4122().'@example.test', 'hash');
        $payment = new Payment(
            buyer: $buyer,
            amount: new Money(1_000, 'EUR'),
            providerName: 'fake',
            createdAt: $createdAt,
        );
        $this->entityManager->persist($buyer);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();
        $databaseUpdatedAt = new DateTimeImmutable('2026-08-03T20:00:00+00:00');
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE payments SET updated_at = :updatedAt WHERE id = CAST(:id AS uuid)',
            [
                'id' => $payment->getId()->toRfc4122(),
                'updatedAt' => $databaseUpdatedAt->format('Y-m-d H:i:s'),
            ],
        );
        $staleUpdatedAt = $payment->getUpdatedAt();

        // Act
        $lockedPayment = $this->entityManager->wrapInTransaction(
            fn (): ?Payment => $this->payments->reloadAndLockById($payment->getId()->toRfc4122()),
        );

        // Assert
        $this->assertInstanceOf(Payment::class, $lockedPayment);
        $this->assertNotSame($payment, $lockedPayment);
        $this->assertSame($createdAt->format(DATE_ATOM), $staleUpdatedAt->format(DATE_ATOM));
        $this->assertSame($databaseUpdatedAt->format(DATE_ATOM), $lockedPayment->getUpdatedAt()->format(DATE_ATOM));
        $this->assertSame($createdAt->format(DATE_ATOM), $lockedPayment->getCreatedAt()->format(DATE_ATOM));
        $this->assertSame(1_000, $lockedPayment->getAmount()->amount);
        $this->assertSame('EUR', $lockedPayment->getAmount()->currency);
    }
}
