<?php

declare(strict_types=1);

namespace App\Marketplace\Domain\Entity;

enum ListingStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Suspended = 'suspended';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
