<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use SeviaFlow\Core\Analytics\Exceptions\AnalyticsAuthorizationException;

final class AnalyticsExecutor
{
    public function __construct(
        private readonly AnalyticsRegistry $registry,
        private readonly AnalyticsCompiler $compiler = new AnalyticsCompiler,
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
            order: $plan['order'],
            limit: $plan['limit'],
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
            'order' => array_map(static fn (AnalyticsOrder $order): array => $order->toArray(), $plan['order']),
            'limit' => $plan['limit'],
        ];
    }

    /**
     * @return array{
     *   dataset:AnalyticsDataset,
     *   measures:array<int, string>,
     *   dimensions:array<int, string>,
     *   time:array{dimension:string, grain:string, from:string|null, to:string|null}|null,
     *   filters:array<int, AnalyticsFilter>,
     *   order:array<int, AnalyticsOrder>,
     *   limit:int
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
            }
        }

        $dimensions = $query->dimensionsDefinition();
        if (count($dimensions) > 1) {
            throw new InvalidArgumentException('Analytics query supports at most one dimension in this release.');
        }

        foreach ($dimensions as $dimensionKey) {
            $dataset->dimension($dimensionKey);
        }

        $time = $query->timeDefinition();
        if ($time !== null) {
            $timeDimension = $dataset->timeDimension($time['dimension']);
            if (! $timeDimension->supportsGrain($time['grain'])) {
                throw new InvalidArgumentException(sprintf('Analytics time dimension [%s] does not support grain [%s].', $time['dimension'], $time['grain']));
            }
        }

        foreach ($query->filtersDefinition() as $filter) {
            $this->assertFilterFieldIsRegistered($dataset, $filter);
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

        return [
            'dataset' => $dataset,
            'measures' => $measures,
            'dimensions' => $dimensions,
            'time' => $time,
            'filters' => $query->filtersDefinition(),
            'order' => $query->orderDefinition(),
            'limit' => $this->normalizeLimit($query->limitValue(), $grouped),
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
