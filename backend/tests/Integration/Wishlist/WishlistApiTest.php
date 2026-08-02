<?php

declare(strict_types=1);

namespace App\Tests\Integration\Wishlist;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CardVariant;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use App\Collection\Domain\Entity\CardLanguage;
use App\Identity\Domain\Entity\AuthToken;
use App\Identity\Domain\Entity\User;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class WishlistApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private string $ownerToken;
    private string $otherToken;
    private CatalogCard $mudkip;
    private CardVariant $mudkipNormal;
    private CardVariant $mudkipHolo;
    private CardVariant $pikachuNormal;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        [, $this->ownerToken] = $this->createAuthenticatedUser('owner');
        [, $this->otherToken] = $this->createAuthenticatedUser('other');
        [$this->mudkip, $this->mudkipNormal, $this->mudkipHolo, $this->pikachuNormal] = $this->createCatalog();
        $this->entityManager->flush();
    }

    public function testOwnerCanCreateListRenameAndDeleteAWishlistPrivately(): void
    {
        // Arrange & Act
        $this->jsonRequest('POST', '/api/v1/me/wishlists', ['name' => 'Mudkip cards'], $this->ownerToken);

        // Assert
        self::assertResponseStatusCodeSame(201);
        $wishlistId = $this->responseData()['id'] ?? null;
        self::assertIsString($wishlistId);
        self::assertSame(0, $this->responseData()['progress']['desiredQuantity'] ?? null);

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/wishlists', $this->ownerToken);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->responseData()['total'] ?? null);

        // Act
        $this->jsonRequest(
            'PATCH',
            '/api/v1/me/wishlists/'.$wishlistId,
            ['name' => 'Water master set'],
            $this->ownerToken,
        );

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame('Water master set', $this->responseData()['name'] ?? null);

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/wishlists/'.$wishlistId, $this->otherToken);

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('wishlist_not_found', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->authorizedRequest('DELETE', '/api/v1/me/wishlists/'.$wishlistId, $this->ownerToken);

        // Assert
        self::assertResponseStatusCodeSame(204);
        $this->authorizedRequest('GET', '/api/v1/me/wishlists/'.$wishlistId, $this->ownerToken);
        self::assertResponseStatusCodeSame(404);
    }

    #[DataProvider('cardLanguages')]
    public function testEveryCardLanguagePersistsAndRoundTripsThroughTheApi(CardLanguage $language): void
    {
        // Arrange
        $wishlistId = $this->createWishlist();

        // Act
        $this->addWishlistItem($wishlistId, [
            'catalogCardId' => $this->mudkip->getId()->toRfc4122(),
            'language' => $language->value,
        ]);

        // Assert
        self::assertResponseStatusCodeSame(201);
        self::assertSame($language->value, $this->responseData()['items'][0]['language'] ?? null);

        // Act
        $this->entityManager->clear();
        $this->authorizedRequest('GET', '/api/v1/me/wishlists/'.$wishlistId, $this->ownerToken);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame($language->value, $this->responseData()['items'][0]['language'] ?? null);
    }

    public function testAddingAnItemRejectsUnknownLanguage(): void
    {
        // Arrange
        $wishlistId = $this->createWishlist();

        // Act
        $this->addWishlistItem($wishlistId, [
            'catalogCardId' => $this->mudkip->getId()->toRfc4122(),
            'language' => 'xx',
        ]);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $this->responseData()['error']['code'] ?? null);
    }

    public function testDatabaseRejectsUnknownWishlistLanguage(): void
    {
        // Arrange
        $wishlistId = $this->createWishlist();
        $this->addWishlistItem($wishlistId, [
            'catalogCardId' => $this->mudkip->getId()->toRfc4122(),
            'language' => CardLanguage::English->value,
        ]);
        self::assertResponseStatusCodeSame(201);
        $itemId = $this->responseData()['items'][0]['id'] ?? null;
        self::assertIsString($itemId);
        $this->expectException(DriverException::class);

        // Act
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE wishlist_items SET desired_language = :language WHERE id = :id',
            ['language' => 'xx', 'id' => $itemId],
        );
    }

    public function testAddingAnItemCountsMatchingCardsAlreadyInTheCollection(): void
    {
        // Arrange
        $this->createCollectionItems(1, 'holo', 'en', 'near_mint', 350);
        $wishlistId = $this->createWishlist();

        // Act
        $this->addWishlistItem($wishlistId, [
            'catalogCardId' => $this->mudkip->getId()->toRfc4122(),
            'variantId' => $this->mudkipHolo->getId()->toRfc4122(),
            'language' => 'en',
            'minimumCondition' => 'excellent',
            'maxPrice' => 500,
            'maxCurrency' => 'EUR',
            'desiredQuantity' => 2,
            'priority' => 'wanted',
            'notes' => 'Clean copies only',
        ]);

        // Assert
        self::assertResponseStatusCodeSame(201);
        $wishlist = $this->responseData();
        self::assertSame(1, $wishlist['items'][0]['fulfilledQuantity'] ?? null);
        self::assertSame(1, $wishlist['items'][0]['remainingQuantity'] ?? null);
        self::assertSame(50, $wishlist['progress']['percentage'] ?? null);
        self::assertSame(1, $wishlist['setProgress'][0]['progress']['fulfilledQuantity'] ?? null);
        self::assertSame('wanted', $wishlist['items'][0]['priority'] ?? null);

        // Arrange
        $payload = [
            'catalogCardId' => $this->mudkip->getId()->toRfc4122(),
            'variantId' => $this->pikachuNormal->getId()->toRfc4122(),
        ];

        // Act
        $this->addWishlistItem($wishlistId, $payload);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('card_variant_unavailable', $this->responseData()['error']['code'] ?? null);
    }

    public function testNewPhysicalCardsAutomaticallyFulfillOnlyMatchingWishlistItems(): void
    {
        // Arrange
        $wishlistId = $this->createWishlist();
        $this->addWishlistItem($wishlistId, [
            'catalogCardId' => $this->mudkip->getId()->toRfc4122(),
            'variantId' => $this->mudkipHolo->getId()->toRfc4122(),
            'language' => 'en',
            'minimumCondition' => 'excellent',
            'maxPrice' => 500,
            'maxCurrency' => 'EUR',
            'desiredQuantity' => 2,
            'priority' => 'high',
        ]);
        self::assertResponseStatusCodeSame(201);
        $this->addWishlistItem($wishlistId, [
            'catalogCardId' => $this->mudkip->getId()->toRfc4122(),
            'variantId' => $this->mudkipHolo->getId()->toRfc4122(),
            'language' => 'en',
            'minimumCondition' => 'excellent',
            'maxPrice' => 200,
            'maxCurrency' => 'EUR',
            'desiredQuantity' => 1,
            'priority' => 'normal',
        ]);
        self::assertResponseStatusCodeSame(201);

        // Act
        $this->createCollectionItems(3, 'holo', 'en', 'near_mint', 350);

        // Assert
        self::assertResponseStatusCodeSame(201);
        $this->authorizedRequest('GET', '/api/v1/me/wishlists/'.$wishlistId, $this->ownerToken);
        self::assertResponseIsSuccessful();
        $items = $this->responseData()['items'] ?? [];
        self::assertCount(2, $items);
        $itemsByMaxPrice = [];
        foreach ($items as $item) {
            self::assertIsArray($item);
            $maxPrice = $item['maxPrice']['amount'] ?? null;
            self::assertIsInt($maxPrice);
            $itemsByMaxPrice[$maxPrice] = $item;
        }

        self::assertSame(2, $itemsByMaxPrice[500]['fulfilledQuantity'] ?? null);
        self::assertTrue($itemsByMaxPrice[500]['completed'] ?? false);
        self::assertNotNull($itemsByMaxPrice[500]['completedAt'] ?? null);
        self::assertSame(0, $itemsByMaxPrice[200]['fulfilledQuantity'] ?? null);
        self::assertFalse($itemsByMaxPrice[200]['completed'] ?? true);
        self::assertSame(67, $this->responseData()['progress']['percentage'] ?? null);
    }

    public function testWishlistItemDeletionIsOwnerScopedAndMoneyPairIsValidated(): void
    {
        // Arrange
        $wishlistId = $this->createWishlist();
        $this->addWishlistItem($wishlistId, [
            'catalogCardId' => $this->mudkip->getId()->toRfc4122(),
            'desiredQuantity' => 1,
        ]);
        self::assertResponseStatusCodeSame(201);
        $itemId = $this->responseData()['items'][0]['id'] ?? null;
        self::assertIsString($itemId);

        // Act
        $this->authorizedRequest(
            'DELETE',
            '/api/v1/me/wishlists/'.$wishlistId.'/items/'.$itemId,
            $this->otherToken,
        );

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('wishlist_item_not_found', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->authorizedRequest(
            'DELETE',
            '/api/v1/me/wishlists/'.$wishlistId.'/items/'.$itemId,
            $this->ownerToken,
        );

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->responseData()['itemCount'] ?? null);

        // Act
        $this->addWishlistItem($wishlistId, [
            'catalogCardId' => $this->mudkip->getId()->toRfc4122(),
            'maxPrice' => 500,
        ]);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $this->responseData()['error']['code'] ?? null);
    }

    public function testOwnerCanUpdateWishlistItemPreferencesAndRecalculateFulfillment(): void
    {
        // Arrange
        $this->createCollectionItems(2, 'holo', 'en', 'near_mint', 350);
        $wishlistId = $this->createWishlist();
        $this->addWishlistItem($wishlistId, [
            'catalogCardId' => $this->mudkip->getId()->toRfc4122(),
            'language' => 'en',
            'variantId' => $this->mudkipHolo->getId()->toRfc4122(),
            'minimumCondition' => 'excellent',
            'maxPrice' => 200,
            'maxCurrency' => 'EUR',
            'desiredQuantity' => 3,
            'priority' => 'low',
            'notes' => 'Initial criteria',
        ]);
        self::assertResponseStatusCodeSame(201);
        $itemId = $this->responseData()['items'][0]['id'] ?? null;
        self::assertIsString($itemId);

        // Act
        $this->jsonRequest('PATCH', '/api/v1/me/wishlists/'.$wishlistId.'/items/'.$itemId, [
            'maxPrice' => 500,
            'maxCurrency' => 'EUR',
            'desiredQuantity' => 2,
            'priority' => 'wanted',
            'notes' => '  Clean holo copies  ',
        ], $this->ownerToken);

        // Assert
        self::assertResponseIsSuccessful();
        $updated = $this->responseData()['items'][0] ?? null;
        self::assertIsArray($updated);
        self::assertSame(500, $updated['maxPrice']['amount'] ?? null);
        self::assertSame(2, $updated['desiredQuantity'] ?? null);
        self::assertSame(2, $updated['fulfilledQuantity'] ?? null);
        self::assertTrue($updated['completed'] ?? false);
        self::assertSame('wanted', $updated['priority'] ?? null);
        self::assertSame('Clean holo copies', $updated['notes'] ?? null);

        // Act
        $this->jsonRequest('PATCH', '/api/v1/me/wishlists/'.$wishlistId.'/items/'.$itemId, [
            'clearLanguage' => true,
            'clearVariant' => true,
            'clearMinimumCondition' => true,
            'clearMaxPrice' => true,
            'clearNotes' => true,
            'desiredQuantity' => 3,
        ], $this->ownerToken);

        // Assert
        self::assertResponseIsSuccessful();
        $cleared = $this->responseData()['items'][0] ?? null;
        self::assertIsArray($cleared);
        self::assertNull($cleared['language'] ?? null);
        self::assertNull($cleared['variant'] ?? null);
        self::assertNull($cleared['minimumCondition'] ?? null);
        self::assertNull($cleared['maxPrice'] ?? null);
        self::assertNull($cleared['notes'] ?? null);
        self::assertSame(3, $cleared['desiredQuantity'] ?? null);
        self::assertSame(2, $cleared['fulfilledQuantity'] ?? null);
        self::assertSame(1, $cleared['remainingQuantity'] ?? null);
        self::assertFalse($cleared['completed'] ?? true);

        // Act
        $this->jsonRequest(
            'PATCH',
            '/api/v1/me/wishlists/'.$wishlistId.'/items/'.$itemId,
            ['desiredQuantity' => 1],
            $this->otherToken,
        );

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('wishlist_item_not_found', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->jsonRequest('PATCH', '/api/v1/me/wishlists/'.$wishlistId.'/items/'.$itemId, [
            'variantId' => $this->pikachuNormal->getId()->toRfc4122(),
        ], $this->ownerToken);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('card_variant_unavailable', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->jsonRequest('PATCH', '/api/v1/me/wishlists/'.$wishlistId.'/items/'.$itemId, [
            'clearMaxPrice' => true,
            'maxPrice' => 500,
            'maxCurrency' => 'EUR',
        ], $this->ownerToken);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $this->responseData()['error']['code'] ?? null);
    }

    /** @return iterable<string, array{CardLanguage}> */
    public static function cardLanguages(): iterable
    {
        foreach (CardLanguage::cases() as $language) {
            yield $language->name => [$language];
        }
    }

    private function createWishlist(): string
    {
        $this->jsonRequest('POST', '/api/v1/me/wishlists', ['name' => 'Master set'], $this->ownerToken);
        self::assertResponseStatusCodeSame(201);
        $id = $this->responseData()['id'] ?? null;
        self::assertIsString($id);

        return $id;
    }

    /** @param array<string, bool|int|string> $payload */
    private function addWishlistItem(string $wishlistId, array $payload): void
    {
        $this->jsonRequest('POST', '/api/v1/me/wishlists/'.$wishlistId.'/items', $payload, $this->ownerToken);
    }

    private function createCollectionItems(
        int $quantity,
        string $variant,
        string $language,
        string $condition,
        int $price,
    ): void {
        $variantId = $variant === 'holo' ? $this->mudkipHolo->getId() : $this->mudkipNormal->getId();
        $this->jsonRequest('POST', '/api/v1/me/collection-items', [
            'catalogCardId' => $this->mudkip->getId()->toRfc4122(),
            'variantId' => $variantId->toRfc4122(),
            'language' => $language,
            'condition' => $condition,
            'quantity' => $quantity,
            'purchasePrice' => $price,
            'purchaseCurrency' => 'EUR',
        ], $this->ownerToken);
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

    /** @return array{CatalogCard, CardVariant, CardVariant, CardVariant} */
    private function createCatalog(): array
    {
        $suffix = Uuid::v7()->toRfc4122();
        $series = new CardSeries('Series '.$suffix);
        $set = new CardSet(
            $series,
            'McDonald Collection 2021',
            'mcd-'.$suffix,
            'en',
            25,
            25,
            status: CardSetStatus::Active,
        );
        $mudkip = new CatalogCard($set, 'Mudkip', '19/25', 'Promo', 'Collectible');
        $normal = $mudkip->addVariant(VariantCode::Normal);
        $holo = $mudkip->addVariant(VariantCode::Holo);
        $pikachu = new CatalogCard($set, 'Pikachu', '25/25', 'Promo', 'Collectible');
        $pikachuNormal = $pikachu->addVariant(VariantCode::Normal);
        $this->entityManager->persist($series);
        $this->entityManager->persist($set);
        $this->entityManager->persist($mudkip);
        $this->entityManager->persist($pikachu);

        return [$mudkip, $normal, $holo, $pikachuNormal];
    }

    /** @param array<string, bool|int|string> $payload */
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
