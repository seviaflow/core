<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\ResourceContext;

final class ToggleField extends Field
{
    protected static string $type = 'toggle';

    protected function defaultApiSchema(): array
    {
        return ['type' => 'boolean', 'nullable' => true];
    }

    public function normalizeValue(mixed $value, ?Model $model = null): mixed
    {
        return $this->parseBoolean($value) ?? (bool) $value;
    }

    public function prepareForValidation(mixed $value): mixed
    {
        $boolean = $this->parseBoolean($value);

        return $boolean === null ? $value : $boolean;
    }

    private function parseBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }

        if (! is_string($value)) {
            return null;
        }

        return match (strtolower(trim($value))) {
            '1', 'true', 'on', 'yes' => true,
            '0', 'false', 'off', 'no' => false,
            default => null,
        };
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return ['boolean'];
    }
}
