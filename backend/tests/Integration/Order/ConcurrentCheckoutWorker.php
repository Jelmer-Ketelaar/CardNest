<?php

declare(strict_types=1);

use App\Identity\Domain\Entity\User;
use App\Kernel;
use App\Marketplace\Domain\Exception\ListingUnavailable;
use App\Order\Application\CheckoutCart;
use App\Order\Application\Dto\CheckoutCartRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Uid\Uuid;

require dirname(__DIR__, 3).'/vendor/autoload.php';

if ($argc !== 5) {
    fwrite(STDERR, "Expected buyer ID, seller ID, ready-file path, and application name.\n");
    exit(2);
}

[, $buyerId, $sellerId, $readyFile, $applicationName] = $argv;

try {
    if (!Uuid::isValid($buyerId) || !Uuid::isValid($sellerId)) {
        throw new InvalidArgumentException('The worker requires valid buyer and seller UUIDs.');
    }
    if (preg_match('/\\A[a-zA-Z0-9_-]{1,63}\\z/', $applicationName) !== 1) {
        throw new InvalidArgumentException('The worker application name is invalid.');
    }

    (new Dotenv())->bootEnv(dirname(__DIR__, 3).'/.env');
    $kernel = new Kernel('test', true);
    $kernel->boot();

    /** @var Symfony\Bundle\FrameworkBundle\Test\TestContainer $container */
    $container = $kernel->getContainer()->get('test.service_container');
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(EntityManagerInterface::class);
    /** @var CheckoutCart $checkout */
    $checkout = $container->get(CheckoutCart::class);
    $entityManager->getConnection()->executeStatement(
        "SELECT set_config('application_name', :applicationName, false)",
        ['applicationName' => $applicationName],
    );

    $buyer = $entityManager->find(User::class, Uuid::fromString($buyerId));
    if (!$buyer instanceof User) {
        throw new RuntimeException('The concurrent checkout buyer no longer exists.');
    }

    if (file_put_contents($readyFile, 'ready') === false) {
        throw new RuntimeException('The concurrent checkout worker could not signal readiness.');
    }

    $checkout->checkout($buyer, new CheckoutCartRequest(
        [$sellerId => 'letter_mail'],
        'Concurrent buyer',
        'Teststraat 1',
        null,
        '1234 AB',
        'Utrecht',
        'NL',
    ));

    $result = ['outcome' => 'succeeded'];
} catch (ListingUnavailable) {
    $result = ['outcome' => 'unavailable'];
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
