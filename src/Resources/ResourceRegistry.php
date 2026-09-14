<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use SeviaFlow\Core\Exceptions\InvalidResourceClassException;
use SeviaFlow\Core\Exceptions\ResourceSlugNotRegisteredException;

final class ResourceRegistry
{
    /**
     * @param  array<string, mixed>  $resources
     */
    public function __construct(
        private readonly array $resources
    ) {}

    /**
     * @return class-string<\SeviaFlow\Core\Resources\Resource>
     */
    public function resolve(string $slug): string
    {
        if (! array_key_exists($slug, $this->resources)) {
            throw ResourceSlugNotRegisteredException::forSlug($slug);
        }

        $resourceClass = $this->resources[$slug];

        if (! is_string($resourceClass) || ! is_a($resourceClass, Resource::class, true)) {
            throw InvalidResourceClassException::forSlug($slug, $resourceClass);
        }

        /** @var class-string<\SeviaFlow\Core\Resources\Resource> $resourceClass */
        return $resourceClass;
    }

    /**
     * @return array<string, class-string<\SeviaFlow\Core\Resources\Resource>>
     */
    public function all(): array
    {
        $resolved = [];

        foreach (array_keys($this->resources) as $slug) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }

            $resolved[$slug] = $this->resolve($slug);
        }

        return $resolved;
    }

    /**
     * @param  class-string  $modelClass
     * @return class-string<\SeviaFlow\Core\Resources\Resource>|null
     */
    public function findByModel(string $modelClass): ?string
    {
        foreach ($this->all() as $resourceClass) {
            if ($resourceClass::model() === $modelClass) {
                return $resourceClass;
            }
        }

        return null;
    }
}
