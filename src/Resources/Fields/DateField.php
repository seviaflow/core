<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use SeviaFlow\Core\Resources\ResourceContext;

final class DateField extends Field
{
    protected static string $type = 'date';

    protected function defaultApiSchema(): array
    {
        return ['type' => 'string', 'format' => 'date', 'nullable' => true];
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return ['date'];
    }
}
