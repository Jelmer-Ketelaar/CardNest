<?php

declare(strict_types=1);

namespace App\Marketplace\Domain\ValueObject;

use App\Marketplace\Domain\Entity\ListingImageSide;
use App\Marketplace\Domain\Exception\InvalidListing;

final readonly class ListingPhotoReference
{
    public string $url;

    public function __construct(
        public ListingImageSide $side,
        string $url,
    ) {
        $url = trim($url);
        if (mb_strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidListing('Een verkoopfoto moet een geldige URL zijn.');
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme !== 'https') {
            throw new InvalidListing('Een verkoopfoto moet via HTTPS beschikbaar zijn.');
        }

        $this->url = $url;
    }
}
