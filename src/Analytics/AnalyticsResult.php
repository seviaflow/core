<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use InvalidArgumentException;

final class AnalyticsResult
{
    /**
     * @param  array<int, array{dimensions:array<string, array{value:mixed,label:string}>, time:array{dimension:string,grain:string,value:mixed,label:string}|null, measures:array<string, int|float|null>}>  $rows
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly string $dataset,
        private readonly array $query,
        private readonly array $rows,
        private readonly array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $dataset = $payload['dataset'] ?? null;
        $query = $payload['query'] ?? null;
        $rows = $payload['rows'] ?? null;
        $metadata = $payload['metadata'] ?? [];

        if (! is_string($dataset) || ! is_array($query) || ! is_array($rows) || ! is_array($metadata)) {
            throw new InvalidArgumentException('Analytics result arrays require dataset, query, rows and metadata values.');
        }

        return new self($dataset, $query, array_values($rows), $metadata);
    }

    public function dataset(): string
    {
        return $this->dataset;
    }

    /**
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * @return array<int, array{dimensions:array<string, array{value:mixed,label:string}>, time:array{dimension:string,grain:string,value:mixed,label:string}|null, measures:array<string, int|float|null>}>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    public function firstMeasureValue(?string $measure = null): int|float|null
    {
        $first = $this->rows[0]['measures'] ?? [];

        if ($first === []) {
            return null;
        }

        if ($measure !== null) {
            $value = $first[$measure] ?? null;

            return is_int($value) || is_float($value) ? $value : null;
        }

        $value = array_values($first)[0] ?? null;

        return is_int($value) || is_float($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dataset' => $this->dataset,
            'query' => $this->query,
            'rows' => $this->rows,
            'metadata' => $this->metadata,
        ];
    }
}
