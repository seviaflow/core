<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields\Support;

use SeviaFlow\Core\Resources\ResourceContext;

final class FieldVisibilityRules
{
    /**
     * @var array<string, bool>
     */
    private array $visibility = [
        ResourceContext::Create->value => true,
        ResourceContext::Edit->value => true,
        ResourceContext::Show->value => true,
        ResourceContext::Index->value => false,
    ];

    /**
     * @param  ResourceContext|array<int, ResourceContext|string>|string  $contexts
     */
    public function visibleOn(ResourceContext|array|string $contexts): void
    {
        foreach (ResourceContext::cases() as $context) {
            $this->visibility[$context->value] = false;
        }

        foreach (FieldContextNormalizer::many($contexts) as $context) {
            $this->visibility[$context->value] = true;
        }
    }

    /**
     * @param  ResourceContext|array<int, ResourceContext|string>|string  $contexts
     */
    public function hiddenOn(ResourceContext|array|string $contexts): void
    {
        foreach (FieldContextNormalizer::many($contexts) as $context) {
            $this->visibility[$context->value] = false;
        }
    }

    public function setForContext(ResourceContext $context, bool $visible): void
    {
        $this->visibility[$context->value] = $visible;
    }

    public function isVisible(ResourceContext|string $context): bool
    {
        $normalized = FieldContextNormalizer::one($context);

        return $this->visibility[$normalized->value];
    }
}
