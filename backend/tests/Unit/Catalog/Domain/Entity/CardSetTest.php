<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Entity;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CardSetTest extends TestCase
{
    public function testProviderSynchronizationRefreshesOwnedMetadata(): void
    {
        // Arrange
        $set = new CardSet(
            series: new CardSeries('Mega Evolution'),
            name: 'Initial Set',
            code: 'tcgdex:en:future',
            language: 'en',
            printedTotal: 1,
            total: 1,
            status: CardSetStatus::Active,
            releaseDate: new DateTimeImmutable('2020-01-01'),
        );
        $syncedAt = new DateTimeImmutable('2026-08-02T14:00:00+00:00');

        // Act
        $set->synchronizeFromProvider(
            name: 'Future Set',
            printedTotal: 100,
            total: 120,
            status: CardSetStatus::Announced,
            releaseDate: new DateTimeImmutable('2100-01-02'),
            logoUrl: 'https://assets.example.test/future/logo.webp',
            symbolUrl: 'https://assets.example.test/future/symbol.webp',
            syncedAt: $syncedAt,
        );

        // Assert
        self::assertSame('Future Set', $set->getName());
        self::assertSame(100, $set->getPrintedTotal());
        self::assertSame(120, $set->getTotal());
        self::assertSame(CardSetStatus::Announced, $set->getStatus());
        self::assertSame('2100-01-02', $set->getReleaseDate()?->format('Y-m-d'));
        self::assertSame('https://assets.example.test/future/logo.webp', $set->getLogoUrl());
        self::assertSame($syncedAt, $set->getUpdatedAt());
    }
}
