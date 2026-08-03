<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Provider;

use App\Catalog\Domain\Entity\VariantCode;
use App\Catalog\Infrastructure\Provider\CatalogProviderException;
use App\Catalog\Infrastructure\Provider\TcgDexCardCatalogProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TcgDexCardCatalogProviderTest extends TestCase
{
    public function testItNormalizesASetAndItsCardsWithoutDownloadingImages(): void
    {
        // Arrange
        $client = new MockHttpClient(static function (string $method, string $url): MockResponse {
            $payloads = [
                '/en/sets' => [['id' => 'base1', 'name' => 'Base Set']],
                '/en/sets/base1' => [
                    'id' => 'base1',
                    'name' => 'Base Set',
                    'releaseDate' => '1999-01-09',
                    'serie' => ['id' => 'base', 'name' => 'Base'],
                    'logo' => 'https://assets.example.test/base/logo',
                    'cardCount' => ['official' => 102, 'total' => 102],
                    'cards' => [['id' => 'base1-4', 'localId' => '4', 'name' => 'Charizard']],
                ],
                '/en/cards/base1-4' => [
                    'id' => 'base1-4',
                    'name' => 'Charizard',
                    'localId' => '4',
                    'category' => 'Pokemon',
                    'rarity' => 'Rare',
                    'illustrator' => 'Mitsuhiro Arita',
                    'image' => 'https://assets.example.test/base/4',
                    'set' => ['id' => 'base1', 'cardCount' => ['official' => 102, 'total' => 102]],
                    'variants' => [
                        'normal' => false,
                        'holo' => true,
                        'reverse' => false,
                        'firstEdition' => true,
                        'wPromo' => false,
                    ],
                    'variants_detailed' => [[
                        'type' => 'holo',
                        'subtype' => 'shadowless',
                        'stamp' => ['1st-edition'],
                    ]],
                    'dexId' => [6],
                    'pricing' => ['cardmarket' => ['avg' => 446.70]],
                ],
            ];
            $path = (string) parse_url($url, PHP_URL_PATH);

            return new MockResponse(
                json_encode($payloads[$path] ?? ['message' => 'not found'], JSON_THROW_ON_ERROR),
                ['http_code' => isset($payloads[$path]) ? 200 : 404],
            );
        });
        $provider = new TcgDexCardCatalogProvider($client, 'https://api.example.test');

        // Act
        $sets = iterator_to_array($provider->importSets('en'));
        $cards = iterator_to_array($provider->importCards($sets[0], includeCardDetails: true));

        // Assert
        self::assertCount(1, $sets);
        self::assertSame('base1', $sets[0]->externalId);
        self::assertSame(['base1-4'], $sets[0]->cardExternalIds);
        self::assertSame('1999-01-09', $sets[0]->releaseDate?->format('Y-m-d'));

        self::assertCount(1, $cards);
        self::assertSame('4/102', $cards[0]->number);
        self::assertSame('Charizard', $cards[0]->primaryCollectible);
        self::assertSame('https://assets.example.test/base/4/high.webp', $cards[0]->imageUrl);
        self::assertSame('https://assets.example.test/base/4/low.webp', $cards[0]->thumbnailUrl);
        self::assertSame('https://assets.example.test/base/logo.webp', $sets[0]->logoUrl);
        self::assertSame(['holo', 'first_edition', 'shadowless'], array_map(
            static fn (VariantCode $variant): string => $variant->value,
            $cards[0]->variants,
        ));
        self::assertSame('external_url_only', $cards[0]->metadata['imagePolicy']);
        self::assertSame(['cardmarket' => ['avg' => 446.70]], $cards[0]->rawData['pricing']);
        self::assertArrayNotHasKey('pricing', $cards[0]->metadata);

        $summaryCards = iterator_to_array($provider->importCards($sets[0]));
        self::assertSame('4/102', $summaryCards[0]->number);
        self::assertSame('Unknown', $summaryCards[0]->rarity);
        self::assertSame(['normal'], array_map(
            static fn (VariantCode $variant): string => $variant->value,
            $summaryCards[0]->variants,
        ));
        self::assertSame('summary', $summaryCards[0]->metadata['sourceDetailLevel']);
    }

    public function testItReturnsNullForASourceCardThatDoesNotExist(): void
    {
        // Arrange
        $client = new MockHttpClient(new MockResponse('{"message":"not found"}', ['http_code' => 404]));
        $provider = new TcgDexCardCatalogProvider($client, 'https://api.example.test');

        // Act
        $card = $provider->findCard('en', 'unknown-card');

        // Assert
        self::assertNull($card);
    }

    public function testItImportsAndDeduplicatesArtistCardMappings(): void
    {
        // Arrange
        $client = new MockHttpClient(static function (string $method, string $url): MockResponse {
            $payloads = [
                '/en/illustrators' => ['  Mitsuhiro   Arita ', '0313', 'mitsuhiro arita'],
                '/en/illustrators/Mitsuhiro%20Arita' => [
                    'name' => 'Mitsuhiro Arita',
                    'cards' => [
                        ['id' => 'base1-4', 'name' => 'Charizard'],
                        ['id' => 'base1-4', 'name' => 'Charizard'],
                    ],
                ],
                '/en/illustrators/0313' => [
                    'name' => '0313',
                    'cards' => [['id' => 'sv01-1', 'name' => 'Pikachu']],
                ],
            ];
            $path = (string) parse_url($url, PHP_URL_PATH);

            return new MockResponse(
                json_encode($payloads[$path] ?? ['message' => 'not found'], JSON_THROW_ON_ERROR),
                ['http_code' => isset($payloads[$path]) ? 200 : 404],
            );
        });
        $provider = new TcgDexCardCatalogProvider(
            $client,
            'https://api.example.test',
            artistRequestConcurrency: 2,
        );

        // Act
        $artists = iterator_to_array($provider->importArtistCardLinks('en'));

        // Assert
        self::assertCount(2, $artists);
        self::assertSame('Mitsuhiro Arita', $artists[0]->canonicalName);
        self::assertSame(['base1-4'], $artists[0]->cardExternalIds);
        self::assertSame('0313', $artists[1]->canonicalName);
        self::assertSame(['sv01-1'], $artists[1]->cardExternalIds);
        self::assertSame(3, $client->getRequestsCount());
    }

    public function testTargetedArtistImportSkipsTheSourceIndex(): void
    {
        // Arrange
        $client = new MockHttpClient(static function (string $method, string $url): MockResponse {
            $path = (string) parse_url($url, PHP_URL_PATH);
            self::assertSame('/ja/illustrators/Yuka%20Morii', $path);

            return new MockResponse(json_encode([
                'name' => 'Yuka Morii',
                'cards' => [['id' => 'base1-7', 'name' => 'Squirtle']],
            ], JSON_THROW_ON_ERROR));
        });
        $provider = new TcgDexCardCatalogProvider($client, 'https://api.example.test');

        // Act
        $artists = iterator_to_array($provider->importArtistCardLinks('ja', ['Yuka Morii']));

        // Assert
        self::assertCount(1, $artists);
        self::assertSame('ja', $artists[0]->language);
        self::assertSame(['base1-7'], $artists[0]->cardExternalIds);
        self::assertSame(1, $client->getRequestsCount());
    }

    public function testItRejectsAFailedArtistSourceResponse(): void
    {
        // Arrange
        $client = new MockHttpClient(new MockResponse('{"message":"unavailable"}', ['http_code' => 503]));
        $provider = new TcgDexCardCatalogProvider($client, 'https://api.example.test');
        $this->expectException(CatalogProviderException::class);
        $this->expectExceptionMessage('HTTP status 503');

        // Act
        iterator_to_array($provider->importArtistCardLinks('en'));

        // Assert
        // The expected provider exception leaves completed database batches retryable.
    }
}
