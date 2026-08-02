<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Domain\Entity\Artist;
use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class ArtistApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private Artist $artist;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $suffix = Uuid::v7()->toRfc4122();
        $series = new CardSeries('Artist series '.$suffix);
        $set = new CardSet(
            series: $series,
            name: 'Artist Set',
            code: 'artist-set-'.$suffix,
            language: 'en',
            printedTotal: 2,
            total: 2,
            status: CardSetStatus::Active,
        );
        $this->artist = Artist::fromCanonicalName('Mitsuhiro Arita '.$suffix);
        $otherArtist = Artist::fromCanonicalName('Ken Sugimori '.$suffix);
        $card = new CatalogCard($set, 'Pikachu', '1/2', 'Rare', 'Pokémon');
        $card->addVariant(VariantCode::Normal);
        $card->addArtist($this->artist);

        $entityManager->persist($series);
        $entityManager->persist($set);
        $entityManager->persist($this->artist);
        $entityManager->persist($otherArtist);
        $entityManager->persist($card);
        $entityManager->flush();
    }

    public function testVisitorCanListSearchAndSortArtists(): void
    {
        // Arrange
        $query = http_build_query([
            'q' => 'Mitsuhiro',
            'sort' => 'card_count',
            'withCardsOnly' => true,
            'perPage' => 10,
        ]);

        // Act
        $this->client->request('GET', '/api/v1/artists?'.$query);

        // Assert
        self::assertResponseIsSuccessful();
        $response = $this->responseData();
        self::assertSame(1, $response['total'] ?? null);
        self::assertSame($this->artist->getId()->toRfc4122(), $response['items'][0]['id'] ?? null);
        self::assertSame(1, $response['items'][0]['cardCount'] ?? null);
    }

    public function testVisitorCanUseSuggestionsAndOpenArtistCards(): void
    {
        // Arrange
        $artistId = $this->artist->getId()->toRfc4122();

        // Act
        $this->client->request('GET', '/api/v1/artists/suggestions?q=Mitsu&limit=10');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame($artistId, $this->responseData()['items'][0]['id'] ?? null);

        // Act
        $this->client->request('GET', '/api/v1/artists/slug/'.$this->artist->getSlug());

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame($artistId, $this->responseData()['id'] ?? null);

        // Act
        $this->client->request('GET', '/api/v1/artists/'.$artistId.'/cards');

        // Assert
        self::assertResponseIsSuccessful();
        $cards = $this->responseData();
        self::assertSame(1, $cards['total'] ?? null);
        self::assertSame('Pikachu', $cards['items'][0]['name'] ?? null);
    }

    public function testSuggestionRequiresAtLeastTwoCharacters(): void
    {
        // Act
        $this->client->request('GET', '/api/v1/artists/suggestions?q=M');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->responseData()['items'] ?? null);
    }

    /** @return array<string, mixed> */
    private function responseData(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
