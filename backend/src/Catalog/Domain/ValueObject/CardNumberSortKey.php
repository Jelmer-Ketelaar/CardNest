<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

/**
 * Card numbers ("19/25", "SWSH100", "TG01/TG30") do not sort correctly as plain
 * strings. This derives a zero-padded, lowercase key so numeric segments compare
 * the way a human expects (2 before 10) while keeping non-numeric segments intact.
 */
final class CardNumberSortKey
{
    private function __construct()
    {
    }

    public static function generate(string $number): string
    {
        $normalized = mb_strtolower(trim($number));

        return (string) preg_replace_callback(
            '/\d+/',
            static fn (array $match): string => str_pad($match[0], 6, '0', STR_PAD_LEFT),
            $normalized,
        );
    }
}
