<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Console;

use App\Catalog\Application\Dto\ImportCatalogRequest;
use App\Catalog\Application\ImportCatalog;
use App\Catalog\Domain\Entity\CatalogImportStatus;
use App\Catalog\Domain\Entity\CatalogLanguage;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;

#[AsCommand(
    name: 'catalog:import:tcgdex',
    description: 'Import available card sets and cards from TCGdex.',
)]
final class ImportTcgDexCatalogCommand extends Command
{
    public function __construct(
        private readonly ImportCatalog $importCatalog,
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
                'One or more TCGdex language codes. Omit to import every supported language.',
            )
            ->addOption(
                'set',
                's',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'One or more external set identifiers. Omit to import every set.',
            )
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of cards to flush before releasing Doctrine memory.',
                '100',
            )
            ->addOption(
                'with-card-details',
                null,
                InputOption::VALUE_NONE,
                'Fetch full source payloads per card. This is substantially slower than the standard catalog import.',
            )
            ->addOption(
                'source-version',
                null,
                InputOption::VALUE_REQUIRED,
                'Optional immutable source release identifier supplied by an approved import process.',
            )
            ->addOption(
                'data-cutoff',
                null,
                InputOption::VALUE_REQUIRED,
                'Optional ISO-8601 cutoff timestamp for the supplied source release.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var list<string> $requestedLanguages */
        $requestedLanguages = $input->getOption('language');
        /** @var list<string> $setIds */
        $setIds = $input->getOption('set');
        $languages = $requestedLanguages === []
            ? CatalogLanguage::values()
            : array_values(array_unique($requestedLanguages));
        $batchSize = filter_var($input->getOption('batch-size'), FILTER_VALIDATE_INT);
        $sourceVersion = $this->optionalString($input->getOption('source-version'));
        $dataCutoffValue = $this->optionalString($input->getOption('data-cutoff'));
        try {
            $dataCutoff = $this->dataCutoff($dataCutoffValue);
        } catch (InvalidArgumentException) {
            $io->error('The --data-cutoff option must be a valid ISO-8601 timestamp.');

            return Command::INVALID;
        }

        if ($batchSize === false || $batchSize < 1 || $batchSize > 1_000) {
            $io->error('The --batch-size option must be a whole number between 1 and 1000.');

            return Command::INVALID;
        }

        foreach ($languages as $language) {
            if (CatalogLanguage::tryFrom($language) !== null) {
                continue;
            }

            $io->error(sprintf('Unsupported TCGdex language "%s".', $language));

            return Command::INVALID;
        }

        if ($languages === []) {
            $io->error('At least one catalog language must be selected.');

            return Command::INVALID;
        }

        /* @var non-empty-list<string> $languages */

        if ($this->kernel->isDebug() && $setIds === []) {
            $io->error(
                'Run an all-set catalog import with --no-debug to prevent development query profiling '
                .'from exhausting memory.',
            );

            return Command::INVALID;
        }

        try {
            $result = $this->importCatalog->execute(
                new ImportCatalogRequest(
                    languages: $languages,
                    onlySetIds: array_values(array_unique($setIds)),
                    batchSize: $batchSize,
                    includeCardDetails: $input->getOption('with-card-details') === true,
                    sourceVersion: $sourceVersion,
                    dataCutoff: $dataCutoff,
                ),
                static function (string $language, string $setId, int $cardCount) use ($io): void {
                    $io->writeln(sprintf('[%s] %s: %d cards imported or synchronized.', $language, $setId, $cardCount));
                },
            );
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($result->getRunStatus() === CatalogImportStatus::Skipped) {
            $io->success('Catalog import skipped: this exact provider scope and content was already published.');
        } else {
            $io->success(sprintf(
                'Catalog import completed: %d new sets, %d existing sets, %d new cards, %d synchronized cards.',
                $result->getCreatedSets(),
                $result->getExistingSets(),
                $result->getCreatedCards(),
                $result->getSyncedCards(),
            ));
        }
        $runStatus = $result->getRunStatus();
        $io->definitionList(
            ['Run ID' => $result->getRunId() ?? 'unavailable'],
            ['Status' => $runStatus instanceof CatalogImportStatus ? $runStatus->value : 'unavailable'],
            ['Content checksum' => $result->getContentChecksum() ?? 'unavailable'],
            ['Source version' => $sourceVersion ?? 'not provided by TCGdex'],
            ['Data cutoff' => $dataCutoff?->format(DATE_ATOM) ?? 'not provided by TCGdex'],
        );

        return Command::SUCCESS;
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function dataCutoff(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $dataCutoff = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (!$dataCutoff instanceof DateTimeImmutable
            || (is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
            throw new InvalidArgumentException('Invalid catalog source data cutoff.');
        }

        return $dataCutoff;
    }
}
