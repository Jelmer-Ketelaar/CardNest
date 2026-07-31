<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain\Entity;

use App\Identity\Domain\Entity\AuthToken;
use App\Identity\Domain\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AuthTokenTest extends TestCase
{
    public function testIssuedTokenStoresOnlyAHash(): void
    {
        // Arrange
        $plainToken = 'secret-token';

        // Act
        $token = AuthToken::issue(new User('collector@example.com', 'hash'), $plainToken);

        // Assert
        self::assertSame(hash('sha256', $plainToken), $token->getTokenHash());
        self::assertFalse($token->isExpired(new DateTimeImmutable()));
    }
}
