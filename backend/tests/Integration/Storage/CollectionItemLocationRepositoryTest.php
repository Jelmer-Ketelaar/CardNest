<?php

declare(strict_types=1);

namespace App\Tests\Integration\Storage;

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
use App\Storage\Domain\Entity\StorageLocation;
use App\Storage\Domain\Entity\StorageLocationType;
use App\Storage\Domain\Repository\CollectionItemLocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class CollectionItemLocationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CollectionItemLocationRepository $repository;
    private User $owner;
    private Collection $collection;
    private CollectionItem $item;
    private StorageLocation $firstLocation;
    private StorageLocation $secondLocation;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(CollectionItemLocationRepository::class);
        $this->owner = new User('owner-'.Uuid::v7()->toRfc4122().'@example.com', 'hash');
        $this->collection = new Collection($this->owner);
        $this->item = $this->createItem($this->collection);
        $this->firstLocation = new StorageLocation($this->owner, 'Binderkast', StorageLocationType::Other);
        $this->secondLocation = new StorageLocation(
            $this->owner,
            'Top-loaderbox',
            StorageLocationType::TopLoaderBox,
            $this->firstLocation,
        );
        $this->entityManager->persist($this->owner);
        $this->entityManager->persist($this->collection);
        $this->entityManager->persist($this->firstLocation);
        $this->entityManager->persist($this->secondLocation);
        $this->entityManager->flush();
    }

    public function testMovingAnItemClosesThePreviousLocationAndKeepsHistory(): void
    {
        // Arrange
        $first = $this->repository->place($this->item, $this->firstLocation);

        // Act
        $current = $this->repository->place($this->item, $this->secondLocation);
        $history = $this->repository->historyForItem($this->item);

        // Assert
        self::assertFalse($first->isCurrent());
        self::assertTrue($current->isCurrent());
        self::assertCount(2, $history);
        $currentLocation = $history[0]->getStorageLocation();
        self::assertNotNull($currentLocation);
        self::assertSame($this->secondLocation->getId()->toRfc4122(), $currentLocation->getId()->toRfc4122());
        self::assertSame(1, $this->repository->currentItemCounts($this->owner)[$this->secondLocation->getId()->toRfc4122()] ?? null);
    }

    public function testPlacingInTheCurrentLocationIsIdempotentAndRemovalKeepsHistory(): void
    {
        // Arrange
        $first = $this->repository->place($this->item, $this->firstLocation);

        // Act
        $same = $this->repository->place($this->item, $this->firstLocation);
        $this->repository->removeCurrent($this->item);

        // Assert
        self::assertSame($first->getId()->toRfc4122(), $same->getId()->toRfc4122());
        self::assertNull($this->repository->currentForItem($this->item));
        self::assertCount(1, $this->repository->historyForItem($this->item));
    }

    private function createItem(Collection $collection): CollectionItem
    {
        $suffix = Uuid::v7()->toRfc4122();
        $series = new CardSeries('Series '.$suffix);
        $set = new CardSet(
            series: $series,
            name: 'Set '.$suffix,
            code: 'set-'.$suffix,
            language: 'en',
            printedTotal: 25,
            total: 25,
            status: CardSetStatus::Active,
        );
        $card = new CatalogCard($set, 'Mudkip', '19/25', 'Promo', 'Collectible');
        $variant = $card->addVariant(VariantCode::Holo);
        $item = new CollectionItem($collection, $card, $variant, CardLanguage::English, CardCondition::NearMint);
        $this->entityManager->persist($series);
        $this->entityManager->persist($set);
        $this->entityManager->persist($card);
        $this->entityManager->persist($item);

        return $item;
    }
}
