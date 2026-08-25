<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Support\Capabilities;

final class Capability
{
    public function __construct(
        public readonly string $name,
        public readonly bool $enabled = true,
        public readonly ?string $description = null
    ) {}
}
