<?php

declare(strict_types=1);

namespace App\Tests\Integration\Binder;

use App\Binder\Domain\Entity\Binder;
use App\Binder\Domain\Entity\BinderSlot;
use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Entity\Collection;
use App\Collection\Domain\Entity\CollectionItem;
use App\Identity\Domain\Entity\User;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class BinderSlotConcurrencyTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    /** @var array<string, string> */
    private array $fixture = [];

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            if ($this->fixture !== []) {
                $this->cleanUpFixture();
            }
            $this->entityManager->clear();
        }

        parent::tearDown();
    }

    #[SkipDatabaseRollback]
    public function testConfigureAndAssignCannotPersistAnOccupiedPlaceholder(): void
    {
        $fixture = $this->createFixture();
        $connection = $this->entityManager->getConnection();
        $temporaryDirectory = $this->createTemporaryDirectory();
        $workers = [];

        try {
            $applications = [
                'cardnest_binder_'.$fixture['runId'].'_assign',
                'cardnest_binder_'.$fixture['runId'].'_configure',
            ];
            $readyFiles = [
                $temporaryDirectory.'/assign.ready',
                $temporaryDirectory.'/configure.ready',
            ];

            $connection->beginTransaction();
            try {
                $connection->executeQuery(
                    'SELECT id FROM binder_slots WHERE id = CAST(:slotId AS uuid) FOR UPDATE',
                    ['slotId' => $fixture['slotId']],
                );
                $workers[] = $this->startWorker('assign', $fixture, $readyFiles[0], $applications[0]);
                $workers[] = $this->startWorker('configure', $fixture, $readyFiles[1], $applications[1]);

                $this->waitForWorkersToStart($readyFiles);
                $this->waitForWorkersToBlock($connection, $applications);
            } finally {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
            }

            $outcomes = array_map(
                fn (array $worker): string => $this->finishWorker($worker),
                $workers,
            );

            self::assertContains('assigned', $outcomes);
            self::assertTrue(
                in_array('configured', $outcomes, true) || in_array('occupied', $outcomes, true),
                'Configure must either precede assignment or reject the occupied slot.',
            );
            self::assertSame(1, (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM collection_item_location_history WHERE binder_slot_id = CAST(:slotId AS uuid) AND removed_at IS NULL',
                ['slotId' => $fixture['slotId']],
            ));
            $slot = $connection->fetchAssociative(
                'SELECT reserved_catalog_card_id, placeholder_label FROM binder_slots WHERE id = CAST(:slotId AS uuid)',
                ['slotId' => $fixture['slotId']],
            );
            self::assertIsArray($slot);
            self::assertNull($slot['reserved_catalog_card_id']);
            self::assertNull($slot['placeholder_label']);
        } finally {
            foreach ($workers as $worker) {
                $this->stopWorker($worker);
            }
            $this->removeTemporaryDirectory($temporaryDirectory);
        }
    }

    /** @return array{ownerId: string, binderId: string, slotId: string, itemId: string, cardId: string, runId: string} */
    private function createFixture(): array
    {
        $runId = substr(str_replace('-', '', Uuid::v7()->toRfc4122()), 0, 16);
        $owner = new User('binder-concurrency-'.$runId.'@example.test', 'hash');
        $series = new CardSeries('Binder concurrency '.$runId);
        $set = new CardSet($series, 'Binder concurrency set', 'binder-'.$runId, 'en', 1, 1, CardSetStatus::Active);
        $card = new CatalogCard($set, 'Concurrency card', '1/1', 'Rare', 'Collectible');
        $variant = $card->addVariant(VariantCode::Holo);
        $collection = new Collection($owner);
        $item = new CollectionItem(
            $collection,
            $card,
            $variant,
            CardLanguage::English,
            CardCondition::NearMint,
        );
        $binder = new Binder($owner, 'Concurrency binder', 1, 1, 1, false);
        $page = $binder->getPages()->first();
        $slot = $page !== false ? $page->getSlots()->first() : false;
        if (!$slot instanceof BinderSlot) {
            throw new RuntimeException('The concurrency binder did not create a slot.');
        }

        foreach ([$owner, $series, $set, $card, $collection, $item, $binder] as $entity) {
            $this->entityManager->persist($entity);
        }

        $this->fixture = [
            'ownerId' => $owner->getId()->toRfc4122(),
            'binderId' => $binder->getId()->toRfc4122(),
            'slotId' => $slot->getId()->toRfc4122(),
            'itemId' => $item->getId()->toRfc4122(),
            'cardId' => $card->getId()->toRfc4122(),
            'setId' => $set->getId()->toRfc4122(),
            'seriesId' => $series->getId()->toRfc4122(),
        ];
        $this->entityManager->flush();
        $this->entityManager->clear();

        return [...$this->fixture, 'runId' => $runId];
    }

    /** @param list<string> $readyFiles */
    private function waitForWorkersToStart(array $readyFiles): void
    {
        $deadline = microtime(true) + 5;
        do {
            if (array_reduce($readyFiles, static fn (bool $ready, string $file): bool => $ready && file_exists($file), true)) {
                return;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        self::fail('The concurrent binder workers did not both start within five seconds.');
    }

    /** @param list<string> $applications */
    private function waitForWorkersToBlock(Connection $connection, array $applications): void
    {
        $deadline = microtime(true) + 5;
        do {
            $blockedWorkers = (int) $connection->fetchOne(
                "SELECT COUNT(*) FROM pg_stat_activity WHERE (application_name = :firstApplication OR application_name = :secondApplication) AND wait_event_type = 'Lock'",
                [
                    'firstApplication' => $applications[0],
                    'secondApplication' => $applications[1],
                ],
            );
            if ($blockedWorkers === 2) {
                return;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        self::fail('Both binder workers must wait for the locked slot row before it is released.');
    }

    /**
     * @param array<string, string> $fixture
     *
     * @return array{process: resource, stdout: resource, stderr: resource}
     */
    private function startWorker(string $operation, array $fixture, string $readyFile, string $applicationName): array
    {
        $environment = getenv();
        $environment['APP_ENV'] = 'test';
        $environment['APP_DEBUG'] = '1';
        $process = proc_open(
            [
                PHP_BINARY,
                __DIR__.'/ConcurrentBinderSlotWorker.php',
                $operation,
                $fixture['ownerId'],
                $fixture['binderId'],
                $fixture['slotId'],
                $fixture['itemId'],
                $fixture['cardId'],
                $readyFile,
                $applicationName,
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            dirname(__DIR__, 3),
            $environment,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('The concurrent binder worker could not be started.');
        }

        fclose($pipes[0]);

        return [
            'process' => $process,
            'stdout' => $pipes[1],
            'stderr' => $pipes[2],
        ];
    }

    /** @param array{process: resource, stdout: resource, stderr: resource} $worker */
    private function finishWorker(array $worker): string
    {
        $stdout = stream_get_contents($worker['stdout']);
        $stderr = stream_get_contents($worker['stderr']);
        fclose($worker['stdout']);
        fclose($worker['stderr']);
        $exitCode = proc_close($worker['process']);
        if ($exitCode !== 0) {
            self::fail(sprintf('The concurrent binder worker failed: %s%s', $stdout, $stderr));
        }

        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($result) || !is_string($result['outcome'] ?? null)) {
            self::fail('The concurrent binder worker returned an invalid result.');
        }

        return $result['outcome'];
    }

    /** @param array{process: resource, stdout: resource, stderr: resource} $worker */
    private function stopWorker(array $worker): void
    {
        if (!is_resource($worker['process'])) {
            return;
        }

        proc_terminate($worker['process']);
        foreach (['stdout', 'stderr'] as $pipe) {
            if (is_resource($worker[$pipe])) {
                fclose($worker[$pipe]);
            }
        }
        proc_close($worker['process']);
    }

    private function createTemporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().'/cardnest-binder-'.Uuid::v7()->toRfc4122();
        if (!mkdir($directory, 0700) && !is_dir($directory)) {
            throw new RuntimeException('The concurrent binder test directory could not be created.');
        }

        return $directory;
    }

    private function removeTemporaryDirectory(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $file) {
            if (is_file($file) && !unlink($file)) {
                throw new RuntimeException('The concurrent binder test file could not be removed.');
            }
        }
        if (is_dir($directory) && !rmdir($directory)) {
            throw new RuntimeException('The concurrent binder test directory could not be removed.');
        }
    }

    private function cleanUpFixture(): void
    {
        $connection = $this->entityManager->getConnection();
        $this->entityManager->clear();
        $connection->executeStatement(
            'DELETE FROM collection_item_location_history WHERE collection_item_id = CAST(:itemId AS uuid)',
            ['itemId' => $this->fixture['itemId']],
        );
        $connection->executeStatement(
            'DELETE FROM binders WHERE id = CAST(:binderId AS uuid)',
            ['binderId' => $this->fixture['binderId']],
        );
        $connection->executeStatement(
            'DELETE FROM collections WHERE owner_id = CAST(:ownerId AS uuid)',
            ['ownerId' => $this->fixture['ownerId']],
        );
        $connection->executeStatement(
            'DELETE FROM catalog_cards WHERE id = CAST(:cardId AS uuid)',
            ['cardId' => $this->fixture['cardId']],
        );
        $connection->executeStatement(
            'DELETE FROM card_sets WHERE id = CAST(:setId AS uuid)',
            ['setId' => $this->fixture['setId']],
        );
        $connection->executeStatement(
            'DELETE FROM card_series WHERE id = CAST(:seriesId AS uuid)',
            ['seriesId' => $this->fixture['seriesId']],
        );
        $connection->executeStatement(
            'DELETE FROM users WHERE id = CAST(:ownerId AS uuid)',
            ['ownerId' => $this->fixture['ownerId']],
        );
        $this->fixture = [];
    }
}
