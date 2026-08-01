<?php

declare(strict_types=1);

namespace App\Tests\Integration\Review;

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
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Marketplace\Domain\ValueObject\ListingPhotoReference;
use App\Marketplace\Domain\ValueObject\ShippingOption;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderStatus;
use App\Order\Domain\ValueObject\ShippingAddress;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Provider\PaymentResult;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class ReviewApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private User $buyer;
    private User $seller;
    private Collection $sellerCollection;
    private string $buyerToken;
    private string $sellerToken;
    private string $outsiderToken;
    private Order $completedOrder;
    private Order $paidOrder;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        [$this->buyer, $this->buyerToken] = $this->createAuthenticatedUser('buyer');
        [$this->seller, $this->sellerToken] = $this->createAuthenticatedUser('seller');
        $this->sellerCollection = new Collection($this->seller);
        $this->entityManager->persist($this->sellerCollection);
        [, $this->outsiderToken] = $this->createAuthenticatedUser('outsider');
        $this->completedOrder = $this->createOrder(true);
        $this->paidOrder = $this->createOrder(false);
        $this->entityManager->flush();
    }

    public function testOnlyCompletedOrderParticipantsCanReviewOnce(): void
    {
        // Arrange
        $payload = $this->reviewPayload(5, '  Zorgvuldig verpakt.  ');

        // Act
        $this->jsonRequest(
            'POST',
            '/api/v1/me/orders/'.$this->completedOrder->getId()->toRfc4122().'/reviews',
            $payload,
        );

        // Assert
        self::assertResponseStatusCodeSame(401);
        self::assertSame('authentication_required', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->jsonRequest(
            'POST',
            '/api/v1/me/orders/'.$this->completedOrder->getId()->toRfc4122().'/reviews',
            $payload,
            $this->outsiderToken,
        );

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('order_not_found', $this->responseData()['error']['code'] ?? null);

        // Act
        $invalidPayload = $payload;
        $invalidPayload['overallScore'] = 6;
        $this->jsonRequest(
            'POST',
            '/api/v1/me/orders/'.$this->completedOrder->getId()->toRfc4122().'/reviews',
            $invalidPayload,
            $this->buyerToken,
        );

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->jsonRequest(
            'POST',
            '/api/v1/me/orders/'.$this->paidOrder->getId()->toRfc4122().'/reviews',
            $payload,
            $this->buyerToken,
        );

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('invalid_review', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->jsonRequest(
            'POST',
            '/api/v1/me/orders/'.$this->completedOrder->getId()->toRfc4122().'/reviews',
            $payload,
            $this->buyerToken,
        );

        // Assert
        self::assertResponseStatusCodeSame(201);
        $review = $this->responseData();
        self::assertSame($this->completedOrder->getId()->toRfc4122(), $review['orderId'] ?? null);
        self::assertSame($this->seller->getId()->toRfc4122(), $review['reviewedUser']['id'] ?? null);
        self::assertSame('Zorgvuldig verpakt.', $review['comment'] ?? null);

        // Act
        $this->jsonRequest(
            'POST',
            '/api/v1/me/orders/'.$this->completedOrder->getId()->toRfc4122().'/reviews',
            $payload,
            $this->buyerToken,
        );

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('review_already_exists', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->authorizedRequest('GET', '/api/v1/me/reviews', $this->buyerToken);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->responseData()['total'] ?? null);
        self::assertSame($this->completedOrder->getId()->toRfc4122(), $this->responseData()['items'][0]['orderId'] ?? null);
    }

    public function testBothDirectionsProducePublicReputationWithoutOrderDisclosure(): void
    {
        // Arrange
        $orderId = $this->completedOrder->getId()->toRfc4122();
        $this->jsonRequest('POST', '/api/v1/me/orders/'.$orderId.'/reviews', $this->reviewPayload(5), $this->buyerToken);
        self::assertResponseStatusCodeSame(201);
        $this->jsonRequest('POST', '/api/v1/me/orders/'.$orderId.'/reviews', $this->reviewPayload(4), $this->sellerToken);
        self::assertResponseStatusCodeSame(201);

        // Act
        $this->publicRequest('GET', '/api/v1/users/'.$this->seller->getId()->toRfc4122().'/reputation');

        // Assert
        self::assertResponseIsSuccessful();
        $sellerReputation = $this->responseData();
        self::assertSame(1, $sellerReputation['summary']['reviewCount'] ?? null);
        self::assertSame(5, $sellerReputation['summary']['averageOverall'] ?? null);
        self::assertSame(4, $sellerReputation['summary']['averages']['communication'] ?? null);
        self::assertCount(1, $sellerReputation['reviews']['items'] ?? []);
        self::assertArrayNotHasKey('orderId', $sellerReputation['reviews']['items'][0] ?? []);

        // Act
        $this->publicRequest('GET', '/api/v1/users/'.$this->buyer->getId()->toRfc4122().'/reputation');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame(4, $this->responseData()['summary']['averageOverall'] ?? null);

        // Act
        $this->publicRequest('GET', '/api/v1/users/'.Uuid::v7()->toRfc4122().'/reputation');

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame('user_not_found', $this->responseData()['error']['code'] ?? null);
    }

    /** @return array<string, int|string> */
    private function reviewPayload(int $overall, string $comment = 'Prettige transactie.'): array
    {
        return [
            'overallScore' => $overall,
            'descriptionAccuracyScore' => 5,
            'packagingScore' => 5,
            'communicationScore' => 4,
            'shippingSpeedScore' => 5,
            'comment' => $comment,
        ];
    }

    private function createOrder(bool $completed): Order
    {
        $suffix = Uuid::v7()->toRfc4122();
        $series = new CardSeries('Review series '.$suffix);
        $set = new CardSet($series, 'Review set '.$suffix, 'review-'.$suffix, 'en', 1, 1, status: CardSetStatus::Active);
        $card = new CatalogCard($set, 'Mudkip', '1/1', 'Promo', 'Collectible');
        $variant = $card->addVariant(VariantCode::Holo);
        $item = new CollectionItem($this->sellerCollection, $card, $variant, CardLanguage::English, CardCondition::NearMint);
        $listing = new Listing(
            $this->seller,
            $item,
            new Money(750, 'EUR'),
            [new ShippingOption(ShippingMethodCode::LetterMail, new Money(150, 'EUR'))],
            [
                new ListingPhotoReference(ListingImageSide::Front, 'https://images.example/'.$suffix.'-front.jpg'),
                new ListingPhotoReference(ListingImageSide::Back, 'https://images.example/'.$suffix.'-back.jpg'),
            ],
        );
        $listing->setWorkflowStatus('reserved');
        $item->markForSale();
        $payment = new Payment($this->buyer, new Money(900, 'EUR'), 'fake');
        $payment->recordResult(PaymentResult::succeeded(new Money(900, 'EUR'), 'review-'.$suffix));
        $order = new Order(
            $this->buyer,
            $this->seller,
            $payment,
            [$listing],
            ShippingMethodCode::LetterMail,
            new Money(150, 'EUR'),
            new ShippingAddress('Sam Collector', 'Dorpsstraat 1', null, '1234 AB', 'Utrecht', 'NL'),
        );
        $order->setWorkflowStatus(OrderStatus::Paid->value);
        $order->recordStatusTransition(OrderStatus::PendingPayment, $this->buyer);
        $listing->setWorkflowStatus('sold');
        if ($completed) {
            $order->setWorkflowStatus(OrderStatus::Shipped->value);
            $order->recordStatusTransition(OrderStatus::Paid, $this->seller);
            $order->setWorkflowStatus(OrderStatus::Completed->value);
            $order->recordStatusTransition(OrderStatus::Shipped, $this->buyer);
            $item->completeSale();
        }

        foreach ([$series, $set, $card, $item, $listing, $payment, $order] as $entity) {
            $this->entityManager->persist($entity);
        }

        return $order;
    }

    /** @return array{User, string} */
    private function createAuthenticatedUser(string $prefix): array
    {
        $user = new User($prefix.'-'.Uuid::v7()->toRfc4122().'@example.com', 'hash');
        $token = bin2hex(random_bytes(32));
        $this->entityManager->persist($user);
        $this->entityManager->persist(AuthToken::issue($user, $token));

        return [$user, $token];
    }

    /** @param array<string, mixed> $payload */
    private function jsonRequest(string $method, string $uri, array $payload, ?string $token = null): void
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request(
            $method,
            $uri,
            server: $server,
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
