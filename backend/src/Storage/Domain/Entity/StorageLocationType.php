<?php

declare(strict_types=1);

namespace App\Storage\Domain\Entity;

enum StorageLocationType: string
{
    case Binder = 'binder';
    case Box = 'box';
    case TopLoaderBox = 'top_loader_box';
    case GradedCardBox = 'graded_card_box';
    case Display = 'display';
    case Safe = 'safe';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
