<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Exceptions;

use InvalidArgumentException;

final class ResourceSlugNotRegisteredException extends InvalidArgumentException
{
    public static function forSlug(string $slug): self
    {
        return new self(sprintf('Resource slug "%s" is not registered.', $slug));
    }
}
