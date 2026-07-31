<?php

declare(strict_types=1);

namespace App\Tests\Integration\Collection;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CardVariant;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use App\Identity\Domain\Entity\AuthToken;
use App\Identity\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CollectionItemApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private string $ownerToken;
    private string $otherUserToken;
    private CardSet $set;
    private CatalogCard $card;
    private CardVariant $normalVariant;
    private CardVariant $holoVariant;
    private CardVariant $otherCardVariant;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        [, $this->ownerToken] = $this->createAuthenticatedUser('owner');
        [, $this->otherUserToken] = $this->createAuthenticatedUser('other');
        [$this->set, $this->card, $this->normalVariant, $this->holoVariant, $this->otherCardVariant]
            = $this->createCatalog();
        $this->entityManager->flush();
    }

    public function testOwnerCanCreateSeparatePhysicalItemsAndFilterThem(): void
    {
        // Arrange
        $payload = [
            'catalogCardId' => $this->card->getId()->toRfc4122(),
            'variantId' => $this->holoVariant->getId()->toRfc4122(),
            'language' => 'en',
            'condition' => 'near_mint',
            'quantity' => 3,
            'purchasePrice' => 350,
            'purchaseCurrency' => 'EUR',
            'purchasedAt' => '2026-07-20',
            'sellerName' => 'Card shop',
            'notes' => 'Clean copies',
        ];

        // Act
        $this->jsonRequest('POST', '/api/v1/me/collection-items', $payload, $this->ownerToken);

        // Assert
        self::assertResponseStatusCodeSame(201);
        $created = $this->responseData();
        self::assertSame(3, $created['created'] ?? null);
        self::assertCount(3, $created['items'] ?? []);
        $ids = array_column($created['items'], 'id');
        self::assertCount(3, array_unique($ids));
        self::assertSame(3, $created['items'][0]['duplicate']['ownedQuantity'] ?? null);
        self::assertTrue($created['items'][0]['duplicate']['isDuplicate'] ?? false);
        self::assertSame(350, $created['items'][0]['purchase']['price']['amount'] ?? null);

        // Arrange
        $query = http_build_query([
            'cardId' => $this->card->getId()->toRfc4122(),
            'setId' => $this->set->getId()->toRfc4122(),
            'cardName' => 'mud',
            'cardNumber' => '19',
            'language' => 'en',
            'variant' => 'holo',
            'condition' => 'near_mint',
            'status' => 'in_collection',
        ]);

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/collection-items?'.$query, $this->ownerToken);

        // Assert
        self::assertResponseIsSuccessful();
        $list = $this->responseData();
        self::assertSame(3, $list['total'] ?? null);
        self::assertCount(3, $list['items'] ?? []);
    }

    public function testOwnerCanUpdateAndArchiveAnItem(): void
    {
        // Arrange
        $itemId = $this->createOwnedItem();

        // Act
        $this->jsonRequest('PATCH', '/api/v1/me/collection-items/'.$itemId, [
            'language' => 'ja',
            'condition' => 'excellent',
            'variantId' => $this->holoVariant->getId()->toRfc4122(),
            'purchasePrice' => 725,
            'purchaseCurrency' => 'EUR',
            'purchasedAt' => '2026-07-25',
            'sellerName' => 'Collector',
            'notes' => 'Updated note',
        ], $this->ownerToken);

        // Assert
        self::assertResponseIsSuccessful();
        $updated = $this->responseData();
        self::assertSame('ja', $updated['language'] ?? null);
        self::assertSame('holo', $updated['variant']['code'] ?? null);
        self::assertSame(725, $updated['purchase']['price']['amount'] ?? null);

        // Act
        $this->authorizedRequest('DELETE', '/api/v1/me/collection-items/'.$itemId, $this->ownerToken);

        // Assert
        self::assertResponseStatusCodeSame(204);

        // Act
        $this->jsonRequest(
            'PATCH',
            '/api/v1/me/collection-items/'.$itemId,
            ['condition' => 'mint'],
            $this->ownerToken,
        );

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('collection_item_archived', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/collection-items', $this->ownerToken);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->responseData()['total'] ?? null);

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/collection-items?status=archived', $this->ownerToken);

        // Assert
        self::assertResponseIsSuccessful();
        $archived = $this->responseData();
        self::assertSame(1, $archived['total'] ?? null);
        self::assertSame('archived', $archived['items'][0]['status'] ?? null);
    }

    public function testOtherUserCannotReadUpdateOrArchivePrivateItem(): void
    {
        // Arrange
        $itemId = $this->createOwnedItem();

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/collection-items/'.$itemId, $this->otherUserToken);

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('collection_item_not_found', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->jsonRequest(
            'PATCH',
            '/api/v1/me/collection-items/'.$itemId,
            ['condition' => 'poor'],
            $this->otherUserToken,
        );

        // Assert
        self::assertResponseStatusCodeSame(404);

        // Act
        $this->authorizedRequest('DELETE', '/api/v1/me/collection-items/'.$itemId, $this->otherUserToken);

        // Assert
        self::assertResponseStatusCodeSame(404);

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/collection-items/'.$itemId, $this->ownerToken);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame('in_collection', $this->responseData()['status'] ?? null);
    }

    public function testCreateRejectsVariantFromAnotherCardAndIncompleteMoney(): void
    {
        // Arrange
        $payload = $this->validCreatePayload();
        $payload['variantId'] = $this->otherCardVariant->getId()->toRfc4122();

        // Act
        $this->jsonRequest('POST', '/api/v1/me/collection-items', $payload, $this->ownerToken);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('card_variant_unavailable', $this->responseData()['error']['code'] ?? null);

        // Arrange
        $payload = $this->validCreatePayload();
        $payload['purchasePrice'] = 350;

        // Act
        $this->jsonRequest('POST', '/api/v1/me/collection-items', $payload, $this->ownerToken);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $this->responseData()['error']['code'] ?? null);
    }

    private function createOwnedItem(): string
    {
        $this->jsonRequest('POST', '/api/v1/me/collection-items', $this->validCreatePayload(), $this->ownerToken);
        self::assertResponseStatusCodeSame(201);
        $id = $this->responseData()['items'][0]['id'] ?? null;
        self::assertIsString($id);

        return $id;
    }

    /** @return array<string, int|string> */
    private function validCreatePayload(): array
    {
        return [
            'catalogCardId' => $this->card->getId()->toRfc4122(),
            'variantId' => $this->normalVariant->getId()->toRfc4122(),
            'language' => 'en',
            'condition' => 'near_mint',
            'quantity' => 1,
        ];
    }

    /** @return array{User, string} */
    private function createAuthenticatedUser(string $prefix): array
    {
        $suffix = Uuid::v7()->toRfc4122();
        $user = new User($prefix.'-'.$suffix.'@example.com', 'hash');
        $plainToken = bin2hex(random_bytes(32));
        $token = AuthToken::issue($user, $plainToken);
        $this->entityManager->persist($user);
        $this->entityManager->persist($token);

        return [$user, $plainToken];
    }

    /** @return array{CardSet, CatalogCard, CardVariant, CardVariant, CardVariant} */
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
        $otherCard = new CatalogCard($set, 'Pikachu', '25/25', 'Promo', 'Collectible');
        $otherVariant = $otherCard->addVariant(VariantCode::Normal);
        $this->entityManager->persist($series);
        $this->entityManager->persist($set);
        $this->entityManager->persist($card);
        $this->entityManager->persist($otherCard);

        return [$set, $card, $normal, $holo, $otherVariant];
    }

    /** @param array<string, int|string> $payload */
    private function jsonRequest(string $method, string $uri, array $payload, string $token): void
    {
        $this->client->request(
            $method,
            $uri,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    private function authorizedRequest(string $method, string $uri, string $token): void
    {
        $this->client->request($method, $uri, server: [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /** @return array<string, mixed> */
    private function responseData(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
