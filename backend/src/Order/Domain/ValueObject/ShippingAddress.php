<?php

declare(strict_types=1);

namespace App\Order\Domain\ValueObject;

use App\Order\Domain\Exception\InvalidOrder;

final readonly class ShippingAddress
{
    public string $fullName;
    public string $addressLine1;
    public ?string $addressLine2;
    public string $postalCode;
    public string $city;
    public string $countryCode;

    public function __construct(
        string $fullName,
        string $addressLine1,
        ?string $addressLine2,
        string $postalCode,
        string $city,
        string $countryCode,
    ) {
        $this->fullName = self::required($fullName, 180, 'Vul een geldige volledige naam in.');
        $this->addressLine1 = self::required($addressLine1, 180, 'Vul een geldig adres in.');
        $this->addressLine2 = self::optional($addressLine2, 180);
        $this->city = self::required($city, 120, 'Vul een geldige plaats in.');
        $this->countryCode = strtoupper(trim($countryCode));
        if (!in_array($this->countryCode, ['NL', 'BE'], true)) {
            throw new InvalidOrder('Alleen Nederlandse en Belgische afleveradressen worden ondersteund.');
        }

        $this->postalCode = strtoupper(trim($postalCode));
        $pattern = $this->countryCode === 'NL' ? '/^\d{4}\s?[A-Z]{2}$/' : '/^\d{4}$/';
        if (preg_match($pattern, $this->postalCode) !== 1) {
            throw new InvalidOrder('De postcode past niet bij het gekozen land.');
        }
    }

    private static function required(string $value, int $maximum, string $message): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maximum) {
            throw new InvalidOrder($message);
        }

        return $value;
    }

    private static function optional(?string $value, int $maximum): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (mb_strlen($value) > $maximum) {
            throw new InvalidOrder('De tweede adresregel is te lang.');
        }

        return $value;
    }
}
