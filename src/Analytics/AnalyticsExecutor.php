<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use SeviaFlow\Core\Analytics\Exceptions\AnalyticsAuthorizationException;
use SeviaFlow\Core\Analytics\Exceptions\AnalyticsQueryTooComplexException;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceAuthorization;

final class AnalyticsExecutor
{
    public function __construct(
        private readonly AnalyticsRegistry $registry,
        private readonly AnalyticsCompiler $compiler = new AnalyticsCompiler,
        private readonly AnalyticsComplexity $complexity = new AnalyticsComplexity,
    ) {}

    public function execute(AnalyticsQuery $query, ?Authenticatable $user = null): AnalyticsResult
    {
        $plan = $this->plan($query, $user);

        return $this->executeForUser($user, fn (): AnalyticsResult => $this->compiler->execute(
            dataset: $plan['dataset'],
            analyticsQuery: $query,
            measureKeys: $plan['measures'],
            dimensionKeys: $plan['dimensions'],
            time: $plan['time'],
            filters: $plan['filters'],
            aggregateFilters: $plan['aggregate_filters'],
            ranking: $plan['ranking'],
            order: $plan['order'],
            limit: $plan['limit'],
            complexity: $plan['complexity'],
            user: $user,
        ));
    }

    public function canExecute(AnalyticsQuery $query, ?Authenticatable $user = null): bool
    {
        try {
            $this->plan($query, $user);

            return true;
        } catch (AnalyticsAuthorizationException) {
            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function explain(AnalyticsQuery $query, ?Authenticatable $user = null): array
    {
        $plan = $this->plan($query, $user);
        /** @var AnalyticsDataset $dataset */
        $dataset = $plan['dataset'];

        return [
            'dataset' => $dataset->key(),
            'measures' => $plan['measures'],
            'dimensions' => $plan['dimensions'],
            'time' => $plan['time'],
            'filters' => array_map(static fn (AnalyticsFilter $filter): array => $filter->toArray(), $plan['filters']),
            'aggregate_filters' => array_map(static fn (AnalyticsAggregateFilter $filter): array => $filter->toArray(), $plan['aggregate_filters']),
            'ranking' => $plan['ranking'] instanceof AnalyticsRanking ? $plan['ranking']->toArray() : null,
            'order' => array_map(static fn (AnalyticsOrder $order): array => $order->toArray(), $plan['order']),
            'limit' => $plan['limit'],
            'complexity' => $plan['complexity'],
        ];
    }

    /**
     * @return array{
     *   dataset:AnalyticsDataset,
     *   measures:array<int, string>,
     *   dimensions:array<int, string>,
     *   time:array{dimension:string, grain:string, from:string|null, to:string|null}|null,
     *   filters:array<int, AnalyticsFilter>,
     *   aggregate_filters:array<int, AnalyticsAggregateFilter>,
     *   ranking:AnalyticsRanking|null,
     *   order:array<int, AnalyticsOrder>,
     *   limit:int,
     *   complexity:array{score:int, max:int, breakdown:array<string, int>}
     * }
     */
    private function plan(AnalyticsQuery $query, ?Authenticatable $user): array
    {
        $dataset = $this->registry->resolve($query->dataset());

        if (! $dataset->canAccess($user)) {
            throw new AnalyticsAuthorizationException(sprintf('Analytics dataset [%s] is not authorized for the current user.', $dataset->key()));
        }

        $measures = $query->measuresDefinition();
        if ($measures === []) {
            throw new InvalidArgumentException('Analytics query requires at least one measure.');
        }

        foreach ($measures as $measureKey) {
            $measure = $dataset->measure($measureKey);
            if (! $measure->canAccess($user)) {
                throw new AnalyticsAuthorizationException(sprintf('Analytics measure [%s] is not authorized for the current user.', $measureKey));
            }

            foreach ($measure->filtersDefinition() as $filter) {
                $this->assertFilterFieldIsRegistered($dataset, $filter);
                $this->assertRelationshipAccessForFilter($dataset, $filter, $user);
            }
        }

        $dimensions = $query->dimensionsDefinition();
        $maxDimensions = max(0, (int) config('seviaflow.analytics.max_dimensions', 4));
        if (count($dimensions) > $maxDimensions) {
            throw new InvalidArgumentException(sprintf('Analytics query supports at most %d dimensions.', $maxDimensions));
        }

        foreach ($dimensions as $dimensionKey) {
            $dimension = $dataset->dimension($dimensionKey);
            $this->assertRelationshipDepth($dimension, 'dimension');
            $this->assertRelationshipResourceAccess($dimension, $user);
        }

        $time = $query->timeDefinition();
        if ($time !== null) {
            $timeDimension = $dataset->timeDimension($time['dimension']);
            if (! $timeDimension->supportsGrain($time['grain'])) {
                throw new InvalidArgumentException(sprintf('Analytics time dimension [%s] does not support grain [%s].', $time['dimension'], $time['grain']));
            }

            $this->assertRelationshipDepth($timeDimension, 'time dimension');
            $this->assertRelationshipResourceAccess($timeDimension, $user);
        }

        foreach ($query->filtersDefinition() as $filter) {
            $this->assertFilterFieldIsRegistered($dataset, $filter);
            $this->assertFilterRelationshipDepth($dataset, $filter);
            $this->assertRelationshipAccessForFilter($dataset, $filter, $user);
        }

        $aggregateFilters = $query->aggregateFiltersDefinition();
        foreach ($aggregateFilters as $filter) {
            $measureKey = $filter->measure();
            if (! in_array($measureKey, $measures, true)) {
                throw new InvalidArgumentException(sprintf('Analytics aggregate filter measure [%s] must be selected before filtering.', $measureKey));
            }

            $measure = $dataset->measure($measureKey);
            if (! $measure->canAccess($user)) {
                throw new AnalyticsAuthorizationException(sprintf('Analytics measure [%s] is not authorized for the current user.', $measureKey));
            }
        }

        $ranking = $query->rankingDefinition();
        if ($ranking instanceof AnalyticsRanking) {
            $rankingMeasure = $ranking->measure();
            if (! in_array($rankingMeasure, $measures, true)) {
                throw new InvalidArgumentException(sprintf('Analytics ranking measure [%s] must be selected before ranking.', $rankingMeasure));
            }

            $maxRankingLimit = max(1, (int) config('seviaflow.analytics.max_ranking_limit', 25));
            if ($ranking->limit() > $maxRankingLimit) {
                throw new InvalidArgumentException(sprintf('Analytics ranking limit [%d] exceeds the configured max [%d].', $ranking->limit(), $maxRankingLimit));
            }

            foreach ($ranking->partitionBy() as $partition) {
                if (AnalyticsRanking::isTimeToken($partition)) {
                    if ($time === null) {
                        throw new InvalidArgumentException('Analytics ranking partition $time requires a selected time dimension.');
                    }

                    continue;
                }

                if (! in_array($partition, $dimensions, true) && ($time === null || $partition !== $time['dimension'])) {
                    throw new InvalidArgumentException(sprintf('Analytics ranking partition [%s] must be a selected dimension or selected time dimension.', $partition));
                }
            }
        }

        foreach ($query->orderDefinition() as $order) {
            $field = $order->field();
            if (! in_array($field, $measures, true)
                && ! in_array($field, $dimensions, true)
                && ($time === null || $field !== $time['dimension'])) {
                throw new InvalidArgumentException(sprintf('Analytics order field [%s] must be selected before ordering.', $field));
            }
        }

        $grouped = $dimensions !== [] || $time !== null;
        $limit = $this->normalizeLimit($query->limitValue(), $grouped);
        $complexity = $this->complexity->score(
            dataset: $dataset,
            measureKeys: $measures,
            dimensionKeys: $dimensions,
            time: $time,
            filters: $query->filtersDefinition(),
            aggregateFilters: $aggregateFilters,
            ranking: $ranking,
            limit: $limit,
        );

        if ($complexity['score'] > $complexity['max']) {
            throw new AnalyticsQueryTooComplexException($complexity['score'], $complexity['max'], $complexity['breakdown']);
        }

        return [
            'dataset' => $dataset,
            'measures' => $measures,
            'dimensions' => $dimensions,
            'time' => $time,
            'filters' => $query->filtersDefinition(),
            'aggregate_filters' => $aggregateFilters,
            'ranking' => $ranking,
            'order' => $query->orderDefinition(),
            'limit' => $limit,
            'complexity' => $complexity,
        ];
    }

    private function assertFilterFieldIsRegistered(AnalyticsDataset $dataset, AnalyticsFilter $filter): void
    {
        if (! $dataset->hasDimension($filter->field()) && ! $dataset->hasTimeDimension($filter->field())) {
            throw new InvalidArgumentException(sprintf(
                'Analytics filter field [%s] is not registered on dataset [%s].',
                $filter->field(),
                $dataset->key()
            ));
        }
    }

    private function assertFilterRelationshipDepth(AnalyticsDataset $dataset, AnalyticsFilter $filter): void
    {
        if ($dataset->hasDimension($filter->field())) {
            $this->assertRelationshipDepth($dataset->dimension($filter->field()), 'filter dimension');

            return;
        }

        if ($dataset->hasTimeDimension($filter->field())) {
            $this->assertRelationshipDepth($dataset->timeDimension($filter->field()), 'filter time dimension');
        }
    }

    private function assertRelationshipAccessForFilter(
        AnalyticsDataset $dataset,
        AnalyticsFilter $filter,
        ?Authenticatable $user
    ): void {
        if ($dataset->hasDimension($filter->field())) {
            $this->assertRelationshipResourceAccess($dataset->dimension($filter->field()), $user);

            return;
        }

        if ($dataset->hasTimeDimension($filter->field())) {
            $this->assertRelationshipResourceAccess($dataset->timeDimension($filter->field()), $user);
        }
    }

    private function assertRelationshipResourceAccess(
        Dimension|TimeDimension $definition,
        ?Authenticatable $user
    ): void {
        $path = $definition->relationshipPathDefinition();
        if ($path === []) {
            return;
        }

        $resourceClasses = $definition->relationshipResourceClasses();
        if (count($resourceClasses) !== count($path)) {
            throw new InvalidArgumentException(sprintf(
                'Analytics relationship path [%s] must declare one governing Resource per hop.',
                implode('.', $path)
            ));
        }

        foreach ($resourceClasses as $resourceClass) {
            if (! is_a($resourceClass, Resource::class, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Analytics relationship Resource [%s] must extend %s.',
                    $resourceClass,
                    Resource::class
                ));
            }

            if (! ResourceAuthorization::for($resourceClass)->canViewAny($user)) {
                throw new AnalyticsAuthorizationException(sprintf(
                    'Analytics relationship Resource [%s] is not authorized for the current user.',
                    $resourceClass
                ));
            }
        }
    }

    private function assertRelationshipDepth(Dimension|TimeDimension $dimension, string $type): void
    {
        $maxDepth = max(0, (int) config('seviaflow.analytics.max_relationship_depth', 3));

        if ($dimension->relationshipDepth() > $maxDepth) {
            throw new InvalidArgumentException(sprintf(
                'Analytics %s [%s] relationship depth [%d] exceeds the configured max [%d].',
                $type,
                $dimension->key(),
                $dimension->relationshipDepth(),
                $maxDepth
            ));
        }
    }

    private function normalizeLimit(?int $limit, bool $grouped): int
    {
        $default = (int) config('seviaflow.analytics.default_limit', 20);
        $max = (int) config('seviaflow.analytics.max_limit', 500);
        $safeDefault = $default > 0 ? $default : 20;
        $safeMax = $max > 0 ? $max : 500;

        if (! $grouped) {
            return 1;
        }

        if ($limit === null) {
            return min($safeDefault, $safeMax);
        }

        return min($limit, $safeMax);
    }

    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    private function executeForUser(?Authenticatable $user, callable $callback): mixed
    {
        if (! $user instanceof Authenticatable) {
            return $callback();
        }

        $guard = Auth::guard();
        $previousUser = $guard->user();
        Auth::setUser($user);

        try {
            return $callback();
        } finally {
            if ($previousUser instanceof Authenticatable) {
                Auth::setUser($previousUser);
            } elseif (method_exists($guard, 'forgetUser')) {
                $guard->forgetUser();
            }
        }
    }
}
