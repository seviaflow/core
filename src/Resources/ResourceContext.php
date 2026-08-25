<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

enum ResourceContext: string
{
    case Index = 'index';
    case Show = 'show';
    case Create = 'create';
    case Edit = 'edit';
}
