<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Exceptions;

use LogicException;

final class WorkflowTransitionConflictException extends LogicException
{
    public function __construct()
    {
        parent::__construct('The record changed while this transition was being prepared. Refresh and try again.');
    }
}
