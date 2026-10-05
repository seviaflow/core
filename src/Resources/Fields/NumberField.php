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
        $schema = ['type' => 'number', 'nullable' => true];

        if ($this->precision !== null) {
            $schema['multipleOf'] = 1 / (10 ** $this->precision);
        }

        return $schema;
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

    private int|float|string|null $step = null;

    private ?int $precision = null;

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

    public function step(int|float|string $value): static
    {
        if ((float) $value <= 0) {
            throw new \InvalidArgumentException('NumberField step must be greater than zero.');
        }

        $this->step = $value;

        return $this;
    }

    public function getStep(): int|float|string|null
    {
        if ($this->step !== null) {
            return $this->step;
        }

        return $this->precision === null ? null : 1 / (10 ** $this->precision);
    }

    public function precision(int $precision): static
    {
        if ($precision < 0 || $precision > 8) {
            throw new \InvalidArgumentException('NumberField precision must be between 0 and 8.');
        }

        $this->precision = $precision;

        return $this;
    }

    public function getPrecision(): ?int
    {
        return $this->precision;
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

        if ($this->precision !== null) {
            $rules[] = 'decimal:0,'.$this->precision;
        }

        return $rules;
    }
}
