<?php

declare(strict_types=1);

namespace App\Catalog\Application\Exception;

use RuntimeException;

final class CatalogDiscrepancyNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Catalog discrepancy not found.');
    }
}
