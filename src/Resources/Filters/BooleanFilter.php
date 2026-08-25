<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Filters;

use Illuminate\Database\Eloquent\Builder;

final class BooleanFilter extends Filter
{
    public function apply(Builder $query, mixed $value): Builder
    {
        if (! $this->isActive($value)) {
            return $query;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if (! is_bool($parsed)) {
            return $query;
        }

        return $query->where($this->name(), $parsed);
    }
}
