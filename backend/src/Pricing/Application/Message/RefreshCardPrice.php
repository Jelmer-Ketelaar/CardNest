<?php

declare(strict_types=1);

namespace App\Pricing\Application\Message;

final readonly class RefreshCardPrice
{
    public function __construct(public string $cardId)
    {
    }
}
