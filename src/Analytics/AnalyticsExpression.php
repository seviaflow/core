<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use InvalidArgumentException;

final class AnalyticsExpression
{
    /**
     * @param  array<int, AnalyticsExpression|int|float|string>  $operands
     */
    private function __construct(
        private readonly string $type,
        private readonly array $operands,
    ) {}

    public static function column(string $column): self
    {
        self::assertColumn($column);

        return new self('column', [$column]);
    }

    public static function literal(int|float $value): self
    {
        return new self('literal', [$value]);
    }

    public static function add(self|string|int|float $left, self|string|int|float $right): self
    {
        return new self('add', [self::normalizeOperand($left), self::normalizeOperand($right)]);
    }

    public static function subtract(self|string|int|float $left, self|string|int|float $right): self
    {
        return new self('subtract', [self::normalizeOperand($left), self::normalizeOperand($right)]);
    }

    public static function multiply(self|string|int|float $left, self|string|int|float $right): self
    {
        return new self('multiply', [self::normalizeOperand($left), self::normalizeOperand($right)]);
    }

    public static function divide(self|string|int|float $left, self|string|int|float $right): self
    {
        return new self('divide', [self::normalizeOperand($left), self::normalizeOperand($right)]);
    }

    public static function clampMinimum(self|string|int|float $expression, int|float $minimum): self
    {
        return new self('clamp_minimum', [self::normalizeOperand($expression), self::literal($minimum)]);
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * @return array<int, AnalyticsExpression|int|float|string>
     */
    public function operands(): array
    {
        return $this->operands;
    }

    /**
     * @return array<int, string>
     */
    public function columns(): array
    {
        if ($this->type === 'column') {
            return [(string) $this->operands[0]];
        }

        $columns = [];
        foreach ($this->operands as $operand) {
            if ($operand instanceof self) {
                $columns = array_merge($columns, $operand->columns());
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'operands' => array_map(
                static fn (mixed $operand): mixed => $operand instanceof self ? $operand->toArray() : $operand,
                $this->operands
            ),
        ];
    }

    private static function normalizeOperand(self|string|int|float $operand): self
    {
        if ($operand instanceof self) {
            return $operand;
        }

        if (is_string($operand)) {
            return self::column($operand);
        }

        return self::literal($operand);
    }

    private static function assertColumn(string $column): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            throw new InvalidArgumentException(sprintf('Analytics expression column [%s] is invalid.', $column));
        }
    }
}
