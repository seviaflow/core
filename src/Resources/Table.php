<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use InvalidArgumentException;
use SeviaFlow\Core\Resources\Columns\Column;
use SeviaFlow\Core\Resources\Filters\Filter;

final class Table
{
    /**
     * @var array<int, Column>
     */
    private array $columns = [];

    /**
     * @var array<int, Filter>
     */
    private array $filters = [];

    private bool $selectOnlyVisibleColumns;

    public function __construct()
    {
        $this->selectOnlyVisibleColumns = (bool) config('seviaflow.performance.select_only_visible_columns', true);
    }

    /**
     * @param  array<int, Column>  $columns
     */
    public function columns(array $columns): self
    {
        foreach ($columns as $index => $column) {
            if (! $column instanceof Column) {
                throw new InvalidArgumentException(sprintf(
                    'Table column at index %d must be an instance of [%s], [%s] given.',
                    $index,
                    Column::class,
                    get_debug_type($column)
                ));
            }
        }

        $this->columns = array_values($columns);

        return $this;
    }

    /**
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @param  array<int, Filter>  $filters
     */
    public function filters(array $filters): self
    {
        foreach ($filters as $index => $filter) {
            if (! $filter instanceof Filter) {
                throw new InvalidArgumentException(sprintf(
                    'Table filter at index %d must be an instance of [%s], [%s] given.',
                    $index,
                    Filter::class,
                    get_debug_type($filter)
                ));
            }
        }

        $this->filters = array_values($filters);

        return $this;
    }

    /**
     * @return array<int, Filter>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function selectOnlyVisibleColumns(bool $condition = true): self
    {
        $this->selectOnlyVisibleColumns = $condition;

        return $this;
    }

    public function shouldSelectOnlyVisibleColumns(): bool
    {
        return $this->selectOnlyVisibleColumns;
    }
}
