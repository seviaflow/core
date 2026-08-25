<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Illuminate\Validation\Rule;
use SeviaFlow\Core\Resources\ResourceContext;

final class MultiSelectField extends Field
{
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
            '*' => [Rule::in(array_keys($this->options))],
        ];
    }
}
