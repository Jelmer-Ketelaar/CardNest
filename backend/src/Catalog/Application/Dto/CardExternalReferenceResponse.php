<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use App\Catalog\Domain\Entity\CardExternalReference;

final readonly class CardExternalReferenceResponse
{
    public function __construct(private CardExternalReference $reference)
    {
    }

    /** @return array{id: string, source: string, externalId: string, importedAt: string, lastSyncedAt: ?string} */
    public function toArray(): array
    {
        return [
            'id' => $this->reference->getId()->toRfc4122(),
            'source' => $this->reference->getSourceName(),
            'externalId' => $this->reference->getExternalId(),
            'importedAt' => $this->reference->getImportedAt()->format(DATE_ATOM),
            'lastSyncedAt' => $this->reference->getLastSyncedAt()?->format(DATE_ATOM),
        ];
    }
}
