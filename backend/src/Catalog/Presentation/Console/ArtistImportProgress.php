<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Console;

final class ArtistImportProgress
{
    private int $processedGroups = 0;

    private ?string $lastSourceGroup = null;

    public function record(string $language, string $artistName): int
    {
        ++$this->processedGroups;
        $this->lastSourceGroup = sprintf('%s:%s', $language, $artistName);

        return $this->processedGroups;
    }

    public function getLastSourceGroup(): ?string
    {
        return $this->lastSourceGroup;
    }
}
