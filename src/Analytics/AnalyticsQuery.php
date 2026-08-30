<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use InvalidArgumentException;

final class AnalyticsQuery
{
    /**
     * @var array<int, string>
     */
    private array $measures = [];

    /**
     * @var array<int, string>
     */
    private array $dimensions = [];

    /**
     * @var array{dimension:string, grain:string, from:string|null, to:string|null}|null
     */
    private ?array $time = null;

    /**
     * @var array<int, AnalyticsFilter>
     */
    private array $filters = [];

    /**
     * @var array<int, AnalyticsOrder>
     */
    private array $order = [];

    private ?int $limit = null;

    private function __construct(
        private readonly string $dataset,
    ) {
        self::assertKey($dataset, 'dataset key');
    }

    public static function make(string $dataset): self
    {
        return new self($dataset);
    }

    public static function fromArray(array $payload): self
    {
        $dataset = $payload['dataset'] ?? null;
        if (! is_string($dataset)) {
            throw new InvalidArgumentException('Analytics query arrays require a string dataset.');
        }

        $query = self::make($dataset);

        /** @var mixed $measures */
        $measures = $payload['measures'] ?? [];
        if (is_array($measures)) {
            $query->measures(array_values($measures));
        }

        /** @var mixed $dimensions */
        $dimensions = $payload['dimensions'] ?? [];
        if (is_array($dimensions)) {
            $query->dimensions(array_values($dimensions));
        }

        /** @var mixed $time */
        $time = $payload['time'] ?? null;
        if (is_array($time) && isset($time['dimension'], $time['grain']) && is_string($time['dimension']) && is_string($time['grain'])) {
            $query->time(
                $time['dimension'],
                $time['grain'],
                is_string($time['from'] ?? null) ? $time['from'] : null,
                is_string($time['to'] ?? null) ? $time['to'] : null
            );
        }

        /** @var mixed $filters */
        $filters = $payload['filters'] ?? [];
        if (is_array($filters)) {
            foreach ($filters as $filter) {
                if (is_array($filter)) {
                    $query->filter(AnalyticsFilter::fromArray($filter));
                }
            }
        }

        /** @var mixed $order */
        $order = $payload['order'] ?? [];
        if (is_array($order)) {
            foreach ($order as $orderItem) {
                if (is_array($orderItem)) {
                    $query->order(AnalyticsOrder::fromArray($orderItem));
                }
            }
        }

        if (isset($payload['limit']) && is_numeric($payload['limit'])) {
            $query->limit((int) $payload['limit']);
        }

        return $query;
    }

    public function measure(string $measure): self
    {
        self::assertKey($measure, 'measure key');
        $this->measures[] = $measure;
        $this->measures = array_values(array_unique($this->measures));

        return $this;
    }

    /**
     * @param  array<int, string>  $measures
     */
    public function measures(array $measures): self
    {
        $this->measures = [];

        foreach ($measures as $measure) {
            if (! is_string($measure)) {
                throw new InvalidArgumentException('Analytics query measures must be strings.');
            }

            $this->measure($measure);
        }

        return $this;
    }

    public function dimension(string $dimension): self
    {
        self::assertKey($dimension, 'dimension key');
        $this->dimensions[] = $dimension;
        $this->dimensions = array_values(array_unique($this->dimensions));

        return $this;
    }

    /**
     * @param  array<int, string>  $dimensions
     */
    public function dimensions(array $dimensions): self
    {
        $this->dimensions = [];

        foreach ($dimensions as $dimension) {
            if (! is_string($dimension)) {
                throw new InvalidArgumentException('Analytics query dimensions must be strings.');
            }

            $this->dimension($dimension);
        }

        return $this;
    }

    public function time(string $dimension, string $grain, ?string $from = null, ?string $to = null): self
    {
        self::assertKey($dimension, 'time dimension key');
        $normalizedGrain = strtolower(trim($grain));

        if (! in_array($normalizedGrain, TimeDimension::GRAINS, true)) {
            throw new InvalidArgumentException(sprintf('Analytics time grain [%s] is not supported.', $grain));
        }

        $this->time = [
            'dimension' => $dimension,
            'grain' => $normalizedGrain,
            'from' => $from !== null ? self::dateString($from, 'from') : null,
            'to' => $to !== null ? self::dateString($to, 'to') : null,
        ];

        return $this;
    }

    public function filter(AnalyticsFilter $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * @param  array<int, AnalyticsFilter>  $filters
     */
    public function filters(array $filters): self
    {
        $this->filters = [];

        foreach ($filters as $filter) {
            if (! $filter instanceof AnalyticsFilter) {
                throw new InvalidArgumentException('Analytics query filters must contain only AnalyticsFilter instances.');
            }

            $this->filter($filter);
        }

        return $this;
    }

    public function order(AnalyticsOrder $order): self
    {
        $this->order[] = $order;

        return $this;
    }

    public function orderBy(string $field, string $direction = 'asc'): self
    {
        return $this->order(AnalyticsOrder::make($field, $direction));
    }

    public function limit(int $limit): self
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Analytics query limit must be greater than zero.');
        }

        $this->limit = $limit;

        return $this;
    }

    public function dataset(): string
    {
        return $this->dataset;
    }

    /**
     * @return array<int, string>
     */
    public function measuresDefinition(): array
    {
        return $this->measures;
    }

    /**
     * @return array<int, string>
     */
    public function dimensionsDefinition(): array
    {
        return $this->dimensions;
    }

    /**
     * @return array{dimension:string, grain:string, from:string|null, to:string|null}|null
     */
    public function timeDefinition(): ?array
    {
        return $this->time;
    }

    /**
     * @return array<int, AnalyticsFilter>
     */
    public function filtersDefinition(): array
    {
        return $this->filters;
    }

    /**
     * @return array<int, AnalyticsOrder>
     */
    public function orderDefinition(): array
    {
        return $this->order;
    }

    public function limitValue(): ?int
    {
        return $this->limit;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dataset' => $this->dataset,
            'measures' => $this->measures,
            'dimensions' => $this->dimensions,
            'time' => $this->time,
            'filters' => array_map(static fn (AnalyticsFilter $filter): array => $filter->toArray(), $this->filters),
            'order' => array_map(static fn (AnalyticsOrder $order): array => $order->toArray(), $this->order),
            'limit' => $this->limit,
        ];
    }

    private static function assertKey(string $key, string $label): void
    {
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $key)) {
            throw new InvalidArgumentException(sprintf('Analytics %s [%s] is invalid.', $label, $key));
        }
    }

    private static function dateString(string $date, string $field): string
    {
        $normalized = trim($date);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
            throw new InvalidArgumentException(sprintf('Analytics time %s date [%s] must use YYYY-MM-DD.', $field, $date));
        }

        return $normalized;
    }
}
