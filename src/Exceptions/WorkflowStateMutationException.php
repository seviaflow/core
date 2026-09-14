<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Exceptions;

use LogicException;

final class WorkflowStateMutationException extends LogicException
{
    public static function forField(string $field): self
    {
        return new self(sprintf(
            'The [%s] field is workflow-controlled and must be changed through a workflow transition.',
            $field
        ));
    }
}
