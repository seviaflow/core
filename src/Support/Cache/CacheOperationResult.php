<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Support\Cache;

final class CacheOperationResult
{
    private function __construct(
        public readonly string $message,
        public readonly bool $skipped = false
    ) {}

    public static function done(string $message): self
    {
        return new self($message);
    }

    public static function skipped(string $message): self
    {
        return new self($message, true);
    }
}
