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
use App\Collection\Domain\Entity\Collection;
use App\Collection\Domain\Entity\CollectionItem;
use App\Identity\Domain\Entity\AuthToken;
use App\Identity\Domain\Entity\User;
use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Entity\ListingImageSide;
use App\Marketplace\Domain\Entity\ListingStatus;
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Marketplace\Domain\ValueObject\ListingPhotoReference;
use App\Marketplace\Domain\ValueObject\ShippingOption;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderStatus;
use App\Order\Domain\ValueObject\ShippingAddress;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Infrastructure\Provider\FakePaymentProvider;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class OrderCancellationApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private User $buyer;
    private User $seller;
    private string $buyerToken;
    private string $sellerToken;
    private string $outsiderToken;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        [$this->buyer, $this->buyerToken] = $this->createAuthenticatedUser('buyer');
        [$this->seller, $this->sellerToken] = $this->createAuthenticatedUser('seller');
        [, $this->outsiderToken] = $this->createAuthenticatedUser('outsider');
        $this->entityManager->flush();
    }

    public function testBuyerCanCancelPaidOrderAndReceiveExactRefund(): void
    {
        // Arrange
        [$listing, $item] = $this->createListing($this->seller, 'Mudkip', 2_000, 250);
        $this->entityManager->flush();
        $orders = $this->checkout([$listing]);
        $order = $orders[0];
        $orderId = $this->orderId($order);
        self::assertTrue($order['availableActions']['cancel'] ?? false);

        // Act
        $this->cancel($this->buyerToken, $orderId, '  Card is no longer available.  ');

        // Assert
        self::assertResponseIsSuccessful();
        $response = $this->responseData();
        self::assertSame('refunded', $response['status'] ?? null);
        self::assertSame('Card is no longer available.', $response['cancellation']['reason'] ?? null);
        self::assertSame($this->buyer->getId()->toRfc4122(), $response['cancellation']['cancelledBy']['id'] ?? null);
        self::assertSame('succeeded', $response['refund']['status'] ?? null);
        self::assertSame(2_250, $response['refund']['amount']['amount'] ?? null);
        self::assertSame('EUR', $response['refund']['amount']['currency'] ?? null);
        self::assertFalse($response['availableActions']['cancel'] ?? true);
        self::assertSame(
            ['pending_payment', 'paid', 'cancelled', 'refunded'],
            array_column($response['statusHistory'] ?? [], 'to'),
        );

        $connection = $this->entityManager->getConnection();
        self::assertSame('in_collection', $connection->fetchOne(
            'SELECT status FROM collection_items WHERE id = :id',
            ['id' => $item->getId()->toRfc4122()],
        ));
        self::assertSame('cancelled', $connection->fetchOne(
            'SELECT status FROM listings WHERE id = :id',
            ['id' => $listing->getId()->toRfc4122()],
        ));
        self::assertSame('refunded', $connection->fetchOne(
            'SELECT status FROM payments WHERE id = :id',
            ['id' => $order['payment']['id']],
        ));
        $refund = $connection->fetchAssociative(
            'SELECT amount, currency, status, reason, order_id::text AS order_id FROM refunds WHERE order_id = :order_id',
            ['order_id' => $orderId],
        );
        self::assertIsArray($refund);
        self::assertSame(2_250, (int) $refund['amount']);
        self::assertSame('EUR', $refund['currency']);
        self::assertSame('succeeded', $refund['status']);
        self::assertSame('Card is no longer available.', $refund['reason']);
        self::assertSame($orderId, $refund['order_id']);
        self::assertSame(1, (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM payment_transactions WHERE type = 'refund' AND status = 'succeeded' AND order_id = :order_id",
            ['order_id' => $orderId],
        ));
    }

    public function testSellerCanCancelPaidOrder(): void
    {
        // Arrange
        [$listing, $item] = $this->createListing($this->seller, 'Pikachu', 1_000, 150);
        $this->entityManager->flush();
        $orders = $this->checkout([$listing]);
        $order = $orders[0];

        // Act
        $this->cancel($this->sellerToken, $this->orderId($order), 'Seller cannot fulfil this order.');

        // Assert
        self::assertResponseIsSuccessful();
        $response = $this->responseData();
        self::assertSame('refunded', $response['status'] ?? null);
        self::assertSame($this->seller->getId()->toRfc4122(), $response['cancellation']['cancelledBy']['id'] ?? null);
        self::assertSame('in_collection', $this->entityManager->getConnection()->fetchOne(
            'SELECT status FROM collection_items WHERE id = :id',
            ['id' => $item->getId()->toRfc4122()],
        ));
    }

    public function testUnrelatedUserGetsNotFoundWithoutSideEffects(): void
    {
        // Arrange
        [$listing, $item] = $this->createListing($this->seller, 'Charmander', 900, 100);
        $this->entityManager->flush();
        $orders = $this->checkout([$listing]);
        $order = $orders[0];
        $orderId = $this->orderId($order);

        // Act
        $this->cancel($this->outsiderToken, $orderId, 'I should not be able to cancel this.');

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('order_not_found', $this->responseData()['error']['code'] ?? null);
        $connection = $this->entityManager->getConnection();
        self::assertSame('paid', $connection->fetchOne('SELECT status FROM orders WHERE id = :id', ['id' => $orderId]));
        self::assertSame('for_sale', $connection->fetchOne(
            'SELECT status FROM collection_items WHERE id = :id',
            ['id' => $item->getId()->toRfc4122()],
        ));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM refunds WHERE order_id = :id', ['id' => $orderId]));
        self::assertSame(0, (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM payment_transactions WHERE type = 'refund' AND order_id = :id",
            ['id' => $orderId],
        ));
    }

    public function testSharedPaymentRefundsOnlyTheSelectedOrderUntilEveryOrderIsRefunded(): void
    {
        // Arrange
        [$firstListing, $firstItem] = $this->createListing($this->seller, 'Bulbasaur', 2_000, 250);
        [$secondSeller] = $this->createAuthenticatedUser('second-seller');
        [$secondListing, $secondItem] = $this->createListing($secondSeller, 'Squirtle', 2_000, 345);
        $this->entityManager->flush();
        $orders = $this->checkout([$firstListing, $secondListing]);
        self::assertCount(2, $orders);
        $firstOrder = $this->orderForSeller($orders, $this->seller);
        $secondOrder = $this->orderNotForSeller($orders, $this->seller);
        $paymentId = $firstOrder['payment']['id'] ?? null;
        self::assertIsString($paymentId);

        // Act
        $this->cancel($this->buyerToken, $this->orderId($firstOrder), 'First seller cannot fulfil this order.');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame('refunded', $this->responseData()['status'] ?? null);
        self::assertSame(2_250, $this->responseData()['refund']['amount']['amount'] ?? null);
        $connection = $this->entityManager->getConnection();
        self::assertSame('succeeded', $connection->fetchOne('SELECT status FROM payments WHERE id = :id', ['id' => $paymentId]));
        self::assertSame('paid', $connection->fetchOne(
            'SELECT status FROM orders WHERE id = :id',
            ['id' => $this->orderId($secondOrder)],
        ));
        self::assertSame(0, (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM refunds WHERE order_id = :id',
            ['id' => $this->orderId($secondOrder)],
        ));
        self::assertSame('for_sale', $connection->fetchOne(
            'SELECT status FROM collection_items WHERE id = :id',
            ['id' => $secondItem->getId()->toRfc4122()],
        ));
        self::assertSame('sold', $connection->fetchOne(
            'SELECT status FROM listings WHERE id = :id',
            ['id' => $secondListing->getId()->toRfc4122()],
        ));
        self::assertSame('in_collection', $connection->fetchOne(
            'SELECT status FROM collection_items WHERE id = :id',
            ['id' => $firstItem->getId()->toRfc4122()],
        ));

        // Act
        $this->cancel($this->buyerToken, $this->orderId($secondOrder), 'Second seller cannot fulfil this order.');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame('refunded', $connection->fetchOne('SELECT status FROM payments WHERE id = :id', ['id' => $paymentId]));
        self::assertSame(4_595, (int) $connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE payment_id = :payment_id AND status = 'succeeded'",
            ['payment_id' => $paymentId],
        ));
    }

    public function testPendingPaymentOrderCancelsWithoutCreatingARefund(): void
    {
        // Arrange
        [$listing, $item] = $this->createListing($this->seller, 'Eevee', 700, 100);
        $listing->setWorkflowStatus(ListingStatus::Reserved->value);
        $payment = new Payment($this->buyer, new Money(800, 'EUR'), 'fake');
        $order = new Order(
            buyer: $this->buyer,
            seller: $this->seller,
            payment: $payment,
            listings: [$listing],
            shippingMethod: ShippingMethodCode::LetterMail,
            shippingPrice: new Money(100, 'EUR'),
            shippingAddress: new ShippingAddress('Sam Collector', 'Dorpsstraat 1', null, '1234 AB', 'Utrecht', 'NL'),
        );
        $this->entityManager->persist($payment);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Act
        $this->cancel($this->buyerToken, $order->getId()->toRfc4122(), 'Payment has not completed yet.');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame('cancelled', $this->responseData()['status'] ?? null);
        $response = $this->responseData();
        self::assertArrayHasKey('refund', $response);
        self::assertNull($response['refund']);
        $connection = $this->entityManager->getConnection();
        self::assertSame('pending', $connection->fetchOne('SELECT status FROM payments WHERE id = :id', ['id' => $payment->getId()->toRfc4122()]));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM refunds WHERE order_id = :id', ['id' => $order->getId()->toRfc4122()]));
        self::assertSame('in_collection', $connection->fetchOne(
            'SELECT status FROM collection_items WHERE id = :id',
            ['id' => $item->getId()->toRfc4122()],
        ));
    }

    /** @param array<string, string> $payload */
    #[DataProvider('invalidReasonPayloads')]
    public function testCancellationReasonIsValidated(array $payload): void
    {
        // Arrange
        [$listing] = $this->createListing($this->seller, 'Jigglypuff', 500, 100);
        $this->entityManager->flush();
        $orders = $this->checkout([$listing]);
        $order = $orders[0];

        // Act
        $this->jsonRequest('POST', '/api/v1/me/orders/'.$this->orderId($order).'/cancel', $payload, $this->buyerToken);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $this->responseData()['error']['code'] ?? null);
    }

    public function testMalformedCancellationPayloadReturnsBadRequest(): void
    {
        // Arrange
        [$listing] = $this->createListing($this->seller, 'Vulpix', 500, 100);
        $this->entityManager->flush();
        $order = $this->checkout([$listing])[0];

        // Act
        $this->client->request('POST', '/api/v1/me/orders/'.$this->orderId($order).'/cancel', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->buyerToken,
        ], content: '{');

        // Assert
        self::assertResponseStatusCodeSame(400);
        self::assertSame('request_failed', $this->responseData()['error']['code'] ?? null);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function invalidReasonPayloads(): iterable
    {
        yield 'missing' => [[]];
        yield 'empty' => [['reason' => '']];
        yield 'whitespace' => [['reason' => '   ']];
        yield 'too short' => [['reason' => 'No']];
        yield 'too long' => [['reason' => str_repeat('a', 501)]];
    }

    public function testShippedOrAlreadyRefundedOrderCannotBeCancelledTwice(): void
    {
        // Arrange
        [$listing] = $this->createListing($this->seller, 'Meowth', 600, 100);
        [$secondSeller, $secondSellerToken] = $this->createAuthenticatedUser('second-seller');
        [$secondListing] = $this->createListing($secondSeller, 'Psyduck', 650, 100);
        $this->entityManager->flush();
        $orders = $this->checkout([$listing]);
        $order = $orders[0];
        $orderId = $this->orderId($order);
        $this->authorizedRequest('POST', '/api/v1/me/orders/'.$orderId.'/mark-shipped', $this->sellerToken);
        self::assertResponseIsSuccessful();

        // Act
        $this->cancel($this->buyerToken, $orderId, 'It is already in transit.');

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('order_not_cancellable', $this->responseData()['error']['code'] ?? null);

        // Arrange
        $secondOrders = $this->checkout([$secondListing]);
        $secondOrder = $secondOrders[0];
        $secondOrderId = $this->orderId($secondOrder);
        $this->cancel($this->buyerToken, $secondOrderId, 'The seller cancelled the sale.');
        self::assertResponseIsSuccessful();

        // Act
        $this->cancel($secondSellerToken, $secondOrderId, 'Second cancellation must not refund again.');

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('order_not_cancellable', $this->responseData()['error']['code'] ?? null);
        $connection = $this->entityManager->getConnection();
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM refunds WHERE order_id = :id', ['id' => $secondOrderId]));
        self::assertSame(1, (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM payment_transactions WHERE type = 'refund' AND order_id = :id",
            ['id' => $secondOrderId],
        ));
    }

    #[DataProvider('nonCancellableStatuses')]
    public function testNonCancellableOrderStatusesReturnConflict(OrderStatus $status): void
    {
        // Arrange
        [$listing] = $this->createListing($this->seller, 'Ditto', 800, 100);
        $this->entityManager->flush();
        $order = $this->checkout([$listing])[0];
        $orderId = $this->orderId($order);
        $connection = $this->entityManager->getConnection();
        $parameters = ['status' => $status->value, 'id' => $orderId];
        $sql = 'UPDATE orders SET status = :status WHERE id = :id';
        if ($status === OrderStatus::Completed) {
            $sql = 'UPDATE orders SET status = :status, completed_at = CURRENT_TIMESTAMP WHERE id = :id';
        } elseif (in_array($status, [OrderStatus::Cancelled, OrderStatus::Refunded], true)) {
            $sql = 'UPDATE orders SET status = :status, cancellation_reason = :reason, cancelled_at = CURRENT_TIMESTAMP, cancelled_by_id = :actor WHERE id = :id';
            $parameters['reason'] = 'A prior cancellation was recorded.';
            $parameters['actor'] = $this->buyer->getId()->toRfc4122();
        }
        $connection->executeStatement($sql, $parameters);

        // Act
        $this->cancel($this->buyerToken, $orderId, 'This order cannot be cancelled again.');

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('order_not_cancellable', $this->responseData()['error']['code'] ?? null);
    }

    /** @return iterable<string, array{OrderStatus}> */
    public static function nonCancellableStatuses(): iterable
    {
        yield 'shipped' => [OrderStatus::Shipped];
        yield 'completed' => [OrderStatus::Completed];
        yield 'cancelled' => [OrderStatus::Cancelled];
        yield 'refunded' => [OrderStatus::Refunded];
    }

    public function testProviderFailureRollsBackTheCancellation(): void
    {
        // Arrange
        $this->client->disableReboot();
        self::getContainer()->set(FakePaymentProvider::class, new FakePaymentProvider(refundsShouldFail: true));
        [$listing, $item] = $this->createListing($this->seller, 'Snorlax', 1_200, 150);
        $this->entityManager->flush();
        $orders = $this->checkout([$listing]);
        $order = $orders[0];
        $orderId = $this->orderId($order);

        // Act
        $this->cancel($this->buyerToken, $orderId, 'The provider should reject this refund.');

        // Assert
        self::assertResponseStatusCodeSame(502);
        self::assertSame('refund_failed', $this->responseData()['error']['code'] ?? null);
        $connection = $this->entityManager->getConnection();
        self::assertSame('paid', $connection->fetchOne('SELECT status FROM orders WHERE id = :id', ['id' => $orderId]));
        self::assertSame('succeeded', $connection->fetchOne(
            'SELECT status FROM payments WHERE id = :id',
            ['id' => $order['payment']['id']],
        ));
        self::assertSame('for_sale', $connection->fetchOne(
            'SELECT status FROM collection_items WHERE id = :id',
            ['id' => $item->getId()->toRfc4122()],
        ));
        self::assertSame('sold', $connection->fetchOne(
            'SELECT status FROM listings WHERE id = :id',
            ['id' => $listing->getId()->toRfc4122()],
        ));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM refunds WHERE order_id = :id', ['id' => $orderId]));
        self::assertSame(0, (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM payment_transactions WHERE type = 'refund' AND order_id = :id",
            ['id' => $orderId],
        ));
    }

    /** @return array{Listing, CollectionItem} */
    private function createListing(User $seller, string $name, int $price, int $shipping): array
    {
        $suffix = Uuid::v7()->toRfc4122();
        $series = new CardSeries('Cancellation series '.$suffix);
        $set = new CardSet($series, 'Cancellation set '.$suffix, 'cancel-'.$suffix, 'en', 1, 1, status: CardSetStatus::Active);
        $card = new CatalogCard($set, $name, '1/1', 'Rare', 'Collectible');
        $variant = $card->addVariant(VariantCode::Holo);
        $collection = new Collection($seller);
        $item = new CollectionItem($collection, $card, $variant, CardLanguage::English, CardCondition::NearMint);
        $listing = new Listing(
            seller: $seller,
            collectionItem: $item,
            price: new Money($price, 'EUR'),
            shippingOptions: [new ShippingOption(ShippingMethodCode::LetterMail, new Money($shipping, 'EUR'))],
            photos: [
                new ListingPhotoReference(ListingImageSide::Front, 'https://images.example/'.$suffix.'-front.jpg'),
                new ListingPhotoReference(ListingImageSide::Back, 'https://images.example/'.$suffix.'-back.jpg'),
            ],
            status: ListingStatus::Active,
        );
        $item->markForSale();
        $this->entityManager->persist($series);
        $this->entityManager->persist($set);
        $this->entityManager->persist($card);
        $this->entityManager->persist($collection);
        $this->entityManager->persist($item);
        $this->entityManager->persist($listing);

        return [$listing, $item];
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

    /** @param list<Listing> $listings
     *
     * @return list<array<string, mixed>>
     */
    private function checkout(array $listings): array
    {
        $shippingMethods = [];
        foreach ($listings as $listing) {
            $this->jsonRequest(
                'POST',
                '/api/v1/me/cart/items',
                ['listingId' => $listing->getId()->toRfc4122()],
                $this->buyerToken,
            );
            self::assertResponseStatusCodeSame(201);
            $shippingMethods[$listing->getSeller()->getId()->toRfc4122()] = ShippingMethodCode::LetterMail->value;
        }

        $this->jsonRequest('POST', '/api/v1/me/cart/checkout', [
            'shippingMethods' => $shippingMethods,
            'fullName' => 'Sam Collector',
            'addressLine1' => 'Dorpsstraat 1',
            'addressLine2' => null,
            'postalCode' => '1234 AB',
            'city' => 'Utrecht',
            'countryCode' => 'NL',
        ], $this->buyerToken);
        self::assertResponseIsSuccessful();
        $orders = $this->responseData()['orders'] ?? null;
        self::assertIsArray($orders);

        $result = [];
        foreach ($orders as $order) {
            self::assertIsArray($order);
            $typedOrder = [];
            foreach ($order as $key => $value) {
                self::assertIsString($key);
                $typedOrder[$key] = $value;
            }
            $result[] = $typedOrder;
        }

        return $result;
    }

    /** @param array<string, mixed>|list<array<string, mixed>> $order */
    private function orderId(array $order): string
    {
        $id = $order['id'] ?? null;
        self::assertIsString($id);

        return $id;
    }

    /** @param list<array<string, mixed>> $orders
     *
     * @return array<string, mixed>
     */
    private function orderForSeller(array $orders, User $seller): array
    {
        foreach ($orders as $order) {
            if (($order['seller']['id'] ?? null) === $seller->getId()->toRfc4122()) {
                return $order;
            }
        }

        self::fail('Expected an order for the specified seller.');
    }

    /** @param list<array<string, mixed>> $orders
     *
     * @return array<string, mixed>
     */
    private function orderNotForSeller(array $orders, User $seller): array
    {
        foreach ($orders as $order) {
            if (($order['seller']['id'] ?? null) !== $seller->getId()->toRfc4122()) {
                return $order;
            }
        }

        self::fail('Expected an order for another seller.');
    }

    private function cancel(string $token, string $orderId, string $reason): void
    {
        $this->jsonRequest('POST', '/api/v1/me/orders/'.$orderId.'/cancel', ['reason' => $reason], $token);
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
