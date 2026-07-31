<?php

declare(strict_types=1);

namespace App\Binder\Domain\Entity;

enum BinderPageSide: string
{
    case Front = 'front';
    case Back = 'back';
}
