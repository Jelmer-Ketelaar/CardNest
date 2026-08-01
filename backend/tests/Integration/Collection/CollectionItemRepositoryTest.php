<?php

declare(strict_types=1);

namespace App\Tests\Integration\Collection;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CardVariant;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Entity\Collection;
use App\Collection\Domain\Entity\CollectionItem;
use App\Collection\Domain\Repository\CollectionItemRepository;
use App\Collection\Domain\Repository\CollectionItemSearchCriteria;
use App\Identity\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class CollectionItemRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CollectionItemRepository $repository;
    private User $owner;
    private Collection $collection;
    private CardSet $set;
    private CatalogCard $card;
    private CardVariant $normalVariant;
    private CardVariant $holoVariant;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(CollectionItemRepository::class);
        $this->owner = new User('owner-'.Uuid::v7()->toRfc4122().'@example.com', 'hash');
        $this->collection = new Collection($this->owner);
        [$this->set, $this->card, $this->normalVariant, $this->holoVariant] = $this->createCatalog();
        $this->entityManager->persist($this->owner);
        $this->entityManager->persist($this->collection);
    }

    public function testSearchFiltersOwnerCardSetLanguageVariantConditionAndStatus(): void
    {
        // Arrange
        $matching = $this->item($this->collection, $this->normalVariant, CardLanguage::English, CardCondition::NearMint);
        $this->item($this->collection, $this->holoVariant, CardLanguage::Japanese, CardCondition::Mint);
        $otherOwner = new User('other-'.Uuid::v7()->toRfc4122().'@example.com', 'hash');
        $otherCollection = new Collection($otherOwner);
        $this->entityManager->persist($otherOwner);
        $this->entityManager->persist($otherCollection);
        $this->item($otherCollection, $this->normalVariant, CardLanguage::English, CardCondition::NearMint);
        $this->entityManager->flush();

        // Act
        $result = $this->repository->search($this->owner, new CollectionItemSearchCriteria(
            cardId: $this->card->getId()->toRfc4122(),
            setId: $this->set->getId()->toRfc4122(),
            cardName: 'mud',
            cardNumber: '19',
            language: 'en',
            variant: 'normal',
            condition: 'near_mint',
            status: 'in_collection',
        ));

        // Assert
        self::assertSame(1, $result->total);
        self::assertSame($matching->getId()->toRfc4122(), $result->items[0]->getId()->toRfc4122());
    }

    public function testDuplicateCountsAreOwnerScopedAndExcludeArchivedItems(): void
    {
        // Arrange
        $first = $this->item($this->collection, $this->normalVariant, CardLanguage::English, CardCondition::NearMint);
        $this->item($this->collection, $this->normalVariant, CardLanguage::English, CardCondition::Good);
        $archived = $this->item(
            $this->collection,
            $this->normalVariant,
            CardLanguage::English,
            CardCondition::Played,
        );
        $archived->archive();
        $this->entityManager->flush();

        // Act
        $counts = $this->repository->duplicateCounts($this->owner);

        // Assert
        self::assertSame(2, $counts[$first->duplicateKey()] ?? null);
    }

    /** @return array{CardSet, CatalogCard, CardVariant, CardVariant} */
    private function createCatalog(): array
    {
        $suffix = Uuid::v7()->toRfc4122();
        $series = new CardSeries('Series '.$suffix);
        $set = new CardSet(
            series: $series,
            name: "McDonald's Collection 2021",
            code: 'mcd-'.$suffix,
            language: 'en',
            printedTotal: 25,
            total: 25,
            status: CardSetStatus::Active,
        );
        $card = new CatalogCard($set, 'Mudkip', '19/25', 'Promo', 'Collectible');
        $normal = $card->addVariant(VariantCode::Normal);
        $holo = $card->addVariant(VariantCode::Holo);
        $this->entityManager->persist($series);
        $this->entityManager->persist($set);
        $this->entityManager->persist($card);

        return [$set, $card, $normal, $holo];
    }

    private function item(
        Collection $collection,
        CardVariant $variant,
        CardLanguage $language,
        CardCondition $condition,
    ): CollectionItem {
        $item = new CollectionItem($collection, $this->card, $variant, $language, $condition);
        $this->entityManager->persist($item);

        return $item;
    }
}
