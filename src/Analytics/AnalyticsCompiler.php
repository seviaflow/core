<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class AnalyticsCompiler
{
    /**
     * @param  array<int, string>  $measureKeys
     * @param  array<int, string>  $dimensionKeys
     * @param  array{dimension:string, grain:string, from:string|null, to:string|null}|null  $time
     * @param  array<int, AnalyticsFilter>  $filters
     * @param  array<int, AnalyticsAggregateFilter>  $aggregateFilters
     * @param  array<string, mixed>  $complexity
     * @param  array<int, AnalyticsOrder>  $order
     */
    public function execute(
        AnalyticsDataset $dataset,
        AnalyticsQuery $analyticsQuery,
        array $measureKeys,
        array $dimensionKeys,
        ?array $time,
        array $filters,
        array $aggregateFilters,
        ?AnalyticsRanking $ranking,
        array $order,
        int $limit,
        array $complexity = [],
        ?Authenticatable $user = null,
    ): AnalyticsResult {
        $query = $this->baseQuery($dataset);
        $joins = [];
        $selectedDimensions = [];
        $selectedMeasures = [];
        $timeSelection = null;

        $this->clearSelectedColumns($query);
        $this->applyTimeRange($query, $dataset, $time, $joins);

        foreach ($filters as $filter) {
            $this->applyFilter($query, $dataset, $filter, $joins);
        }

        foreach ($dimensionKeys as $dimensionKey) {
            $dimension = $dataset->dimension($dimensionKey);
            $selection = $this->dimensionSelection($query, $dimension, $joins);
            $valueAlias = $this->alias('dimension_'.$dimensionKey.'_value');
            $labelAlias = $this->alias('dimension_'.$dimensionKey.'_label');

            $query->selectRaw($selection['value_sql'].' as '.$this->wrap($query, $valueAlias), $selection['value_bindings']);
            $query->selectRaw($selection['label_sql'].' as '.$this->wrap($query, $labelAlias), $selection['label_bindings']);

            foreach ($selection['group_sql'] as $groupSql) {
                $query->groupByRaw($groupSql);
            }

            $selectedDimensions[$dimensionKey] = [
                'dimension' => $dimension,
                'value_alias' => $valueAlias,
                'label_alias' => $labelAlias,
            ];
        }

        if ($time !== null) {
            $dimension = $dataset->timeDimension($time['dimension']);
            $timeExpression = $this->timeExpression($query, $dimension, $time['grain'], $joins);
            $timeAlias = $this->alias('time_'.$dimension->key().'_'.$time['grain']);

            $query->selectRaw($timeExpression.' as '.$this->wrap($query, $timeAlias));
            $query->groupByRaw($timeExpression);
            $timeSelection = [
                'dimension' => $dimension,
                'grain' => $time['grain'],
                'alias' => $timeAlias,
            ];
        }

        $grouped = $selectedDimensions !== [] || $timeSelection !== null;

        foreach ($measureKeys as $measureKey) {
            $measure = $dataset->measure($measureKey);
            $measureAlias = $this->alias('measure_'.$measureKey);
            [$sql, $bindings] = $this->measureSql($query, $dataset, $measure, $joins);

            $query->selectRaw($sql.' as '.$this->wrap($query, $measureAlias), $bindings);
            $selectedMeasures[$measureKey] = [
                'measure' => $measure,
                'alias' => $measureAlias,
                'sql' => $sql,
                'bindings' => $bindings,
            ];
        }

        $this->applyAggregateFilters($query, $aggregateFilters, $selectedMeasures);

        if ($ranking instanceof AnalyticsRanking) {
            $rawRows = $this->rankedRows($query, $dataset, $ranking, $selectedDimensions, $selectedMeasures, $timeSelection, $order, $limit);
        } else {
            $this->applyOrdering($query, $dataset, $order, $selectedDimensions, $selectedMeasures, $timeSelection);

            if ($grouped) {
                $query->limit($limit);
            }

            $rawRows = $query->get();
        }

        $rows = $this->normalizeRows(
            rows: $rawRows,
            selectedDimensions: $selectedDimensions,
            selectedMeasures: $selectedMeasures,
            timeSelection: $timeSelection,
        );

        return new AnalyticsResult(
            dataset: $dataset->key(),
            query: $analyticsQuery->toArray(),
            rows: $rows,
            metadata: [
                'dataset' => $dataset->toArray($user),
                'grouped' => $grouped,
                'limit' => $grouped ? $limit : null,
                'ranking' => $ranking?->toArray(),
                'complexity' => $complexity,
            ],
        );
    }

    private function baseQuery(AnalyticsDataset $dataset): Builder
    {
        $resourceClass = $dataset->resourceClass();
        /** @var class-string<Model> $modelClass */
        $modelClass = $resourceClass::model();

        /** @var Model $model */
        $model = new $modelClass;

        return $resourceClass::query($model->newQuery())->withoutEagerLoads();
    }

    private function clearSelectedColumns(Builder $query): void
    {
        $query->select([]);
    }

    /**
     * @param  array{dimension:string, grain:string, from:string|null, to:string|null}|null  $time
     * @param  array<string, array{alias:string, model:class-string<Model>, owner_key:string}>  $joins
     */
    private function applyTimeRange(Builder $query, AnalyticsDataset $dataset, ?array $time, array &$joins): void
    {
        if ($time === null) {
            return;
        }

        $dimension = $dataset->timeDimension($time['dimension']);
        $column = $this->timeFieldSql($query, $dimension, $joins);

        if ($time['from'] !== null) {
            $query->where($column, '>=', $time['from']);
        }

        if ($time['to'] !== null) {
            $query->where($column, '<', $time['to']);
        }
    }

    /**
     * @param  array<string, array{alias:string, model:class-string<Model>, owner_key:string}>  $joins
     */
    private function applyFilter(Builder $query, AnalyticsDataset $dataset, AnalyticsFilter $filter, array &$joins): void
    {
        [$sql, $bindings] = $this->filterSql($query, $dataset, $filter, $joins);

        $query->whereRaw($sql, $bindings);
    }

    /**
     * @param  array<string, array{alias:string, model:class-string<Model>, owner_key:string}>  $joins
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function filterSql(Builder $query, AnalyticsDataset $dataset, AnalyticsFilter $filter, array &$joins): array
    {
        $fieldSql = $this->filterFieldSql($query, $dataset, $filter->field(), $joins);
        $operator = $filter->operator();
        $value = $filter->value();

        return match ($operator) {
            'equals' => $value === null ? [$fieldSql.' is null', []] : [$fieldSql.' = ?', [$value]],
            'not_equals' => $value === null ? [$fieldSql.' is not null', []] : [$fieldSql.' <> ?', [$value]],
            'gt' => [$fieldSql.' > ?', [$value]],
            'gte' => [$fieldSql.' >= ?', [$value]],
            'lt' => [$fieldSql.' < ?', [$value]],
            'lte' => [$fieldSql.' <= ?', [$value]],
            'in' => $this->listFilterSql($fieldSql, 'in', $value),
            'not_in' => $this->listFilterSql($fieldSql, 'not in', $value),
            'between' => $this->betweenFilterSql($fieldSql, $value),
            'is_null' => [$fieldSql.' is null', []],
            'is_not_null' => [$fieldSql.' is not null', []],
            default => throw new InvalidArgumentException(sprintf('Analytics filter operator [%s] is not supported.', $operator)),
        };
    }

    /**
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function listFilterSql(string $fieldSql, string $operator, mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            throw new InvalidArgumentException(sprintf('Analytics filter operator [%s] requires a non-empty array value.', $operator));
        }

        $placeholders = implode(', ', array_fill(0, count($value), '?'));

        return [$fieldSql.' '.$operator.' ('.$placeholders.')', array_values($value)];
    }

    /**
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function betweenFilterSql(string $fieldSql, mixed $value): array
    {
        if (! is_array($value) || count($value) !== 2) {
            throw new InvalidArgumentException('Analytics between filter requires exactly two values.');
        }

        return [$fieldSql.' between ? and ?', array_values($value)];
    }

    /**
     * @param  array<string, array{alias:string, model:class-string<Model>, owner_key:string}>  $joins
     */
    private function filterFieldSql(Builder $query, AnalyticsDataset $dataset, string $field, array &$joins): string
    {
        if ($dataset->hasDimension($field)) {
            return $this->dimensionSelection($query, $dataset->dimension($field), $joins)['filter_sql'];
        }

        if ($dataset->hasTimeDimension($field)) {
            return $this->wrap($query, $this->timeFieldSql($query, $dataset->timeDimension($field), $joins));
        }

        throw new InvalidArgumentException(sprintf('Analytics filter field [%s] is not registered on dataset [%s].', $field, $dataset->key()));
    }

    /**
     * @param  array<string, array{alias:string, model:class-string<Model>, owner_key:string}>  $joins
     * @return array{
     *   value_sql:string,
     *   value_bindings:array<int, mixed>,
     *   label_sql:string,
     *   label_bindings:array<int, mixed>,
     *   filter_sql:string,
     *   group_sql:array<int, string>
     * }
     */
    private function dimensionSelection(Builder $query, Dimension $dimension, array &$joins): array
    {
        if (is_string($dimension->fieldName())) {
            $sql = $this->wrap($query, $this->qualifiedBaseColumn($query, $dimension->fieldName()));

            return [
                'value_sql' => $sql,
                'value_bindings' => [],
                'label_sql' => $sql,
                'label_bindings' => [],
                'filter_sql' => $sql,
                'group_sql' => [$sql],
            ];
        }

        $relationship = $dimension->relationshipDefinition();
        if ($relationship === null) {
            throw new InvalidArgumentException(sprintf('Analytics dimension [%s] must define field() or relationship().', $dimension->key()));
        }

        $resolved = $this->ensureRelationshipPath($query, $relationship['path'], $joins);
        $alias = $resolved['alias'];
        $relatedValue = $relationship['mode'] === 'entity'
            ? ($relationship['key_attribute'] ?? $resolved['owner_key'])
            : $relationship['key_attribute'];
        if (! is_string($relatedValue) || $relatedValue === '') {
            throw new InvalidArgumentException(sprintf('Analytics dimension [%s] relationship key is not configured.', $dimension->key()));
        }

        $valueSql = $this->wrap($query, $alias.'.'.$relatedValue);
        $labelSql = $this->wrap($query, $alias.'.'.$relationship['label_attribute']);
        $groupSql = $relationship['mode'] === 'entity'
            ? [$valueSql, $labelSql]
            : [$valueSql];

        return [
            'value_sql' => $valueSql,
            'value_bindings' => [],
            'label_sql' => $labelSql,
            'label_bindings' => [],
            'filter_sql' => $valueSql,
            'group_sql' => array_values(array_unique($groupSql)),
        ];
    }

    /**
     * @param  array<int, string>  $path
     * @param  array<string, array{alias:string, model:class-string<Model>, owner_key:string}>  $joins
     * @return array{alias:string, model:class-string<Model>, owner_key:string}
     */
    private function ensureRelationshipPath(Builder $query, array $path, array &$joins): array
    {
        if ($path === []) {
            throw new InvalidArgumentException('Analytics relationship path cannot be empty.');
        }

        $parentModel = $query->getModel();
        $parentTable = $parentModel->getTable();
        $prefix = [];
        $resolved = null;

        foreach ($path as $relationship) {
            $prefix[] = $relationship;
            $joinKey = implode('.', $prefix);

            if (! isset($joins[$joinKey])) {
                $relation = $this->belongsToRelation($parentModel, $relationship);
                $alias = $this->alias('rel_'.$joinKey);
                $relatedTable = $relation->getRelated()->getTable();
                $ownerKey = $relation->getOwnerKeyName();

                $query->leftJoin(
                    $relatedTable.' as '.$alias,
                    $parentTable.'.'.$relation->getForeignKeyName(),
                    '=',
                    $alias.'.'.$ownerKey
                );

                $joins[$joinKey] = [
                    'alias' => $alias,
                    'model' => $relation->getRelated()::class,
                    'owner_key' => $ownerKey,
                ];
            }

            $resolved = $joins[$joinKey];
            $parentTable = $resolved['alias'];
            $modelClass = $resolved['model'];
            $parentModel = new $modelClass;
        }

        return $resolved;
    }

    private function belongsToRelation(Model $model, string $relationship): BelongsTo
    {
        if (! method_exists($model, $relationship)) {
            throw new InvalidArgumentException(sprintf('Analytics relationship [%s] does not exist on model [%s].', $relationship, $model::class));
        }

        $relation = $model->{$relationship}();
        if (! $relation instanceof BelongsTo) {
            throw new InvalidArgumentException(sprintf('Analytics relationship [%s] on model [%s] must be a BelongsTo relation.', $relationship, $model::class));
        }

        return $relation;
    }

    /**
     * @param  array<string, array{alias:string, model:class-string<Model>, owner_key:string}>  $joins
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function measureSql(Builder $query, AnalyticsDataset $dataset, Measure $measure, array &$joins): array
    {
        $aggregation = $measure->aggregation();
        $filters = $measure->filtersDefinition();
        [$expressionSql, $expressionBindings] = $this->measureExpressionSql($query, $measure);

        if ($filters === []) {
            if ($aggregation === 'count') {
                return ['count(*)', []];
            }

            return [$aggregation.'('.$expressionSql.')', $expressionBindings];
        }

        $predicates = [];
        $bindings = [];
        foreach ($filters as $filter) {
            [$predicateSql, $predicateBindings] = $this->filterSql($query, $dataset, $filter, $joins);
            $predicates[] = '('.$predicateSql.')';
            $bindings = array_merge($bindings, $predicateBindings);
        }

        $predicateSql = implode(' and ', $predicates);

        if ($aggregation === 'count') {
            return ['count(case when '.$predicateSql.' then 1 end)', $bindings];
        }

        $thenValue = in_array($aggregation, ['sum'], true) ? '0' : 'null';

        return [
            $aggregation.'(case when '.$predicateSql.' then '.$expressionSql.' else '.$thenValue.' end)',
            array_merge($bindings, $expressionBindings),
        ];
    }

    /**
     * @param  array<int, AnalyticsAggregateFilter>  $filters
     * @param  array<string, array{measure:Measure, alias:string, sql:string, bindings:array<int, mixed>}>  $selectedMeasures
     */
    private function applyAggregateFilters(Builder $query, array $filters, array $selectedMeasures): void
    {
        foreach ($filters as $filter) {
            if (! isset($selectedMeasures[$filter->measure()])) {
                throw new InvalidArgumentException(sprintf('Analytics aggregate filter measure [%s] is not selected.', $filter->measure()));
            }

            [$sql, $bindings] = $this->comparisonSql(
                $selectedMeasures[$filter->measure()]['sql'],
                $filter->operator(),
                $filter->value()
            );

            $query->havingRaw($sql, array_merge($selectedMeasures[$filter->measure()]['bindings'], $bindings));
        }
    }

    /**
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function comparisonSql(string $fieldSql, string $operator, mixed $value): array
    {
        return match ($operator) {
            'equals' => $value === null ? [$fieldSql.' is null', []] : [$fieldSql.' = ?', [$value]],
            'not_equals' => $value === null ? [$fieldSql.' is not null', []] : [$fieldSql.' <> ?', [$value]],
            'gt' => [$fieldSql.' > ?', [$value]],
            'gte' => [$fieldSql.' >= ?', [$value]],
            'lt' => [$fieldSql.' < ?', [$value]],
            'lte' => [$fieldSql.' <= ?', [$value]],
            'in' => $this->listFilterSql($fieldSql, 'in', $value),
            'not_in' => $this->listFilterSql($fieldSql, 'not in', $value),
            'between' => $this->betweenFilterSql($fieldSql, $value),
            'is_null' => [$fieldSql.' is null', []],
            'is_not_null' => [$fieldSql.' is not null', []],
            default => throw new InvalidArgumentException(sprintf('Analytics comparison operator [%s] is not supported.', $operator)),
        };
    }

    /**
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function measureExpressionSql(Builder $query, Measure $measure): array
    {
        $expression = $measure->expression();

        if ($expression === null) {
            return ['*', []];
        }

        if (is_string($expression)) {
            return [$this->wrap($query, $this->qualifiedBaseColumn($query, $expression)), []];
        }

        return $this->expressionSql($query, $expression);
    }

    /**
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function expressionSql(Builder $query, AnalyticsExpression $expression): array
    {
        $operands = $expression->operands();

        return match ($expression->type()) {
            'column' => [$this->wrap($query, $this->qualifiedBaseColumn($query, (string) $operands[0])), []],
            'literal' => ['?', [$operands[0]]],
            'add' => $this->binaryExpressionSql($query, '+', $operands),
            'subtract' => $this->binaryExpressionSql($query, '-', $operands),
            'multiply' => $this->binaryExpressionSql($query, '*', $operands),
            'divide' => $this->binaryExpressionSql($query, '/', $operands),
            'clamp_minimum' => $this->clampMinimumSql($query, $operands),
            default => throw new InvalidArgumentException(sprintf('Analytics expression type [%s] is not supported.', $expression->type())),
        };
    }

    /**
     * @param  array<int, mixed>  $operands
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function binaryExpressionSql(Builder $query, string $operator, array $operands): array
    {
        if (count($operands) !== 2 || ! $operands[0] instanceof AnalyticsExpression || ! $operands[1] instanceof AnalyticsExpression) {
            throw new InvalidArgumentException('Analytics binary expression requires two expression operands.');
        }

        [$leftSql, $leftBindings] = $this->expressionSql($query, $operands[0]);
        [$rightSql, $rightBindings] = $this->expressionSql($query, $operands[1]);

        return ['('.$leftSql.' '.$operator.' '.$rightSql.')', array_merge($leftBindings, $rightBindings)];
    }

    /**
     * @param  array<int, mixed>  $operands
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function clampMinimumSql(Builder $query, array $operands): array
    {
        if (count($operands) !== 2 || ! $operands[0] instanceof AnalyticsExpression || ! $operands[1] instanceof AnalyticsExpression) {
            throw new InvalidArgumentException('Analytics clampMinimum expression requires two expression operands.');
        }

        [$expressionSql, $expressionBindings] = $this->expressionSql($query, $operands[0]);
        [$minimumSql, $minimumBindings] = $this->expressionSql($query, $operands[1]);

        return [
            '(case when '.$expressionSql.' < '.$minimumSql.' then '.$minimumSql.' else '.$expressionSql.' end)',
            array_merge($expressionBindings, $minimumBindings, $minimumBindings, $expressionBindings),
        ];
    }

    /**
     * @param  array<string, array{alias:string, model:class-string<Model>, owner_key:string}>  $joins
     */
    private function timeExpression(Builder $query, TimeDimension $dimension, string $grain, array &$joins): string
    {
        $field = $this->wrap($query, $this->timeFieldSql($query, $dimension, $joins));
        /** @var Connection $connection */
        $connection = $query->getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            return match ($grain) {
                'day' => 'date('.$field.')',
                'week' => 'date('.$field.", '-' || ((cast(strftime('%w', ".$field.") as integer) + 6) % 7) || ' days')",
                'month' => "strftime('%Y-%m-01', ".$field.')',
                'quarter' => "(strftime('%Y', ".$field.") || '-Q' || ((cast(strftime('%m', ".$field.') as integer) + 2) / 3))',
                'year' => "strftime('%Y', ".$field.')',
                default => throw new InvalidArgumentException(sprintf('Analytics time grain [%s] is not supported.', $grain)),
            };
        }

        if ($driver === 'pgsql') {
            return match ($grain) {
                'day' => 'date('.$field.')',
                'week' => "to_char(date_trunc('week', ".$field."), 'YYYY-MM-DD')",
                'month' => "to_char(date_trunc('month', ".$field."), 'YYYY-MM-DD')",
                'quarter' => 'to_char('.$field.", 'YYYY') || '-Q' || extract(quarter from ".$field.')',
                'year' => 'to_char('.$field.", 'YYYY')",
                default => throw new InvalidArgumentException(sprintf('Analytics time grain [%s] is not supported.', $grain)),
            };
        }

        return match ($grain) {
            'day' => 'date('.$field.')',
            'week' => 'date_sub(date('.$field.'), interval weekday('.$field.') day)',
            'month' => 'date_format('.$field.", '%Y-%m-01')",
            'quarter' => 'concat(year('.$field."), '-Q', quarter(".$field.'))',
            'year' => 'year('.$field.')',
            default => throw new InvalidArgumentException(sprintf('Analytics time grain [%s] is not supported.', $grain)),
        };
    }

    /**
     * @param  array<string, array{alias:string, model:class-string<Model>, owner_key:string}>  $joins
     */
    private function timeFieldSql(Builder $query, TimeDimension $dimension, array &$joins): string
    {
        $path = $dimension->relationshipPathDefinition();
        if ($path === []) {
            return $this->qualifiedBaseColumn($query, $dimension->fieldName());
        }

        $resolved = $this->ensureRelationshipPath($query, $path, $joins);

        return $resolved['alias'].'.'.$dimension->fieldName();
    }

    /**
     * @param  array<string, array{dimension:Dimension, value_alias:string, label_alias:string}>  $selectedDimensions
     * @param  array<string, array{measure:Measure, alias:string, sql:string, bindings:array<int, mixed>}>  $selectedMeasures
     * @param  array{dimension:TimeDimension, grain:string, alias:string}|null  $timeSelection
     */
    private function rankedRows(
        Builder $query,
        AnalyticsDataset $dataset,
        AnalyticsRanking $ranking,
        array $selectedDimensions,
        array $selectedMeasures,
        ?array $timeSelection,
        array $order,
        int $limit,
    ): Collection {
        if (! isset($selectedMeasures[$ranking->measure()])) {
            throw new InvalidArgumentException(sprintf('Analytics ranking measure [%s] is not selected on dataset [%s].', $ranking->measure(), $dataset->key()));
        }

        /** @var Connection $connection */
        $connection = $query->getConnection();
        $baseAlias = 'sf_analytics_rank_base';
        $rankedAlias = 'sf_analytics_ranked';
        $rankAlias = 'sf_analytics_rank';

        $windowQuery = $this->queryBuilder($connection);
        $ranked = $this->queryBuilder($connection)
            ->fromSub($query->toBase(), $baseAlias)
            ->select($baseAlias.'.*')
            ->selectRaw($this->rowNumberSql($windowQuery, $ranking, $selectedDimensions, $selectedMeasures, $timeSelection).' as '.$this->wrapBase($windowQuery, $rankAlias));

        $outer = $this->queryBuilder($connection)
            ->fromSub($ranked, $rankedAlias)
            ->select($rankedAlias.'.*')
            ->where($rankAlias, '<=', $ranking->limit());

        $this->applyOuterOrdering($outer, $dataset, $ranking, $order, $selectedDimensions, $selectedMeasures, $timeSelection, $rankAlias);
        $outer->limit($limit);

        return $outer->get();
    }

    private function queryBuilder(Connection $connection): QueryBuilder
    {
        return new QueryBuilder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());
    }

    /**
     * @param  array<string, array{dimension:Dimension, value_alias:string, label_alias:string}>  $selectedDimensions
     * @param  array<string, array{measure:Measure, alias:string, sql:string, bindings:array<int, mixed>}>  $selectedMeasures
     * @param  array{dimension:TimeDimension, grain:string, alias:string}|null  $timeSelection
     */
    private function rowNumberSql(
        QueryBuilder $query,
        AnalyticsRanking $ranking,
        array $selectedDimensions,
        array $selectedMeasures,
        ?array $timeSelection,
    ): string {
        $partition = $this->rankingPartitionAliases($ranking, $selectedDimensions, $timeSelection);
        $partitionSql = $partition === []
            ? ''
            : 'partition by '.implode(', ', array_map(fn (string $alias): string => $this->wrapBase($query, $alias), $partition)).' ';

        $orderParts = [
            $this->wrapBase($query, $selectedMeasures[$ranking->measure()]['alias']).' '.$ranking->direction(),
        ];

        foreach ($selectedDimensions as $key => $selection) {
            if (! in_array($selection['value_alias'], $partition, true)) {
                $orderParts[] = $this->wrapBase($query, $selection['label_alias']).' asc';
            }
        }

        if ($timeSelection !== null && ! in_array($timeSelection['alias'], $partition, true)) {
            $orderParts[] = $this->wrapBase($query, $timeSelection['alias']).' asc';
        }

        return 'row_number() over ('.$partitionSql.'order by '.implode(', ', array_values(array_unique($orderParts))).')';
    }

    /**
     * @param  array<string, array{dimension:Dimension, value_alias:string, label_alias:string}>  $selectedDimensions
     * @param  array{dimension:TimeDimension, grain:string, alias:string}|null  $timeSelection
     * @return array<int, string>
     */
    private function rankingPartitionAliases(AnalyticsRanking $ranking, array $selectedDimensions, ?array $timeSelection): array
    {
        $aliases = [];

        foreach ($ranking->partitionBy() as $partition) {
            if (AnalyticsRanking::isTimeToken($partition)) {
                if ($timeSelection !== null) {
                    $aliases[] = $timeSelection['alias'];
                }

                continue;
            }

            if (isset($selectedDimensions[$partition])) {
                $aliases[] = $selectedDimensions[$partition]['value_alias'];

                continue;
            }

            if ($timeSelection !== null && $partition === $timeSelection['dimension']->key()) {
                $aliases[] = $timeSelection['alias'];
            }
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @param  array<int, AnalyticsOrder>  $order
     * @param  array<string, array{dimension:Dimension, value_alias:string, label_alias:string}>  $selectedDimensions
     * @param  array<string, array{measure:Measure, alias:string, sql:string, bindings:array<int, mixed>}>  $selectedMeasures
     * @param  array{dimension:TimeDimension, grain:string, alias:string}|null  $timeSelection
     */
    private function applyOuterOrdering(
        QueryBuilder $query,
        AnalyticsDataset $dataset,
        AnalyticsRanking $ranking,
        array $order,
        array $selectedDimensions,
        array $selectedMeasures,
        ?array $timeSelection,
        string $rankAlias,
    ): void {
        if ($order === []) {
            foreach ($ranking->partitionBy() as $partition) {
                if (isset($selectedDimensions[$partition])) {
                    $query->orderBy($selectedDimensions[$partition]['label_alias']);

                    continue;
                }

                if (($partition === '$time' || ($timeSelection !== null && $partition === $timeSelection['dimension']->key())) && $timeSelection !== null) {
                    $query->orderBy($timeSelection['alias']);
                }
            }

            $query->orderBy($rankAlias);

            return;
        }

        foreach ($order as $orderItem) {
            $field = $orderItem->field();

            if (isset($selectedMeasures[$field])) {
                $query->orderBy($selectedMeasures[$field]['alias'], $orderItem->direction());

                continue;
            }

            if (isset($selectedDimensions[$field])) {
                $query->orderBy($selectedDimensions[$field]['label_alias'], $orderItem->direction());

                continue;
            }

            if ($timeSelection !== null && $field === $timeSelection['dimension']->key()) {
                $query->orderBy($timeSelection['alias'], $orderItem->direction());

                continue;
            }

            throw new InvalidArgumentException(sprintf('Analytics order field [%s] is not selected on dataset [%s].', $field, $dataset->key()));
        }
    }

    /**
     * @param  array<int, AnalyticsOrder>  $order
     * @param  array<string, array{dimension:Dimension, value_alias:string, label_alias:string}>  $selectedDimensions
     * @param  array<string, array{measure:Measure, alias:string, sql:string, bindings:array<int, mixed>}>  $selectedMeasures
     * @param  array{dimension:TimeDimension, grain:string, alias:string}|null  $timeSelection
     */
    private function applyOrdering(
        Builder $query,
        AnalyticsDataset $dataset,
        array $order,
        array $selectedDimensions,
        array $selectedMeasures,
        ?array $timeSelection,
    ): void {
        if ($order === []) {
            if ($timeSelection !== null) {
                $query->orderBy($timeSelection['alias']);

                return;
            }

            $firstMeasure = array_values($selectedMeasures)[0] ?? null;
            if ($selectedDimensions !== [] && is_array($firstMeasure)) {
                $query->orderBy($firstMeasure['alias'], 'desc');
            }

            return;
        }

        foreach ($order as $orderItem) {
            $field = $orderItem->field();

            if (isset($selectedMeasures[$field])) {
                $query->orderBy($selectedMeasures[$field]['alias'], $orderItem->direction());

                continue;
            }

            if (isset($selectedDimensions[$field])) {
                $query->orderBy($selectedDimensions[$field]['label_alias'], $orderItem->direction());

                continue;
            }

            if ($timeSelection !== null && $field === $timeSelection['dimension']->key()) {
                $query->orderBy($timeSelection['alias'], $orderItem->direction());

                continue;
            }

            throw new InvalidArgumentException(sprintf('Analytics order field [%s] is not selected on dataset [%s].', $field, $dataset->key()));
        }
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @param  array<string, array{dimension:Dimension, value_alias:string, label_alias:string}>  $selectedDimensions
     * @param  array<string, array{measure:Measure, alias:string, sql:string, bindings:array<int, mixed>}>  $selectedMeasures
     * @param  array{dimension:TimeDimension, grain:string, alias:string}|null  $timeSelection
     * @return array<int, array{dimensions:array<string, array{value:mixed,label:string}>, time:array{dimension:string,grain:string,value:mixed,label:string}|null, measures:array<string, int|float|null>}>
     */
    private function normalizeRows(Collection $rows, array $selectedDimensions, array $selectedMeasures, ?array $timeSelection): array
    {
        return $rows
            ->map(function (mixed $row) use ($selectedDimensions, $selectedMeasures, $timeSelection): array {
                $dimensions = [];
                foreach ($selectedDimensions as $key => $selection) {
                    $value = $this->rowAttribute($row, $selection['value_alias']);
                    $label = $selection['dimension']->displayLabel($value, $this->rowAttribute($row, $selection['label_alias']));
                    $dimensions[$key] = [
                        'value' => $value,
                        'label' => $label,
                    ];
                }

                $time = null;
                if ($timeSelection !== null) {
                    $value = $this->rowAttribute($row, $timeSelection['alias']);
                    $time = [
                        'dimension' => $timeSelection['dimension']->key(),
                        'grain' => $timeSelection['grain'],
                        'value' => $value,
                        'label' => (string) $value,
                    ];
                }

                $measures = [];
                foreach ($selectedMeasures as $key => $selection) {
                    $value = $this->rowAttribute($row, $selection['alias']);
                    $measures[$key] = $this->normalizeMeasureValue($selection['measure'], $value);
                }

                return [
                    'dimensions' => $dimensions,
                    'time' => $time,
                    'measures' => $measures,
                ];
            })
            ->values()
            ->all();
    }

    private function rowAttribute(mixed $row, string $attribute): mixed
    {
        if ($row instanceof Model) {
            return $row->getAttribute($attribute);
        }

        if (is_array($row)) {
            return $row[$attribute] ?? null;
        }

        if (is_object($row) && property_exists($row, $attribute)) {
            return $row->{$attribute};
        }

        return null;
    }

    private function normalizeMeasureValue(Measure $measure, mixed $value): int|float|null
    {
        if ($value === null) {
            return null;
        }

        if ($measure->typeName() === 'integer') {
            return (int) $value;
        }

        return (float) $value;
    }

    private function qualifiedBaseColumn(Builder $query, string $column): string
    {
        return $query->getModel()->getTable().'.'.$column;
    }

    private function wrap(Builder $query, string $identifier): string
    {
        return $query->getQuery()->getGrammar()->wrap($identifier);
    }

    private function wrapBase(QueryBuilder $query, string $identifier): string
    {
        return $query->getGrammar()->wrap($identifier);
    }

    private function alias(string $value): string
    {
        $safe = trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $value) ?: 'value', '_');
        $safe = $safe !== '' ? substr($safe, 0, 42) : 'value';

        return 'sf_analytics_'.$safe.'_'.substr(sha1($value), 0, 8);
    }
}
