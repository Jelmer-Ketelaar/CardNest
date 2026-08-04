<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Entity;

use App\Catalog\Domain\Entity\CatalogDiscrepancy;
use App\Catalog\Domain\Entity\CatalogDiscrepancyEntityType;
use App\Catalog\Domain\Entity\CatalogDiscrepancyResolutionStatus;
use App\Catalog\Domain\Entity\CatalogDiscrepancySeverity;
use App\Catalog\Domain\Entity\CatalogDiscrepancyType;
use App\Catalog\Domain\Entity\CatalogImportRun;
use App\Identity\Domain\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CatalogDiscrepancyTest extends TestCase
{
    public function testResolutionIsAuditedAndAnIdenticalResolutionIsIdempotent(): void
    {
        // Arrange
        $discrepancy = $this->discrepancy($this->completedRun('version-1'));
        $actor = new User('admin@example.com', 'hash');
        $resolvedAt = new DateTimeImmutable('2026-08-04T08:00:00+00:00');

        // Act
        $changed = $discrepancy->resolve(
            CatalogDiscrepancyResolutionStatus::Resolved,
            'The approved provider record was restored.',
            $actor,
            $resolvedAt,
        );
        $repeated = $discrepancy->resolve(
            CatalogDiscrepancyResolutionStatus::Resolved,
            'The approved provider record was restored.',
            $actor,
            $resolvedAt,
        );

        // Assert
        $this->assertTrue($changed);
        $this->assertFalse($repeated);
        $this->assertSame(CatalogDiscrepancyResolutionStatus::Resolved, $discrepancy->getResolutionStatus());
        $this->assertSame($actor->getId()->toRfc4122(), $discrepancy->getResolvedByIdentifier());
        $this->assertCount(1, $discrepancy->getResolutionHistory());
        $this->assertSame('open', $discrepancy->getResolutionHistory()[0]['from']);
        $this->assertSame('resolved', $discrepancy->getResolutionHistory()[0]['to']);
    }

    public function testLaterRecurrenceReopensResolvedDiscrepancyWithoutDuplicatingSameRun(): void
    {
        // Arrange
        $firstRun = $this->completedRun('version-1');
        $secondRun = $this->completedRun('version-2');
        $discrepancy = $this->discrepancy($firstRun);
        $discrepancy->resolve(
            CatalogDiscrepancyResolutionStatus::Resolved,
            'The approved provider record was restored.',
            new User('admin@example.com', 'hash'),
        );

        // Act
        $sameRunChanged = $discrepancy->observe(
            $firstRun,
            CatalogDiscrepancySeverity::Critical,
            'Set base1 is still missing.',
        );
        $laterRunChanged = $discrepancy->observe(
            $secondRun,
            CatalogDiscrepancySeverity::Critical,
            'Set base1 is missing again.',
            new DateTimeImmutable('2026-08-05T08:00:00+00:00'),
        );

        // Assert
        $this->assertFalse($sameRunChanged);
        $this->assertTrue($laterRunChanged);
        $this->assertSame(2, $discrepancy->getOccurrenceCount());
        $this->assertSame(CatalogDiscrepancyResolutionStatus::Open, $discrepancy->getResolutionStatus());
        $this->assertNull($discrepancy->getResolutionReason());
        $this->assertCount(2, $discrepancy->getResolutionHistory());
        $this->assertSame('open', $discrepancy->getResolutionHistory()[1]['to']);
    }

    private function discrepancy(CatalogImportRun $run): CatalogDiscrepancy
    {
        return new CatalogDiscrepancy(
            lastImportRun: $run,
            provider: 'tcgdex',
            scopeKey: hash('sha256', 'scope'),
            language: 'en',
            region: 'international',
            entityType: CatalogDiscrepancyEntityType::Set,
            externalReference: 'base1',
            discrepancyType: CatalogDiscrepancyType::MissingSet,
            severity: CatalogDiscrepancySeverity::Critical,
            message: 'Set base1 is missing.',
            fingerprint: hash('sha256', 'fingerprint'),
        );
    }

    private function completedRun(string $sourceVersion): CatalogImportRun
    {
        $run = new CatalogImportRun(
            provider: 'tcgdex',
            scopeKey: hash('sha256', 'scope'),
            dataScope: ['languages' => ['en'], 'setIds' => ['base1']],
            sourceVersion: $sourceVersion,
            dataCutoff: new DateTimeImmutable('2026-08-04T00:00:00+00:00'),
        );
        $run->markValidating(hash('sha256', $sourceVersion), 1, 1);
        $run->markPublishing();
        $run->complete(1, 1);

        return $run;
    }
}
