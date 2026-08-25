<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Filters;

use Illuminate\Database\Eloquent\Builder;

final class SelectFilter extends Filter
{
    /**
     * @var array<string, string>
     */
    private array $options = [];

    /**
     * @param  array<string, string>  $options
     */
    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if (! $this->isActive($value)) {
            return $query;
        }

        $selectValue = (string) $value;

        if (! array_key_exists($selectValue, $this->options)) {
            return $query;
        }

        return $query->where($this->name(), $selectValue);
    }
}
