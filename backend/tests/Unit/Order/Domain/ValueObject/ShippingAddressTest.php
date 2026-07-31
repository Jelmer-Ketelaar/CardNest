<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\ValueObject;

use App\Order\Domain\Exception\InvalidOrder;
use App\Order\Domain\ValueObject\ShippingAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShippingAddressTest extends TestCase
{
    #[DataProvider('validPostcodes')]
    public function testAcceptsSupportedCountryPostcodes(string $country, string $postcode): void
    {
        // Arrange
        // Input is supplied by the data provider.

        // Act
        $address = new ShippingAddress('Sam Collector', 'Dorpsstraat 1', null, $postcode, 'Utrecht', $country);

        // Assert
        self::assertSame($country, $address->countryCode);
        self::assertSame(strtoupper($postcode), $address->postalCode);
    }

    public function testRejectsPostcodeForWrongCountry(): void
    {
        // Arrange
        $this->expectException(InvalidOrder::class);

        // Act
        new ShippingAddress('Sam Collector', 'Dorpsstraat 1', null, '1234 AB', 'Antwerpen', 'BE');

        // Assert
        // The expected domain exception is the observable result.
    }

    /** @return iterable<string, array{string, string}> */
    public static function validPostcodes(): iterable
    {
        yield 'Netherlands' => ['NL', '1234 AB'];
        yield 'Belgium' => ['BE', '2000'];
    }
}
