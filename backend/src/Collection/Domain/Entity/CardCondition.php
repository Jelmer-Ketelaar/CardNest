<?php

declare(strict_types=1);

namespace App\Collection\Domain\Entity;

enum CardCondition: string
{
    case Mint = 'mint';
    case NearMint = 'near_mint';
    case Excellent = 'excellent';
    case Good = 'good';
    case LightPlayed = 'light_played';
    case Played = 'played';
    case Poor = 'poor';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function meetsMinimum(self $minimum): bool
    {
        return $this->qualityRank() >= $minimum->qualityRank();
    }

    /** @return list<string> */
    public static function valuesMeetingMinimum(self $minimum): array
    {
        return array_values(array_map(
            static fn (self $condition): string => $condition->value,
            array_filter(self::cases(), static fn (self $condition): bool => $condition->meetsMinimum($minimum)),
        ));
    }

    private function qualityRank(): int
    {
        return match ($this) {
            self::Mint => 7,
            self::NearMint => 6,
            self::Excellent => 5,
            self::Good => 4,
            self::LightPlayed => 3,
            self::Played => 2,
            self::Poor => 1,
        };
    }
}
