<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain\Entity;

use App\Identity\Domain\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testItNormalizesEmailAndAlwaysHasUserRole(): void
    {
        // Arrange
        $user = new User('  Collector@Example.COM ', 'hashed-password');

        // Act
        $email = $user->getEmail();
        $roles = $user->getRoles();

        // Assert
        self::assertSame('collector@example.com', $email);
        self::assertSame(['ROLE_USER'], $roles);
    }

    public function testEmailVerificationKeepsTheFirstVerificationMoment(): void
    {
        // Arrange
        $user = new User('collector@example.com', 'hashed-password');
        $firstVerification = new DateTimeImmutable('2026-07-31 10:00:00');

        // Act
        $user->verifyEmail($firstVerification);
        $user->verifyEmail(new DateTimeImmutable('2026-07-31 11:00:00'));

        // Assert
        self::assertTrue($user->isEmailVerified());
        self::assertSame($firstVerification, $user->getEmailVerifiedAt());
        self::assertSame($firstVerification, $user->getUpdatedAt());
    }
}
