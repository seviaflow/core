<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Filters;

use Illuminate\Database\Eloquent\Builder;

class NumberFilter extends Filter
{
    public function apply(Builder $query, mixed $value): Builder
    {
        if (! $this->isActive($value)) {
            return $query;
        }

        if (! is_numeric($value)) {
            return $query;
        }

        return $query->where($this->name(), '=', $this->normalizeNumeric($value));
    }

    protected function normalizeNumeric(mixed $value): int|float
    {
        $string = (string) $value;

        if (str_contains($string, '.')) {
            return (float) $value;
        }

        return (int) $value;
    }
}
