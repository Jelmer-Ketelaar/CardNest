<?php

declare(strict_types=1);

namespace App\Collection\Application;

use App\Collection\Domain\Entity\CollectionItem;
use App\Identity\Domain\Entity\User;

interface RegisteredCollectionItemsProcessor
{
    /** @param list<CollectionItem> $items */
    public function process(User $owner, array $items): void;
}
