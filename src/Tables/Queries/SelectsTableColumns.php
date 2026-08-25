<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Tables\Queries;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Columns\BelongsToColumn;
use SeviaFlow\Core\Resources\Columns\Column;
use SeviaFlow\Core\Resources\Filters\Filter;

final class SelectsTableColumns
{
    /**
     * @param  array<int, Column>  $indexColumns
     * @param  array<int, Filter>  $filters
     * @param  class-string<Model>|null  $modelClass
     * @return array<int, string>
     */
    public function resolve(
        string $primaryKey,
        array $indexColumns,
        array $filters,
        string $sortColumn,
        bool $sortColumnIsSortable,
        ?string $modelClass = null
    ): array {
        $columns = [$primaryKey];

        foreach ($indexColumns as $column) {
            if ($column instanceof BelongsToColumn) {
                continue;
            }

            $name = $column->name();

            if ($this->isSafeColumnName($name)) {
                $columns[] = $name;
            }
        }

        if ($sortColumnIsSortable && $this->isSafeColumnName($sortColumn)) {
            $columns[] = $sortColumn;
        }

        foreach ($filters as $filter) {
            $name = $filter->name();

            if ($this->isSafeColumnName($name)) {
                $columns[] = $name;
            }
        }

        if ($modelClass !== null) {
            $columns = array_merge($columns, $this->belongsToForeignKeys($indexColumns, $modelClass));
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param  array<int, Column>  $indexColumns
     * @param  class-string<Model>  $modelClass
     * @return array<int, string>
     */
    private function belongsToForeignKeys(array $indexColumns, string $modelClass): array
    {
        /** @var Model $model */
        $model = new $modelClass;
        $columns = [];

        foreach ($indexColumns as $column) {
            if (! $column instanceof BelongsToColumn) {
                continue;
            }

            $relation = $model->{$column->getRelationshipName()}();

            if (! $relation instanceof BelongsTo) {
                throw new InvalidArgumentException(sprintf(
                    'Relationship [%s] on model [%s] must be an instance of [%s].',
                    $column->getRelationshipName(),
                    $model::class,
                    BelongsTo::class
                ));
            }

            $columns[] = $relation->getForeignKeyName();
        }

        return $columns;
    }

    private function isSafeColumnName(string $column): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) === 1;
    }
}
