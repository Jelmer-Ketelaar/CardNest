<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use App\Catalog\Domain\Repository\CardSetReleaseFilter;
use App\Catalog\Domain\Repository\CardSetRepository;
use App\Catalog\Domain\Repository\CardSetSearchCriteria;
use App\Catalog\Domain\Repository\CatalogCardRepository;
use App\Catalog\Domain\Repository\CatalogCardSearchCriteria;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class CatalogRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CardSetRepository $cardSets;
    private CatalogCardRepository $cards;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->cardSets = $container->get(CardSetRepository::class);
        $this->cards = $container->get(CatalogCardRepository::class);
    }

    public function testSearchAppliesAllCatalogCardFilters(): void
    {
        // Arrange
        $set = $this->createSet();
        $mudkip = $this->createCard($set, 'Mudkip', '19/25', 'Promo');
        $this->createCard($set, 'Pikachu', '25/25', 'Promo');
        $otherSet = $this->createSet('Other Set');
        $this->createCard($otherSet, 'Mudkip', '1/10', 'Common');
        $this->entityManager->flush();

        // Act
        $result = $this->cards->search(new CatalogCardSearchCriteria(
            setId: $set->getId()->toRfc4122(),
            name: 'mud',
            number: '19',
            rarity: 'promo',
        ));

        // Assert
        self::assertSame(1, $result->total);
        self::assertSame($mudkip->getId()->toRfc4122(), $result->items[0]->getId()->toRfc4122());
    }

    public function testSearchComposesSeriesAndVariantFilters(): void
    {
        // Arrange
        $series = new CardSeries('Target series');
        $set = $this->createSet('Target Set', $series);
        $matchingCard = $this->createCard($set, 'Mudkip', '19/25', 'Promo');
        $matchingCard->addVariant(VariantCode::Holo);
        $this->createCard($set, 'Mudkip', '20/25', 'Promo');

        $otherSet = $this->createSet('Other Set');
        $otherSeriesCard = $this->createCard($otherSet, 'Mudkip', '19/25', 'Promo');
        $otherSeriesCard->addVariant(VariantCode::Holo);
        $this->entityManager->flush();

        // Act
        $result = $this->cards->search(new CatalogCardSearchCriteria(
            seriesId: $series->getId()->toRfc4122(),
            name: 'mud',
            variant: VariantCode::Holo,
            page: 1,
            perPage: 1,
        ));

        // Assert
        self::assertSame(1, $result->total);
        self::assertCount(1, $result->items);
        self::assertSame($matchingCard->getId()->toRfc4122(), $result->items[0]->getId()->toRfc4122());
    }

    public function testSearchPaginatesCardsInStableOrder(): void
    {
        // Arrange
        $set = $this->createSet();
        $this->createCard($set, 'Mudkip', '10/25', 'Promo');
        $this->createCard($set, 'Mudkip', '2/25', 'Promo');
        $this->entityManager->flush();

        // Act
        $result = $this->cards->search(new CatalogCardSearchCriteria(page: 1, perPage: 1));

        // Assert
        self::assertSame(2, $result->total);
        self::assertCount(1, $result->items);
        self::assertSame('2/25', $result->items[0]->getNumber());
    }

    public function testSearchUsesNaturalCardNumberOrderForOneSet(): void
    {
        // Arrange
        $set = $this->createSet();
        $this->createCard($set, 'Zubat', '10/151', 'Common');
        $this->createCard($set, 'Abra', '2/151', 'Common');
        $this->createCard($set, 'Bulbasaur', '1/151', 'Common');
        $this->entityManager->flush();

        // Act
        $result = $this->cards->search(new CatalogCardSearchCriteria(
            setId: $set->getId()->toRfc4122(),
            perPage: 10,
        ));

        // Assert
        self::assertSame(['1/151', '2/151', '10/151'], array_map(
            static fn (CatalogCard $card): string => $card->getNumber(),
            $result->items,
        ));
    }

    public function testCardSetRepositoryListsAndFindsSets(): void
    {
        // Arrange
        $set = $this->createSet();
        $this->createSet('Second Set');
        $this->entityManager->flush();

        // Act
        $result = $this->cardSets->list(new CardSetSearchCriteria(page: 1, perPage: 1));
        $found = $this->cardSets->get($set->getId()->toRfc4122());

        // Assert
        self::assertSame(2, $result->total);
        self::assertCount(1, $result->items);
        self::assertSame($set->getId()->toRfc4122(), $found?->getId()->toRfc4122());
    }

    public function testCardSetRepositoryFiltersUpcomingSetsByNameAndLanguage(): void
    {
        // Arrange
        $matchingSet = $this->createSet('Future English Set', language: 'en', releaseDate: new DateTimeImmutable('2100-01-02'));
        $this->createSet('Future French Set', language: 'fr', releaseDate: new DateTimeImmutable('2100-01-01'));
        $this->createSet('Released English Set', language: 'en', releaseDate: new DateTimeImmutable('2020-01-01'));
        $this->entityManager->flush();

        // Act
        $result = $this->cardSets->list(new CardSetSearchCriteria(
            name: 'future',
            language: 'en',
            release: CardSetReleaseFilter::Upcoming,
        ));

        // Assert
        self::assertSame(1, $result->total);
        self::assertSame($matchingSet->getId()->toRfc4122(), $result->items[0]->getId()->toRfc4122());
    }

    private function createSet(
        string $name = "McDonald's Collection 2021",
        ?CardSeries $series = null,
        string $language = 'en',
        ?DateTimeImmutable $releaseDate = null,
    ): CardSet {
        $suffix = Uuid::v7()->toRfc4122();
        $series ??= new CardSeries('Series '.$suffix);
        $set = new CardSet(
            series: $series,
            name: $name,
            code: 'set-'.$suffix,
            language: $language,
            printedTotal: 25,
            total: 25,
            status: CardSetStatus::Active,
            releaseDate: $releaseDate,
        );
        $this->entityManager->persist($series);
        $this->entityManager->persist($set);

        return $set;
    }

    private function createCard(CardSet $set, string $name, string $number, string $rarity): CatalogCard
    {
        $card = new CatalogCard(
            set: $set,
            name: $name,
            number: $number,
            rarity: $rarity,
            cardType: 'Collectible',
        );
        $card->addVariant(VariantCode::Normal);
        $this->entityManager->persist($card);

        return $card;
    }
}
