<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields\Concerns;

use BackedEnum;
use Closure;
use InvalidArgumentException;
use SeviaFlow\Core\Forms\FormState;
use SeviaFlow\Core\Resources\ResourceContext;
use UnitEnum;

trait HasSelectOptions
{
    /** @var array<array-key, mixed>|Closure */
    private array|Closure $options = [];

    /** @var array<int, string> */
    private array $disabledOptionValues = [];

    /** @var array<int, string> */
    private array $optionDependencies = [];

    private bool $searchable = false;

    /** @param array<array-key, mixed>|Closure|class-string<UnitEnum> $options */
    public function options(array|Closure|string $options): static
    {
        if (is_string($options)) {
            if (! enum_exists($options)) {
                throw new InvalidArgumentException('Select options must be an enum class, array or closure.');
            }
            $values = [];
            foreach ($options::cases() as $case) {
                $values[$case instanceof BackedEnum ? $case->value : $case->name] = $case->name;
            }
            $options = $values;
        }
        $this->options = $options;

        return $this;
    }

    public function searchable(bool $condition = true): static
    {
        $this->searchable = $condition;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /** @param array<int, int|string> $values */
    public function disabledOptions(array $values): static
    {
        $this->disabledOptionValues = array_values(array_map(static fn (int|string $value): string => (string) $value, $values));

        return $this;
    }

    /** @return array<int, string> */
    public function getDisabledOptions(): array
    {
        return $this->disabledOptionValues;
    }

    /** @param string|array<int, string> $fields */
    public function dependsOn(string|array $fields): static
    {
        $fields = is_array($fields) ? $fields : [$fields];
        if ($fields === [] || count(array_filter($fields, static fn (string $name): bool => trim($name) === '')) > 0) {
            throw new InvalidArgumentException('Select dependencies must contain non-empty field names.');
        }
        $this->optionDependencies = array_values(array_unique($fields));

        return $this;
    }

    /** @return array<int, string> */
    public function getDependencyFields(): array
    {
        return array_values(array_unique(array_merge(parent::getDependencyFields(), $this->optionDependencies)));
    }

    public function hasDynamicOptions(): bool
    {
        return $this->options instanceof Closure;
    }

    /** @return array<array-key, array<array-key, string>> */
    public function getOptionGroups(?FormState $state = null): array
    {
        $options = $this->options instanceof Closure
            ? ($this->options)($state ?? new FormState([], ResourceContext::Create))
            : $this->options;
        if (! is_array($options)) {
            throw new InvalidArgumentException('Select option callback must return an array.');
        }
        $groups = [];
        $seen = [];
        foreach ($options as $key => $label) {
            $group = is_array($label) ? (string) $key : '';
            $entries = is_array($label) ? $label : [$key => $label];
            foreach ($entries as $value => $text) {
                if (! is_string($text) || isset($seen[(string) $value])) {
                    throw new InvalidArgumentException('Select option labels must be strings and values must be unique across groups.');
                }
                $seen[(string) $value] = true;
                $groups[$group][$value] = $text;
            }
        }

        return $groups;
    }

    /** @return array<array-key, string> */
    public function getOptions(?FormState $state = null): array
    {
        $options = [];
        foreach ($this->getOptionGroups($state) as $group) {
            foreach ($group as $value => $label) {
                $options[$value] = $label;
            }
        }

        return $options;
    }

    /** @return array<int, string> */
    public function getSelectableOptionKeys(?FormState $state = null): array
    {
        return array_values(array_diff(
            array_map(static fn (int|string $value): string => (string) $value, array_keys($this->getOptions($state))),
            $this->disabledOptionValues,
        ));
    }
}
