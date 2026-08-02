<?php

declare(strict_types=1);

use App\Binder\Application\AssignBinderSlot;
use App\Binder\Application\ConfigureBinderSlot;
use App\Binder\Application\Dto\AssignBinderSlotRequest;
use App\Binder\Application\Dto\ConfigureBinderSlotRequest;
use App\Binder\Domain\Exception\BinderSlotOccupied;
use App\Identity\Domain\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Uid\Uuid;

require dirname(__DIR__, 3).'/vendor/autoload.php';

/** @var list<string> $arguments */
$arguments = $_SERVER['argv'] ?? [];
if (count($arguments) !== 9) {
    fwrite(
        STDERR,
        "Expected operation, owner ID, binder ID, slot ID, item ID, card ID, ready-file path, and application name.\n",
    );
    exit(2);
}

[, $operation, $ownerId, $binderId, $slotId, $itemId, $cardId, $readyFile, $applicationName] = $arguments;

try {
    if (!in_array($operation, ['assign', 'configure'], true)) {
        throw new InvalidArgumentException('The worker operation is invalid.');
    }
    foreach ([$ownerId, $binderId, $slotId, $itemId, $cardId] as $id) {
        if (!Uuid::isValid($id)) {
            throw new InvalidArgumentException('The worker requires valid UUIDs.');
        }
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
    $entityManager->getConnection()->executeStatement(
        "SELECT set_config('application_name', :applicationName, false)",
        ['applicationName' => $applicationName],
    );

    $owner = $entityManager->find(User::class, Uuid::fromString($ownerId));
    if (!$owner instanceof User) {
        throw new RuntimeException('The concurrent binder owner no longer exists.');
    }

    if (file_put_contents($readyFile, 'ready') === false) {
        throw new RuntimeException('The concurrent binder worker could not signal readiness.');
    }

    if ($operation === 'assign') {
        /** @var AssignBinderSlot $assigner */
        $assigner = $container->get(AssignBinderSlot::class);
        $assigner->assign($owner, $binderId, $slotId, new AssignBinderSlotRequest($itemId));
        $result = ['outcome' => 'assigned'];
    } else {
        /** @var ConfigureBinderSlot $configurer */
        $configurer = $container->get(ConfigureBinderSlot::class);
        $configurer->configure(
            $owner,
            $binderId,
            $slotId,
            new ConfigureBinderSlotRequest(catalogCardId: $cardId),
        );
        $result = ['outcome' => 'configured'];
    }
} catch (BinderSlotOccupied) {
    $result = ['outcome' => 'occupied'];
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
