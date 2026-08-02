<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Console;

use App\Pricing\Application\PriceRefreshStatus;
use App\Pricing\Application\RefreshCardPrices;
use App\Pricing\Domain\Provider\PriceProviderException;
use App\Pricing\Domain\Repository\TrackedPriceCardRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pricing:refresh',
    description: 'Refresh Cardmarket snapshots for one card or every tracked card.',
)]
final class RefreshCardPricesCommand extends Command
{
    public function __construct(
        private readonly RefreshCardPrices $refresher,
        private readonly TrackedPriceCardRepository $trackedCards,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('card-id', InputArgument::OPTIONAL, 'Catalog card UUID; omit it to refresh tracked cards.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Replace an existing snapshot for today.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cardId = $input->getArgument('card-id');
        if ($cardId !== null && !is_string($cardId)) {
            $io->error('The card ID must be a string.');

            return Command::INVALID;
        }

        $cardIds = $cardId !== null ? [$cardId] : $this->trackedCards->cardIds();
        $counts = [];
        $hasFailures = false;
        foreach ($cardIds as $candidateId) {
            try {
                $status = $this->refresher->refresh($candidateId, (bool) $input->getOption('force'));
                $counts[$status->value] = ($counts[$status->value] ?? 0) + 1;
            } catch (PriceProviderException $exception) {
                $hasFailures = true;
                $counts[PriceRefreshStatus::RefreshFailed->value]
                    = ($counts[PriceRefreshStatus::RefreshFailed->value] ?? 0) + 1;
                $io->warning(sprintf('Card %s failed: %s', $candidateId, $exception->getMessage()));
            }
        }

        $io->success(sprintf(
            'Price refresh finished: %s.',
            implode(', ', array_map(
                static fn (string $status, int $count): string => sprintf('%s=%d', $status, $count),
                array_keys($counts),
                array_values($counts),
            )) ?: PriceRefreshStatus::Current->value.'=0',
        ));

        return $hasFailures ? Command::FAILURE : Command::SUCCESS;
    }
}
