<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Console;

use App\Catalog\Application\Dto\ImportCatalogArtistsRequest;
use App\Catalog\Application\ImportCatalogArtists;
use App\Catalog\Domain\Entity\CatalogLanguage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;

#[AsCommand(
    name: 'catalog:import:tcgdex-artists',
    description: 'Import TCGdex artists and link their source-backed catalog cards.',
)]
final class ImportTcgDexArtistsCommand extends Command
{
    public function __construct(
        private readonly ImportCatalogArtists $importArtists,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'language',
                'l',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'One or more imported TCGdex languages. Omit to use languages already in the local catalog.',
            )
            ->addOption(
                'artist',
                'a',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'One or more exact source artist names for a targeted retry.',
            )
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of source card references to reconcile per database flush.',
                '100',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var list<string> $requestedLanguages */
        $requestedLanguages = $input->getOption('language');
        /** @var list<string> $requestedArtists */
        $requestedArtists = $input->getOption('artist');
        $languages = $requestedLanguages === []
            ? $this->importArtists->availableLanguages()
            : array_values(array_unique($requestedLanguages));
        $artistNames = array_values(array_unique(array_filter(
            array_map(static fn (string $name): string => trim($name), $requestedArtists),
            static fn (string $name): bool => $name !== '',
        )));
        $batchSize = filter_var($input->getOption('batch-size'), FILTER_VALIDATE_INT);

        if ($batchSize === false || $batchSize < 1 || $batchSize > 500) {
            $io->error('The --batch-size option must be a whole number between 1 and 500.');

            return Command::INVALID;
        }
        if ($languages === []) {
            $io->error('No catalog languages are available. Import cards first or pass --language.');

            return Command::INVALID;
        }

        foreach ($languages as $language) {
            if (CatalogLanguage::tryFrom($language) !== null) {
                continue;
            }

            $io->error(sprintf('Unsupported TCGdex language "%s".', $language));

            return Command::INVALID;
        }

        if ($this->kernel->isDebug() && $artistNames === []) {
            $io->error('Run an all-artist import with --no-debug to prevent development query profiling from exhausting memory.');

            return Command::INVALID;
        }

        $startedAt = microtime(true);
        $processedArtists = 0;
        $lastProcessedGroup = null;
        try {
            $result = $this->importArtists->execute(
                new ImportCatalogArtistsRequest(
                    languages: $languages,
                    onlyArtistNames: $artistNames,
                    batchSize: $batchSize,
                ),
                static function (string $language, string $artistName, int $cardCount) use (
                    $io,
                    &$processedArtists,
                    &$lastProcessedGroup,
                ): void {
                    ++$processedArtists;
                    $lastProcessedGroup = sprintf('%s:%s', $language, $artistName);
                    if ($processedArtists === 1 || $processedArtists % 25 === 0) {
                        $io->writeln(sprintf(
                            '[%s] %d artist groups processed; latest: %s (%d source cards).',
                            $language,
                            $processedArtists,
                            $artistName,
                            $cardCount,
                        ));
                    }
                },
            );
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());
            $io->note('Completed batches are retained. Re-run the command safely or use --artist for a targeted retry.');

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Artist import completed: %d source groups, %d created artists, %d existing groups, '
            .'%d new links, %d existing links, %d legacy backfills, %d missing cards, %d conflicts.',
            $result->getProcessedArtists(),
            $result->getCreatedArtists(),
            $result->getExistingArtists(),
            $result->getLinkedCards(),
            $result->getExistingCardLinks(),
            $result->getBackfilledCards(),
            $result->getMissingCards(),
            $result->getConflictingCards(),
        ));
        $io->writeln(sprintf(
            'Provider references evaluated: %d.',
            $result->getProviderCardReferences(),
        ));
        $io->writeln(sprintf('Duration: %.2f seconds.', microtime(true) - $startedAt));
        $io->writeln(sprintf('Last source group: %s.', $lastProcessedGroup ?? 'none'));
        $io->writeln('Provider data cutoff: unavailable; TCGdex does not expose a version or cutoff for this response.');

        if ($result->getMissingCards() > 0) {
            $io->warning(sprintf(
                'Some source cards are not present locally. Samples: %s',
                implode(', ', $result->getMissingCardSamples()),
            ));
        }
        if ($result->getConflictingCards() > 0) {
            $io->warning(sprintf(
                'Some source attributions conflict with stored illustrator values. Samples: %s',
                implode(', ', $result->getConflictingCardSamples()),
            ));
        }

        return Command::SUCCESS;
    }
}
