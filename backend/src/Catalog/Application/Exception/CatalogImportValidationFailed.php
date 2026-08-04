<?php

declare(strict_types=1);

namespace App\Catalog\Application\Exception;

use App\Catalog\Domain\ValueObject\CatalogDiscrepancyObservation;
use RuntimeException;
use Throwable;

final class CatalogImportValidationFailed extends RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?CatalogDiscrepancyObservation $observation = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function observed(
        CatalogDiscrepancyObservation $observation,
        ?Throwable $previous = null,
    ): self {
        return new self($observation->message, previous: $previous, observation: $observation);
    }
}
