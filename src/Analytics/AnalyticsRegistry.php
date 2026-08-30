<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

final class AnalyticsRegistry
{
    /**
     * @var array<string, AnalyticsDataset>
     */
    private array $datasets = [];

    /**
     * @param  array<int, AnalyticsDataset>  $datasets
     */
    public function __construct(array $datasets = [])
    {
        foreach ($datasets as $dataset) {
            $this->register($dataset);
        }
    }

    public function register(AnalyticsDataset $dataset): self
    {
        if (isset($this->datasets[$dataset->key()])) {
            throw new InvalidArgumentException(sprintf('Analytics dataset [%s] is already registered.', $dataset->key()));
        }

        $this->datasets[$dataset->key()] = $dataset;

        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->datasets[$key]);
    }

    public function resolve(string $key): AnalyticsDataset
    {
        if (! isset($this->datasets[$key])) {
            throw new InvalidArgumentException(sprintf('Analytics dataset [%s] is not registered.', $key));
        }

        return $this->datasets[$key];
    }

    /**
     * @return array<string, AnalyticsDataset>
     */
    public function all(): array
    {
        return $this->datasets;
    }

    /**
     * @return array<string, AnalyticsDataset>
     */
    public function visibleFor(?Authenticatable $user): array
    {
        $visible = [];

        foreach ($this->datasets as $key => $dataset) {
            if ($dataset->canAccess($user)) {
                $visible[$key] = $dataset;
            }
        }

        return $visible;
    }

    /**
     * @return array<string, mixed>
     */
    public function catalog(?Authenticatable $user = null): array
    {
        return [
            'datasets' => array_map(
                static fn (AnalyticsDataset $dataset): array => $dataset->toArray(),
                $this->visibleFor($user)
            ),
        ];
    }
}
