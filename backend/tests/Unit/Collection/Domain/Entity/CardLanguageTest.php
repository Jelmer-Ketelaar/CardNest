<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Domain\Entity;

use App\Catalog\Domain\Entity\CatalogLanguage;
use App\Collection\Domain\Entity\CardLanguage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ValueError;

final class CardLanguageTest extends TestCase
{
    #[DataProvider('cardLanguages')]
    public function testEverySupportedCodeRoundTripsThroughTheDomainEnum(CardLanguage $language): void
    {
        // Act
        $roundTripped = CardLanguage::from($language->value);

        // Assert
        self::assertSame($language, $roundTripped);
    }

    public function testUnknownCodeIsRejectedByTheDomainEnum(): void
    {
        // Assert
        $this->expectException(ValueError::class);

        // Act
        CardLanguage::from('xx');
    }

    public function testEveryCatalogProviderLanguageCanBeUsedForAPhysicalCard(): void
    {
        // Act
        $collectionLanguages = CardLanguage::values();

        // Assert
        foreach (CatalogLanguage::values() as $catalogLanguage) {
            self::assertContains($catalogLanguage, $collectionLanguages);
        }
    }

    /** @return iterable<string, array{CardLanguage}> */
    public static function cardLanguages(): iterable
    {
        foreach (CardLanguage::cases() as $language) {
            yield $language->name => [$language];
        }
    }
}
