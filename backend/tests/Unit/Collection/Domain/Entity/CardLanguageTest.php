<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Domain\Entity;

use App\Catalog\Domain\Entity\CatalogLanguage;
use App\Collection\Domain\Entity\CardLanguage;
use PHPUnit\Framework\TestCase;

final class CardLanguageTest extends TestCase
{
    public function testEveryCatalogProviderLanguageCanBeUsedForAPhysicalCard(): void
    {
        // Act
        $collectionLanguages = CardLanguage::values();

        // Assert
        foreach (CatalogLanguage::values() as $catalogLanguage) {
            self::assertContains($catalogLanguage, $collectionLanguages);
        }
    }
}
