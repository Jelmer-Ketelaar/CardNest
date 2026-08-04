<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use App\Catalog\Domain\Entity\CatalogDiscrepancy;

final readonly class CatalogDiscrepancyResponse
{
    public function __construct(private CatalogDiscrepancy $discrepancy)
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->discrepancy->getId()->toRfc4122(),
            'importRunId' => $this->discrepancy->getLastImportRun()->getId()->toRfc4122(),
            'provider' => $this->discrepancy->getProvider(),
            'scopeKey' => $this->discrepancy->getScopeKey(),
            'language' => $this->discrepancy->getLanguage(),
            'region' => $this->discrepancy->getRegion(),
            'entityType' => $this->discrepancy->getEntityType()->value,
            'externalReference' => $this->discrepancy->getExternalReference(),
            'type' => $this->discrepancy->getDiscrepancyType()->value,
            'severity' => $this->discrepancy->getSeverity()->value,
            'message' => $this->discrepancy->getMessage(),
            'status' => $this->discrepancy->getResolutionStatus()->value,
            'occurrenceCount' => $this->discrepancy->getOccurrenceCount(),
            'resolution' => [
                'reason' => $this->discrepancy->getResolutionReason(),
                'actorId' => $this->discrepancy->getResolvedByIdentifier(),
                'resolvedAt' => $this->discrepancy->getResolvedAt()?->format(DATE_ATOM),
                'history' => $this->discrepancy->getResolutionHistory(),
            ],
            'firstSeenAt' => $this->discrepancy->getCreatedAt()->format(DATE_ATOM),
            'lastSeenAt' => $this->discrepancy->getLastSeenAt()->format(DATE_ATOM),
            'updatedAt' => $this->discrepancy->getUpdatedAt()->format(DATE_ATOM),
        ];
    }
}
