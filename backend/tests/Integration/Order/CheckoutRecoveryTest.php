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
use App\Identity\Domain\Entity\User;
use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Entity\ListingImageSide;
use App\Marketplace\Domain\Entity\ListingStatus;
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Marketplace\Domain\ValueObject\ListingPhotoReference;
use App\Marketplace\Domain\ValueObject\ShippingOption;
use App\Order\Application\CheckoutCart;
use App\Order\Application\CheckoutCheckpoint;
use App\Order\Application\CheckoutFaultInjector;
use App\Order\Application\Dto\CheckoutCartRequest;
use App\Order\Application\Dto\CheckoutResponse;
use App\Order\Application\NullCheckoutFaultInjector;
use App\Order\Domain\Entity\ShoppingCart;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Provider\PaymentInstruction;
use App\Payment\Domain\Provider\PaymentProvider;
use App\Payment\Domain\Provider\PaymentResult;
use App\Payment\Domain\Provider\RefundInstruction;
use App\Payment\Domain\Provider\RefundResult;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class CheckoutRecoveryTest extends KernelTestCase
{
    private const string IDEMPOTENCY_KEY = 'checkout-recovery-key';

    private EntityManagerInterface $entityManager;
    private string $buyerId;
    private string $sellerId;
    private string $listingId;
    private string $cartId;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->reloadEntityManager();
        $this->createFixture();
    }

    /**
     * @return iterable<string, array{CheckoutCheckpoint, string, int, int, int}>
     */
    public static function faultCheckpoints(): iterable
    {
        yield 'after durable prepare' => [CheckoutCheckpoint::PreparePersisted, 'pending', 0, 0, 1];
        yield 'while provider result is persisted' => [CheckoutCheckpoint::ProviderResultPersistence, 'pending', 0, 1, 2];
        yield 'while finalization is committed' => [CheckoutCheckpoint::Finalization, 'succeeded', 1, 1, 1];
    }

    #[DataProvider('faultCheckpoints')]
    public function testRetryRecoversEachCheckoutPhaseWithOneFinancialSideEffect(
        CheckoutCheckpoint $checkpoint,
        string $statusBeforeRetry,
        int $transactionsBeforeRetry,
        int $sideEffectsBeforeRetry,
        int $expectedProviderCalls,
    ): void {
        // Arrange
        $provider = new IdempotentRecordingPaymentProvider();
        self::getContainer()->set(PaymentProvider::class, $provider);
        self::getContainer()->set(CheckoutFaultInjector::class, new ThrowingCheckoutFaultInjector($checkpoint));
        $buyer = $this->buyer();

        // Act
        try {
            self::getContainer()->get(CheckoutCart::class)->checkout($buyer, $this->request());
            self::fail('The configured checkout checkpoint should have failed.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected checkout failure at '.$checkpoint->value.'.', $exception->getMessage());
        }

        // Assert
        self::assertSame($sideEffectsBeforeRetry, $provider->financialSideEffects);
        $this->reboot($provider, new NullCheckoutFaultInjector());
        $connection = $this->entityManager->getConnection();
        self::assertSame(1, (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM payments WHERE idempotency_key = :key',
            ['key' => self::IDEMPOTENCY_KEY],
        ));
        self::assertSame($statusBeforeRetry, $connection->fetchOne(
            'SELECT status FROM payments WHERE idempotency_key = :key',
            ['key' => self::IDEMPOTENCY_KEY],
        ));
        self::assertSame('pending', $connection->fetchOne(
            'SELECT finalization_status FROM payments WHERE idempotency_key = :key',
            ['key' => self::IDEMPOTENCY_KEY],
        ));
        self::assertSame($transactionsBeforeRetry, (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM payment_transactions transaction INNER JOIN payments payment ON payment.id = transaction.payment_id WHERE payment.idempotency_key = :key',
            ['key' => self::IDEMPOTENCY_KEY],
        ));
        self::assertSame(1, (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM orders purchase INNER JOIN payments payment ON payment.id = purchase.payment_id WHERE payment.idempotency_key = :key AND purchase.status = 'pending_payment'",
            ['key' => self::IDEMPOTENCY_KEY],
        ));
        self::assertSame('reserved', $connection->fetchOne(
            'SELECT status FROM listings WHERE id = :id',
            ['id' => $this->listingId],
        ));
        self::assertSame('active', $connection->fetchOne(
            'SELECT status FROM listing_reservations WHERE checkout_reference = (SELECT id FROM payments WHERE idempotency_key = :key)',
            ['key' => self::IDEMPOTENCY_KEY],
        ));
        if ($checkpoint === CheckoutCheckpoint::Finalization) {
            self::assertNotFalse($connection->fetchOne(
                'SELECT finalization_attempted_at FROM payments WHERE idempotency_key = :key',
                ['key' => self::IDEMPOTENCY_KEY],
            ));
        }

        // Act
        $response = self::getContainer()->get(CheckoutCart::class)->checkout($this->buyer(), $this->request());

        // Assert
        $this->assertRecovered($response, $provider, $expectedProviderCalls);
    }

    private function assertRecovered(
        CheckoutResponse $response,
        IdempotentRecordingPaymentProvider $provider,
        int $expectedProviderCalls,
    ): void {
        $data = $response->toArray();
        self::assertSame('succeeded', $data['payment']['status'] ?? null);
        self::assertSame('finalized', $data['payment']['finalizationStatus'] ?? null);
        self::assertSame(self::IDEMPOTENCY_KEY, $data['payment']['idempotencyKey'] ?? null);
        self::assertSame(1, $provider->financialSideEffects);
        self::assertSame($expectedProviderCalls, $provider->chargeCalls);

        $connection = $this->entityManager->getConnection();
        self::assertSame(1, (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM payments WHERE idempotency_key = :key',
            ['key' => self::IDEMPOTENCY_KEY],
        ));
        self::assertSame(1, (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM payment_transactions transaction INNER JOIN payments payment ON payment.id = transaction.payment_id WHERE payment.idempotency_key = :key',
            ['key' => self::IDEMPOTENCY_KEY],
        ));
        self::assertSame(1, (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM orders purchase INNER JOIN payments payment ON payment.id = purchase.payment_id WHERE payment.idempotency_key = :key',
            ['key' => self::IDEMPOTENCY_KEY],
        ));
        self::assertSame('paid', $connection->fetchOne(
            'SELECT purchase.status FROM orders purchase INNER JOIN payments payment ON payment.id = purchase.payment_id WHERE payment.idempotency_key = :key',
            ['key' => self::IDEMPOTENCY_KEY],
        ));
        self::assertSame('sold', $connection->fetchOne(
            'SELECT status FROM listings WHERE id = :id',
            ['id' => $this->listingId],
        ));
        self::assertSame('completed', $connection->fetchOne(
            'SELECT status FROM listing_reservations WHERE checkout_reference = (SELECT id FROM payments WHERE idempotency_key = :key)',
            ['key' => self::IDEMPOTENCY_KEY],
        ));
        self::assertSame(0, (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM shopping_cart_items WHERE cart_id = :cartId',
            ['cartId' => $this->cartId],
        ));
    }

    private function request(): CheckoutCartRequest
    {
        return new CheckoutCartRequest(
            [$this->sellerId => ShippingMethodCode::LetterMail->value],
            'Recovery buyer',
            'Teststraat 1',
            null,
            '1234 AB',
            'Utrecht',
            'NL',
            self::IDEMPOTENCY_KEY,
        );
    }

    private function buyer(): User
    {
        $buyer = $this->entityManager->find(User::class, Uuid::fromString($this->buyerId));
        if (!$buyer instanceof User) {
            throw new RuntimeException('The checkout recovery buyer no longer exists.');
        }

        return $buyer;
    }

    private function reboot(PaymentProvider $provider, CheckoutFaultInjector $faults): void
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        $this->reloadEntityManager();
        self::getContainer()->set(PaymentProvider::class, $provider);
        self::getContainer()->set(CheckoutFaultInjector::class, $faults);
    }

    private function reloadEntityManager(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;
    }

    private function createFixture(): void
    {
        $runId = substr(str_replace('-', '', Uuid::v7()->toRfc4122()), 0, 16);
        $buyer = new User('recovery-buyer-'.$runId.'@example.test', 'hash');
        $seller = new User('recovery-seller-'.$runId.'@example.test', 'hash');
        $series = new CardSeries('Recovery '.$runId);
        $set = new CardSet($series, 'Recovery set', 'recovery-'.$runId, 'en', 1, 1, CardSetStatus::Active);
        $card = new CatalogCard($set, 'Recovery card', '1/1', 'Rare', 'Collectible');
        $variant = $card->addVariant(VariantCode::Holo);
        $collection = new Collection($seller);
        $item = new CollectionItem($collection, $card, $variant, CardLanguage::English, CardCondition::NearMint);
        $listing = new Listing(
            $seller,
            $item,
            new Money(750, 'EUR'),
            [new ShippingOption(ShippingMethodCode::LetterMail, new Money(150, 'EUR'))],
            [
                new ListingPhotoReference(ListingImageSide::Front, 'https://images.example/recovery-front.jpg'),
                new ListingPhotoReference(ListingImageSide::Back, 'https://images.example/recovery-back.jpg'),
            ],
            status: ListingStatus::Active,
        );
        $item->markForSale();
        $cart = new ShoppingCart($buyer);
        $cart->add($listing);

        foreach ([$buyer, $seller, $series, $set, $card, $collection, $item, $listing, $cart] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        $this->buyerId = $buyer->getId()->toRfc4122();
        $this->sellerId = $seller->getId()->toRfc4122();
        $this->listingId = $listing->getId()->toRfc4122();
        $this->cartId = $cart->getId()->toRfc4122();
        $this->entityManager->clear();
    }
}

final class ThrowingCheckoutFaultInjector implements CheckoutFaultInjector
{
    private bool $thrown = false;

    public function __construct(private readonly CheckoutCheckpoint $checkpoint)
    {
    }

    public function trigger(CheckoutCheckpoint $checkpoint, Payment $payment): void
    {
        if ($this->thrown || $checkpoint !== $this->checkpoint) {
            return;
        }

        $this->thrown = true;

        throw new RuntimeException('Injected checkout failure at '.$checkpoint->value.'.');
    }
}

final class IdempotentRecordingPaymentProvider implements PaymentProvider
{
    public int $chargeCalls = 0;
    public int $financialSideEffects = 0;

    /** @var array<string, PaymentResult> */
    private array $results = [];

    public function name(): string
    {
        return 'recording_fake';
    }

    public function charge(PaymentInstruction $instruction): PaymentResult
    {
        ++$this->chargeCalls;
        if (!isset($this->results[$instruction->idempotencyKey])) {
            ++$this->financialSideEffects;
            $this->results[$instruction->idempotencyKey] = PaymentResult::succeeded(
                $instruction->amount,
                'recording_'.hash('sha256', $instruction->idempotencyKey),
            );
        }

        return $this->results[$instruction->idempotencyKey];
    }

    public function refund(RefundInstruction $instruction): RefundResult
    {
        return RefundResult::succeeded('recording_refund_'.$instruction->idempotencyKey);
    }
}
