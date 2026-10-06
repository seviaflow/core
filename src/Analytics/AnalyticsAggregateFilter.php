<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use InvalidArgumentException;

final class AnalyticsAggregateFilter
{
    /**
     * @var array<int, string>
     */
    public const OPERATORS = AnalyticsFilter::OPERATORS;

    private function __construct(
        private readonly string $measure,
        private readonly string $operator,
        private readonly mixed $value = null,
    ) {
        self::assertKey($measure, 'aggregate filter measure');

        if (! in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException(sprintf('Analytics aggregate filter operator [%s] is not supported.', $operator));
        }
    }

    public static function make(string $measure): self
    {
        return new self($measure, 'equals', null);
    }

    public static function equals(string $measure, string|int|float|bool|null $value): self
    {
        return new self($measure, 'equals', $value);
    }

    public static function notEquals(string $measure, string|int|float|bool|null $value): self
    {
        return new self($measure, 'not_equals', $value);
    }

    /**
     * @param  array<int, string|int|float|bool|null>  $values
     */
    public static function in(string $measure, array $values): self
    {
        return new self($measure, 'in', self::normalizeValues($values, 'in'));
    }

    /**
     * @param  array<int, string|int|float|bool|null>  $values
     */
    public static function notIn(string $measure, array $values): self
    {
        return new self($measure, 'not_in', self::normalizeValues($values, 'not_in'));
    }

    public static function gt(string $measure, string|int|float $value): self
    {
        return new self($measure, 'gt', $value);
    }

    public static function gte(string $measure, string|int|float $value): self
    {
        return new self($measure, 'gte', $value);
    }

    public static function lt(string $measure, string|int|float $value): self
    {
        return new self($measure, 'lt', $value);
    }

    public static function lte(string $measure, string|int|float $value): self
    {
        return new self($measure, 'lte', $value);
    }

    public static function between(string $measure, string|int|float $from, string|int|float $to): self
    {
        return new self($measure, 'between', [$from, $to]);
    }

    public static function isNull(string $measure): self
    {
        return new self($measure, 'is_null');
    }

    public static function isNotNull(string $measure): self
    {
        return new self($measure, 'is_not_null');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $measure = $payload['measure'] ?? null;
        $operator = $payload['operator'] ?? null;

        if (! is_string($measure) || ! is_string($operator)) {
            throw new InvalidArgumentException('Analytics aggregate filter arrays require string measure and operator values.');
        }

        return new self($measure, $operator, $payload['value'] ?? null);
    }

    public function measure(): string
    {
        return $this->measure;
    }

    public function operator(): string
    {
        return $this->operator;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    /**
     * @return array{measure:string, operator:string, value:mixed}
     */
    public function toArray(): array
    {
        return [
            'measure' => $this->measure,
            'operator' => $this->operator,
            'value' => $this->value,
        ];
    }

    /**
     * @param  array<int, string|int|float|bool|null>  $values
     * @return array<int, string|int|float|bool|null>
     */
    private static function normalizeValues(array $values, string $operator): array
    {
        if ($values === []) {
            throw new InvalidArgumentException(sprintf('Analytics aggregate filter operator [%s] requires at least one value.', $operator));
        }

        return array_values($values);
    }

    private static function assertKey(string $key, string $label): void
    {
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $key)) {
            throw new InvalidArgumentException(sprintf('Analytics %s [%s] is invalid.', $label, $key));
        }
    }
}
