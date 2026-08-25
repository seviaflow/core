<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Support\Cache;

interface CacheParticipant
{
    public function name(): string;

    public function warm(): CacheOperationResult;

    public function clear(): CacheOperationResult;
}
