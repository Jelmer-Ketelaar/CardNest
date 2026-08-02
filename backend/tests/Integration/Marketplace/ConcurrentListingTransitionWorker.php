<?php

declare(strict_types=1);

use App\Identity\Domain\Entity\User;
use App\Kernel;
use App\Marketplace\Application\CancelListing;
use App\Marketplace\Application\Message\ExpireListing;
use App\Marketplace\Application\Message\ExpireListingHandler;
use App\Marketplace\Domain\Exception\InvalidListingTransition;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Uid\Uuid;

require dirname(__DIR__, 3).'/vendor/autoload.php';

/** @var list<string> $arguments */
$arguments = $_SERVER['argv'] ?? [];
if (count($arguments) !== 6) {
    fwrite(STDERR, "Expected action, listing ID, seller ID, ready-file path, and application name.\n");
    exit(2);
}

[, $action, $listingId, $sellerId, $readyFile, $applicationName] = $arguments;

try {
    if (!in_array($action, ['cancel', 'expire'], true)) {
        throw new InvalidArgumentException('The worker action is invalid.');
    }
    if (!Uuid::isValid($listingId) || !Uuid::isValid($sellerId)) {
        throw new InvalidArgumentException('The worker requires valid listing and seller UUIDs.');
    }
    if (preg_match('/\A[a-zA-Z0-9_-]{1,63}\z/', $applicationName) !== 1) {
        throw new InvalidArgumentException('The worker application name is invalid.');
    }

    (new Dotenv())->bootEnv(dirname(__DIR__, 3).'/.env');
    $kernel = new Kernel('test', true);
    $kernel->boot();

    /** @var Symfony\Bundle\FrameworkBundle\Test\TestContainer $container */
    $container = $kernel->getContainer()->get('test.service_container');
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(EntityManagerInterface::class);
    $entityManager->getConnection()->executeStatement(
        "SELECT set_config('application_name', :applicationName, false)",
        ['applicationName' => $applicationName],
    );

    $seller = $entityManager->find(User::class, Uuid::fromString($sellerId));
    if (!$seller instanceof User) {
        throw new RuntimeException('The listing transition seller no longer exists.');
    }

    if (file_put_contents($readyFile, 'ready') === false) {
        throw new RuntimeException('The listing transition worker could not signal readiness.');
    }

    if ($action === 'cancel') {
        /** @var CancelListing $cancelListing */
        $cancelListing = $container->get(CancelListing::class);
        try {
            $cancelListing->cancel($seller, $listingId);
            $result = ['outcome' => 'cancelled'];
        } catch (InvalidListingTransition $exception) {
            $result = [
                'outcome' => 'conflict',
                'message' => $exception->getMessage(),
            ];
        }
    } else {
        /** @var ExpireListingHandler $expireListing */
        $expireListing = $container->get(ExpireListingHandler::class);
        $expireListing(new ExpireListing($listingId));
        $result = ['outcome' => 'completed'];
    }
} catch (Throwable $exception) {
    $result = [
        'outcome' => 'failed',
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ];
} finally {
    if (isset($kernel)) {
        $kernel->shutdown();
    }
}

echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;

exit($result['outcome'] === 'failed' ? 1 : 0);
