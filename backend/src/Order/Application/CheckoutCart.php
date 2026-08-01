<?php

declare(strict_types=1);

namespace App\Order\Application;

use App\Identity\Domain\Entity\User;
use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Entity\ListingReservation;
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Marketplace\Domain\Exception\ListingUnavailable;
use App\Marketplace\Domain\Repository\ListingRepository;
use App\Marketplace\Domain\Repository\ListingReservationRepository;
use App\Order\Application\Dto\CheckoutCartRequest;
use App\Order\Application\Dto\CheckoutResponse;
use App\Order\Application\Exception\PaymentFailed;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\ShoppingCart;
use App\Order\Domain\Exception\EmptyCart;
use App\Order\Domain\Exception\InvalidShippingSelection;
use App\Order\Domain\Repository\OrderRepository;
use App\Order\Domain\Repository\ShoppingCartRepository;
use App\Order\Domain\Service\ShippingQuoteCalculator;
use App\Order\Domain\ValueObject\ShippingAddress;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Provider\PaymentInstruction;
use App\Payment\Domain\Provider\PaymentProvider;
use App\Payment\Domain\Repository\PaymentRepository;
use App\Shared\Domain\Transaction\TransactionManager;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;
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

        return $this->transactions->run(function () use ($buyer, $request, $address): CheckoutResponse {
            $cart = $this->carts->lockForCheckout($buyer);
            if (!$cart instanceof ShoppingCart || $cart->getItems()->isEmpty()) {
                throw new EmptyCart('Je winkelmand is leeg.');
            }

            $lockedListings = $this->lockedListings($cart);
            $groups = $this->sellerGroups($buyer, $cart, $lockedListings);
            $preparedOrders = $this->prepareOrders($groups, $request->shippingMethods);
            $now = new DateTimeImmutable();
            $checkoutReference = Uuid::v7();
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
            $payment = new Payment($buyer, new Money($paymentTotal, 'EUR'), $this->paymentProvider->name());
            $orders = [];
            $reservations = [];
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
                $orders[] = $order;
                $this->orders->add($order);

                foreach ($prepared->group->getListings() as $listing) {
                    $reservation = new ListingReservation(
                        $listing,
                        $buyer,
                        $checkoutReference,
                        $reservationExpiresAt,
                        createdAt: $now,
                    );
                    $reservations[] = $reservation;
                    $this->reservations->add($reservation);
                }
            }

            $result = $this->paymentProvider->charge(new PaymentInstruction(
                $payment->getId()->toRfc4122(),
                $buyer->getId()->toRfc4122(),
                $payment->getAmount(),
                $this->idempotencyKey($cart, $preparedOrders),
            ));
            if (!$result->succeeded) {
                throw new PaymentFailed($result->failureReason ?? 'De testbetaling is mislukt.');
            }

            $payment->recordResult($result);
            $this->payments->add($payment);
            foreach ($orders as $order) {
                $previousStatus = $order->getStatus();
                $this->orderWorkflow->apply($order, 'payment_received');
                $order->recordStatusTransition($previousStatus, $buyer);
            }
            foreach ($lockedListings as $listing) {
                $this->listingWorkflow->apply($listing, 'sell');
            }
            foreach ($reservations as $reservation) {
                $reservation->complete();
            }
            $cart->clear();

            return new CheckoutResponse($payment, $orders);
        });
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

    /**
     * A retry for the same cart contents and shipping selections keeps the
     * same provider key. New cart items receive new UUIDs, so a later
     * checkout is a distinct payment attempt.
     *
     * @param list<PreparedSellerOrder> $preparedOrders
     */
    private function idempotencyKey(ShoppingCart $cart, array $preparedOrders): string
    {
        $groups = [];
        foreach ($preparedOrders as $prepared) {
            $listingIds = array_map(
                static fn (Listing $listing): string => $listing->getId()->toRfc4122(),
                $prepared->group->getListings(),
            );
            sort($listingIds);
            $groups[] = implode(':', [
                $prepared->group->getSeller()->getId()->toRfc4122(),
                $prepared->shippingQuote->code->value,
                (string) $prepared->shippingQuote->price->amount,
                implode(',', $listingIds),
            ]);
        }
        sort($groups);

        return 'checkout_'.hash('sha256', $cart->getId()->toRfc4122().'|'.implode('|', $groups));
    }
}
