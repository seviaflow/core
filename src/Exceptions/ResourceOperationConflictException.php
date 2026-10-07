<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Exceptions;

use LogicException;

final class ResourceOperationConflictException extends LogicException
{
    public static function staleRevision(): self
    {
        return new self('The record changed while this operation was being prepared. Refresh the record and try again.');
    }

    public static function unavailable(): self
    {
        return new self('The record is no longer available in the current resource scope. Refresh the record and try again.');
    }
}
