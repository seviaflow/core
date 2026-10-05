<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use SeviaFlow\Core\Resources\ResourceContext;

final class RadioGroupField extends Field
{
    protected static string $type = 'radio-group';

    /**
     * @var array<array-key, string>
     */
    private array $options = [];

    /**
     * @var array<array-key, string>
     */
    private array $optionDescriptions = [];

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

    /**
     * @param  array<array-key, string>  $descriptions
     */
    public function optionDescriptions(array $descriptions): static
    {
        $this->optionDescriptions = $descriptions;

        return $this;
    }

    /**
     * @return array<array-key, string>
     */
    public function getOptionDescriptions(): array
    {
        return $this->optionDescriptions;
    }

    protected function defaultApiSchema(): array
    {
        return [
            'type' => 'string',
            'enum' => array_values(array_map(static fn (mixed $value): string => (string) $value, array_keys($this->options))),
            'nullable' => true,
            'x-seviaflow-option-descriptions' => $this->optionDescriptions,
        ];
    }

    public function normalizeValue(mixed $value, ?Model $model = null): mixed
    {
        return $value === null || $value === '' ? null : trim((string) $value);
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        return [Rule::in(array_map(static fn (mixed $value): string => (string) $value, array_keys($this->options)))];
    }
}
