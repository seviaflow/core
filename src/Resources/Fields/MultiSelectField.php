<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use SeviaFlow\Core\Forms\FormState;
use SeviaFlow\Core\Resources\Fields\Concerns\HasSelectOptions;
use SeviaFlow\Core\Resources\ResourceContext;

class MultiSelectField extends Field
{
    use HasSelectOptions;

    protected static string $type = 'multiselect';

    public static function make(string $name): static
    {
        return parent::make($name)->searchable();
    }

    private ?int $minSelected = null;

    private ?int $maxSelected = null;

    protected function defaultApiSchema(): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'string',
                ...($this->hasDynamicOptions() ? [] : ['enum' => array_values(array_map(static fn (mixed $value): string => (string) $value, array_keys($this->getOptions())))]),
            ],
            'nullable' => true,
        ];
    }

    public function normalizeValue(mixed $value, ?Model $model = null): array
    {
        return $this->normalizeList($value);
    }

    public function prepareForValidation(mixed $value): mixed
    {
        return is_array($value) ? $this->normalizeList($value) : parent::prepareForValidation($value);
    }

    public function prepareForImport(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [];
            }

            $decoded = json_decode($value, true);

            return $this->normalizeList(is_array($decoded) ? $decoded : explode(',', $value));
        }

        return $this->prepareForValidation($value);
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeList(mixed $value): array
    {
        if ($value === null || $value === '' || ! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            $item = is_string($item) ? trim($item) : $item;
            if ($item === '') {
                continue;
            }

            if (! in_array($item, $normalized, true)) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    public function minSelected(int $min): static
    {
        $this->minSelected = $min;

        return $this;
    }

    public function getMinSelected(): ?int
    {
        return $this->minSelected;
    }

    public function maxSelected(int $max): static
    {
        $this->maxSelected = $max;

        return $this;
    }

    public function getMaxSelected(): ?int
    {
        return $this->maxSelected;
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
        return [
            '*' => [Rule::in($this->getSelectableOptionKeys(new FormState($fieldValues, $context instanceof ResourceContext ? $context : ResourceContext::Create, $record instanceof Model ? $record : null)))],
        ];
    }
}
