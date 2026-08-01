<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain\Entity;

use App\Identity\Domain\Entity\EmailVerificationToken;
use App\Identity\Domain\Entity\User;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EmailVerificationTokenTest extends TestCase
{
    public function testItStoresOnlyAHashAndExpiresAfterTwentyFourHours(): void
    {
        // Arrange
        $plainToken = str_repeat('a', 64);
        $issuedAt = new DateTimeImmutable('2026-07-31 10:00:00');
        $user = new User('collector@example.com', 'hash');

        // Act
        $token = EmailVerificationToken::issue($user, $plainToken, $issuedAt);

        // Assert
        self::assertSame(hash('sha256', $plainToken), $token->getTokenHash());
        self::assertSame('2026-08-01 10:00:00', $token->getExpiresAt()->format('Y-m-d H:i:s'));
        self::assertFalse($token->isExpired(new DateTimeImmutable('2026-08-01 09:59:59')));
        self::assertTrue($token->isExpired(new DateTimeImmutable('2026-08-01 10:00:00')));
    }

    public function testItRejectsAnInvalidPlainToken(): void
    {
        // Arrange
        $user = new User('collector@example.com', 'hash');
        $this->expectException(InvalidArgumentException::class);

        // Act
        EmailVerificationToken::issue($user, 'predictable');

        // Assert
        // The expected domain exception is the observable result.
    }
}
