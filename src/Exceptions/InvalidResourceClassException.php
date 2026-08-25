<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Exceptions;

use InvalidArgumentException;

final class InvalidResourceClassException extends InvalidArgumentException
{
    public static function forSlug(string $slug, mixed $resourceClass): self
    {
        $type = is_string($resourceClass) ? $resourceClass : gettype($resourceClass);

        return new self(sprintf(
            'Resource slug "%s" must map to a class extending %s. Got: %s',
            $slug,
            \SeviaFlow\Core\Resources\Resource::class,
            $type
        ));
    }
}
