<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Provider;

use App\Catalog\Domain\Entity\VariantCode;
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
}
