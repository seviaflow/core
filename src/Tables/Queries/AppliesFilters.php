<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Tables\Queries;

use Illuminate\Database\Eloquent\Builder;
use SeviaFlow\Core\Resources\Filters\Filter;

final class AppliesFilters
{
    /**
     * @param  array<int, Filter>  $filters
     * @param  array<string, mixed>  $values
     */
    public function apply(Builder $query, array $filters, array $values): void
    {
        foreach ($filters as $filter) {
            $value = $values[$filter->name()] ?? null;

            if (! $filter->isActive($value)) {
                continue;
            }

            $filter->apply($query, $value);
        }
    }
}
