<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\ResourceContext;

class NumberField extends Field
{
    protected static string $type = 'number';

    protected function defaultApiSchema(): array
    {
        return ['type' => 'number', 'nullable' => true];
    }

    public function normalizeValue(mixed $value, ?Model $model = null): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        return strpbrk($normalized, '.eE') !== false
            ? (float) $normalized
            : (int) $normalized;
    }

    private int|float|null $min = null;

    private int|float|null $max = null;

    public function min(int|float $value): static
    {
        $this->min = $value;

        return $this;
    }

    public function getMin(): int|float|null
    {
        return $this->min;
    }

    public function max(int|float $value): static
    {
        $this->max = $value;

        return $this;
    }

    public function getMax(): int|float|null
    {
        return $this->max;
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        $rules = ['numeric'];

        if ($this->min !== null) {
            $rules[] = 'min:'.$this->min;
        }

        if ($this->max !== null) {
            $rules[] = 'max:'.$this->max;
        }

        return $rules;
    }
}
