<?php

declare(strict_types=1);

namespace App\Tests\Integration\Pricing;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Pricing\Domain\Entity\PriceSnapshot;
use App\Pricing\Domain\Entity\PriceVariant;
use App\Pricing\Domain\Repository\PriceSnapshotRepository;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CardPriceApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private CatalogCard $card;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->card = $this->createCatalogCard();
        $this->createHistory();
        $this->entityManager->flush();
    }

    public function testVisitorCanReadCurrentPricesAndDailyChangeHistory(): void
    {
        // Arrange
        $cardId = $this->card->getId()->toRfc4122();

        // Act
        $this->client->request('GET', '/api/v1/cards/'.$cardId.'/prices?days=30');

        // Assert
        self::assertResponseIsSuccessful();
        $response = $this->responseData();
        self::assertSame('current', $response['refreshStatus'] ?? null);
        self::assertSame('cardmarket', $response['market'] ?? null);
        self::assertSame(15, $response['prices'][0]['trend']['amount'] ?? null);
        self::assertSame(3, $response['prices'][0]['change']['amount'] ?? null);
        self::assertSame(25, $response['prices'][0]['change']['percentage'] ?? null);
        self::assertCount(2, $response['history'] ?? []);
    }

    public function testPriceHistoryValidatesRangeAndUnknownCard(): void
    {
        // Act
        $this->client->request('GET', '/api/v1/cards/'.$this->card->getId()->toRfc4122().'/prices?days=1');

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->client->request('GET', '/api/v1/cards/'.Uuid::v7()->toRfc4122().'/prices');

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('request_failed', $this->responseData()['error']['code'] ?? null);
    }

    public function testDailySnapshotCanBeForceReplacedAfterDoctrineHydration(): void
    {
        // Arrange
        $cardId = $this->card->getId();
        $timezone = new DateTimeZone('Europe/Amsterdam');
        $today = new DateTimeImmutable('today', $timezone);
        $this->entityManager->clear();
        $card = $this->entityManager->find(CatalogCard::class, $cardId);
        self::assertInstanceOf(CatalogCard::class, $card);
        $repository = self::getContainer()->get(PriceSnapshotRepository::class);
        self::assertInstanceOf(PriceSnapshotRepository::class, $repository);

        // Act
        $repository->replaceDaily(
            $card,
            'tcgdex',
            'cardmarket',
            $today,
            [$this->snapshotForCard($card, $today, 18)],
        );

        // Assert
        $history = $repository->history($card, 'tcgdex', 'cardmarket', $today->modify('-1 day'));
        self::assertCount(2, $history);
        self::assertSame(18, $history[0]->getTrend()?->amount);
    }

    private function createCatalogCard(): CatalogCard
    {
        $suffix = Uuid::v7()->toRfc4122();
        $series = new CardSeries('Sword & Shield '.$suffix);
        $set = new CardSet(
            series: $series,
            name: 'Darkness Ablaze',
            code: 'swsh3-'.$suffix,
            language: 'en',
            printedTotal: 189,
            total: 201,
            status: CardSetStatus::Active,
        );
        $card = new CatalogCard($set, 'Furret', '136/189', 'Uncommon', 'Pokemon');
        $this->entityManager->persist($series);
        $this->entityManager->persist($set);
        $this->entityManager->persist($card);

        return $card;
    }

    private function createHistory(): void
    {
        $timezone = new DateTimeZone('Europe/Amsterdam');
        $today = new DateTimeImmutable('today', $timezone);
        $yesterday = $today->modify('-1 day');
        $this->entityManager->persist($this->snapshot($yesterday, 12));
        $this->entityManager->persist($this->snapshot($today, 15));
    }

    private function snapshot(DateTimeImmutable $day, int $trend): PriceSnapshot
    {
        return $this->snapshotForCard($this->card, $day, $trend);
    }

    private function snapshotForCard(CatalogCard $card, DateTimeImmutable $day, int $trend): PriceSnapshot
    {
        return new PriceSnapshot(
            catalogCard: $card,
            source: 'tcgdex',
            market: 'cardmarket',
            variant: PriceVariant::Standard,
            currency: 'EUR',
            lowest: new Money(2, 'EUR'),
            average: new Money(11, 'EUR'),
            trend: new Money($trend, 'EUR'),
            averageOneDay: new Money(5, 'EUR'),
            averageSevenDays: new Money(13, 'EUR'),
            averageThirtyDays: new Money(11, 'EUR'),
            sourceUpdatedAt: $day->setTime(8, 3),
            observedAt: $day->setTime(12, 5),
            observedOn: $day,
        );
    }

    /** @return array<string, mixed> */
    private function responseData(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
