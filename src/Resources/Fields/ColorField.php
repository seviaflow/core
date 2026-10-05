<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\ResourceContext;

final class ColorField extends Field
{
    protected static string $type = 'color';

    protected function defaultApiSchema(): array
    {
        return [
            'type' => 'string',
            'pattern' => '^#[0-9A-Fa-f]{6}$',
            'nullable' => true,
            'x-seviaflow-color' => 'hex6',
        ];
    }

    public function normalizeValue(mixed $value, ?Model $model = null): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_scalar($value)) {
            return $value;
        }

        if (trim((string) $value) === '') {
            return null;
        }

        return strtoupper(trim((string) $value));
    }

    public function prepareForValidation(mixed $value): mixed
    {
        return $this->normalizeValue($value);
    }

    public function prepareForImport(mixed $value): mixed
    {
        return $this->prepareForValidation($value);
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return ['regex:/^#[0-9A-Fa-f]{6}$/'];
    }
}
