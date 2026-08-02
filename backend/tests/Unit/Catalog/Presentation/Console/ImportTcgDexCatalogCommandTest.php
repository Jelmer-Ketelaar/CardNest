<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Presentation\Console;

use App\Catalog\Application\Import\CatalogImportStore;
use App\Catalog\Application\ImportCatalog;
use App\Catalog\Domain\Provider\CardCatalogProvider;
use App\Catalog\Presentation\Console\ImportTcgDexCatalogCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class ImportTcgDexCatalogCommandTest extends TestCase
{
    public function testItRequiresNoDebugForAnAllSetImportInDevelopment(): void
    {
        // Arrange
        $provider = $this->createStub(CardCatalogProvider::class);
        $store = $this->createStub(CatalogImportStore::class);
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('isDebug')->willReturn(true);
        $command = new ImportTcgDexCatalogCommand(new ImportCatalog($provider, $store), $kernel);
        $tester = new CommandTester($command);

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('--no-debug', $tester->getDisplay());
    }
}
