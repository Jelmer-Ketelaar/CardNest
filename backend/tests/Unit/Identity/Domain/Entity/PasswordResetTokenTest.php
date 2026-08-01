<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain\Entity;

use App\Identity\Domain\Entity\PasswordResetToken;
use App\Identity\Domain\Entity\User;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PasswordResetTokenTest extends TestCase
{
    public function testItStoresOnlyAHashAndExpiresAfterOneHour(): void
    {
        // Arrange
        $plainToken = str_repeat('a', 64);
        $issuedAt = new DateTimeImmutable('2026-08-01 10:00:00');
        $user = new User('collector@example.com', 'hash');

        // Act
        $token = PasswordResetToken::issue($user, $plainToken, $issuedAt);

        // Assert
        self::assertSame(hash('sha256', $plainToken), $token->getTokenHash());
        self::assertSame('2026-08-01 11:00:00', $token->getExpiresAt()->format('Y-m-d H:i:s'));
        self::assertFalse($token->isExpired(new DateTimeImmutable('2026-08-01 10:59:59')));
        self::assertTrue($token->isExpired(new DateTimeImmutable('2026-08-01 11:00:00')));
    }

    public function testItRejectsAnInvalidPlainToken(): void
    {
        // Arrange
        $user = new User('collector@example.com', 'hash');
        $this->expectException(InvalidArgumentException::class);

        // Act
        PasswordResetToken::issue($user, 'predictable');

        // Assert
        // The expected domain exception is the observable result.
    }
}
