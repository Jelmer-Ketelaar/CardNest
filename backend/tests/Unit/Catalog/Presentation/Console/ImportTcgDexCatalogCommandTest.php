<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Presentation\Console;

use App\Catalog\Application\CatalogDiscrepancyRecorder;
use App\Catalog\Application\Import\CatalogImportStagingStore;
use App\Catalog\Application\Import\CatalogImportStore;
use App\Catalog\Application\Import\CatalogImportValidator;
use App\Catalog\Application\ImportCatalog;
use App\Catalog\Domain\Provider\CardCatalogProvider;
use App\Catalog\Domain\Repository\CatalogDiscrepancyRepository;
use App\Catalog\Domain\Repository\CatalogImportRunRepository;
use App\Catalog\Presentation\Console\ImportTcgDexCatalogCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class ImportTcgDexCatalogCommandTest extends TestCase
{
    public function testItRequiresNoDebugForAnAllSetImportInDevelopment(): void
    {
        // Arrange
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('isDebug')->willReturn(true);
        $tester = new CommandTester($this->command($kernel));

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('--no-debug', $tester->getDisplay());
    }

    public function testItRejectsAnInvalidSourceDataCutoff(): void
    {
        // Arrange
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('isDebug')->willReturn(false);
        $tester = new CommandTester($this->command($kernel));

        // Act
        $exitCode = $tester->execute([
            '--language' => ['en'],
            '--data-cutoff' => 'tomorrow',
        ]);

        // Assert
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('valid ISO-8601', $tester->getDisplay());
    }

    private function command(KernelInterface $kernel): ImportTcgDexCatalogCommand
    {
        return new ImportTcgDexCatalogCommand(new ImportCatalog(
            $this->createStub(CardCatalogProvider::class),
            $this->createStub(CatalogImportStore::class),
            $this->createStub(CatalogImportStagingStore::class),
            $this->createStub(CatalogImportRunRepository::class),
            new CatalogImportValidator(),
            new CatalogDiscrepancyRecorder($this->createStub(CatalogDiscrepancyRepository::class)),
            new LockFactory(new InMemoryStore()),
        ), $kernel);
    }
}
