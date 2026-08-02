<?php

declare(strict_types=1);

namespace App\Pricing\Application;

enum PriceRefreshStatus: string
{
    case Refreshed = 'refreshed';
    case Current = 'current';
    case NoData = 'no_data';
    case Unsupported = 'unsupported';
    case NotFound = 'not_found';
    case RefreshFailed = 'refresh_failed';
}
