<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use SeviaFlow\Core\Resources\ResourceContext;

final class DateTimeField extends Field
{
    protected static string $type = 'datetime';

    protected function defaultApiSchema(): array
    {
        return ['type' => 'string', 'format' => 'date-time', 'nullable' => true];
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
