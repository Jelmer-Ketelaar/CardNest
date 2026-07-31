<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Binder\Application\Exception\BinderNotFound;
use App\Binder\Application\Exception\BinderSlotNotFound;
use App\Binder\Domain\Exception\BinderSlotOccupied;
use App\Binder\Domain\Exception\BinderSlotReservationMismatch;
use App\Binder\Domain\Exception\InvalidBinderConfiguration;
use App\Collection\Application\Exception\CardVariantUnavailable;
use App\Collection\Application\Exception\CatalogCardUnavailable;
use App\Collection\Application\Exception\CollectionItemNotFound;
use App\Collection\Domain\Exception\ArchivedCollectionItemCannotBeChanged;
use App\Collection\Domain\Exception\CardVariantDoesNotBelongToCard;
use App\Collection\Domain\Exception\ListedCollectionItemCannotBeChanged;
use App\Identity\Application\Exception\EmailAlreadyRegistered;
use App\Identity\Application\Exception\InvalidCredentials;
use App\Marketplace\Application\Exception\ListingNotFound;
use App\Marketplace\Domain\Exception\InvalidListing;
use App\Marketplace\Domain\Exception\InvalidListingTransition;
use App\Marketplace\Domain\Exception\ListingAlreadyExists;
use App\Storage\Application\Exception\StorageLocationNotFound;
use App\Storage\Domain\Exception\InvalidStorageHierarchy;
use App\Storage\Domain\Exception\InvalidStorageTarget;
use App\Storage\Domain\Exception\StorageOwnerMismatch;
use App\Wishlist\Application\Exception\WishlistCardUnavailable;
use App\Wishlist\Application\Exception\WishlistItemNotFound;
use App\Wishlist\Application\Exception\WishlistNotFound;
use App\Wishlist\Application\Exception\WishlistVariantUnavailable;
use App\Wishlist\Domain\Exception\InvalidWishlist;
use App\Wishlist\Domain\Exception\InvalidWishlistItem;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

#[AsEventListener(event: 'kernel.exception')]
final readonly class JsonExceptionSubscriber
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        [$status, $code, $message] = match (true) {
            $exception instanceof EmailAlreadyRegistered => [409, 'email_already_registered', $exception->getMessage()],
            $exception instanceof InvalidCredentials => [401, 'invalid_credentials', $exception->getMessage()],
            $exception instanceof CollectionItemNotFound => [404, 'collection_item_not_found', $exception->getMessage()],
            $exception instanceof CatalogCardUnavailable => [422, 'catalog_card_unavailable', $exception->getMessage()],
            $exception instanceof CardVariantUnavailable,
            $exception instanceof CardVariantDoesNotBelongToCard => [422, 'card_variant_unavailable', $exception->getMessage()],
            $exception instanceof ArchivedCollectionItemCannotBeChanged => [409, 'collection_item_archived', $exception->getMessage()],
            $exception instanceof ListedCollectionItemCannotBeChanged => [409, 'collection_item_listed', $exception->getMessage()],
            $exception instanceof StorageLocationNotFound => [404, 'storage_location_not_found', $exception->getMessage()],
            $exception instanceof InvalidStorageHierarchy,
            $exception instanceof InvalidStorageTarget,
            $exception instanceof StorageOwnerMismatch => [422, 'invalid_storage_hierarchy', $exception->getMessage()],
            $exception instanceof BinderNotFound,
            $exception instanceof BinderSlotNotFound => [404, 'binder_not_found', $exception->getMessage()],
            $exception instanceof BinderSlotOccupied => [409, 'binder_slot_occupied', $exception->getMessage()],
            $exception instanceof BinderSlotReservationMismatch => [409, 'binder_slot_reserved', $exception->getMessage()],
            $exception instanceof InvalidBinderConfiguration => [422, 'invalid_binder_configuration', $exception->getMessage()],
            $exception instanceof WishlistNotFound => [404, 'wishlist_not_found', $exception->getMessage()],
            $exception instanceof WishlistItemNotFound => [404, 'wishlist_item_not_found', $exception->getMessage()],
            $exception instanceof WishlistCardUnavailable => [422, 'catalog_card_unavailable', $exception->getMessage()],
            $exception instanceof WishlistVariantUnavailable => [422, 'card_variant_unavailable', $exception->getMessage()],
            $exception instanceof InvalidWishlist,
            $exception instanceof InvalidWishlistItem => [422, 'invalid_wishlist', $exception->getMessage()],
            $exception instanceof ListingNotFound => [404, 'listing_not_found', $exception->getMessage()],
            $exception instanceof ListingAlreadyExists => [409, 'listing_already_exists', $exception->getMessage()],
            $exception instanceof InvalidListingTransition => [409, 'invalid_listing_transition', $exception->getMessage()],
            $exception instanceof InvalidListing => [422, 'invalid_listing', $exception->getMessage()],
            $exception instanceof HttpExceptionInterface => [$exception->getStatusCode(), 'request_failed', $exception->getMessage()],
            default => [500, 'internal_error', 'Er is een onverwachte fout opgetreden.'],
        };

        if ($status >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            $this->logger->error('Unhandled API exception.', ['exception' => $exception]);
        }

        $details = $this->validationDetails($exception);
        $error = ['code' => $code, 'message' => '' !== $message ? $message : Response::$statusTexts[$status]];
        if ([] !== $details) {
            $error = [
                'code' => 'validation_failed',
                'message' => 'Controleer de ingevulde gegevens.',
                'details' => $details,
            ];
        }

        $event->setResponse(new JsonResponse(['error' => $error], $status));
    }

    /** @return array<string, list<string>> */
    private function validationDetails(Throwable $exception): array
    {
        $validation = $exception instanceof ValidationFailedException ? $exception : $exception->getPrevious();
        if (!$validation instanceof ValidationFailedException) {
            return [];
        }

        $details = [];
        foreach ($validation->getViolations() as $violation) {
            $details[$violation->getPropertyPath()][] = (string) $violation->getMessage();
        }

        return $details;
    }
}
