<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Tables\Queries;

use Illuminate\Database\Eloquent\Builder;
use SeviaFlow\Core\Resources\Columns\Column;

final class AppliesSorting
{
    /**
     * @param  array<int, Column>  $indexColumns
     */
    public function apply(Builder $query, string $sortColumn, string $sortDirection, array $indexColumns): void
    {
        if (! $this->isSortable($sortColumn, $indexColumns)) {
            return;
        }

        $direction = $sortDirection === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortColumn, $direction);
    }

    /**
     * @param  array<int, Column>  $indexColumns
     */
    public function isSortable(string $column, array $indexColumns): bool
    {
        if ($column === '') {
            return false;
        }

        foreach ($indexColumns as $configuredColumn) {
            if ($configuredColumn->name() === $column && $configuredColumn->isSortable()) {
                return true;
            }
        }

        return false;
    }
}
