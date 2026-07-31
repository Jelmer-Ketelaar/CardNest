<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain\Entity;

use App\Identity\Domain\Entity\User;
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
}
