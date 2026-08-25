<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Support\Cache;

use SeviaFlow\Core\Resources\Metadata\ResourceMetadataCache;

final class ResourceMetadataCacheParticipant implements CacheParticipant
{
    public function __construct(
        private readonly ResourceMetadataCache $cache
    ) {}

    public function name(): string
    {
        return 'resource metadata';
    }

    public function warm(): CacheOperationResult
    {
        if (! $this->cacheEnabled()) {
            return CacheOperationResult::skipped('Resource metadata cache skipped (disabled)');
        }

        $this->cache->all();

        return CacheOperationResult::done('Resource metadata cached');
    }

    public function clear(): CacheOperationResult
    {
        $this->cache->clear();

        return CacheOperationResult::done('Resource metadata cache cleared');
    }

    private function cacheEnabled(): bool
    {
        return (bool) config('seviaflow.performance.cache.enabled', false)
            && (bool) config('seviaflow.performance.cache.resources', true);
    }
}
