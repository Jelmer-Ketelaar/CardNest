<?php

declare(strict_types=1);

namespace App\Order\Application;

use App\Identity\Domain\Entity\User;
use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Entity\ListingReservation;
use App\Marketplace\Domain\Entity\ListingReservationStatus;
use App\Marketplace\Domain\Entity\ListingStatus;
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Marketplace\Domain\Exception\ListingUnavailable;
use App\Marketplace\Domain\Repository\ListingRepository;
use App\Marketplace\Domain\Repository\ListingReservationRepository;
use App\Order\Application\Dto\CheckoutCartRequest;
use App\Order\Application\Dto\CheckoutResponse;
use App\Order\Application\Exception\PaymentFailed;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderStatus;
use App\Order\Domain\Entity\ShoppingCart;
use App\Order\Domain\Exception\EmptyCart;
use App\Order\Domain\Exception\InvalidShippingSelection;
use App\Order\Domain\Repository\OrderRepository;
use App\Order\Domain\Repository\ShoppingCartRepository;
use App\Order\Domain\Service\ShippingQuoteCalculator;
use App\Order\Domain\ValueObject\ShippingAddress;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Entity\PaymentFinalizationStatus;
use App\Payment\Domain\Entity\PaymentStatus;
use App\Payment\Domain\Exception\InvalidPayment;
use App\Payment\Domain\Provider\PaymentInstruction;
use App\Payment\Domain\Provider\PaymentProvider;
use App\Payment\Domain\Provider\PaymentResult;
use App\Payment\Domain\Repository\PaymentRepository;
use App\Shared\Domain\Transaction\TransactionManager;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class CheckoutCart
{
    public function __construct(
        private ShoppingCartRepository $carts,
        private ListingRepository $listings,
        private ListingReservationRepository $reservations,
        private OrderRepository $orders,
        private PaymentRepository $payments,
        private PaymentProvider $paymentProvider,
        private ShippingQuoteCalculator $shippingQuotes,
        private TransactionManager $transactions,
        private CheckoutFaultInjector $faults,
        #[Autowire(service: 'state_machine.marketplace_listing')]
        private WorkflowInterface $listingWorkflow,
        #[Autowire(service: 'state_machine.order')]
        private WorkflowInterface $orderWorkflow,
        #[Autowire('%env(int:CART_RESERVATION_MINUTES)%')]
        private int $reservationMinutes,
    ) {
        if ($reservationMinutes < 1 || $reservationMinutes > 60) {
            throw new InvalidArgumentException('CART_RESERVATION_MINUTES must be between 1 and 60.');
        }
    }

    public function checkout(User $buyer, CheckoutCartRequest $request): CheckoutResponse
    {
        $address = new ShippingAddress(
            $request->fullName,
            $request->addressLine1,
            $request->addressLine2,
            $request->postalCode,
            $request->city,
            $request->countryCode,
        );
        $requestFingerprint = $this->requestFingerprint($request);

        /** @var Payment $payment */
        $payment = $this->transactions->run(
            fn (): Payment => $this->prepareIntent($buyer, $request, $address, $requestFingerprint),
        );
        $this->faults->trigger(CheckoutCheckpoint::PreparePersisted, $payment);

        if ($payment->getStatus() === PaymentStatus::Pending) {
            $result = $this->paymentProvider->charge(new PaymentInstruction(
                $payment->getId()->toRfc4122(),
                $buyer->getId()->toRfc4122(),
                $payment->getAmount(),
                $payment->getIdempotencyKey(),
            ));
            [$payment] = $this->persistProviderResult($payment, $result);
        }

        if ($payment->getStatus() === PaymentStatus::Failed) {
            $payment = $this->releaseFailedCheckout($payment);

            throw new PaymentFailed($payment->getFailureReason() ?? 'De betaling is mislukt.');
        }
        if ($payment->getStatus() !== PaymentStatus::Succeeded) {
            throw new InvalidPayment('De checkoutbetaling heeft geen verwerkbaar providerresultaat.');
        }
        if ($payment->getFinalizationStatus() === PaymentFinalizationStatus::Finalized) {
            return $this->response($payment);
        }

        $payment = $this->recordFinalizationAttempt($payment);
        if ($payment->getFinalizationStatus() === PaymentFinalizationStatus::Finalized) {
            return $this->response($payment);
        }

        /** @var CheckoutResponse $response */
        $response = $this->transactions->run(fn (): CheckoutResponse => $this->finalizeCheckout($payment));

        return $response;
    }

    private function prepareIntent(
        User $buyer,
        CheckoutCartRequest $request,
        ShippingAddress $address,
        string $requestFingerprint,
    ): Payment {
        $cart = $this->carts->lockForCheckout($buyer);
        if (!$cart instanceof ShoppingCart) {
            throw new EmptyCart('Je winkelmand is leeg.');
        }

        $cartFingerprint = $this->cartFingerprint($cart);
        if ($request->idempotencyKey !== null) {
            $idempotencyKey = $request->idempotencyKey;
        } elseif ($cart->getItems()->isEmpty()) {
            $completed = $this->payments->findLatestFinalizedCheckout($cart, $requestFingerprint);
            if ($completed instanceof Payment) {
                $this->assertMatchingIntent($completed, $buyer, $cart, $requestFingerprint, null);

                return $completed;
            }

            throw new EmptyCart('Je winkelmand is leeg.');
        } else {
            $idempotencyKey = 'checkout_'.hash(
                'sha256',
                $cart->getId()->toRfc4122().'|'.$requestFingerprint.'|'.$cartFingerprint,
            );
        }

        $existing = $this->payments->findByIdempotencyKey($idempotencyKey);
        if ($existing instanceof Payment) {
            $this->assertMatchingIntent($existing, $buyer, $cart, $requestFingerprint, $cartFingerprint);

            return $existing;
        }
        if ($cart->getItems()->isEmpty()) {
            throw new EmptyCart('Je winkelmand is leeg.');
        }

        $lockedListings = $this->lockedListings($cart);
        $groups = $this->sellerGroups($buyer, $cart, $lockedListings);
        $preparedOrders = $this->prepareOrders($groups, $request->shippingMethods);
        $now = new DateTimeImmutable();
        $reservationExpiresAt = $now->modify('+'.$this->reservationMinutes.' minutes');

        foreach ($lockedListings as $listing) {
            if (!$this->listingWorkflow->can($listing, 'reserve')) {
                throw new ListingUnavailable('Een listing in je winkelmand kan niet meer worden gereserveerd.');
            }
            $this->listingWorkflow->apply($listing, 'reserve');
        }

        $paymentTotal = array_sum(array_map(
            static fn (PreparedSellerOrder $prepared): int => $prepared->totalAmount(),
            $preparedOrders,
        ));
        $payment = new Payment(
            buyer: $buyer,
            amount: new Money($paymentTotal, 'EUR'),
            providerName: $this->paymentProvider->name(),
            idempotencyKey: $idempotencyKey,
            checkoutCart: $cart,
            requestFingerprint: $requestFingerprint,
            cartFingerprint: $cartFingerprint,
            createdAt: $now,
        );
        $this->payments->add($payment);

        foreach ($preparedOrders as $prepared) {
            $order = new Order(
                $buyer,
                $prepared->group->getSeller(),
                $payment,
                $prepared->group->getListings(),
                $prepared->shippingQuote->code,
                $prepared->shippingQuote->price,
                $address,
                createdAt: $now,
            );
            $this->orders->add($order);

            foreach ($prepared->group->getListings() as $listing) {
                $this->reservations->add(new ListingReservation(
                    $listing,
                    $buyer,
                    $payment->getId(),
                    $reservationExpiresAt,
                    createdAt: $now,
                ));
            }
        }

        return $payment;
    }

    /** @return array{Payment, bool} */
    private function persistProviderResult(Payment $payment, PaymentResult $result): array
    {
        /** @var array{Payment, bool} $recorded */
        $recorded = $this->transactions->run(function () use ($payment, $result): array {
            $locked = $this->payments->reloadAndLockById($payment->getId()->toRfc4122());
            if (!$locked instanceof Payment) {
                throw new InvalidPayment('De voorbereide checkoutbetaling bestaat niet meer.');
            }
            if ($locked->getStatus() !== PaymentStatus::Pending) {
                return [$locked, false];
            }

            $locked->recordResult($result);
            $this->faults->trigger(CheckoutCheckpoint::ProviderResultPersistence, $locked);

            return [$locked, true];
        });

        return $recorded;
    }

    private function recordFinalizationAttempt(Payment $payment): Payment
    {
        /** @var Payment $locked */
        $locked = $this->transactions->run(function () use ($payment): Payment {
            $current = $this->payments->reloadAndLockById($payment->getId()->toRfc4122());
            if (!$current instanceof Payment) {
                throw new InvalidPayment('De voorbereide checkoutbetaling bestaat niet meer.');
            }
            if ($current->getFinalizationStatus() === PaymentFinalizationStatus::Pending) {
                $current->markFinalizationAttempted();
            }

            return $current;
        });

        return $locked;
    }

    private function finalizeCheckout(Payment $payment): CheckoutResponse
    {
        [$payment, $cart, $orders, $listings, $reservations] = $this->lockCheckoutContext($payment);
        if ($payment->getFinalizationStatus() === PaymentFinalizationStatus::Finalized) {
            return new CheckoutResponse($payment, $orders);
        }
        if ($payment->getStatus() !== PaymentStatus::Succeeded
            || $payment->getFinalizationStatus() !== PaymentFinalizationStatus::Pending) {
            throw new InvalidPayment('De checkoutbetaling kan niet worden gefinaliseerd.');
        }

        foreach ($orders as $order) {
            if ($order->getStatus() !== OrderStatus::PendingPayment
                || !$this->orderWorkflow->can($order, 'payment_received')) {
                throw new InvalidPayment('Een voorbereide order kan niet meer als betaald worden gemarkeerd.');
            }
        }
        foreach ($listings as $listing) {
            if ($listing->getStatus() !== ListingStatus::Reserved
                || !$this->listingWorkflow->can($listing, 'sell')) {
                throw new InvalidPayment('Een betaalde listing is niet meer gereserveerd voor deze checkout.');
            }
        }
        foreach ($reservations as $reservation) {
            if ($reservation->getStatus() !== ListingReservationStatus::Active) {
                throw new InvalidPayment('Een betaalde listingreservering is niet meer actief.');
            }
        }

        foreach ($orders as $order) {
            $previousStatus = $order->getStatus();
            $this->orderWorkflow->apply($order, 'payment_received');
            $order->recordStatusTransition($previousStatus, $payment->getBuyer());
        }
        foreach ($listings as $listing) {
            $this->listingWorkflow->apply($listing, 'sell');
        }
        foreach ($reservations as $reservation) {
            $reservation->complete();
        }
        $this->removePurchasedListings($cart, $listings);
        $payment->markFinalized();
        $this->faults->trigger(CheckoutCheckpoint::Finalization, $payment);

        return new CheckoutResponse($payment, $orders);
    }

    private function releaseFailedCheckout(Payment $payment): Payment
    {
        /** @var Payment $released */
        $released = $this->transactions->run(function () use ($payment): Payment {
            [$payment, , $orders, $listings, $reservations] = $this->lockCheckoutContext($payment);
            if ($payment->getFinalizationStatus() === PaymentFinalizationStatus::Released) {
                return $payment;
            }
            if ($payment->getStatus() !== PaymentStatus::Failed
                || $payment->getFinalizationStatus() !== PaymentFinalizationStatus::Pending) {
                throw new InvalidPayment('De afgewezen checkout kan niet veilig worden vrijgegeven.');
            }

            foreach ($orders as $order) {
                if ($order->getStatus() === OrderStatus::PendingPayment && $this->orderWorkflow->can($order, 'cancel')) {
                    $previousStatus = $order->getStatus();
                    $this->orderWorkflow->apply($order, 'cancel');
                    $order->recordStatusTransition($previousStatus, $payment->getBuyer());
                } elseif ($order->getStatus() !== OrderStatus::Cancelled) {
                    throw new InvalidPayment('Een afgewezen voorbereide order kon niet worden geannuleerd.');
                }
            }
            foreach ($listings as $listing) {
                if ($listing->getStatus() === ListingStatus::Reserved && $this->listingWorkflow->can($listing, 'release')) {
                    $this->listingWorkflow->apply($listing, 'release');
                } elseif ($listing->getStatus() !== ListingStatus::Active) {
                    throw new InvalidPayment('Een afgewezen listing kon niet veilig worden vrijgegeven.');
                }
            }
            foreach ($reservations as $reservation) {
                if (!in_array($reservation->getStatus(), [
                    ListingReservationStatus::Active,
                    ListingReservationStatus::Released,
                ], true)) {
                    throw new InvalidPayment('Een afgewezen listingreservering kon niet veilig worden vrijgegeven.');
                }
                $reservation->release();
            }
            $payment->markReservationsReleased();

            return $payment;
        });

        return $released;
    }

    /**
     * @return array{Payment, ShoppingCart, list<Order>, list<Listing>, list<ListingReservation>}
     */
    private function lockCheckoutContext(Payment $payment): array
    {
        $lockedPayment = $this->payments->reloadAndLockById($payment->getId()->toRfc4122());
        if (!$lockedPayment instanceof Payment) {
            throw new InvalidPayment('De voorbereide checkoutbetaling bestaat niet meer.');
        }

        $expectedCart = $lockedPayment->getCheckoutCart();
        if (!$expectedCart instanceof ShoppingCart) {
            throw new InvalidPayment('De betaling bevat geen checkoutwinkelmand.');
        }

        $cart = $this->carts->lockForCheckout($lockedPayment->getBuyer());
        if (!$cart instanceof ShoppingCart || !$cart->getId()->equals($expectedCart->getId())) {
            throw new InvalidPayment('De checkoutwinkelmand bestaat niet meer.');
        }

        $orders = $this->payments->lockCheckoutOrders($lockedPayment);
        if ($orders === []) {
            throw new InvalidPayment('De checkoutbetaling bevat geen voorbereide orders.');
        }
        $listingIds = $this->listingIds($orders, $lockedPayment);
        $listings = $this->listings->lockForCheckout($listingIds);
        if (count($listings) !== count($listingIds)) {
            throw new InvalidPayment('Niet alle voorbereide checkoutlistings bestaan nog.');
        }

        $reservations = $this->payments->lockCheckoutReservations($lockedPayment);
        if (count($reservations) !== count($listingIds)) {
            throw new InvalidPayment('Niet alle voorbereide checkoutreserveringen bestaan nog.');
        }
        $reservedListingIds = [];
        foreach ($reservations as $reservation) {
            if (!$reservation->getCheckoutReference()->equals($lockedPayment->getId())) {
                throw new InvalidPayment('Een checkoutreservering hoort bij een andere betaling.');
            }
            $reservedListingIds[] = $reservation->getListing()->getId()->toRfc4122();
        }
        sort($reservedListingIds);
        if ($reservedListingIds !== $listingIds) {
            throw new InvalidPayment('De checkoutreserveringen komen niet overeen met de voorbereide orders.');
        }

        return [$lockedPayment, $cart, $orders, $listings, $reservations];
    }

    /**
     * @param list<Order> $orders
     *
     * @return list<string>
     */
    private function listingIds(array $orders, Payment $payment): array
    {
        $listingIds = [];
        foreach ($orders as $order) {
            if (!$order->getPayment()->getId()->equals($payment->getId())) {
                throw new InvalidPayment('Een voorbereide order hoort bij een andere betaling.');
            }
            foreach ($order->getItems() as $item) {
                $listingIds[] = $item->getListing()->getId()->toRfc4122();
            }
        }
        sort($listingIds);
        if ($listingIds === [] || count(array_unique($listingIds)) !== count($listingIds)) {
            throw new InvalidPayment('De voorbereide orders bevatten ongeldige checkoutlistings.');
        }

        return $listingIds;
    }

    /**
     * @param list<Listing> $listings
     */
    private function removePurchasedListings(ShoppingCart $cart, array $listings): void
    {
        $purchased = array_fill_keys(array_map(
            static fn (Listing $listing): string => $listing->getId()->toRfc4122(),
            $listings,
        ), true);
        foreach ($cart->getItems()->toArray() as $item) {
            if (isset($purchased[$item->getListing()->getId()->toRfc4122()])) {
                $cart->remove($item->getId()->toRfc4122());
            }
        }
    }

    private function response(Payment $payment): CheckoutResponse
    {
        $orders = $this->payments->findCheckoutOrders($payment);
        if ($orders === []) {
            throw new InvalidPayment('De checkoutbetaling bevat geen orders.');
        }

        return new CheckoutResponse($payment, $orders);
    }

    private function assertMatchingIntent(
        Payment $payment,
        User $buyer,
        ShoppingCart $cart,
        string $requestFingerprint,
        ?string $cartFingerprint,
    ): void {
        $intentCart = $payment->getCheckoutCart();
        if (!$payment->getBuyer()->getId()->equals($buyer->getId())
            || !$intentCart instanceof ShoppingCart
            || !$intentCart->getId()->equals($cart->getId())
            || $payment->getRequestFingerprint() !== $requestFingerprint) {
            throw new InvalidPayment('De idempotentiesleutel is al voor een andere checkout gebruikt.');
        }
        if ($cartFingerprint !== null && $payment->getCartFingerprint() !== $cartFingerprint) {
            throw new InvalidPayment('De winkelmand is gewijzigd sinds deze checkout werd voorbereid.');
        }
        if ($cart->getItems()->isEmpty() && $payment->getStatus() === PaymentStatus::Pending) {
            throw new InvalidPayment('De winkelmand is gewijzigd voordat de betaling kon worden verwerkt.');
        }
    }

    /**
     * @return array<string, Listing>
     */
    private function lockedListings(ShoppingCart $cart): array
    {
        $ids = array_values(array_map(
            static fn ($item): string => $item->getListing()->getId()->toRfc4122(),
            $cart->getItems()->toArray(),
        ));
        $locked = [];
        foreach ($this->listings->lockForCheckout($ids) as $listing) {
            $locked[$listing->getId()->toRfc4122()] = $listing;
        }
        if (count($locked) !== count($ids)) {
            throw new ListingUnavailable('Een listing in je winkelmand bestaat niet meer.');
        }

        return $locked;
    }

    /**
     * @param array<string, Listing> $lockedListings
     *
     * @return array<string, CheckoutSellerGroup>
     */
    private function sellerGroups(User $buyer, ShoppingCart $cart, array $lockedListings): array
    {
        $groups = [];
        $now = new DateTimeImmutable();
        foreach ($cart->getItems() as $item) {
            $id = $item->getListing()->getId()->toRfc4122();
            $listing = $lockedListings[$id] ?? null;
            if (!$listing instanceof Listing) {
                throw new ListingUnavailable('Een listing in je winkelmand bestaat niet meer.');
            }

            $listing->assertPurchasableBy($buyer, $now);
            $sellerId = $listing->getSeller()->getId()->toRfc4122();
            $groups[$sellerId] ??= new CheckoutSellerGroup($listing->getSeller());
            $groups[$sellerId]->add($listing);
        }

        return $groups;
    }

    /**
     * @param array<string, CheckoutSellerGroup> $groups
     * @param array<string, string>              $shippingSelections
     *
     * @return list<PreparedSellerOrder>
     */
    private function prepareOrders(array $groups, array $shippingSelections): array
    {
        $expectedSellerIds = array_keys($groups);
        $providedSellerIds = array_keys($shippingSelections);
        sort($expectedSellerIds);
        sort($providedSellerIds);
        if ($expectedSellerIds !== $providedSellerIds) {
            throw new InvalidShippingSelection('Kies precies één verzendmethode per verkoper.');
        }

        $prepared = [];
        foreach ($groups as $sellerId => $group) {
            $code = ShippingMethodCode::tryFrom($shippingSelections[$sellerId]);
            if (!$code instanceof ShippingMethodCode) {
                throw new InvalidShippingSelection('Kies een ondersteunde verzendmethode.');
            }
            $prepared[] = new PreparedSellerOrder(
                $group,
                $this->shippingQuotes->require($group->getListings(), $code),
            );
        }

        return $prepared;
    }

    private function requestFingerprint(CheckoutCartRequest $request): string
    {
        $shippingMethods = $request->shippingMethods;
        ksort($shippingMethods);

        return hash('sha256', json_encode([
            'shippingMethods' => $shippingMethods,
            'fullName' => $request->fullName,
            'addressLine1' => $request->addressLine1,
            'addressLine2' => $request->addressLine2,
            'postalCode' => $request->postalCode,
            'city' => $request->city,
            'countryCode' => $request->countryCode,
        ], JSON_THROW_ON_ERROR));
    }

    private function cartFingerprint(ShoppingCart $cart): string
    {
        $listingIds = array_map(
            static fn ($item): string => $item->getListing()->getId()->toRfc4122(),
            $cart->getItems()->toArray(),
        );
        sort($listingIds);

        return hash('sha256', implode('|', $listingIds));
    }
}
