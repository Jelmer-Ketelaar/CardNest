<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CatalogApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private CardSet $set;
    private CatalogCard $card;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->set = $this->createSet();
        $this->card = $this->createCard($this->set, 'Mudkip', '19/25', 'Promo');
        $this->createCard($this->set, 'Pikachu', '25/25', 'Promo');
        $this->entityManager->flush();
    }

    public function testVisitorCanListAndRetrieveCardSets(): void
    {
        // Arrange
        $setId = $this->set->getId()->toRfc4122();

        // Act
        $this->client->request('GET', '/api/v1/card-sets?perPage=1');

        // Assert
        self::assertResponseIsSuccessful();
        $list = $this->responseData();
        self::assertSame(1, $list['total'] ?? null);
        self::assertCount(1, $list['items'] ?? []);

        // Act
        $this->client->request('GET', '/api/v1/card-sets/'.$setId);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame($setId, $this->responseData()['id'] ?? null);
    }

    public function testVisitorCanSearchAndRetrieveCatalogCards(): void
    {
        // Arrange
        $query = http_build_query([
            'setId' => $this->set->getId()->toRfc4122(),
            'name' => 'mud',
            'number' => '19',
            'rarity' => 'promo',
            'page' => 1,
            'perPage' => 10,
        ]);

        // Act
        $this->client->request('GET', '/api/v1/cards?'.$query);

        // Assert
        self::assertResponseIsSuccessful();
        $search = $this->responseData();
        self::assertSame(1, $search['total'] ?? null);
        self::assertSame('Mudkip', $search['items'][0]['name'] ?? null);

        // Act
        $this->client->request('GET', '/api/v1/cards/'.$this->card->getId()->toRfc4122());

        // Assert
        self::assertResponseIsSuccessful();
        $card = $this->responseData();
        self::assertSame('normal', $card['variants'][0]['code'] ?? null);
        self::assertSame(['source' => 'test'], $card['metadata'] ?? null);
        self::assertSame('provider-card-19', $card['externalReferences'][0]['externalId'] ?? null);
    }

    public function testInvalidPaginationReturnsStructuredValidationError(): void
    {
        // Arrange
        $uri = '/api/v1/cards?page=0&perPage=101';

        // Act
        $this->client->request('GET', $uri);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $this->responseData()['error']['code'] ?? null);
    }

    public function testUnknownCardReturnsStructuredNotFoundError(): void
    {
        // Arrange
        $unknownId = Uuid::v7()->toRfc4122();

        // Act
        $this->client->request('GET', '/api/v1/cards/'.$unknownId);

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('request_failed', $this->responseData()['error']['code'] ?? null);
    }

    private function createSet(): CardSet
    {
        $suffix = Uuid::v7()->toRfc4122();
        $series = new CardSeries('Series '.$suffix);
        $set = new CardSet(
            series: $series,
            name: "McDonald's Collection 2021",
            code: 'mcd21-'.$suffix,
            language: 'en',
            printedTotal: 25,
            total: 25,
            status: CardSetStatus::Active,
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
            metadata: ['source' => 'test'],
        );
        $card->addVariant(VariantCode::Normal);
        if ($name === 'Mudkip') {
            $card->addExternalReference('test-provider', 'provider-card-19', ['source' => 'test']);
        }
        $this->entityManager->persist($card);

        return $card;
    }

    /** @return array<string, mixed> */
    private function responseData(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
