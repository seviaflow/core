<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Columns\BelongsToColumn;
use SeviaFlow\Core\Resources\Columns\Column;
use SeviaFlow\Core\Resources\Filters\Filter;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Core\Tables\Queries\AppliesFilters;
use SeviaFlow\Core\Tables\Queries\AppliesSearch;
use SeviaFlow\Core\Tables\Queries\AppliesSorting;
use SeviaFlow\Core\Tables\Queries\ResolvesPagination;
use SeviaFlow\Core\Tables\Queries\SelectsTableColumns;

final class ResourceTableQuery
{
    private string $search = '';

    private string $sortColumn = '';

    private string $sortDirection = 'asc';

    private string $searchMode = 'contains';

    private string $trashedScope = 'active';

    /**
     * @var array<string, mixed>
     */
    private array $filterValues = [];

    private AppliesSearch $appliesSearch;

    private AppliesFilters $appliesFilters;

    private AppliesSorting $appliesSorting;

    private SelectsTableColumns $selectsTableColumns;

    private ResolvesPagination $resolvesPagination;

    private function __construct(
        private readonly string $resourceClass,
        private readonly Table $table
    ) {
        $this->appliesSearch = new AppliesSearch;
        $this->appliesFilters = new AppliesFilters;
        $this->appliesSorting = new AppliesSorting;
        $this->selectsTableColumns = new SelectsTableColumns;
        $this->resolvesPagination = new ResolvesPagination;
    }

    public static function make(string $resourceClass, Table $table): self
    {
        $searchMode = (string) config(
            'seviaflow.performance.search.default_mode',
            (string) config('seviaflow.performance.search.mode', 'starts_with')
        );

        return (new self($resourceClass, $table))
            ->searchMode($searchMode);
    }

    public function search(string $value): self
    {
        $this->search = $value;

        return $this;
    }

    public function sort(string $column, string $direction): self
    {
        $this->sortColumn = $column;
        $this->sortDirection = $direction;

        return $this;
    }

    public function searchMode(string $mode): self
    {
        $this->searchMode = $mode;

        return $this;
    }

    public function trashedScope(string $scope): self
    {
        $this->trashedScope = in_array($scope, ['active', 'trashed', 'with_trashed'], true)
            ? $scope
            : 'active';

        return $this;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function filters(array $values): self
    {
        $this->filterValues = $values;

        return $this;
    }

    public function query(): Builder
    {
        $query = $this->baseQuery();

        $this->applySearch($query);
        $this->applyFilters($query);
        $this->applySorting($query);

        return $query;
    }

    public function paginate(int $perPage): LengthAwarePaginator|Paginator
    {
        $safePerPage = $this->resolvesPagination->normalizePerPage($perPage);
        $mode = (string) config('seviaflow.performance.pagination.mode', 'length_aware');

        if ($mode === 'simple') {
            return $this->query()->simplePaginate($safePerPage);
        }

        return $this->query()->paginate($safePerPage);
    }

    public function find(int|string $recordId): ?Model
    {
        $query = $this->newModelQuery();

        if ($this->usesSoftDeletes()) {
            call_user_func([$query, 'withTrashed']);
        }

        return $query->find($recordId);
    }

    public function baseQuery(): Builder
    {
        $query = $this->newModelQuery();
        $this->applyTrashedScope($query);
        $this->applyBelongsToEagerLoads($query);

        if (! $this->table->shouldSelectOnlyVisibleColumns()) {
            return $query;
        }

        $selectColumns = $this->requiredSelectColumns();

        if ($selectColumns === []) {
            return $query;
        }

        return $query->select($selectColumns);
    }

    /**
     * @return array<int, Column>
     */
    private function columns(): array
    {
        return $this->table->getColumns();
    }

    /**
     * @return array<int, Column>
     */
    private function indexColumns(): array
    {
        return array_values(array_filter(
            $this->columns(),
            fn (Column $column): bool => $column->isVisible(ResourceContext::Index)
        ));
    }

    /**
     * @return array<int, Filter>
     */
    private function filtersDefinition(): array
    {
        return $this->table->getFilters();
    }

    private function applySearch(Builder $query): void
    {
        $this->appliesSearch->apply($query, $this->search, $this->indexColumns(), $this->searchMode);
    }

    private function applySorting(Builder $query): void
    {
        $this->appliesSorting->apply($query, $this->sortColumn, $this->sortDirection, $this->indexColumns());
    }

    private function applyFilters(Builder $query): void
    {
        $this->appliesFilters->apply($query, $this->filtersDefinition(), $this->filterValues);
    }

    private function isSortable(string $column): bool
    {
        return $this->appliesSorting->isSortable($column, $this->indexColumns());
    }

    /**
     * @return array<int, string>
     */
    private function requiredSelectColumns(): array
    {
        return $this->selectsTableColumns->resolve(
            $this->primaryKeyName(),
            $this->indexColumns(),
            $this->filtersDefinition(),
            $this->sortColumn,
            $this->isSortable($this->sortColumn),
            $this->resourceClass::model()
        );
    }

    private function primaryKeyName(): string
    {
        return $this->newModelQuery()->getModel()->getKeyName();
    }

    private function newModelQuery(): Builder
    {
        $modelClass = $this->resourceClass::model();

        /** @var Model $model */
        $model = new $modelClass;

        $query = $model->newQuery();

        return $this->resourceClass::query($query);
    }

    private function applyTrashedScope(Builder $query): void
    {
        if (! $this->usesSoftDeletes()) {
            return;
        }

        if ($this->trashedScope === 'trashed') {
            call_user_func([$query, 'onlyTrashed']);

            return;
        }

        if ($this->trashedScope === 'with_trashed') {
            call_user_func([$query, 'withTrashed']);
        }
    }

    private function usesSoftDeletes(): bool
    {
        $modelClass = $this->resourceClass::model();

        return in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
    }

    private function applyBelongsToEagerLoads(Builder $query): void
    {
        $with = [];

        foreach ($this->indexColumns() as $column) {
            if (! $column instanceof BelongsToColumn) {
                continue;
            }

            $relation = $this->belongsToRelation($query->getModel(), $column);
            $relationName = $column->getRelationshipName();
            $ownerKey = $relation->getOwnerKeyName();
            $titleAttribute = $column->getTitleAttribute();

            $with[$relationName] = static function (Relation $relatedQuery) use ($ownerKey, $titleAttribute): void {
                $relatedQuery->select(array_values(array_unique([$ownerKey, $titleAttribute])));
            };
        }

        if ($with !== []) {
            $query->with($with);
        }
    }

    private function belongsToRelation(Model $model, BelongsToColumn $column): BelongsTo
    {
        $relation = $model->{$column->getRelationshipName()}();

        if (! $relation instanceof BelongsTo) {
            throw new InvalidArgumentException(sprintf(
                'Relationship [%s] on model [%s] must be an instance of [%s].',
                $column->getRelationshipName(),
                $model::class,
                BelongsTo::class
            ));
        }

        return $relation;
    }
}
