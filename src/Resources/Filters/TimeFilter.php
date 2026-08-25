<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Filters;

use Illuminate\Database\Eloquent\Builder;

final class TimeFilter extends Filter
{
    public function apply(Builder $query, mixed $value): Builder
    {
        if (! $this->isActive($value)) {
            return $query;
        }

        if (! is_string($value)) {
            return $query;
        }

        $time = trim($value);

        if (! preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
            return $query;
        }

        return $query->where($this->name(), '=', $time);
    }
}
