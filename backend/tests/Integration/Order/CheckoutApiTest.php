<?php

declare(strict_types=1);

namespace App\Tests\Integration\Order;

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
use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Entity\ListingImageSide;
use App\Marketplace\Domain\Entity\ListingStatus;
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Marketplace\Domain\ValueObject\ListingPhotoReference;
use App\Marketplace\Domain\ValueObject\ShippingOption;
use App\Shared\Domain\ValueObject\Money;
use App\Storage\Domain\Entity\CollectionItemLocationHistory;
use App\Storage\Domain\Entity\StorageLocation;
use App\Storage\Domain\Entity\StorageLocationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CheckoutApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private User $sellerA;
    private User $sellerB;
    private string $buyerToken;
    private string $secondBuyerToken;
    private string $sellerAToken;
    private string $outsiderToken;
    private Listing $sellerAFirst;
    private Listing $sellerASecond;
    private Listing $sellerBListing;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        [, $this->buyerToken] = $this->createAuthenticatedUser('buyer');
        [, $this->secondBuyerToken] = $this->createAuthenticatedUser('second-buyer');
        [$this->sellerA, $this->sellerAToken] = $this->createAuthenticatedUser('seller-a');
        [$this->sellerB] = $this->createAuthenticatedUser('seller-b');
        [, $this->outsiderToken] = $this->createAuthenticatedUser('outsider');
        [$this->sellerAFirst, $this->sellerASecond, $this->sellerBListing] = $this->createListings();
        $this->entityManager->flush();
    }

    public function testCartGroupsListingsAndCalculatesCombinedShipping(): void
    {
        // Arrange
        $this->addToCart($this->buyerToken, $this->sellerAFirst);
        $this->addToCart($this->buyerToken, $this->sellerASecond);

        // Act
        $this->addToCart($this->buyerToken, $this->sellerBListing);

        // Assert
        self::assertResponseStatusCodeSame(201);
        $cart = $this->responseData();
        self::assertSame(3, $cart['itemCount'] ?? null);
        self::assertTrue($cart['checkoutAvailable'] ?? false);
        self::assertCount(2, $cart['sellerGroups'] ?? []);
        $groups = $this->groupsBySeller($cart);
        $sellerA = $groups[$this->sellerA->getId()->toRfc4122()] ?? null;
        self::assertIsArray($sellerA);
        self::assertSame(1150, $sellerA['itemSubtotal']['amount'] ?? null);
        self::assertCount(2, $sellerA['items'] ?? []);
        $shipping = array_column($sellerA['shippingMethods'] ?? [], null, 'code');
        self::assertSame(250, $shipping['letter_mail']['price']['amount'] ?? null);
        self::assertSame(1400, $shipping['letter_mail']['totalPrice']['amount'] ?? null);
        self::assertArrayNotHasKey('parcel', $shipping);
        self::assertSame(0, $shipping['pickup']['price']['amount'] ?? null);
    }

    public function testCannotAddOwnOrDuplicateListing(): void
    {
        // Arrange
        $this->addToCart($this->buyerToken, $this->sellerAFirst);
        self::assertResponseStatusCodeSame(201);

        // Act
        $this->addToCart($this->buyerToken, $this->sellerAFirst);

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('invalid_cart', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->addToCart($this->sellerAToken, $this->sellerASecond);

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('own_listing_purchase', $this->responseData()['error']['code'] ?? null);
    }

    public function testCartItemsArePrivateAndEndpointsRequireAuthentication(): void
    {
        // Arrange

        // Act
        $this->publicRequest('GET', '/api/v1/me/cart');

        // Assert
        self::assertResponseStatusCodeSame(401);
        self::assertSame('authentication_required', $this->responseData()['error']['code'] ?? null);

        // Arrange
        $this->addToCart($this->buyerToken, $this->sellerAFirst);
        $cart = $this->responseData();
        $itemId = $cart['sellerGroups'][0]['items'][0]['id'] ?? null;
        self::assertIsString($itemId);

        // Act
        $this->authorizedRequest('DELETE', '/api/v1/me/cart/items/'.$itemId, $this->secondBuyerToken);

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('cart_item_not_found', $this->responseData()['error']['code'] ?? null);
        $this->authorizedRequest('GET', '/api/v1/me/cart', $this->buyerToken);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->responseData()['itemCount'] ?? null);

        // Act
        $this->publicRequest('GET', '/api/v1/me/orders');

        // Assert
        self::assertResponseStatusCodeSame(401);
        self::assertSame('authentication_required', $this->responseData()['error']['code'] ?? null);
    }

    public function testCheckoutSplitsOrdersPaysAndPreventsSecondSale(): void
    {
        // Arrange
        foreach ([$this->sellerAFirst, $this->sellerASecond, $this->sellerBListing] as $listing) {
            $this->addToCart($this->buyerToken, $listing);
            self::assertResponseStatusCodeSame(201);
        }
        $this->addToCart($this->secondBuyerToken, $this->sellerAFirst);
        self::assertResponseStatusCodeSame(201);

        // Act
        $this->checkout($this->buyerToken, [
            $this->sellerA->getId()->toRfc4122() => 'letter_mail',
            $this->sellerB->getId()->toRfc4122() => 'parcel',
        ]);

        // Assert
        self::assertResponseIsSuccessful();
        $checkout = $this->responseData();
        self::assertSame('succeeded', $checkout['payment']['status'] ?? null);
        self::assertSame('fake', $checkout['payment']['provider'] ?? null);
        self::assertStringStartsWith('fake_', (string) ($checkout['payment']['providerReference'] ?? ''));
        self::assertSame(3995, $checkout['payment']['amount']['amount'] ?? null);
        self::assertCount(2, $checkout['orders'] ?? []);
        $orders = $this->ordersBySeller($checkout['orders'] ?? []);
        $sellerAOrder = $orders[$this->sellerA->getId()->toRfc4122()] ?? null;
        self::assertIsArray($sellerAOrder);
        self::assertSame('paid', $sellerAOrder['status'] ?? null);
        self::assertSame(1150, $sellerAOrder['costs']['items']['amount'] ?? null);
        self::assertSame(250, $sellerAOrder['costs']['shipping']['amount'] ?? null);
        self::assertSame(0, $sellerAOrder['costs']['serviceFee']['amount'] ?? null);
        self::assertSame(1400, $sellerAOrder['costs']['total']['amount'] ?? null);
        self::assertCount(2, $sellerAOrder['items'] ?? []);
        $sellerBOrder = $orders[$this->sellerB->getId()->toRfc4122()] ?? null;
        self::assertIsArray($sellerBOrder);
        self::assertSame(2595, $sellerBOrder['costs']['total']['amount'] ?? null);
        self::assertSame('NL', $sellerBOrder['shippingAddress']['countryCode'] ?? null);

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/cart', $this->buyerToken);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->responseData()['itemCount'] ?? null);
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertSame(3, (int) $connection->fetchOne("SELECT COUNT(*) FROM listing_reservations WHERE status = 'completed'"));
        self::assertSame(1, (int) $connection->fetchOne("SELECT COUNT(*) FROM payments WHERE status = 'succeeded'"));
        self::assertSame(1, (int) $connection->fetchOne("SELECT COUNT(*) FROM payment_transactions WHERE status = 'succeeded'"));

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/orders', $this->sellerAToken);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->responseData()['total'] ?? null);
        $orderId = $sellerAOrder['id'] ?? null;
        self::assertIsString($orderId);
        $this->authorizedRequest('GET', '/api/v1/me/orders/'.$orderId, $this->outsiderToken);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('order_not_found', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->checkout($this->secondBuyerToken, [
            $this->sellerA->getId()->toRfc4122() => 'letter_mail',
        ]);

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('listing_unavailable', $this->responseData()['error']['code'] ?? null);
        $this->authorizedRequest('GET', '/api/v1/me/cart', $this->secondBuyerToken);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->responseData()['itemCount'] ?? null);
        self::assertFalse($this->responseData()['checkoutAvailable'] ?? true);
    }

    public function testCheckoutRollsBackAllListingsWhenOneWasSoldElsewhere(): void
    {
        // Arrange
        $this->addToCart($this->buyerToken, $this->sellerASecond);
        $this->addToCart($this->buyerToken, $this->sellerBListing);
        $this->addToCart($this->secondBuyerToken, $this->sellerBListing);
        $this->checkout($this->secondBuyerToken, [
            $this->sellerB->getId()->toRfc4122() => 'parcel',
        ]);
        self::assertResponseIsSuccessful();

        // Act
        $this->checkout($this->buyerToken, [
            $this->sellerA->getId()->toRfc4122() => 'letter_mail',
            $this->sellerB->getId()->toRfc4122() => 'parcel',
        ]);

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('listing_unavailable', $this->responseData()['error']['code'] ?? null);
        $this->publicRequest('GET', '/api/v1/listings/'.$this->sellerASecond->getId()->toRfc4122());
        self::assertResponseIsSuccessful();
        self::assertSame('active', $this->responseData()['status'] ?? null);
        $this->authorizedRequest('GET', '/api/v1/me/cart', $this->buyerToken);
        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->responseData()['itemCount'] ?? null);
        $this->authorizedRequest('GET', '/api/v1/me/orders', $this->buyerToken);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->responseData()['total'] ?? null);
    }

    public function testSellerShipsAndBuyerCompletesOrderWithCollectionArchival(): void
    {
        // Arrange
        $soldItem = $this->sellerAFirst->getCollectionItem();
        $location = new StorageLocation($this->sellerA, 'Verkoopbox', StorageLocationType::Box);
        $placement = CollectionItemLocationHistory::inStorage($soldItem, $location);
        $this->entityManager->persist($location);
        $this->entityManager->persist($placement);
        $this->entityManager->flush();
        $this->addToCart($this->buyerToken, $this->sellerAFirst);
        $this->checkout($this->buyerToken, [
            $this->sellerA->getId()->toRfc4122() => 'letter_mail',
        ]);
        self::assertResponseIsSuccessful();
        $orderId = $this->responseData()['orders'][0]['id'] ?? null;
        self::assertIsString($orderId);

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/orders/'.$orderId, $this->sellerAToken);

        // Assert
        self::assertTrue($this->responseData()['availableActions']['markShipped'] ?? false);

        // Act
        $this->authorizedRequest('POST', '/api/v1/me/orders/'.$orderId.'/mark-shipped', $this->buyerToken);

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('order_not_found', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->authorizedRequest('POST', '/api/v1/me/orders/'.$orderId.'/mark-shipped', $this->sellerAToken);

        // Assert
        self::assertResponseIsSuccessful();
        $shippedOrder = $this->responseData();
        self::assertSame('shipped', $shippedOrder['status'] ?? null);
        self::assertFalse($shippedOrder['availableActions']['markShipped'] ?? true);
        self::assertSame(['pending_payment', 'paid', 'shipped'], array_column($shippedOrder['statusHistory'] ?? [], 'to'));
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertSame(1, (int) $connection->fetchOne("SELECT COUNT(*) FROM shipments WHERE status = 'shipped'"));

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/orders/'.$orderId, $this->buyerToken);

        // Assert
        self::assertTrue($this->responseData()['availableActions']['confirmReceived'] ?? false);

        // Act
        $this->authorizedRequest('POST', '/api/v1/me/orders/'.$orderId.'/confirm-received', $this->sellerAToken);

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('order_not_found', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->authorizedRequest('POST', '/api/v1/me/orders/'.$orderId.'/confirm-received', $this->buyerToken);

        // Assert
        self::assertResponseIsSuccessful();
        $completedOrder = $this->responseData();
        self::assertSame('completed', $completedOrder['status'] ?? null);
        self::assertNotNull($completedOrder['completedAt'] ?? null);
        self::assertSame(
            ['pending_payment', 'paid', 'shipped', 'completed'],
            array_column($completedOrder['statusHistory'] ?? [], 'to'),
        );
        self::assertFalse($completedOrder['availableActions']['confirmReceived'] ?? true);
        self::assertSame(1, (int) $connection->fetchOne("SELECT COUNT(*) FROM shipments WHERE status = 'delivered'"));
        self::assertSame('archived', $connection->fetchOne(
            'SELECT status FROM collection_items WHERE id = :id',
            ['id' => $soldItem->getId()->toRfc4122()],
        ));
        self::assertNotFalse($connection->fetchOne(
            'SELECT removed_at FROM collection_item_location_history WHERE id = :id',
            ['id' => $placement->getId()->toRfc4122()],
        ));

        // Act
        $this->authorizedRequest('POST', '/api/v1/me/orders/'.$orderId.'/confirm-received', $this->buyerToken);

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('invalid_order_transition', $this->responseData()['error']['code'] ?? null);
    }

    public function testCheckoutValidatesCartShippingAndAddress(): void
    {
        // Arrange
        $validShipping = [$this->sellerA->getId()->toRfc4122() => 'letter_mail'];

        // Act
        $this->checkout($this->buyerToken, $validShipping);

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('cart_empty', $this->responseData()['error']['code'] ?? null);

        // Arrange
        $this->addToCart($this->buyerToken, $this->sellerAFirst);

        // Act
        $this->checkout($this->buyerToken, []);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $this->responseData()['error']['code'] ?? null);

        // Act
        $payload = $this->checkoutPayload($validShipping);
        $payload['postalCode'] = '1234 AB';
        $payload['countryCode'] = 'BE';
        $this->jsonRequest('POST', '/api/v1/me/cart/checkout', $payload, $this->buyerToken);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_order', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->checkout($this->buyerToken, [$this->sellerA->getId()->toRfc4122() => 'parcel']);

        // Assert
        self::assertResponseIsSuccessful();
    }

    /** @return array{User, string} */
    private function createAuthenticatedUser(string $prefix): array
    {
        $suffix = Uuid::v7()->toRfc4122();
        $user = new User($prefix.'-'.$suffix.'@example.com', 'hash');
        $token = bin2hex(random_bytes(32));
        $this->entityManager->persist($user);
        $this->entityManager->persist(AuthToken::issue($user, $token));

        return [$user, $token];
    }

    /** @return array{Listing, Listing, Listing} */
    private function createListings(): array
    {
        $suffix = Uuid::v7()->toRfc4122();
        $series = new CardSeries('Checkout series '.$suffix);
        $set = new CardSet($series, 'Checkout set', 'checkout-'.$suffix, 'en', 3, 3, status: CardSetStatus::Active);
        $this->entityManager->persist($series);
        $this->entityManager->persist($set);

        return [
            $this->createListing($set, $this->sellerA, 'Mudkip', '1/3', 750, 150, true, true),
            $this->createListing($set, $this->sellerA, 'Pikachu', '2/3', 400, 250, false, true),
            $this->createListing($set, $this->sellerB, 'Charizard', '3/3', 2000, 350, true, false),
        ];
    }

    private function createListing(
        CardSet $set,
        User $seller,
        string $name,
        string $number,
        int $price,
        int $letterPrice,
        bool $parcel,
        bool $pickup,
    ): Listing {
        $card = new CatalogCard($set, $name, $number, 'Rare', 'Collectible');
        $variant = $card->addVariant(VariantCode::Holo);
        $item = new CollectionItem($seller, $card, $variant, CardLanguage::English, CardCondition::NearMint);
        $shipping = [new ShippingOption(ShippingMethodCode::LetterMail, new Money($letterPrice, 'EUR'))];
        if ($parcel) {
            $shipping[] = new ShippingOption(ShippingMethodCode::Parcel, new Money(595, 'EUR'));
        }
        if ($pickup) {
            $shipping[] = new ShippingOption(ShippingMethodCode::Pickup, new Money(0, 'EUR'));
        }
        $listing = new Listing(
            $seller,
            $item,
            new Money($price, 'EUR'),
            $shipping,
            [
                new ListingPhotoReference(ListingImageSide::Front, 'https://images.example/'.$name.'-front.jpg'),
                new ListingPhotoReference(ListingImageSide::Back, 'https://images.example/'.$name.'-back.jpg'),
            ],
            status: ListingStatus::Active,
        );
        $item->markForSale();
        $this->entityManager->persist($card);
        $this->entityManager->persist($item);
        $this->entityManager->persist($listing);

        return $listing;
    }

    private function addToCart(string $token, Listing $listing): void
    {
        $this->jsonRequest(
            'POST',
            '/api/v1/me/cart/items',
            ['listingId' => $listing->getId()->toRfc4122()],
            $token,
        );
    }

    /** @param array<string, string> $shippingMethods */
    private function checkout(string $token, array $shippingMethods): void
    {
        $this->jsonRequest('POST', '/api/v1/me/cart/checkout', $this->checkoutPayload($shippingMethods), $token);
    }

    /**
     * @param array<string, string> $shippingMethods
     *
     * @return array<string, array<string, string>|string|null>
     */
    private function checkoutPayload(array $shippingMethods): array
    {
        return [
            'shippingMethods' => $shippingMethods,
            'fullName' => 'Sam Collector',
            'addressLine1' => 'Dorpsstraat 1',
            'addressLine2' => null,
            'postalCode' => '1234 AB',
            'city' => 'Utrecht',
            'countryCode' => 'NL',
        ];
    }

    /** @param array<string, array<string, string>|string|null> $payload */
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

    /**
     * @param array<string, mixed> $cart
     *
     * @return array<string, array<string, mixed>>
     */
    private function groupsBySeller(array $cart): array
    {
        $groups = [];
        foreach ($cart['sellerGroups'] ?? [] as $group) {
            if (is_array($group) && isset($group['seller']['id']) && is_string($group['seller']['id'])) {
                $groups[$group['seller']['id']] = $group;
            }
        }

        return $groups;
    }

    /**
     * @param list<array<string, mixed>> $orders
     *
     * @return array<string, array<string, mixed>>
     */
    private function ordersBySeller(array $orders): array
    {
        $bySeller = [];
        foreach ($orders as $order) {
            if (isset($order['seller']['id']) && is_string($order['seller']['id'])) {
                $bySeller[$order['seller']['id']] = $order;
            }
        }

        return $bySeller;
    }
}
