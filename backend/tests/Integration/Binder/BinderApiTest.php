<?php

declare(strict_types=1);

namespace App\Tests\Integration\Binder;

use App\Binder\Domain\Entity\BinderSlot;
use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Entity\Collection;
use App\Collection\Domain\Entity\CollectionItem;
use App\Identity\Domain\Entity\AuthToken;
use App\Identity\Domain\Entity\User;
use App\Storage\Domain\Entity\CollectionItemLocationHistory;
use App\Storage\Domain\Entity\StorageLocation;
use App\Storage\Domain\Entity\StorageLocationType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class BinderApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private string $ownerToken;
    private string $otherToken;
    private StorageLocation $parentLocation;
    private CatalogCard $mudkipCard;
    private CollectionItem $mudkipItem;
    private CollectionItem $secondMudkipItem;
    private CollectionItem $pikachuItem;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        [$owner, $this->ownerToken] = $this->createAuthenticatedUser('owner');
        [, $this->otherToken] = $this->createAuthenticatedUser('other');
        [$this->mudkipCard, , $this->mudkipItem, $this->secondMudkipItem, $this->pikachuItem]
            = $this->createCatalogAndItems($owner);
        $this->parentLocation = new StorageLocation($owner, 'Kast links', StorageLocationType::Other);
        $this->entityManager->persist($this->parentLocation);
        $this->entityManager->flush();
    }

    public function testOwnerCanCreateBrowseAndUpdateAConfiguredBinder(): void
    {
        // Arrange
        $payload = [
            'name' => 'Water cards',
            'rows' => 2,
            'columns' => 2,
            'pageCount' => 2,
            'doubleSided' => true,
            'layoutStrategy' => 'set_number',
            'parentLocationId' => $this->parentLocation->getId()->toRfc4122(),
        ];

        // Act
        $this->jsonRequest('POST', '/api/v1/me/binders', $payload, $this->ownerToken);

        // Assert
        self::assertResponseStatusCodeSame(201);
        $binder = $this->responseData();
        $binderId = $binder['id'] ?? null;
        self::assertIsString($binderId);
        self::assertSame(4, $binder['surfaceCount'] ?? null);
        self::assertSame(16, $binder['slotCount'] ?? null);
        self::assertCount(4, $binder['pages'] ?? []);
        self::assertCount(4, $binder['pages'][0]['slots'] ?? []);
        self::assertSame('Kast links', $binder['parentLocation']['name'] ?? null);

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/binders', $this->ownerToken);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->responseData()['total'] ?? null);

        // Act
        $this->jsonRequest(
            'PATCH',
            '/api/v1/me/binders/'.$binderId,
            ['name' => 'Water collection', 'layoutStrategy' => 'alphabetical', 'clearParentLocation' => true],
            $this->ownerToken,
        );

        // Assert
        self::assertResponseIsSuccessful();
        $updated = $this->responseData();
        self::assertSame('Water collection', $updated['name'] ?? null);
        self::assertSame('alphabetical', $updated['layoutStrategy'] ?? null);
        self::assertNull($updated['parentLocation'] ?? null);

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/binders/'.$binderId, $this->otherToken);

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('binder_not_found', $this->responseData()['error']['code'] ?? null);
    }

    public function testAssigningCardsMovesThemAtomicallyAndRejectsAnOccupiedSlot(): void
    {
        // Arrange
        $binder = $this->createBinder();
        $binderId = (string) $binder['id'];
        $firstSlotId = (string) $binder['pages'][0]['slots'][0]['id'];
        $secondSlotId = (string) $binder['pages'][0]['slots'][1]['id'];

        // Act
        $this->assign($binderId, $firstSlotId, $this->mudkipItem->getId()->toRfc4122(), $this->ownerToken);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame(
            $this->mudkipItem->getId()->toRfc4122(),
            $this->responseData()['pages'][0]['slots'][0]['collectionItem']['id'] ?? null,
        );

        // Act
        $this->assign($binderId, $secondSlotId, $this->mudkipItem->getId()->toRfc4122(), $this->ownerToken);

        // Assert
        self::assertResponseIsSuccessful();
        $moved = $this->responseData();
        self::assertNull($moved['pages'][0]['slots'][0]['collectionItem'] ?? null);
        self::assertSame(
            $this->mudkipItem->getId()->toRfc4122(),
            $moved['pages'][0]['slots'][1]['collectionItem']['id'] ?? null,
        );

        // Act
        $this->assign($binderId, $secondSlotId, $this->secondMudkipItem->getId()->toRfc4122(), $this->ownerToken);

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('binder_slot_occupied', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->authorizedRequest(
            'GET',
            '/api/v1/me/collection-items/'.$this->mudkipItem->getId()->toRfc4122().'/location',
            $this->ownerToken,
        );

        // Assert
        self::assertResponseIsSuccessful();
        $location = $this->responseData()['current'] ?? [];
        self::assertSame('binder_slot', $location['type'] ?? null);
        self::assertSame($secondSlotId, $location['binderSlot']['id'] ?? null);

        // Act
        $this->authorizedRequest(
            'DELETE',
            '/api/v1/me/binders/'.$binderId.'/slots/'.$secondSlotId.'/assignment',
            $this->ownerToken,
        );

        // Assert
        self::assertResponseIsSuccessful();
        self::assertNull($this->responseData()['pages'][0]['slots'][1]['collectionItem'] ?? null);

        // Act
        $this->authorizedRequest(
            'GET',
            '/api/v1/me/collection-items/'.$this->mudkipItem->getId()->toRfc4122().'/location-history',
            $this->ownerToken,
        );

        // Assert
        self::assertResponseIsSuccessful();
        $history = $this->responseData();
        self::assertNull($history['current'] ?? null);
        self::assertCount(2, $history['history'] ?? []);
    }

    public function testReservedAndManualPlaceholdersAreEnforcedAndRetained(): void
    {
        // Arrange
        $binder = $this->createBinder();
        $binderId = (string) $binder['id'];
        $reservedSlotId = (string) $binder['pages'][0]['slots'][0]['id'];
        $manualSlotId = (string) $binder['pages'][0]['slots'][1]['id'];

        // Act
        $this->placeholder(
            $binderId,
            $reservedSlotId,
            ['catalogCardId' => $this->mudkipCard->getId()->toRfc4122()],
        );

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame(
            'Mudkip',
            $this->responseData()['pages'][0]['slots'][0]['placeholder']['catalogCard']['name'] ?? null,
        );

        // Act
        $this->assign($binderId, $reservedSlotId, $this->pikachuItem->getId()->toRfc4122(), $this->ownerToken);

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('binder_slot_reserved', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->assign($binderId, $reservedSlotId, $this->mudkipItem->getId()->toRfc4122(), $this->ownerToken);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame(
            'Mudkip',
            $this->responseData()['pages'][0]['slots'][0]['placeholder']['catalogCard']['name'] ?? null,
        );

        // Act
        $this->placeholder($binderId, $manualSlotId, ['label' => 'Secret rare']);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame('Secret rare', $this->responseData()['pages'][0]['slots'][1]['placeholder']['label'] ?? null);

        // Act
        $this->placeholder($binderId, $manualSlotId, ['clear' => true]);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertNull($this->responseData()['pages'][0]['slots'][1]['placeholder'] ?? null);
    }

    public function testArchivingACollectionItemFreesItsBinderSlotAndKeepsHistory(): void
    {
        // Arrange
        $binder = $this->createBinder();
        $binderId = (string) $binder['id'];
        $slotId = (string) $binder['pages'][0]['slots'][0]['id'];
        $itemId = $this->mudkipItem->getId()->toRfc4122();
        $this->assign($binderId, $slotId, $itemId, $this->ownerToken);
        self::assertResponseIsSuccessful();

        // Act
        $this->authorizedRequest('DELETE', '/api/v1/me/collection-items/'.$itemId, $this->ownerToken);

        // Assert
        self::assertResponseStatusCodeSame(204);

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/binders/'.$binderId, $this->ownerToken);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertNull($this->responseData()['pages'][0]['slots'][0]['collectionItem'] ?? null);

        // Act
        $this->authorizedRequest(
            'GET',
            '/api/v1/me/collection-items/'.$itemId.'/location-history',
            $this->ownerToken,
        );

        // Assert
        self::assertResponseIsSuccessful();
        $history = $this->responseData();
        self::assertNull($history['current'] ?? null);
        self::assertSame($slotId, $history['history'][0]['binderSlot']['id'] ?? null);
        self::assertNotNull($history['history'][0]['removedAt'] ?? null);
    }

    public function testDatabaseConstraintRejectsMultipleCurrentItemsInOneBinderSlot(): void
    {
        // Arrange
        $binder = $this->createBinder();
        $slotId = $binder['pages'][0]['slots'][0]['id'] ?? null;
        self::assertIsString($slotId);
        $slot = $this->entityManager->find(BinderSlot::class, $slotId);
        self::assertInstanceOf(BinderSlot::class, $slot);
        $this->entityManager->persist(CollectionItemLocationHistory::inBinderSlot($this->mudkipItem, $slot));
        $this->entityManager->flush();

        // Act
        $this->entityManager->persist(CollectionItemLocationHistory::inBinderSlot($this->secondMudkipItem, $slot));
        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();

        // Assert
        // The partial unique index is the observable concurrency safeguard.
    }

    public function testDatabaseConstraintRejectsOneCurrentItemInMultipleBinderSlots(): void
    {
        // Arrange
        $binder = $this->createBinder();
        $firstSlotId = $binder['pages'][0]['slots'][0]['id'] ?? null;
        $secondSlotId = $binder['pages'][0]['slots'][1]['id'] ?? null;
        self::assertIsString($firstSlotId);
        self::assertIsString($secondSlotId);
        $firstSlot = $this->entityManager->find(BinderSlot::class, $firstSlotId);
        $secondSlot = $this->entityManager->find(BinderSlot::class, $secondSlotId);
        self::assertInstanceOf(BinderSlot::class, $firstSlot);
        self::assertInstanceOf(BinderSlot::class, $secondSlot);
        $this->entityManager->persist(CollectionItemLocationHistory::inBinderSlot($this->mudkipItem, $firstSlot));
        $this->entityManager->flush();

        // Act
        $this->entityManager->persist(CollectionItemLocationHistory::inBinderSlot($this->mudkipItem, $secondSlot));
        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();

        // Assert
        // The item can have only one current physical placement.
    }

    /** @return array<string, mixed> */
    private function createBinder(): array
    {
        $this->jsonRequest('POST', '/api/v1/me/binders', [
            'name' => 'Test binder',
            'rows' => 2,
            'columns' => 2,
            'pageCount' => 1,
            'doubleSided' => false,
            'layoutStrategy' => 'custom',
        ], $this->ownerToken);
        self::assertResponseStatusCodeSame(201);

        return $this->responseData();
    }

    private function assign(string $binderId, string $slotId, string $itemId, string $token): void
    {
        $this->jsonRequest(
            'POST',
            '/api/v1/me/binders/'.$binderId.'/slots/'.$slotId.'/assign',
            ['collectionItemId' => $itemId],
            $token,
        );
    }

    /** @param array<string, mixed> $payload */
    private function placeholder(string $binderId, string $slotId, array $payload): void
    {
        $this->jsonRequest(
            'PATCH',
            '/api/v1/me/binders/'.$binderId.'/slots/'.$slotId.'/placeholder',
            $payload,
            $this->ownerToken,
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

    /** @return array{CatalogCard, CatalogCard, CollectionItem, CollectionItem, CollectionItem} */
    private function createCatalogAndItems(User $owner): array
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
        $mudkip = new CatalogCard($set, 'Mudkip', '19/25', 'Promo', 'Collectible');
        $mudkipVariant = $mudkip->addVariant(VariantCode::Holo);
        $pikachu = new CatalogCard($set, 'Pikachu', '25/25', 'Promo', 'Collectible');
        $pikachuVariant = $pikachu->addVariant(VariantCode::Normal);
        $collection = new Collection($owner);
        $firstMudkip = new CollectionItem(
            $collection,
            $mudkip,
            $mudkipVariant,
            CardLanguage::English,
            CardCondition::NearMint,
        );
        $secondMudkip = new CollectionItem(
            $collection,
            $mudkip,
            $mudkipVariant,
            CardLanguage::English,
            CardCondition::Good,
        );
        $pikachuItem = new CollectionItem(
            $collection,
            $pikachu,
            $pikachuVariant,
            CardLanguage::English,
            CardCondition::Mint,
        );
        $this->entityManager->persist($series);
        $this->entityManager->persist($set);
        $this->entityManager->persist($mudkip);
        $this->entityManager->persist($pikachu);
        $this->entityManager->persist($collection);
        $this->entityManager->persist($firstMudkip);
        $this->entityManager->persist($secondMudkip);
        $this->entityManager->persist($pikachuItem);

        return [$mudkip, $pikachu, $firstMudkip, $secondMudkip, $pikachuItem];
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
