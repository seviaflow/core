<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Metadata;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use SeviaFlow\Core\Resources\ResourceRegistry;

final class ResourceMetadataCache
{
    public function __construct(
        private readonly ResourceRegistry $resourceRegistry,
        private readonly ResourceMetadataResolver $resolver = new ResourceMetadataResolver
    ) {}

    public function forSlug(string $slug): ResourceMetadataSnapshot
    {
        $resourceClass = $this->resourceRegistry->resolve($slug);

        if (! $this->isEnabled()) {
            return $this->resolver->resolve($slug, $resourceClass);
        }

        $cache = $this->cache();

        $key = $this->cacheKey($slug, $resourceClass);
        /** @var array<string, mixed> $cached */
        $cached = $cache->remember($key, $this->ttl(), fn (): array => $this->resolver->resolve($slug, $resourceClass)->toArray());

        return new ResourceMetadataSnapshot($cached);
    }

    /**
     * @return array<string, ResourceMetadataSnapshot>
     */
    public function all(): array
    {
        $metadata = [];

        foreach ($this->resourceRegistry->all() as $slug => $resourceClass) {
            $metadata[$slug] = $this->forSlug($slug);
        }

        return $metadata;
    }

    public function clear(): void
    {
        $cache = $this->cache();

        foreach ($this->resourceRegistry->all() as $slug => $resourceClass) {
            $cache->forget($this->cacheKey($slug, $resourceClass));
        }
    }

    private function isEnabled(): bool
    {
        return (bool) config('seviaflow.performance.cache.enabled', false)
            && (bool) config('seviaflow.performance.cache.resources', true);
    }

    private function ttl(): int
    {
        return max(1, (int) config('seviaflow.performance.cache.ttl', 3600));
    }

    private function cacheKey(string $slug, string $resourceClass): string
    {
        return sprintf('seviaflow:resource-metadata:%s:%s', $slug, sha1($resourceClass));
    }

    private function cache(): CacheRepository
    {
        $store = config('seviaflow.performance.cache.store');
        if (! is_string($store) || trim($store) === '') {
            return cache()->store();
        }

        return cache()->store($store);
    }
}
