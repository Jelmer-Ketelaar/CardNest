<?php

declare(strict_types=1);

namespace App\Tests\Integration\Marketplace;

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
use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Entity\ListingImageSide;
use App\Marketplace\Domain\Entity\ListingStatus;
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Marketplace\Domain\ValueObject\ListingPhotoReference;
use App\Marketplace\Domain\ValueObject\ShippingOption;
use App\Shared\Domain\ValueObject\Money;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class ListingTransitionConcurrencyTest extends KernelTestCase
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
    public function testCancellationRechecksTheWinningTransitionAfterAcquiringTheListingLock(): void
    {
        $result = $this->runWorkerAfterWinningTransition('cancel');

        self::assertSame([
            'outcome' => 'conflict',
            'message' => 'Deze listing kan niet worden geannuleerd.',
        ], $result);
        $this->assertWinningStateWasPreserved();
    }

    #[SkipDatabaseRollback]
    public function testExpirationIsAnIdempotentNoOpAfterAcquiringTheListingLock(): void
    {
        $result = $this->runWorkerAfterWinningTransition('expire');

        self::assertSame(['outcome' => 'completed'], $result);
        $this->assertWinningStateWasPreserved();
    }

    /** @return array<string, string> */
    private function runWorkerAfterWinningTransition(string $action): array
    {
        $fixture = $this->createFixture();
        $connection = $this->entityManager->getConnection();
        $temporaryDirectory = $this->createTemporaryDirectory();
        $worker = null;

        try {
            $applicationName = sprintf('cardnest_listing_%s_%s', $action, $fixture['runId']);
            $readyFile = $temporaryDirectory.'/worker.ready';

            $connection->beginTransaction();
            try {
                $connection->executeQuery(
                    'SELECT id FROM listings WHERE id = CAST(:listingId AS uuid) FOR UPDATE',
                    ['listingId' => $fixture['listingId']],
                );
                $worker = $this->startWorker(
                    $action,
                    $fixture['listingId'],
                    $fixture['sellerId'],
                    $readyFile,
                    $applicationName,
                );
                $this->waitForWorkerToStart($readyFile);
                $this->waitForWorkerToBlock($connection, $applicationName);
                $connection->executeStatement(
                    "UPDATE listings SET status = 'sold' WHERE id = CAST(:listingId AS uuid)",
                    ['listingId' => $fixture['listingId']],
                );
                $connection->commit();
            } finally {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
            }

            return $this->finishWorker($worker);
        } finally {
            if (is_array($worker)) {
                $this->stopWorker($worker);
            }
            $this->removeTemporaryDirectory($temporaryDirectory);
        }
    }

    /**
     * @return array{listingId: string, sellerId: string, cardId: string, setId: string, seriesId: string, runId: string}
     */
    private function createFixture(): array
    {
        $runId = substr(str_replace('-', '', Uuid::v7()->toRfc4122()), 0, 16);
        $seller = new User('listing-transition-'.$runId.'@example.test', 'hash');
        $series = new CardSeries('Listing transition '.$runId);
        $set = new CardSet($series, 'Listing transition set', 'transition-'.$runId, 'en', 1, 1, CardSetStatus::Active);
        $card = new CatalogCard($set, 'Transition card', '1/1', 'Rare', 'Collectible');
        $variant = $card->addVariant(VariantCode::Holo);
        $collection = new Collection($seller);
        $item = new CollectionItem($collection, $card, $variant, CardLanguage::English, CardCondition::NearMint);
        $listing = new Listing(
            $seller,
            $item,
            new Money(750, 'EUR'),
            [new ShippingOption(ShippingMethodCode::LetterMail, new Money(150, 'EUR'))],
            [
                new ListingPhotoReference(ListingImageSide::Front, 'https://images.example/transition-front.jpg'),
                new ListingPhotoReference(ListingImageSide::Back, 'https://images.example/transition-back.jpg'),
            ],
            expiresAt: new DateTimeImmutable('-1 hour'),
            status: ListingStatus::Active,
            createdAt: new DateTimeImmutable('-2 hours'),
        );
        $item->markForSale();

        foreach ([$seller, $series, $set, $card, $collection, $item, $listing] as $entity) {
            $this->entityManager->persist($entity);
        }

        $this->fixture = [
            'listingId' => $listing->getId()->toRfc4122(),
            'sellerId' => $seller->getId()->toRfc4122(),
            'cardId' => $card->getId()->toRfc4122(),
            'setId' => $set->getId()->toRfc4122(),
            'seriesId' => $series->getId()->toRfc4122(),
        ];
        $this->entityManager->flush();
        $this->entityManager->clear();

        return [...$this->fixture, 'runId' => $runId];
    }

    private function waitForWorkerToStart(string $readyFile): void
    {
        $deadline = microtime(true) + 5;
        do {
            if (file_exists($readyFile)) {
                return;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        self::fail('The listing transition worker did not start within five seconds.');
    }

    private function waitForWorkerToBlock(Connection $connection, string $applicationName): void
    {
        $deadline = microtime(true) + 5;
        do {
            $blocked = (int) $connection->fetchOne(
                "SELECT COUNT(*) FROM pg_stat_activity WHERE application_name = :applicationName AND wait_event_type = 'Lock'",
                ['applicationName' => $applicationName],
            );
            if ($blocked === 1) {
                return;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        self::fail('The listing transition worker must wait for the locked listing row.');
    }

    /**
     * @return array{process: resource, stdout: resource, stderr: resource}
     */
    private function startWorker(
        string $action,
        string $listingId,
        string $sellerId,
        string $readyFile,
        string $applicationName,
    ): array {
        $environment = getenv();
        $environment['APP_ENV'] = 'test';
        $environment['APP_DEBUG'] = '1';
        $process = proc_open(
            [
                PHP_BINARY,
                __DIR__.'/ConcurrentListingTransitionWorker.php',
                $action,
                $listingId,
                $sellerId,
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
            throw new RuntimeException('The listing transition worker could not be started.');
        }

        fclose($pipes[0]);

        return [
            'process' => $process,
            'stdout' => $pipes[1],
            'stderr' => $pipes[2],
        ];
    }

    /**
     * @param array{process: resource, stdout: resource, stderr: resource} $worker
     *
     * @return array<string, string>
     */
    private function finishWorker(array $worker): array
    {
        $stdout = stream_get_contents($worker['stdout']);
        $stderr = stream_get_contents($worker['stderr']);
        fclose($worker['stdout']);
        fclose($worker['stderr']);
        $exitCode = proc_close($worker['process']);
        if ($exitCode !== 0) {
            self::fail(sprintf('The listing transition worker failed: %s%s', $stdout, $stderr));
        }

        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($result) || !is_string($result['outcome'] ?? null)) {
            self::fail('The listing transition worker returned an invalid result.');
        }

        /* @var array<string, string> $result */
        return $result;
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

    private function assertWinningStateWasPreserved(): void
    {
        $connection = $this->entityManager->getConnection();
        self::assertSame('sold', $connection->fetchOne(
            'SELECT status FROM listings WHERE id = CAST(:listingId AS uuid)',
            ['listingId' => $this->fixture['listingId']],
        ));
        self::assertSame('for_sale', $connection->fetchOne(
            <<<'SQL'
                SELECT item.status
                FROM collection_items item
                INNER JOIN listings listing ON listing.collection_item_id = item.id
                WHERE listing.id = CAST(:listingId AS uuid)
                SQL,
            ['listingId' => $this->fixture['listingId']],
        ));
    }

    private function createTemporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().'/cardnest-listing-transition-'.Uuid::v7()->toRfc4122();
        if (!mkdir($directory, 0700) && !is_dir($directory)) {
            throw new RuntimeException('The listing transition test directory could not be created.');
        }

        return $directory;
    }

    private function removeTemporaryDirectory(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $file) {
            if (is_file($file) && !unlink($file)) {
                throw new RuntimeException('The listing transition test file could not be removed.');
            }
        }
        if (is_dir($directory) && !rmdir($directory)) {
            throw new RuntimeException('The listing transition test directory could not be removed.');
        }
    }

    private function cleanUpFixture(): void
    {
        $connection = $this->entityManager->getConnection();
        $this->entityManager->clear();
        $connection->executeStatement(
            'DELETE FROM listings WHERE id = CAST(:listingId AS uuid)',
            ['listingId' => $this->fixture['listingId']],
        );
        $connection->executeStatement(
            'DELETE FROM collections WHERE owner_id = CAST(:sellerId AS uuid)',
            ['sellerId' => $this->fixture['sellerId']],
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
            'DELETE FROM users WHERE id = CAST(:sellerId AS uuid)',
            ['sellerId' => $this->fixture['sellerId']],
        );
        $this->fixture = [];
    }
}
