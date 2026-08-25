<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields\Support;

use InvalidArgumentException;
use SeviaFlow\Core\Resources\ResourceContext;

final class FieldContextNormalizer
{
    /**
     * @param  ResourceContext|array<int, ResourceContext|string>|string  $contexts
     * @return array<int, ResourceContext>
     */
    public static function many(ResourceContext|array|string $contexts): array
    {
        if (! is_array($contexts)) {
            return [self::one($contexts)];
        }

        if ($contexts === []) {
            throw new InvalidArgumentException('At least one resource context must be provided.');
        }

        return array_map(
            static fn (ResourceContext|string $context): ResourceContext => self::one($context),
            $contexts
        );
    }

    public static function one(ResourceContext|string $context): ResourceContext
    {
        if ($context instanceof ResourceContext) {
            return $context;
        }

        $normalized = ResourceContext::tryFrom(strtolower(trim($context)));

        if ($normalized === null) {
            throw new InvalidArgumentException(sprintf(
                'Invalid resource context [%s]. Allowed values: %s.',
                $context,
                implode(', ', array_map(
                    static fn (ResourceContext $allowed): string => $allowed->value,
                    ResourceContext::cases()
                ))
            ));
        }

        return $normalized;
    }
}
