<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Dto;

use App\Catalog\Application\Dto\ImportCatalogRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ImportCatalogRequestTest extends TestCase
{
    public function testScopeKeyIsStableAcrossInputOrdering(): void
    {
        // Arrange
        $first = new ImportCatalogRequest(['ja', 'en'], ['set-b', 'set-a'], includeCardDetails: true);
        $second = new ImportCatalogRequest(['en', 'ja'], ['set-a', 'set-b'], includeCardDetails: true);

        // Act
        $firstScopeKey = $first->scopeKey('tcgdex');
        $secondScopeKey = $second->scopeKey('tcgdex');

        // Assert
        self::assertSame($firstScopeKey, $secondScopeKey);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $firstScopeKey);
    }

    public function testRequiresAtLeastOneLanguage(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one');

        // Act
        new ImportCatalogRequest([]);

        // Assert
        // An empty scope cannot accidentally become a misleading import run.
    }

    public function testRejectsControlCharactersInSourceMetadata(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('source version');

        // Act
        new ImportCatalogRequest(['en'], sourceVersion: "release\n42");

        // Assert
        // Control characters must not reach audit logs or terminal output.
    }
}
