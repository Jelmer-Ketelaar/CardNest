<?php

declare(strict_types=1);

namespace App\Marketplace\Application\Message;

final readonly class ExpireListing
{
    public function __construct(public string $listingId)
    {
    }
}
