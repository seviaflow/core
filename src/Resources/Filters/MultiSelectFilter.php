<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Filters;

use Illuminate\Database\Eloquent\Builder;

final class MultiSelectFilter extends Filter
{
    /**
     * @var array<array-key, string>
     */
    private array $options = [];

    /**
     * @param  array<array-key, string>  $options
     */
    public function options(array $options): self
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

    public function isActive(mixed $value): bool
    {
        return is_array($value) && $value !== [];
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if (! is_array($value) || $value === []) {
            return $query;
        }

        $validValues = [];

        foreach ($value as $selected) {
            if (is_int($selected) || is_string($selected)) {
                $normalized = (string) $selected;

                if (array_key_exists($normalized, $this->options)) {
                    $validValues[] = $normalized;
                }
            }
        }

        if ($validValues === [] || count($validValues) !== count($value)) {
            return $query;
        }

        return $query->whereIn($this->name(), $validValues);
    }
}
