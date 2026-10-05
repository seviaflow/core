<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use SeviaFlow\Core\Resources\Fields\Concerns\NormalizesStringList;
use SeviaFlow\Core\Resources\ResourceContext;

final class CheckboxGroupField extends Field
{
    use NormalizesStringList;

    protected static string $type = 'checkbox-group';

    /**
     * @var array<array-key, string>
     */
    private array $options = [];

    private ?int $minSelected = null;

    private ?int $maxSelected = null;

    /**
     * @param  array<array-key, string>  $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<array-key, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function minSelected(int $min): static
    {
        if ($min < 0) {
            throw new \InvalidArgumentException('CheckboxGroupField minimum must not be negative.');
        }

        $this->minSelected = $min;

        return $this;
    }

    public function maxSelected(int $max): static
    {
        if ($max < 1) {
            throw new \InvalidArgumentException('CheckboxGroupField maximum must be greater than zero.');
        }

        $this->maxSelected = $max;

        return $this;
    }

    public function getMinSelected(): ?int
    {
        return $this->minSelected;
    }

    public function getMaxSelected(): ?int
    {
        return $this->maxSelected;
    }

    protected function defaultApiSchema(): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'string',
                'enum' => array_values(array_map(static fn (mixed $value): string => (string) $value, array_keys($this->options))),
            ],
            'nullable' => true,
            'uniqueItems' => true,
        ];
    }

    public function normalizeValue(mixed $value, ?Model $model = null): array
    {
        return $this->normalizeStringList($value);
    }

    public function prepareForValidation(mixed $value): mixed
    {
        return is_array($value) || is_string($value)
            ? $this->normalizeStringList($value)
            : parent::prepareForValidation($value);
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
        $rules = ['array'];

        if ($this->minSelected !== null) {
            $rules[] = 'min:'.$this->minSelected;
        }

        if ($this->maxSelected !== null) {
            $rules[] = 'max:'.$this->maxSelected;
        }

        return $rules;
    }

    public function defaultNestedValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return ['*' => [Rule::in(array_map(static fn (mixed $value): string => (string) $value, array_keys($this->options)))]];
    }
}
