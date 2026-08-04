<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\CatalogDiscrepancyRecorder;
use App\Catalog\Application\Exception\InvalidCatalogCoverageEvidence;
use App\Catalog\Application\GenerateCatalogCoverageSnapshot;
use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\CatalogCoverageSnapshot;
use App\Catalog\Domain\Entity\CatalogDiscrepancy;
use App\Catalog\Domain\Entity\CatalogDiscrepancyEntityType;
use App\Catalog\Domain\Entity\CatalogDiscrepancyResolutionStatus;
use App\Catalog\Domain\Entity\CatalogDiscrepancySeverity;
use App\Catalog\Domain\Entity\CatalogDiscrepancyType;
use App\Catalog\Domain\Entity\CatalogImportRun;
use App\Catalog\Domain\Entity\CatalogReleaseGateStatus;
use App\Catalog\Domain\ValueObject\CatalogCoverageManifest;
use App\Catalog\Domain\ValueObject\CatalogCoverageManifestSet;
use App\Catalog\Domain\ValueObject\CatalogDiscrepancyObservation;
use App\Identity\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogCoverageTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testItPersistsOneSnapshotForTheSameSuccessfulRunAndManifest(): void
    {
        // Arrange
        $approver = new User('coverage-admin@example.com', 'hash');
        $run = $this->completedRun(['base1'], 1, 1);
        $this->persistCatalogSet('base1', 1);
        $this->entityManager->persist($approver);
        $this->entityManager->persist($run);
        $this->entityManager->flush();
        $manifest = $this->manifest([new CatalogCoverageManifestSet('base1', 1)]);
        $generator = self::getContainer()->get(GenerateCatalogCoverageSnapshot::class);

        // Act
        $first = $generator->execute($approver, $run->getId()->toRfc4122(), $manifest);
        $second = $generator->execute($approver, $run->getId()->toRfc4122(), $manifest);

        // Assert
        $this->assertSame($first->getId()->toRfc4122(), $second->getId()->toRfc4122());
        $this->assertSame(CatalogReleaseGateStatus::NotVerified, $first->getReleaseGateStatus());
        $this->assertSame('complete', $first->getDimensions()['sets']['status']);
        $this->assertSame('complete', $first->getDimensions()['cards']['status']);
        $this->assertSame('not_verified', $first->getDimensions()['pokemon_species']['status']);
        $this->assertSame('not_verified', $first->getDimensions()['ordering']['status']);
        $this->assertSame(1, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM catalog_coverage_snapshots',
        ));
        $this->assertSame(0, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM catalog_discrepancies',
        ));
    }

    public function testMissingManifestSetCreatesOneCriticalDiscrepancyAndBlocksTheGate(): void
    {
        // Arrange
        $approver = new User('missing-set-admin@example.com', 'hash');
        $run = $this->completedRun([], 1, 1);
        $this->persistCatalogSet('base1', 1);
        $this->entityManager->persist($approver);
        $this->entityManager->persist($run);
        $this->entityManager->flush();
        $manifest = $this->manifest([
            new CatalogCoverageManifestSet('base1', 1),
            new CatalogCoverageManifestSet('missing-set', 1),
        ]);
        $generator = self::getContainer()->get(GenerateCatalogCoverageSnapshot::class);

        // Act
        $snapshot = $generator->execute($approver, $run->getId()->toRfc4122(), $manifest);
        $repeated = $generator->execute($approver, $run->getId()->toRfc4122(), $manifest);

        // Assert
        $this->assertSame($snapshot->getId()->toRfc4122(), $repeated->getId()->toRfc4122());
        $this->assertSame(CatalogReleaseGateStatus::BlockedDiscrepancies, $snapshot->getReleaseGateStatus());
        $this->assertSame(1, $snapshot->getUnresolvedCriticalDiscrepancyCount());
        $this->assertSame(1, (int) $this->entityManager->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM catalog_discrepancies WHERE discrepancy_type = 'missing_set'",
        ));
        $this->assertSame(1, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT occurrence_count FROM catalog_discrepancies LIMIT 1',
        ));
    }

    public function testCriticalDiscrepancyFromAnotherImportScopeDoesNotBlockTheGate(): void
    {
        // Arrange
        $approver = new User('scope-admin@example.com', 'hash');
        $targetRun = $this->completedRun(['base1'], 1, 1);
        $otherRun = $this->completedRun(['other-set'], 1, 1);
        $this->persistCatalogSet('base1', 1);
        $this->entityManager->persist($approver);
        $this->entityManager->persist($targetRun);
        $this->entityManager->persist($otherRun);
        $this->entityManager->flush();
        self::getContainer()->get(CatalogDiscrepancyRecorder::class)->record(
            $otherRun,
            new CatalogDiscrepancyObservation(
                identity: 'missing-set:other-set',
                entityType: CatalogDiscrepancyEntityType::Set,
                type: CatalogDiscrepancyType::MissingSet,
                severity: CatalogDiscrepancySeverity::Critical,
                message: 'The other import scope is missing a set.',
                externalReference: 'other-set',
                language: 'en',
                region: 'international',
            ),
        );

        // Act
        $snapshot = self::getContainer()->get(GenerateCatalogCoverageSnapshot::class)->execute(
            $approver,
            $targetRun->getId()->toRfc4122(),
            $this->manifest([new CatalogCoverageManifestSet('base1', 1)]),
        );

        // Assert
        $this->assertSame(CatalogReleaseGateStatus::NotVerified, $snapshot->getReleaseGateStatus());
        $this->assertSame(0, $snapshot->getUnresolvedCriticalDiscrepancyCount());
        $this->assertSame(1, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM catalog_discrepancies',
        ));
    }

    public function testCoverageEvidenceRejectsAnUnversionedImportRun(): void
    {
        // Arrange
        $approver = new User('unversioned-admin@example.com', 'hash');
        $run = new CatalogImportRun(
            provider: 'tcgdex',
            scopeKey: hash('sha256', 'unversioned-scope'),
            dataScope: ['languages' => ['en'], 'setIds' => ['base1']],
            sourceVersion: null,
            dataCutoff: new DateTimeImmutable('2026-08-04T00:00:00+00:00'),
        );
        $run->markValidating(hash('sha256', 'unversioned'), 1, 1);
        $run->markPublishing();
        $run->complete(1, 1);
        $this->entityManager->persist($approver);
        $this->entityManager->persist($run);
        $this->entityManager->flush();

        // Assert
        $this->expectException(InvalidCatalogCoverageEvidence::class);
        $this->expectExceptionMessage('requires a versioned catalog import source');

        // Act
        self::getContainer()->get(GenerateCatalogCoverageSnapshot::class)->execute(
            $approver,
            $run->getId()->toRfc4122(),
            $this->manifest([new CatalogCoverageManifestSet('base1', 1)]),
        );
    }

    public function testDeletingApproverKeepsImmutableCoverageAndResolutionAuditIdentifiers(): void
    {
        // Arrange
        $approver = new User('deleted-approver@example.com', 'hash');
        $run = $this->completedRun([], 1, 1);
        $this->persistCatalogSet('base1', 1);
        $this->entityManager->persist($approver);
        $this->entityManager->persist($run);
        $this->entityManager->flush();
        $snapshot = self::getContainer()->get(GenerateCatalogCoverageSnapshot::class)->execute(
            $approver,
            $run->getId()->toRfc4122(),
            $this->manifest([
                new CatalogCoverageManifestSet('base1', 1),
                new CatalogCoverageManifestSet('missing-set', 1),
            ]),
        );
        $discrepancy = $this->entityManager->getRepository(CatalogDiscrepancy::class)->findOneBy([]);
        $this->assertInstanceOf(CatalogDiscrepancy::class, $discrepancy);
        $discrepancy->resolve(
            CatalogDiscrepancyResolutionStatus::Resolved,
            'The evidence was reviewed before deleting the administrator.',
            $approver,
        );
        $this->entityManager->flush();
        $approverId = $approver->getId()->toRfc4122();
        $snapshotId = $snapshot->getId()->toRfc4122();
        $discrepancyId = $discrepancy->getId()->toRfc4122();

        // Act
        $this->entityManager->remove($approver);
        $this->entityManager->flush();
        $this->entityManager->clear();
        $reloadedSnapshot = $this->entityManager->find(CatalogCoverageSnapshot::class, $snapshotId);
        $reloadedDiscrepancy = $this->entityManager->find(CatalogDiscrepancy::class, $discrepancyId);

        // Assert
        $this->assertInstanceOf(CatalogCoverageSnapshot::class, $reloadedSnapshot);
        $this->assertInstanceOf(CatalogDiscrepancy::class, $reloadedDiscrepancy);
        $this->assertSame($approverId, $reloadedSnapshot->getApprovedByIdentifier());
        $this->assertSame($approverId, $reloadedDiscrepancy->getResolvedByIdentifier());
        $this->assertSame($approverId, $reloadedDiscrepancy->getResolutionHistory()[0]['actorId']);
        $this->assertNull($this->entityManager->find(User::class, $approverId));
    }

    /** @param list<string> $setIds */
    private function completedRun(array $setIds, int $setCount, int $cardCount): CatalogImportRun
    {
        $run = new CatalogImportRun(
            provider: 'tcgdex',
            scopeKey: hash('sha256', json_encode($setIds, JSON_THROW_ON_ERROR)),
            dataScope: ['languages' => ['en'], 'setIds' => $setIds],
            sourceVersion: 'approved-manifest-2026-08-04',
            dataCutoff: new DateTimeImmutable('2026-08-04T00:00:00+00:00'),
        );
        $run->markValidating(hash('sha256', implode(',', $setIds)), $setCount, $cardCount);
        $run->markPublishing();
        $run->complete($setCount, $cardCount);

        return $run;
    }

    private function persistCatalogSet(string $externalId, int $cardCount): void
    {
        $series = new CardSeries('Coverage series '.$externalId, 'coverage-'.$externalId);
        $set = new CardSet(
            series: $series,
            name: 'Coverage set '.$externalId,
            code: 'tcgdex:en:'.$externalId,
            language: 'en',
            printedTotal: $cardCount,
            total: $cardCount,
            status: CardSetStatus::Active,
        );
        $this->entityManager->persist($series);
        $this->entityManager->persist($set);
        for ($number = 1; $number <= $cardCount; ++$number) {
            $this->entityManager->persist(new CatalogCard(
                set: $set,
                name: 'Coverage card '.$number,
                number: (string) $number,
                rarity: 'Common',
                cardType: 'Pokemon',
                imageUrl: 'https://assets.example.test/card/'.$number,
            ));
        }
    }

    /** @param list<CatalogCoverageManifestSet> $sets */
    private function manifest(array $sets): CatalogCoverageManifest
    {
        return new CatalogCoverageManifest(
            provider: 'tcgdex',
            region: 'international',
            language: 'en',
            evidenceSource: 'approved-manifest',
            evidenceVersion: 'approved-manifest-2026-08-04',
            evidenceReference: 'catalog-evidence://international/en/2026-08-04',
            dataCutoff: new DateTimeImmutable('2026-08-04T00:00:00+00:00'),
            sets: $sets,
            expectedImageCardCount: count($sets),
        );
    }
}
