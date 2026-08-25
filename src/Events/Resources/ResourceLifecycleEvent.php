<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Events\Resources;

use SeviaFlow\Core\Resources\ResourceEventContext;

abstract class ResourceLifecycleEvent
{
    public function __construct(
        public readonly mixed $record,
        public readonly ResourceEventContext $context
    ) {}
}
