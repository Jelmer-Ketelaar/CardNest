<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Collection\Application\Exception\CardVariantUnavailable;
use App\Collection\Application\Exception\CatalogCardUnavailable;
use App\Collection\Application\Exception\CollectionItemNotFound;
use App\Collection\Domain\Exception\ArchivedCollectionItemCannotBeChanged;
use App\Collection\Domain\Exception\CardVariantDoesNotBelongToCard;
use App\Identity\Application\Exception\EmailAlreadyRegistered;
use App\Identity\Application\Exception\InvalidCredentials;
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
