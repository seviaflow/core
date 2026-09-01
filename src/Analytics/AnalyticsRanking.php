<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use InvalidArgumentException;

final class AnalyticsRanking
{
    /**
     * @param  array<int, string>  $partitionBy
     */
    private function __construct(
        private readonly string $measure,
        private readonly string $direction,
        private readonly int $limit,
        private readonly array $partitionBy = [],
    ) {
        self::assertKey($measure, 'ranking measure');

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException(sprintf('Analytics ranking direction [%s] is not supported.', $direction));
        }

        if ($limit < 1) {
            throw new InvalidArgumentException('Analytics ranking limit must be greater than zero.');
        }

        foreach ($partitionBy as $partition) {
            if (! is_string($partition) || (! self::isTimeToken($partition) && ! self::isSemanticKey($partition))) {
                throw new InvalidArgumentException('Analytics ranking partition_by must contain semantic keys or $time.');
            }
        }
    }

    /**
     * @param  array<int, string>  $partitionBy
     */
    public static function make(string $measure, string $direction, int $limit, array $partitionBy = []): self
    {
        return new self($measure, strtolower(trim($direction)), $limit, array_values($partitionBy));
    }

    /**
     * @param  array<int, string>  $partitionBy
     */
    public static function top(string $measure, int $limit, array $partitionBy = []): self
    {
        return self::make($measure, 'desc', $limit, $partitionBy);
    }

    /**
     * @param  array<int, string>  $partitionBy
     */
    public static function bottom(string $measure, int $limit, array $partitionBy = []): self
    {
        return self::make($measure, 'asc', $limit, $partitionBy);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $measure = $payload['measure'] ?? null;
        $direction = $payload['direction'] ?? null;
        $limit = $payload['limit'] ?? null;
        $partitionBy = $payload['partition_by'] ?? [];

        if (! is_string($measure) || ! is_string($direction) || ! is_numeric($limit) || ! is_array($partitionBy)) {
            throw new InvalidArgumentException('Analytics ranking arrays require measure, direction, limit and partition_by values.');
        }

        return self::make($measure, $direction, (int) $limit, array_values($partitionBy));
    }

    public function measure(): string
    {
        return $this->measure;
    }

    public function direction(): string
    {
        return $this->direction;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    /**
     * @return array<int, string>
     */
    public function partitionBy(): array
    {
        return $this->partitionBy;
    }

    /**
     * @return array{measure:string, direction:string, limit:int, partition_by:array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'measure' => $this->measure,
            'direction' => $this->direction,
            'limit' => $this->limit,
            'partition_by' => $this->partitionBy,
        ];
    }

    public static function isTimeToken(string $value): bool
    {
        return $value === '$time';
    }

    private static function isSemanticKey(string $key): bool
    {
        return (bool) preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $key);
    }

    private static function assertKey(string $key, string $label): void
    {
        if (! self::isSemanticKey($key)) {
            throw new InvalidArgumentException(sprintf('Analytics %s [%s] is invalid.', $label, $key));
        }
    }
}
