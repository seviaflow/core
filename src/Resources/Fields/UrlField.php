<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use SeviaFlow\Core\Resources\ResourceContext;

final class UrlField extends Field
{
    protected static string $type = 'url';

    protected function defaultApiSchema(): array
    {
        return ['type' => 'string', 'format' => 'uri', 'nullable' => true];
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return ['url'];
    }
}
