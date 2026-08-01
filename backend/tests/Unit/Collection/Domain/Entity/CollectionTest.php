<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Domain\Entity;

use App\Collection\Domain\Entity\Collection;
use App\Identity\Domain\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CollectionTest extends TestCase
{
    public function testRepresentsAnOwnerScopedCollectionWithAuditTimestamps(): void
    {
        // Arrange
        $owner = new User('owner@example.com', 'hash');
        $createdAt = new DateTimeImmutable('2026-08-01T12:00:00+00:00');

        // Act
        $collection = new Collection($owner, $createdAt);

        // Assert
        self::assertNotSame('', $collection->getId()->toRfc4122());
        self::assertSame($owner, $collection->getOwner());
        self::assertSame($createdAt, $collection->getCreatedAt());
        self::assertSame($createdAt, $collection->getUpdatedAt());
    }
}
