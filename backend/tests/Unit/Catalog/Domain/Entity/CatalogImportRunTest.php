<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Entity;

use App\Catalog\Domain\Entity\CatalogImportRun;
use App\Catalog\Domain\Entity\CatalogImportStatus;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class CatalogImportRunTest extends TestCase
{
    public function testTracksACompleteImportLifecycleAndSourceMetadata(): void
    {
        // Arrange
        $cutoff = new DateTimeImmutable('2026-08-03T22:00:00+00:00');
        $run = $this->createRun('release-42', $cutoff);

        // Act
        $run->markValidating(str_repeat('b', 64), 2, 20);
        $run->markPublishing();
        $run->complete(2, 20);

        // Assert
        self::assertSame(CatalogImportStatus::Completed, $run->getStatus());
        self::assertSame('release-42', $run->getSourceVersion());
        self::assertSame($cutoff, $run->getDataCutoff());
        self::assertSame(2, $run->getStagedSetCount());
        self::assertSame(20, $run->getStagedCardCount());
        self::assertSame(2, $run->getPublishedSetCount());
        self::assertSame(20, $run->getPublishedCardCount());
        self::assertNotNull($run->getCompletedAt());
    }

    public function testCannotCompleteUntilEveryStagedRecordWasPublished(): void
    {
        // Arrange
        $run = $this->createRun();
        $run->markValidating(str_repeat('c', 64), 2, 20);
        $run->markPublishing();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('publish every staged record');

        // Act
        $run->complete(1, 10);

        // Assert
        // The invalid terminal state is rejected.
    }

    public function testRecordsPartialStagingBeforeAFailure(): void
    {
        // Arrange
        $run = $this->createRun();

        // Act
        $run->recordStagedCounts(3, 120);
        $run->fail('Provider connection failed.');

        // Assert
        self::assertSame(CatalogImportStatus::Failed, $run->getStatus());
        self::assertSame(3, $run->getStagedSetCount());
        self::assertSame(120, $run->getStagedCardCount());
        self::assertSame(1, $run->getErrorCount());
        self::assertSame('Provider connection failed.', $run->getFailureReason());
    }

    public function testLinksAnIdempotentReplayToItsCompletedRun(): void
    {
        // Arrange
        $completed = $this->createRun('release-42');
        $completed->markValidating(str_repeat('d', 64), 1, 10);
        $completed->markPublishing();
        $completed->complete(1, 10);
        $replay = $this->createRun('release-42');
        $replay->markValidating(str_repeat('d', 64), 1, 10);

        // Act
        $replay->skipAsDuplicate($completed);

        // Assert
        self::assertSame(CatalogImportStatus::Skipped, $replay->getStatus());
        self::assertSame($completed, $replay->getDuplicateOf());
        self::assertNotNull($replay->getCompletedAt());
    }

    public function testCannotLinkAReplayWithDifferentSourceMetadata(): void
    {
        // Arrange
        $completed = $this->createRun('release-42');
        $completed->markValidating(str_repeat('d', 64), 1, 10);
        $completed->markPublishing();
        $completed->complete(1, 10);
        $replay = $this->createRun('release-43');
        $replay->markValidating(str_repeat('d', 64), 1, 10);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('source fingerprint');

        // Act
        $replay->skipAsDuplicate($completed);

        // Assert
        // A run cannot hide a different release behind another run's audit record.
    }

    private function createRun(
        ?string $sourceVersion = null,
        ?DateTimeImmutable $dataCutoff = null,
    ): CatalogImportRun {
        return new CatalogImportRun(
            provider: 'tcgdex',
            scopeKey: str_repeat('a', 64),
            dataScope: ['languages' => ['en'], 'setIds' => []],
            sourceVersion: $sourceVersion,
            dataCutoff: $dataCutoff,
            startedAt: new DateTimeImmutable('2026-08-03T21:00:00+00:00'),
        );
    }
}
