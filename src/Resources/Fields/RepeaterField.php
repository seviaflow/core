<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\ResourceContext;

final class RepeaterField extends Field
{
    protected static string $type = 'repeater';

    private const ALLOWED_ITEM_TYPES = ['text', 'number', 'money', 'select', 'toggle'];

    /**
     * @var array<int, Field>
     */
    private array $itemFields = [];

    private ?int $minItems = null;

    private ?int $maxItems = null;

    private int $maxBytes = 131072;

    private ?string $itemLabelField = null;

    private bool $reorderable = true;

    private bool $deletable = true;

    private bool $collapsible = true;

    /**
     * @param  array<int, Field>  $fields
     */
    public function fields(array $fields): static
    {
        $names = [];

        foreach ($fields as $index => $field) {
            if (! $field instanceof Field) {
                throw new \InvalidArgumentException(sprintf(
                    'Repeater item field at index %d must be a field instance.',
                    $index
                ));
            }

            if (! in_array($field->type(), self::ALLOWED_ITEM_TYPES, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'Repeater item field [%s] has unsupported type [%s]. Allowed types: %s.',
                    $field->name(),
                    $field->type(),
                    implode(', ', self::ALLOWED_ITEM_TYPES)
                ));
            }

            if (isset($names[$field->name()])) {
                throw new \InvalidArgumentException('Repeater item field names must be unique.');
            }

            $names[$field->name()] = true;
        }

        $this->itemFields = array_values($fields);

        if ($this->itemLabelField !== null && ! isset($names[$this->itemLabelField])) {
            throw new \InvalidArgumentException('Repeater item label field must be one of the item fields.');
        }

        return $this;
    }

    /**
     * @param  array<int, Field>  $fields
     */
    public function itemFields(array $fields): static
    {
        return $this->fields($fields);
    }

    public function minItems(int $minimum): static
    {
        if ($minimum < 0) {
            throw new \InvalidArgumentException('RepeaterField minimum must not be negative.');
        }

        if ($this->maxItems !== null && $minimum > $this->maxItems) {
            throw new \InvalidArgumentException('RepeaterField minimum cannot exceed its maximum.');
        }

        $this->minItems = $minimum;

        return $this;
    }

    public function maxItems(int $maximum): static
    {
        if ($maximum < 1) {
            throw new \InvalidArgumentException('RepeaterField maximum must be greater than zero.');
        }

        if ($this->minItems !== null && $maximum < $this->minItems) {
            throw new \InvalidArgumentException('RepeaterField maximum cannot be less than its minimum.');
        }

        $this->maxItems = $maximum;

        return $this;
    }

    public function maxBytes(int $bytes): static
    {
        if ($bytes < 1) {
            throw new \InvalidArgumentException('RepeaterField byte limit must be greater than zero.');
        }

        $this->maxBytes = $bytes;

        return $this;
    }

    public function itemLabel(string $fieldName): static
    {
        $fieldName = trim($fieldName);
        if ($fieldName === '') {
            throw new \InvalidArgumentException('RepeaterField item label field cannot be empty.');
        }

        $this->itemLabelField = $fieldName;

        if ($this->itemFields !== [] && ! in_array($fieldName, array_map(
            static fn (Field $field): string => $field->name(),
            $this->itemFields
        ), true)) {
            throw new \InvalidArgumentException('Repeater item label field must be one of the item fields.');
        }

        return $this;
    }

    public function reorderable(bool $condition = true): static
    {
        $this->reorderable = $condition;

        return $this;
    }

    public function deletable(bool $condition = true): static
    {
        $this->deletable = $condition;

        return $this;
    }

    public function collapsible(bool $condition = true): static
    {
        $this->collapsible = $condition;

        return $this;
    }

    /**
     * @return array<int, Field>
     */
    public function getItemFields(): array
    {
        return $this->itemFields;
    }

    public function getMinItems(): ?int
    {
        return $this->minItems;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    public function getMaxBytes(): int
    {
        return $this->maxBytes;
    }

    public function getItemLabelField(): ?string
    {
        return $this->itemLabelField ?? (isset($this->itemFields[0]) ? $this->itemFields[0]->name() : null);
    }

    public function isReorderable(): bool
    {
        return $this->reorderable;
    }

    public function isDeletable(): bool
    {
        return $this->deletable;
    }

    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    protected function defaultApiSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->itemFields as $field) {
            $properties[$field->name()] = $field->getSchema();
            if ($field->isRequired(ResourceContext::Create)) {
                $required[] = $field->name();
            }
        }

        $itemSchema = [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => false,
        ];
        if ($required !== []) {
            $itemSchema['required'] = $required;
        }

        $schema = [
            'type' => 'array',
            'items' => $itemSchema,
            'nullable' => true,
            'x-seviaflow-repeater' => [
                'storage' => 'json-array',
                'depth' => 1,
                'allowedItemTypes' => self::ALLOWED_ITEM_TYPES,
                'maxBytes' => $this->maxBytes,
                'reorderable' => $this->reorderable,
                'deletable' => $this->deletable,
            ],
        ];

        if ($this->minItems !== null) {
            $schema['minItems'] = $this->minItems;
        }
        if ($this->maxItems !== null) {
            $schema['maxItems'] = $this->maxItems;
        }

        return $schema;
    }

    public function normalizeValue(mixed $value, ?Model $model = null): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $value = $this->decodeValue($value);
        $this->assertCollectionShape($value);

        $normalized = [];
        $fields = $this->itemFieldMap();

        foreach ($value as $item) {
            $normalizedItem = [];
            foreach ($item as $name => $itemValue) {
                $normalizedItem[$name] = $fields[$name]->normalizeValue($itemValue, $model);
            }
            $normalized[] = $normalizedItem;
        }

        return $normalized;
    }

    public function prepareForValidation(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            try {
                $value = $this->decodeValue($value);
            } catch (\InvalidArgumentException) {
                return $value;
            }
        }

        if (! is_array($value)) {
            return $value;
        }

        $fields = $this->itemFieldMap();
        $prepared = [];
        foreach ($value as $index => $item) {
            if (! is_array($item)) {
                $prepared[$index] = $item;

                continue;
            }

            $preparedItem = [];
            foreach ($item as $name => $itemValue) {
                $preparedItem[$name] = isset($fields[$name])
                    ? $fields[$name]->prepareForValidation($itemValue)
                    : $itemValue;
            }
            $prepared[$index] = $preparedItem;
        }

        return $prepared;
    }

    public function prepareForImport(mixed $value): mixed
    {
        return $this->prepareForValidation($value);
    }

    public function formatForInput(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            try {
                $value = $this->decodeValue($value);
            } catch (\InvalidArgumentException) {
                return [];
            }
        }

        return is_array($value) && array_is_list($value) ? $value : [];
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        $rules = ['array'];
        if ($this->minItems !== null) {
            $rules[] = 'min:'.$this->minItems;
        }
        if ($this->maxItems !== null) {
            $rules[] = 'max:'.$this->maxItems;
        }

        $rules[] = function (string $attribute, mixed $value, Closure $fail): void {
            try {
                $this->assertCollectionShape($value);
            } catch (\InvalidArgumentException $exception) {
                $fail('The '.$attribute.' field '.$exception->getMessage());
            }
        };

        return $rules;
    }

    public function defaultNestedValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        $resolvedContext = $context instanceof ResourceContext
            ? $context
            : ResourceContext::Create;
        $rules = [];

        foreach ($this->itemFields as $field) {
            $fieldRules = $field->getRulesForContext($resolvedContext, $record);
            if (! $this->containsRule($fieldRules, 'required') && ! $this->containsRule($fieldRules, 'nullable')) {
                $fieldRules[] = 'nullable';
            }

            if ($field->usesDefaultValidation()) {
                foreach ($field->defaultValidationRules($resolvedContext, $record, $modelClass, $fieldValues) as $rule) {
                    $this->appendRuleIfMissing($fieldRules, $rule);
                }
            }

            $rules['*.'.$field->name()] = $fieldRules;
        }

        return $rules;
    }

    /**
     * @return array<string, Field>
     */
    private function itemFieldMap(): array
    {
        $map = [];
        foreach ($this->itemFields as $field) {
            $map[$field->name()] = $field;
        }

        return $map;
    }

    private function decodeValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (strlen($value) > $this->maxBytes) {
            throw new \InvalidArgumentException('exceeds the JSON size limit.');
        }

        try {
            return json_decode($value, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('must contain a valid JSON array.');
        }
    }

    private function assertCollectionShape(mixed $value): void
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new \InvalidArgumentException('must be a JSON array of objects.');
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($encoded) && strlen($encoded) > $this->maxBytes) {
            throw new \InvalidArgumentException('exceeds the JSON size limit.');
        }

        $count = count($value);
        if ($this->minItems !== null && $count < $this->minItems) {
            throw new \InvalidArgumentException('must contain at least '.$this->minItems.' items.');
        }
        if ($this->maxItems !== null && $count > $this->maxItems) {
            throw new \InvalidArgumentException('may not contain more than '.$this->maxItems.' items.');
        }

        $fields = $this->itemFieldMap();
        foreach ($value as $index => $item) {
            if (! is_array($item) || array_is_list($item)) {
                throw new \InvalidArgumentException('item '.($index + 1).' must be an object.');
            }

            $unknown = array_values(array_diff(array_keys($item), array_keys($fields)));
            if ($unknown !== []) {
                throw new \InvalidArgumentException(sprintf(
                    'item %d contains unknown field(s): %s.',
                    $index + 1,
                    implode(', ', array_map(static fn (mixed $name): string => (string) $name, $unknown))
                ));
            }
        }
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function containsRule(array $rules, string $needle): bool
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && strtolower($rule) === strtolower($needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function appendRuleIfMissing(array &$rules, mixed $rule): void
    {
        if (is_string($rule) && ! $this->containsRule($rules, $rule)) {
            $rules[] = $rule;

            return;
        }

        if (! is_string($rule)) {
            $rules[] = $rule;
        }
    }
}
