<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Entity\CatalogDiscrepancy;
use App\Catalog\Domain\Entity\CatalogImportRun;
use App\Catalog\Domain\Repository\CatalogDiscrepancyRepository;
use App\Catalog\Domain\ValueObject\CatalogDiscrepancyObservation;
use DateTimeImmutable;

final readonly class CatalogDiscrepancyRecorder
{
    public function __construct(private CatalogDiscrepancyRepository $discrepancies)
    {
    }

    public function record(
        CatalogImportRun $importRun,
        CatalogDiscrepancyObservation $observation,
        DateTimeImmutable $observedAt = new DateTimeImmutable(),
    ): CatalogDiscrepancy {
        $fingerprint = $observation->fingerprint($importRun->getProvider(), $importRun->getScopeKey());
        $discrepancy = $this->discrepancies->findByFingerprint($fingerprint);
        if ($discrepancy instanceof CatalogDiscrepancy) {
            if ($discrepancy->observe($importRun, $observation->severity, $observation->message, $observedAt)) {
                $this->discrepancies->save($discrepancy);
            }

            return $discrepancy;
        }

        $discrepancy = new CatalogDiscrepancy(
            lastImportRun: $importRun,
            provider: $importRun->getProvider(),
            scopeKey: $importRun->getScopeKey(),
            language: $observation->language,
            region: $observation->region,
            entityType: $observation->entityType,
            externalReference: $observation->externalReference,
            discrepancyType: $observation->type,
            severity: $observation->severity,
            message: $observation->message,
            fingerprint: $fingerprint,
            createdAt: $observedAt,
        );
        $this->discrepancies->save($discrepancy);

        return $discrepancy;
    }
}
