<?php

declare(strict_types=1);

namespace App\Marketplace\Domain\Entity;

enum ShippingMethodCode: string
{
    case LetterMail = 'letter_mail';
    case Parcel = 'parcel';
    case Pickup = 'pickup';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
