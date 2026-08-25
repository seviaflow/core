<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Tables\Queries;

use Illuminate\Database\Eloquent\Builder;
use SeviaFlow\Core\Resources\Columns\Column;

final class AppliesSearch
{
    /**
     * @param  array<int, Column>  $indexColumns
     */
    public function apply(Builder $query, string $search, array $indexColumns, string $defaultMode): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $searchableColumns = array_values(array_filter(
            $indexColumns,
            static fn (Column $column): bool => $column->isSearchable()
        ));

        if ($searchableColumns === []) {
            return;
        }

        $query->where(function (Builder $builder) use ($searchableColumns, $search, $defaultMode): void {
            foreach ($searchableColumns as $index => $column) {
                $callback = $column->getSearchCallback();
                $searchMode = $column->getSearchMode() ?? $defaultMode;

                if ($callback !== null) {
                    $callback($builder, $search, $column->name());

                    continue;
                }

                $method = $index === 0 ? 'where' : 'orWhere';

                if ($searchMode === 'exact') {
                    $builder->{$method}($column->name(), '=', $search);

                    continue;
                }

                $builder->{$method}($column->name(), 'like', $this->searchPattern($search, $searchMode));
            }
        });
    }

    private function searchPattern(string $search, string $mode): string
    {
        return match ($mode) {
            'starts_with' => $search.'%',
            default => '%'.$search.'%',
        };
    }
}
