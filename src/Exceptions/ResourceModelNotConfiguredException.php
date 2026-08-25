<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Exceptions;

use LogicException;

final class ResourceModelNotConfiguredException extends LogicException
{
    public static function forResource(string $resourceClass): self
    {
        return new self(sprintf(
            'Resource [%s] must define a non-empty static $model property.',
            $resourceClass
        ));
    }
}
