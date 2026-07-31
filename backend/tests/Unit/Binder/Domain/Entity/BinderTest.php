<?php

declare(strict_types=1);

namespace App\Tests\Unit\Binder\Domain\Entity;

use App\Binder\Domain\Entity\Binder;
use App\Binder\Domain\Entity\BinderPageSide;
use App\Binder\Domain\Entity\BinderSlot;
use App\Binder\Domain\Exception\InvalidBinderConfiguration;
use App\Identity\Domain\Entity\User;
use PHPUnit\Framework\TestCase;

final class BinderTest extends TestCase
{
    public function testCreatesEveryPageSurfaceAndUniqueSlot(): void
    {
        // Arrange
        $owner = new User('owner@example.com', 'hash');

        // Act
        $binder = new Binder($owner, 'Water cards', 3, 3, 2, true);
        $pages = $binder->getPages()->toArray();

        // Assert
        self::assertSame(4, $binder->getSurfaceCount());
        self::assertSame(36, $binder->getSlotCount());
        self::assertCount(4, $pages);
        self::assertSame(BinderPageSide::Front, $pages[0]->getSide());
        self::assertSame(BinderPageSide::Back, $pages[1]->getSide());
        self::assertCount(9, $pages[0]->getSlots());
        self::assertSame(range(1, 9), array_map(
            static fn (BinderSlot $slot): int => $slot->getPosition(),
            $pages[0]->getSlots()->toArray(),
        ));
    }

    public function testRejectsBinderWithMoreThanMaximumSlots(): void
    {
        // Arrange
        $this->expectException(InvalidBinderConfiguration::class);

        // Act
        new Binder(new User('owner@example.com', 'hash'), 'Too large', 6, 6, 100, true);

        // Assert
        // The expected domain exception is the observable result.
    }
}
