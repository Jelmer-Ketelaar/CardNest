<?php

declare(strict_types=1);

namespace App\Tests\Integration\Storage;

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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class StorageApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private string $ownerToken;
    private string $otherToken;
    private CollectionItem $item;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        [$owner, $this->ownerToken] = $this->createAuthenticatedUser('owner');
        [, $this->otherToken] = $this->createAuthenticatedUser('other');
        $this->item = $this->createItem($owner);
        $this->entityManager->flush();
    }

    public function testOwnerCanCreateBrowseAndReorganizeAStorageHierarchy(): void
    {
        // Arrange
        $rootId = $this->createLocation('Collectiekamer', 'other', null, $this->ownerToken);

        // Act
        $childId = $this->createLocation('Kast links', 'other', $rootId, $this->ownerToken);
        $this->authorizedRequest('GET', '/api/v1/me/storage-locations', $this->ownerToken);

        // Assert
        self::assertResponseIsSuccessful();
        $list = $this->responseData();
        self::assertSame(2, $list['total'] ?? null);
        $child = $this->findItem($list['items'] ?? [], $childId);
        self::assertSame(['Collectiekamer', 'Kast links'], array_column($child['path'] ?? [], 'name'));

        // Act
        $this->jsonRequest(
            'PATCH',
            '/api/v1/me/storage-locations/'.$childId,
            ['name' => 'Kast rechts', 'clearParent' => true],
            $this->ownerToken,
        );

        // Assert
        self::assertResponseIsSuccessful();
        $updated = $this->responseData();
        self::assertSame('Kast rechts', $updated['name'] ?? null);
        self::assertNull($updated['parentId'] ?? null);

        // Arrange
        $this->jsonRequest(
            'PATCH',
            '/api/v1/me/storage-locations/'.$childId,
            ['parentId' => $rootId],
            $this->ownerToken,
        );
        self::assertResponseIsSuccessful();

        // Act
        $this->jsonRequest(
            'PATCH',
            '/api/v1/me/storage-locations/'.$rootId,
            ['parentId' => $childId],
            $this->ownerToken,
        );

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_storage_hierarchy', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/storage-locations/'.$rootId, $this->otherToken);

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('storage_location_not_found', $this->responseData()['error']['code'] ?? null);
    }

    public function testOwnerCanMoveLocateAndUnplaceACollectionItemWithHistory(): void
    {
        // Arrange
        $firstId = $this->createLocation('Binderkast', 'other', null, $this->ownerToken);
        $secondId = $this->createLocation('Top-loaderbox', 'top_loader_box', $firstId, $this->ownerToken);
        $itemId = $this->item->getId()->toRfc4122();

        // Act
        $this->placeItem($itemId, $firstId, $this->ownerToken);
        $this->placeItem($itemId, $secondId, $this->ownerToken);
        $this->authorizedRequest(
            'GET',
            '/api/v1/me/collection-items/'.$itemId.'/location-history',
            $this->ownerToken,
        );

        // Assert
        self::assertResponseIsSuccessful();
        $location = $this->responseData();
        self::assertSame($secondId, $location['current']['location']['id'] ?? null);
        self::assertCount(2, $location['history'] ?? []);
        self::assertNotNull($location['history'][1]['removedAt'] ?? null);

        // Act
        $this->authorizedRequest(
            'DELETE',
            '/api/v1/me/collection-items/'.$itemId.'/location',
            $this->ownerToken,
        );

        // Assert
        self::assertResponseStatusCodeSame(204);

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/collection-items/'.$itemId.'/location', $this->ownerToken);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertNull($this->responseData()['current'] ?? null);
    }

    public function testStoragePlacementCannotCrossOwnershipBoundariesOrMoveArchivedItems(): void
    {
        // Arrange
        $ownerLocation = $this->createLocation('Own box', 'box', null, $this->ownerToken);
        $otherLocation = $this->createLocation('Other box', 'box', null, $this->otherToken);
        $itemId = $this->item->getId()->toRfc4122();

        // Act
        $this->placeItem($itemId, $ownerLocation, $this->otherToken);

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('collection_item_not_found', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->placeItem($itemId, $otherLocation, $this->ownerToken);

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('storage_location_not_found', $this->responseData()['error']['code'] ?? null);

        // Arrange
        $this->placeItem($itemId, $ownerLocation, $this->ownerToken);
        self::assertResponseIsSuccessful();
        $this->authorizedRequest('DELETE', '/api/v1/me/collection-items/'.$itemId, $this->ownerToken);
        self::assertResponseStatusCodeSame(204);

        // Act
        $this->authorizedRequest(
            'GET',
            '/api/v1/me/collection-items/'.$itemId.'/location-history',
            $this->ownerToken,
        );

        // Assert
        self::assertResponseIsSuccessful();
        $archivedLocation = $this->responseData();
        self::assertNull($archivedLocation['current'] ?? null);
        self::assertNotNull($archivedLocation['history'][0]['removedAt'] ?? null);

        // Act
        $this->placeItem($itemId, $ownerLocation, $this->ownerToken);

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('collection_item_archived', $this->responseData()['error']['code'] ?? null);
    }

    private function createLocation(string $name, string $type, ?string $parentId, string $token): string
    {
        $payload = ['name' => $name, 'type' => $type];
        if ($parentId !== null) {
            $payload['parentId'] = $parentId;
        }
        $this->jsonRequest('POST', '/api/v1/me/storage-locations', $payload, $token);
        self::assertResponseStatusCodeSame(201);
        $id = $this->responseData()['id'] ?? null;
        self::assertIsString($id);

        return $id;
    }

    private function placeItem(string $itemId, string $locationId, string $token): void
    {
        $this->jsonRequest(
            'POST',
            '/api/v1/me/collection-items/'.$itemId.'/location',
            ['locationId' => $locationId],
            $token,
        );
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

    private function createItem(User $owner): CollectionItem
    {
        $suffix = Uuid::v7()->toRfc4122();
        $series = new CardSeries('Series '.$suffix);
        $set = new CardSet(
            series: $series,
            name: 'Set '.$suffix,
            code: 'set-'.$suffix,
            language: 'en',
            printedTotal: 25,
            total: 25,
            status: CardSetStatus::Active,
        );
        $card = new CatalogCard($set, 'Mudkip', '19/25', 'Promo', 'Collectible');
        $variant = $card->addVariant(VariantCode::Holo);
        $item = new CollectionItem($owner, $card, $variant, CardLanguage::English, CardCondition::NearMint);
        $this->entityManager->persist($series);
        $this->entityManager->persist($set);
        $this->entityManager->persist($card);
        $this->entityManager->persist($item);

        return $item;
    }

    /** @param array<int, mixed> $items
     * @return array<string, mixed>
     */
    private function findItem(array $items, string $id): array
    {
        foreach ($items as $item) {
            if (is_array($item) && ($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        self::fail('Expected item was not present in the response.');
    }

    /** @param array<string, mixed> $payload */
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
