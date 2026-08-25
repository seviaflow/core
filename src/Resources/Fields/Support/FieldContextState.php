<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields\Support;

use SeviaFlow\Core\Resources\ResourceContext;

final class FieldContextState
{
    /**
     * @var array<string, bool>
     */
    private array $state = [];

    /**
     * @param  ResourceContext|array<int, ResourceContext|string>|string  $contexts
     */
    public function set(ResourceContext|array|string $contexts, bool $value): void
    {
        foreach (FieldContextNormalizer::many($contexts) as $context) {
            $this->state[$context->value] = $value;
        }
    }

    public function setForContext(ResourceContext $context, bool $value): void
    {
        $this->state[$context->value] = $value;
    }

    public function active(ResourceContext|string $context): bool
    {
        $normalized = FieldContextNormalizer::one($context);

        return $this->state[$normalized->value] ?? false;
    }
}
