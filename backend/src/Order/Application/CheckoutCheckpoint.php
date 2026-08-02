<?php

declare(strict_types=1);

namespace App\Order\Application;

enum CheckoutCheckpoint: string
{
    case PreparePersisted = 'prepare_persisted';
    case ProviderResultPersistence = 'provider_result_persistence';
    case Finalization = 'finalization';
}
