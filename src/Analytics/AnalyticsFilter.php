<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use InvalidArgumentException;

final class AnalyticsFilter
{
    /**
     * @var array<int, string>
     */
    public const OPERATORS = [
        'equals',
        'not_equals',
        'in',
        'not_in',
        'gt',
        'gte',
        'lt',
        'lte',
        'between',
        'is_null',
        'is_not_null',
    ];

    private function __construct(
        private readonly string $field,
        private readonly string $operator,
        private readonly mixed $value = null,
    ) {
        self::assertKey($field, 'filter field');

        if (! in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException(sprintf('Analytics filter operator [%s] is not supported.', $operator));
        }
    }

    public static function make(string $field): self
    {
        return new self($field, 'equals', null);
    }

    public static function equals(string $field, string|int|float|bool|null $value): self
    {
        return new self($field, 'equals', $value);
    }

    public static function notEquals(string $field, string|int|float|bool|null $value): self
    {
        return new self($field, 'not_equals', $value);
    }

    /**
     * @param  array<int, string|int|float|bool|null>  $values
     */
    public static function in(string $field, array $values): self
    {
        return new self($field, 'in', self::normalizeValues($values, 'in'));
    }

    /**
     * @param  array<int, string|int|float|bool|null>  $values
     */
    public static function notIn(string $field, array $values): self
    {
        return new self($field, 'not_in', self::normalizeValues($values, 'not_in'));
    }

    public static function gt(string $field, string|int|float $value): self
    {
        return new self($field, 'gt', $value);
    }

    public static function gte(string $field, string|int|float $value): self
    {
        return new self($field, 'gte', $value);
    }

    public static function lt(string $field, string|int|float $value): self
    {
        return new self($field, 'lt', $value);
    }

    public static function lte(string $field, string|int|float $value): self
    {
        return new self($field, 'lte', $value);
    }

    public static function between(string $field, string|int|float $from, string|int|float $to): self
    {
        return new self($field, 'between', [$from, $to]);
    }

    public static function isNull(string $field): self
    {
        return new self($field, 'is_null');
    }

    public static function isNotNull(string $field): self
    {
        return new self($field, 'is_not_null');
    }

    public static function fromArray(array $payload): self
    {
        $field = $payload['field'] ?? null;
        $operator = $payload['operator'] ?? null;

        if (! is_string($field) || ! is_string($operator)) {
            throw new InvalidArgumentException('Analytics filter arrays require string field and operator values.');
        }

        return new self($field, $operator, $payload['value'] ?? null);
    }

    public function field(): string
    {
        return $this->field;
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
     * @return array{field:string, operator:string, value:mixed}
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'operator' => $this->operator,
            'value' => $this->value,
        ];
    }

    public function equalsValue(string|int|float|bool|null $value): self
    {
        return new self($this->field, 'equals', $value);
    }

    public function notEqualsValue(string|int|float|bool|null $value): self
    {
        return new self($this->field, 'not_equals', $value);
    }

    /**
     * @param  array<int, string|int|float|bool|null>  $values
     */
    public function inValues(array $values): self
    {
        return new self($this->field, 'in', self::normalizeValues($values, 'in'));
    }

    /**
     * @param  array<int, string|int|float|bool|null>  $values
     */
    public function notInValues(array $values): self
    {
        return new self($this->field, 'not_in', self::normalizeValues($values, 'not_in'));
    }

    public function greaterThan(string|int|float $value): self
    {
        return new self($this->field, 'gt', $value);
    }

    public function greaterThanOrEqual(string|int|float $value): self
    {
        return new self($this->field, 'gte', $value);
    }

    public function lessThan(string|int|float $value): self
    {
        return new self($this->field, 'lt', $value);
    }

    public function lessThanOrEqual(string|int|float $value): self
    {
        return new self($this->field, 'lte', $value);
    }

    public function betweenValues(string|int|float $from, string|int|float $to): self
    {
        return new self($this->field, 'between', [$from, $to]);
    }

    public function whereNull(): self
    {
        return new self($this->field, 'is_null');
    }

    public function whereNotNull(): self
    {
        return new self($this->field, 'is_not_null');
    }

    /**
     * @param  array<int, string|int|float|bool|null>  $values
     * @return array<int, string|int|float|bool|null>
     */
    private static function normalizeValues(array $values, string $operator): array
    {
        if ($values === []) {
            throw new InvalidArgumentException(sprintf('Analytics filter operator [%s] requires at least one value.', $operator));
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
