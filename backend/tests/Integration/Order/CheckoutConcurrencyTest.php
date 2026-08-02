<?php

declare(strict_types=1);

namespace App\Tests\Integration\Order;

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
use App\Order\Domain\Entity\ShoppingCart;
use App\Shared\Domain\ValueObject\Money;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class CheckoutConcurrencyTest extends KernelTestCase
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
    public function testOnlyOneCheckoutCanPurchaseTheSameListing(): void
    {
        $fixture = $this->createFixture();
        $connection = $this->entityManager->getConnection();
        $temporaryDirectory = $this->createTemporaryDirectory();
        $workers = [];

        try {
            $applications = [
                'cardnest_checkout_'.$fixture['runId'].'_one',
                'cardnest_checkout_'.$fixture['runId'].'_two',
            ];
            $readyFiles = [
                $temporaryDirectory.'/worker-one.ready',
                $temporaryDirectory.'/worker-two.ready',
            ];

            $connection->beginTransaction();
            try {
                $connection->executeQuery(
                    'SELECT id FROM listings WHERE id = CAST(:listingId AS uuid) FOR UPDATE',
                    ['listingId' => $fixture['listingId']],
                );
                $workers[] = $this->startWorker(
                    $fixture['firstBuyerId'],
                    $fixture['sellerId'],
                    $readyFiles[0],
                    $applications[0],
                );
                $workers[] = $this->startWorker(
                    $fixture['secondBuyerId'],
                    $fixture['sellerId'],
                    $readyFiles[1],
                    $applications[1],
                );

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
            sort($outcomes);

            self::assertSame(['succeeded', 'unavailable'], $outcomes);
            self::assertSame(1, (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM orders WHERE buyer_id = CAST(:firstBuyerId AS uuid) OR buyer_id = CAST(:secondBuyerId AS uuid)',
                [
                    'firstBuyerId' => $fixture['firstBuyerId'],
                    'secondBuyerId' => $fixture['secondBuyerId'],
                ],
            ));
            self::assertSame(1, (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM payments WHERE buyer_id = CAST(:firstBuyerId AS uuid) OR buyer_id = CAST(:secondBuyerId AS uuid)',
                [
                    'firstBuyerId' => $fixture['firstBuyerId'],
                    'secondBuyerId' => $fixture['secondBuyerId'],
                ],
            ));
            self::assertSame(1, (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM payment_transactions transaction INNER JOIN payments payment ON payment.id = transaction.payment_id WHERE payment.buyer_id = CAST(:firstBuyerId AS uuid) OR payment.buyer_id = CAST(:secondBuyerId AS uuid)',
                [
                    'firstBuyerId' => $fixture['firstBuyerId'],
                    'secondBuyerId' => $fixture['secondBuyerId'],
                ],
            ));
            self::assertSame(1, (int) $connection->fetchOne(
                "SELECT COUNT(*) FROM listing_reservations WHERE listing_id = CAST(:listingId AS uuid) AND status = 'completed'",
                ['listingId' => $fixture['listingId']],
            ));
            self::assertSame('sold', $connection->fetchOne(
                'SELECT status FROM listings WHERE id = CAST(:listingId AS uuid)',
                ['listingId' => $fixture['listingId']],
            ));
        } finally {
            foreach ($workers as $worker) {
                $this->stopWorker($worker);
            }
            $this->removeTemporaryDirectory($temporaryDirectory);
        }
    }

    /**
     * @return array{firstBuyerId: string, secondBuyerId: string, sellerId: string, listingId: string, runId: string}
     */
    private function createFixture(): array
    {
        $runId = substr(str_replace('-', '', Uuid::v7()->toRfc4122()), 0, 16);
        $firstBuyer = new User('checkout-one-'.$runId.'@example.test', 'hash');
        $secondBuyer = new User('checkout-two-'.$runId.'@example.test', 'hash');
        $seller = new User('checkout-seller-'.$runId.'@example.test', 'hash');
        $series = new CardSeries('Concurrent checkout '.$runId);
        $set = new CardSet($series, 'Concurrent checkout set', 'concurrent-'.$runId, 'en', 1, 1, CardSetStatus::Active);
        $card = new CatalogCard($set, 'Concurrency card', '1/1', 'Rare', 'Collectible');
        $variant = $card->addVariant(VariantCode::Holo);
        $collection = new Collection($seller);
        $item = new CollectionItem($collection, $card, $variant, CardLanguage::English, CardCondition::NearMint);
        $listing = new Listing(
            $seller,
            $item,
            new Money(750, 'EUR'),
            [new ShippingOption(ShippingMethodCode::LetterMail, new Money(150, 'EUR'))],
            [
                new ListingPhotoReference(ListingImageSide::Front, 'https://images.example/concurrent-front.jpg'),
                new ListingPhotoReference(ListingImageSide::Back, 'https://images.example/concurrent-back.jpg'),
            ],
            status: ListingStatus::Active,
        );
        $item->markForSale();
        $firstCart = new ShoppingCart($firstBuyer);
        $firstCart->add($listing);
        $secondCart = new ShoppingCart($secondBuyer);
        $secondCart->add($listing);

        foreach ([$firstBuyer, $secondBuyer, $seller, $series, $set, $card, $collection, $item, $listing, $firstCart, $secondCart] as $entity) {
            $this->entityManager->persist($entity);
        }

        $this->fixture = [
            'firstBuyerId' => $firstBuyer->getId()->toRfc4122(),
            'secondBuyerId' => $secondBuyer->getId()->toRfc4122(),
            'sellerId' => $seller->getId()->toRfc4122(),
            'listingId' => $listing->getId()->toRfc4122(),
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

        self::fail('The concurrent checkout workers did not both start within five seconds.');
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

        self::fail('Both checkout workers must wait for the locked listing row before it is released.');
    }

    /**
     * @return array{process: resource, stdout: resource, stderr: resource}
     */
    private function startWorker(string $buyerId, string $sellerId, string $readyFile, string $applicationName): array
    {
        $environment = getenv();
        $environment['APP_ENV'] = 'test';
        $environment['APP_DEBUG'] = '1';
        $process = proc_open(
            [PHP_BINARY, __DIR__.'/ConcurrentCheckoutWorker.php', $buyerId, $sellerId, $readyFile, $applicationName],
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
            throw new RuntimeException('The concurrent checkout worker could not be started.');
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
            self::fail(sprintf('The concurrent checkout worker failed: %s%s', $stdout, $stderr));
        }

        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($result) || !is_string($result['outcome'] ?? null)) {
            self::fail('The concurrent checkout worker returned an invalid result.');
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
        $directory = sys_get_temp_dir().'/cardnest-checkout-'.Uuid::v7()->toRfc4122();
        if (!mkdir($directory, 0700) && !is_dir($directory)) {
            throw new RuntimeException('The concurrent checkout test directory could not be created.');
        }

        return $directory;
    }

    private function removeTemporaryDirectory(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $file) {
            if (is_file($file) && !unlink($file)) {
                throw new RuntimeException('The concurrent checkout test file could not be removed.');
            }
        }
        if (is_dir($directory) && !rmdir($directory)) {
            throw new RuntimeException('The concurrent checkout test directory could not be removed.');
        }
    }

    private function cleanUpFixture(): void
    {
        $connection = $this->entityManager->getConnection();
        $this->entityManager->clear();
        $connection->executeStatement(
            'DELETE FROM listing_reservations WHERE listing_id = CAST(:listingId AS uuid)',
            ['listingId' => $this->fixture['listingId']],
        );
        $connection->executeStatement(
            'DELETE FROM orders WHERE buyer_id = CAST(:firstBuyerId AS uuid) OR buyer_id = CAST(:secondBuyerId AS uuid)',
            [
                'firstBuyerId' => $this->fixture['firstBuyerId'],
                'secondBuyerId' => $this->fixture['secondBuyerId'],
            ],
        );
        $connection->executeStatement(
            'DELETE FROM payments WHERE buyer_id = CAST(:firstBuyerId AS uuid) OR buyer_id = CAST(:secondBuyerId AS uuid)',
            [
                'firstBuyerId' => $this->fixture['firstBuyerId'],
                'secondBuyerId' => $this->fixture['secondBuyerId'],
            ],
        );
        $connection->executeStatement(
            'DELETE FROM shopping_carts WHERE buyer_id = CAST(:firstBuyerId AS uuid) OR buyer_id = CAST(:secondBuyerId AS uuid)',
            [
                'firstBuyerId' => $this->fixture['firstBuyerId'],
                'secondBuyerId' => $this->fixture['secondBuyerId'],
            ],
        );
        $connection->executeStatement('DELETE FROM listings WHERE id = CAST(:listingId AS uuid)', ['listingId' => $this->fixture['listingId']]);
        $connection->executeStatement('DELETE FROM collections WHERE owner_id = CAST(:sellerId AS uuid)', ['sellerId' => $this->fixture['sellerId']]);
        $connection->executeStatement('DELETE FROM catalog_cards WHERE id = CAST(:cardId AS uuid)', ['cardId' => $this->fixture['cardId']]);
        $connection->executeStatement('DELETE FROM card_sets WHERE id = CAST(:setId AS uuid)', ['setId' => $this->fixture['setId']]);
        $connection->executeStatement('DELETE FROM card_series WHERE id = CAST(:seriesId AS uuid)', ['seriesId' => $this->fixture['seriesId']]);
        $connection->executeStatement(
            'DELETE FROM users WHERE id = CAST(:firstBuyerId AS uuid) OR id = CAST(:secondBuyerId AS uuid) OR id = CAST(:sellerId AS uuid)',
            [
                'firstBuyerId' => $this->fixture['firstBuyerId'],
                'secondBuyerId' => $this->fixture['secondBuyerId'],
                'sellerId' => $this->fixture['sellerId'],
            ],
        );
    }
}
