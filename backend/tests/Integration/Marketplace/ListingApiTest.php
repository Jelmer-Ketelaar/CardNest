<?php

declare(strict_types=1);

namespace App\Tests\Integration\Marketplace;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Entity\CollectionItem;
use App\Identity\Domain\Entity\AuthToken;
use App\Identity\Domain\Entity\User;
use App\Marketplace\Application\Message\ExpireListing;
use App\Marketplace\Application\Message\ExpireListingHandler;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

final class ListingApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private string $sellerToken;
    private string $otherToken;
    private CollectionItem $item;
    private CollectionItem $secondItem;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        [$seller, $this->sellerToken] = $this->createAuthenticatedUser('seller');
        [, $this->otherToken] = $this->createAuthenticatedUser('other');
        [, $this->item, $this->secondItem] = $this->createCatalogAndItems($seller);
        $this->entityManager->flush();
    }

    public function testSellerCanPublishAndVisitorsCanSearchACompletePrice(): void
    {
        // Arrange
        $listingId = $this->createDraft($this->item);

        // Act
        $this->publicRequest('GET', '/api/v1/listings/'.$listingId);

        // Assert
        self::assertResponseStatusCodeSame(404);

        // Act
        $this->authorizedRequest('POST', '/api/v1/listings/'.$listingId.'/publish', $this->sellerToken);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame('active', $this->responseData()['status'] ?? null);

        // Act
        $query = http_build_query([
            'cardName' => 'mud',
            'cardNumber' => '19',
            'language' => 'en',
            'variant' => 'holo',
            'condition' => 'near_mint',
            'maximumPrice' => 750,
            'pickupAllowed' => '1',
        ]);
        $this->publicRequest('GET', '/api/v1/listings?'.$query);

        // Assert
        self::assertResponseIsSuccessful();
        $search = $this->responseData();
        self::assertSame(1, $search['total'] ?? null);
        self::assertSame('Mudkip', $search['items'][0]['card']['name'] ?? null);
        self::assertSame(750, $search['items'][0]['price']['amount'] ?? null);
        self::assertCount(2, $search['items'][0]['images'] ?? []);
        self::assertSame([900, 750], array_column($search['items'][0]['shippingMethods'] ?? [], 'totalPrice', 'code') !== []
            ? array_map(
                static fn (array $method): int => (int) $method['totalPrice']['amount'],
                $search['items'][0]['shippingMethods'],
            )
            : []);
        self::assertArrayNotHasKey('collectionItemId', $search['items'][0]);

        // Act
        $this->authorizedRequest(
            'GET',
            '/api/v1/me/collection-items/'.$this->item->getId()->toRfc4122(),
            $this->sellerToken,
        );

        // Assert
        self::assertResponseIsSuccessful();
        $collectionItem = $this->responseData();
        self::assertSame('for_sale', $collectionItem['status'] ?? null);
        self::assertSame(2, $collectionItem['duplicate']['ownedQuantity'] ?? null);
    }

    public function testOnlySellerCanChangeListingAndPriceChangesAreLogged(): void
    {
        // Arrange
        $listingId = $this->createDraft($this->item);
        $this->authorizedRequest('POST', '/api/v1/listings/'.$listingId.'/publish', $this->sellerToken);
        self::assertResponseIsSuccessful();

        // Act
        $this->jsonRequest(
            'PATCH',
            '/api/v1/listings/'.$listingId,
            ['price' => 900, 'currency' => 'EUR'],
            $this->otherToken,
        );

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('listing_not_found', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->jsonRequest(
            'PATCH',
            '/api/v1/listings/'.$listingId,
            [
                'price' => 900,
                'currency' => 'EUR',
                'frontImageUrl' => 'https://images.example/new-front.jpg',
                'backImageUrl' => 'https://images.example/new-back.jpg',
            ],
            $this->sellerToken,
        );

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame(900, $this->responseData()['price']['amount'] ?? null);
        self::assertCount(2, $this->responseData()['priceHistory'] ?? []);
        $imagesBySide = array_column($this->responseData()['images'] ?? [], 'url', 'side');
        self::assertSame('https://images.example/new-front.jpg', $imagesBySide['front'] ?? null);
        self::assertSame('https://images.example/new-back.jpg', $imagesBySide['back'] ?? null);
        self::assertContains(750, array_column($this->responseData()['priceHistory'] ?? [], 'newPrice', 'changedAt') !== []
            ? array_map(static fn (array $entry): int => (int) $entry['newPrice']['amount'], $this->responseData()['priceHistory'])
            : []);

        // Act
        $this->jsonRequest(
            'PATCH',
            '/api/v1/me/collection-items/'.$this->item->getId()->toRfc4122(),
            ['condition' => 'good'],
            $this->sellerToken,
        );

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('collection_item_listed', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->authorizedRequest('DELETE', '/api/v1/me/collection-items/'.$this->item->getId()->toRfc4122(), $this->sellerToken);

        // Assert
        self::assertResponseStatusCodeSame(409);

        // Act
        $this->authorizedRequest('POST', '/api/v1/listings/'.$listingId.'/cancel', $this->sellerToken);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame('cancelled', $this->responseData()['status'] ?? null);
        $this->publicRequest('GET', '/api/v1/listings/'.$listingId);
        self::assertResponseStatusCodeSame(404);

        // Act
        $this->jsonRequest(
            'PATCH',
            '/api/v1/me/collection-items/'.$this->item->getId()->toRfc4122(),
            ['condition' => 'good'],
            $this->sellerToken,
        );

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame('good', $this->responseData()['condition'] ?? null);
    }

    public function testPhysicalCardCannotHaveTwoActiveListings(): void
    {
        // Arrange
        $firstListingId = $this->createDraft($this->item);
        $secondListingId = $this->createDraft($this->item);
        $this->authorizedRequest('POST', '/api/v1/listings/'.$firstListingId.'/publish', $this->sellerToken);
        self::assertResponseIsSuccessful();

        // Act
        $this->authorizedRequest('POST', '/api/v1/listings/'.$secondListingId.'/publish', $this->sellerToken);

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('listing_already_exists', $this->responseData()['error']['code'] ?? null);
    }

    public function testSellerListingOverviewIncludesDraftsAndPrivateCollectionReference(): void
    {
        // Arrange
        $listingId = $this->createDraft($this->secondItem);

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/listings', $this->sellerToken);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->responseData()['total'] ?? null);
        self::assertSame('draft', $this->responseData()['items'][0]['status'] ?? null);
        self::assertSame(
            $this->secondItem->getId()->toRfc4122(),
            $this->responseData()['items'][0]['collectionItemId'] ?? null,
        );

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/listings/'.$listingId, $this->otherToken);

        // Assert
        self::assertResponseStatusCodeSame(404);
    }

    public function testCreateValidatesShippingPhotosAndExpiration(): void
    {
        // Arrange
        $payload = $this->validPayload($this->secondItem);
        $payload['letterMailPrice'] = null;
        $payload['pickupAllowed'] = false;

        // Act
        $this->jsonRequest('POST', '/api/v1/listings', $payload, $this->sellerToken);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $this->responseData()['error']['code'] ?? null);

        // Arrange
        $payload = $this->validPayload($this->secondItem);
        $payload['frontImageUrl'] = 'http://unsafe.example/front.jpg';

        // Act
        $this->jsonRequest('POST', '/api/v1/listings', $payload, $this->sellerToken);

        // Assert
        self::assertResponseStatusCodeSame(422);

        // Arrange
        $payload = $this->validPayload($this->secondItem);
        $payload['expiresAt'] = '2020-01-01T00:00:00+00:00';

        // Act
        $this->jsonRequest('POST', '/api/v1/listings', $payload, $this->sellerToken);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_listing', $this->responseData()['error']['code'] ?? null);
    }

    public function testPublishedListingIsScheduledAndExpirationReleasesPhysicalCard(): void
    {
        // Arrange
        $itemId = $this->item->getId()->toRfc4122();
        $payload = $this->validPayload($this->item);
        $payload['expiresAt'] = new DateTimeImmutable('+1 hour')->format(DATE_ATOM);
        $this->jsonRequest('POST', '/api/v1/listings', $payload, $this->sellerToken);
        self::assertResponseStatusCodeSame(201);
        $listingId = $this->responseData()['id'] ?? null;
        self::assertIsString($listingId);

        // Act
        $this->authorizedRequest('POST', '/api/v1/listings/'.$listingId.'/publish', $this->sellerToken);

        // Assert
        self::assertResponseIsSuccessful();
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $scheduled = array_values(array_filter(
            $transport->getSent(),
            static fn ($envelope): bool => $envelope->getMessage() instanceof ExpireListing,
        ));
        self::assertCount(1, $scheduled);
        $delay = $scheduled[0]->last(DelayStamp::class);
        self::assertInstanceOf(DelayStamp::class, $delay);
        self::assertGreaterThan(0, $delay->getDelay());

        // Arrange
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement(
            <<<'SQL'
                UPDATE listings
                SET created_at = NOW() - INTERVAL '2 hours',
                    expires_at = NOW() - INTERVAL '1 minute'
                WHERE id = :id
                SQL,
            ['id' => $listingId],
        );
        $entityManager->clear();

        // Act
        $handler = self::getContainer()->get(ExpireListingHandler::class);
        self::assertInstanceOf(ExpireListingHandler::class, $handler);
        $handler(new ExpireListing($listingId));
        $handler(new ExpireListing($listingId));

        // Assert
        $this->authorizedRequest('GET', '/api/v1/me/listings/'.$listingId, $this->sellerToken);
        self::assertResponseIsSuccessful();
        self::assertSame('expired', $this->responseData()['status'] ?? null);
        $this->authorizedRequest('GET', '/api/v1/me/collection-items/'.$itemId, $this->sellerToken);
        self::assertResponseIsSuccessful();
        self::assertSame('in_collection', $this->responseData()['status'] ?? null);
    }

    private function createDraft(CollectionItem $item): string
    {
        $this->jsonRequest('POST', '/api/v1/listings', $this->validPayload($item), $this->sellerToken);
        self::assertResponseStatusCodeSame(201);
        $id = $this->responseData()['id'] ?? null;
        self::assertIsString($id);
        self::assertSame('draft', $this->responseData()['status'] ?? null);

        return $id;
    }

    /** @return array<string, bool|int|string|null> */
    private function validPayload(CollectionItem $item): array
    {
        return [
            'collectionItemId' => $item->getId()->toRfc4122(),
            'price' => 750,
            'currency' => 'EUR',
            'description' => 'Clean copy from my collection.',
            'letterMailPrice' => 150,
            'parcelPrice' => null,
            'pickupAllowed' => true,
            'frontImageUrl' => 'https://images.example/front.jpg',
            'backImageUrl' => 'https://images.example/back.jpg',
        ];
    }

    /** @return array{User, string} */
    private function createAuthenticatedUser(string $prefix): array
    {
        $suffix = Uuid::v7()->toRfc4122();
        $user = new User($prefix.'-'.$suffix.'@example.com', 'hash');
        $plainToken = bin2hex(random_bytes(32));
        $this->entityManager->persist($user);
        $this->entityManager->persist(AuthToken::issue($user, $plainToken));

        return [$user, $plainToken];
    }

    /** @return array{CatalogCard, CollectionItem, CollectionItem} */
    private function createCatalogAndItems(User $seller): array
    {
        $suffix = Uuid::v7()->toRfc4122();
        $series = new CardSeries('Series '.$suffix);
        $set = new CardSet($series, 'Promo set', 'promo-'.$suffix, 'en', 25, 25, status: CardSetStatus::Active);
        $card = new CatalogCard($set, 'Mudkip', '19/25', 'Promo', 'Collectible');
        $variant = $card->addVariant(VariantCode::Holo);
        $first = new CollectionItem($seller, $card, $variant, CardLanguage::English, CardCondition::NearMint);
        $second = new CollectionItem($seller, $card, $variant, CardLanguage::English, CardCondition::Good);
        $this->entityManager->persist($series);
        $this->entityManager->persist($set);
        $this->entityManager->persist($card);
        $this->entityManager->persist($first);
        $this->entityManager->persist($second);

        return [$card, $first, $second];
    }

    /** @param array<string, bool|int|string|null> $payload */
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

    private function publicRequest(string $method, string $uri): void
    {
        $this->client->request($method, $uri, server: ['HTTP_ACCEPT' => 'application/json']);
    }

    /** @return array<string, mixed> */
    private function responseData(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
