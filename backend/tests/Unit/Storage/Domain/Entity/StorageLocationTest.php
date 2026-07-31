<?php

declare(strict_types=1);

namespace App\Tests\Unit\Storage\Domain\Entity;

use App\Identity\Domain\Entity\User;
use App\Storage\Domain\Entity\StorageLocation;
use App\Storage\Domain\Entity\StorageLocationType;
use App\Storage\Domain\Exception\InvalidStorageHierarchy;
use App\Storage\Domain\Exception\StorageOwnerMismatch;
use PHPUnit\Framework\TestCase;

final class StorageLocationTest extends TestCase
{
    public function testBuildsAPathFromRootToNestedLocation(): void
    {
        // Arrange
        $owner = new User('owner@example.com', 'hash');
        $room = new StorageLocation($owner, 'Collectiekamer', StorageLocationType::Other);
        $cabinet = new StorageLocation($owner, 'Kast links', StorageLocationType::Other, $room);

        // Act
        $box = new StorageLocation($owner, 'Top loaders', StorageLocationType::TopLoaderBox, $cabinet);

        // Assert
        self::assertSame(
            ['Collectiekamer', 'Kast links', 'Top loaders'],
            array_map(static fn (StorageLocation $location): string => $location->getName(), $box->path()),
        );
    }

    public function testRejectsCircularHierarchy(): void
    {
        // Arrange
        $owner = new User('owner@example.com', 'hash');
        $parent = new StorageLocation($owner, 'Parent', StorageLocationType::Other);
        $child = new StorageLocation($owner, 'Child', StorageLocationType::Box, $parent);
        $this->expectException(InvalidStorageHierarchy::class);

        // Act
        $parent->moveTo($child);

        // Assert
        // The expected domain exception is the observable result.
    }

    public function testRejectsParentOwnedByAnotherUser(): void
    {
        // Arrange
        $owner = new User('owner@example.com', 'hash');
        $otherOwner = new User('other@example.com', 'hash');
        $otherLocation = new StorageLocation($otherOwner, 'Other box', StorageLocationType::Box);
        $this->expectException(StorageOwnerMismatch::class);

        // Act
        new StorageLocation($owner, 'Own box', StorageLocationType::Box, $otherLocation);

        // Assert
        // The expected domain exception is the observable result.
    }
}
