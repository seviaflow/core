<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Filters;

use Illuminate\Database\Eloquent\Builder;

class NumberRangeFilter extends NumberFilter
{
    public function isActive(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        $min = $value['min'] ?? null;
        $max = $value['max'] ?? null;

        return (is_string($min) ? trim($min) !== '' : $min !== null)
            || (is_string($max) ? trim($max) !== '' : $max !== null);
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if (! is_array($value)) {
            return $query;
        }

        $min = $value['min'] ?? null;
        $max = $value['max'] ?? null;

        if (is_numeric($min)) {
            $query->where($this->name(), '>=', $this->normalizeNumeric($min));
        }

        if (is_numeric($max)) {
            $query->where($this->name(), '<=', $this->normalizeNumeric($max));
        }

        return $query;
    }
}
