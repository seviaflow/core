<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Exceptions;

use LogicException;

final class ResourceOperationAuthorizationException extends LogicException
{
    public function __construct()
    {
        parent::__construct('You are not authorized to update this record.');
    }
}
