<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use SeviaFlow\Core\Resources\ResourceContext;

final class ToggleField extends Field
{
    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return ['boolean'];
    }
}
