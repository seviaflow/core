<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

final class AnalyticsComplexity
{
    /**
     * @param  array<int, string>  $measureKeys
     * @param  array<int, string>  $dimensionKeys
     * @param  array{dimension:string, grain:string, from:string|null, to:string|null}|null  $time
     * @param  array<int, AnalyticsFilter>  $filters
     * @param  array<int, AnalyticsAggregateFilter>  $aggregateFilters
     * @return array{score:int, max:int, breakdown:array<string, int>}
     */
    public function score(
        AnalyticsDataset $dataset,
        array $measureKeys,
        array $dimensionKeys,
        ?array $time,
        array $filters,
        array $aggregateFilters,
        ?AnalyticsRanking $ranking,
        int $limit,
    ): array {
        $relationshipPrefixes = [];

        foreach ($dimensionKeys as $dimensionKey) {
            $this->addRelationshipPrefixes($relationshipPrefixes, $dataset->dimension($dimensionKey)->relationshipPathDefinition());
        }

        if ($time !== null) {
            $this->addRelationshipPrefixes($relationshipPrefixes, $dataset->timeDimension($time['dimension'])->relationshipPathDefinition());
        }

        foreach ($filters as $filter) {
            $this->addRelationshipPrefixesForField($relationshipPrefixes, $dataset, $filter->field());
        }

        foreach ($measureKeys as $measureKey) {
            foreach ($dataset->measure($measureKey)->filtersDefinition() as $filter) {
                $this->addRelationshipPrefixesForField($relationshipPrefixes, $dataset, $filter->field());
            }
        }

        $breakdown = [
            'base' => 1,
            'dimensions' => count($dimensionKeys),
            'time_dimension' => $time !== null ? 1 : 0,
            'relationship_hops' => count($relationshipPrefixes),
            'aggregate_filters' => count($aggregateFilters),
            'ranking' => $ranking instanceof AnalyticsRanking ? 2 : 0,
            'limit' => $this->limitScore($limit),
        ];

        return [
            'score' => array_sum($breakdown),
            'max' => max(1, (int) config('seviaflow.analytics.max_complexity', 10)),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @param  array<string, true>  $prefixes
     * @param  array<int, string>  $path
     */
    private function addRelationshipPrefixes(array &$prefixes, array $path): void
    {
        $prefix = [];

        foreach ($path as $relationship) {
            $prefix[] = $relationship;
            $prefixes[implode('.', $prefix)] = true;
        }
    }

    /**
     * @param  array<string, true>  $prefixes
     */
    private function addRelationshipPrefixesForField(array &$prefixes, AnalyticsDataset $dataset, string $field): void
    {
        if ($dataset->hasDimension($field)) {
            $this->addRelationshipPrefixes($prefixes, $dataset->dimension($field)->relationshipPathDefinition());

            return;
        }

        if ($dataset->hasTimeDimension($field)) {
            $this->addRelationshipPrefixes($prefixes, $dataset->timeDimension($field)->relationshipPathDefinition());
        }
    }

    private function limitScore(int $limit): int
    {
        $threshold = max(1, (int) config('seviaflow.analytics.complexity_limit_threshold', config('seviaflow.analytics.default_limit', 20)));
        $divisor = max(1, (int) config('seviaflow.analytics.limit_complexity_divisor', 100));

        if ($limit <= $threshold) {
            return 0;
        }

        return (int) ceil(($limit - $threshold) / $divisor);
    }
}
