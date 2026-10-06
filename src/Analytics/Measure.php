<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use InvalidArgumentException;

final class Measure
{
    /**
     * @var array<int, string>
     */
    public const AGGREGATIONS = ['count', 'sum', 'avg', 'min', 'max'];

    /**
     * @var array<int, string>
     */
    public const UNITS = ['number', 'integer', 'money', 'percent', 'duration', 'count'];

    /**
     * @var callable|null
     */
    private $authorizationCallback = null;

    private ?string $label = null;

    private ?string $description = null;

    /**
     * @var array<int, string>
     */
    private array $aliases = [];

    private string $aggregation = 'count';

    private AnalyticsExpression|string|null $expression = null;

    private string $type = 'number';

    private ?string $unit = null;

    /**
     * @var array<string, mixed>
     */
    private array $format = [];

    /**
     * @var array<int, AnalyticsFilter>
     */
    private array $filters = [];

    private function __construct(
        private readonly string $key,
    ) {
        self::assertKey($key, 'measure key');
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function label(?string $label): self
    {
        $this->label = $label !== null ? trim($label) : null;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description !== null ? $this->normalizeNullableText($description) : null;

        return $this;
    }

    /**
     * @param  array<int, string>  $aliases
     */
    public function aliases(array $aliases): self
    {
        $normalized = [];
        $seen = [];

        foreach ($aliases as $alias) {
            if (! is_string($alias)) {
                throw new InvalidArgumentException('Analytics measure aliases must contain only strings.');
            }

            $value = $this->normalizeNullableText($alias);
            if ($value === null) {
                throw new InvalidArgumentException('Analytics measure aliases cannot be empty.');
            }

            $fingerprint = $this->aliasFingerprint($value);
            if (isset($seen[$fingerprint])) {
                throw new InvalidArgumentException(sprintf('Analytics measure alias [%s] is duplicated.', $value));
            }

            $seen[$fingerprint] = true;
            $normalized[] = $value;
        }

        $this->aliases = $normalized;

        return $this;
    }

    public function alias(string $alias): self
    {
        return $this->aliases([...$this->aliases, $alias]);
    }

    public function count(): self
    {
        $this->aggregation = 'count';
        $this->expression = null;
        $this->type = 'integer';
        $this->unit = 'count';

        return $this;
    }

    public function sum(string|AnalyticsExpression $expression): self
    {
        return $this->aggregate('sum', $expression);
    }

    public function avg(string|AnalyticsExpression $expression): self
    {
        return $this->aggregate('avg', $expression);
    }

    public function min(string|AnalyticsExpression $expression): self
    {
        return $this->aggregate('min', $expression);
    }

    public function max(string|AnalyticsExpression $expression): self
    {
        return $this->aggregate('max', $expression);
    }

    public function type(string $type): self
    {
        $normalized = strtolower(trim($type));

        if (! in_array($normalized, self::UNITS, true)) {
            throw new InvalidArgumentException(sprintf('Analytics measure type [%s] is not supported.', $type));
        }

        $this->type = $normalized;
        if ($this->unit === null) {
            $this->unit = $normalized;
        }

        return $this;
    }

    public function unit(string $unit): self
    {
        $normalized = strtolower(trim($unit));

        if (! in_array($normalized, self::UNITS, true)) {
            throw new InvalidArgumentException(sprintf('Analytics measure unit [%s] is not supported.', $unit));
        }

        $this->unit = $normalized;
        if ($normalized !== 'count') {
            $this->type = $normalized;
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $format
     */
    public function format(array $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function currency(string $currency): self
    {
        $normalized = strtoupper(trim($currency));

        if ($normalized === '') {
            throw new InvalidArgumentException('Analytics measure currency cannot be empty.');
        }

        $this->format['currency'] = $normalized;
        $this->type = 'money';
        $this->unit = 'money';

        return $this;
    }

    public function decimals(int $decimals): self
    {
        if ($decimals < 0 || $decimals > 8) {
            throw new InvalidArgumentException('Analytics measure decimals must be between 0 and 8.');
        }

        $this->format['decimals'] = $decimals;

        return $this;
    }

    /**
     * @param  array<int, AnalyticsFilter>  $filters
     */
    public function filters(array $filters): self
    {
        foreach ($filters as $filter) {
            if (! $filter instanceof AnalyticsFilter) {
                throw new InvalidArgumentException('Measure filters must contain only AnalyticsFilter instances.');
            }
        }

        $this->filters = array_values($filters);

        return $this;
    }

    public function filter(AnalyticsFilter $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    public function authorize(callable $callback): self
    {
        $this->authorizationCallback = $callback;

        return $this;
    }

    public function canAccess(mixed $user = null): bool
    {
        if (! is_callable($this->authorizationCallback)) {
            return true;
        }

        return (bool) call_user_func($this->authorizationCallback, $user, $this);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return is_string($this->label) && $this->label !== ''
            ? $this->label
            : str((string) str_replace(['_', '-'], ' ', $this->key))->headline()->toString();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<int, string>
     */
    public function aliasesDefinition(): array
    {
        return $this->aliases;
    }

    public function aggregation(): string
    {
        return $this->aggregation;
    }

    public function expression(): AnalyticsExpression|string|null
    {
        return $this->expression;
    }

    public function typeName(): string
    {
        return $this->type;
    }

    public function unitName(): string
    {
        if ($this->unit !== null) {
            return $this->unit;
        }

        return $this->aggregation === 'count' ? 'count' : $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatMetadata(): array
    {
        return $this->format;
    }

    /**
     * @return array<int, AnalyticsFilter>
     */
    public function filtersDefinition(): array
    {
        return $this->filters;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->getLabel(),
            'description' => $this->description,
            'aliases' => $this->aliases,
            'aggregation' => $this->aggregation,
            'expression' => $this->expression instanceof AnalyticsExpression
                ? $this->expression->toArray()
                : $this->expression,
            'type' => $this->type,
            'unit' => $this->unitName(),
            'format' => $this->format,
            'filters' => array_map(static fn (AnalyticsFilter $filter): array => $filter->toArray(), $this->filters),
        ];
    }

    private function aggregate(string $aggregation, string|AnalyticsExpression $expression): self
    {
        if (! in_array($aggregation, self::AGGREGATIONS, true) || $aggregation === 'count') {
            throw new InvalidArgumentException(sprintf('Analytics aggregation [%s] is not supported for field expressions.', $aggregation));
        }

        if (is_string($expression)) {
            AnalyticsExpression::column($expression);
        }

        $this->aggregation = $aggregation;
        $this->expression = $expression;
        if ($this->type === 'integer') {
            $this->type = 'number';
        }
        if ($this->unit === 'count') {
            $this->unit = null;
        }

        return $this;
    }

    private function normalizeNullableText(string $value): ?string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $value) ?: $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function aliasFingerprint(string $value): string
    {
        return mb_strtolower($value);
    }

    private static function assertKey(string $key, string $label): void
    {
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $key)) {
            throw new InvalidArgumentException(sprintf('Analytics %s [%s] is invalid.', $label, $key));
        }
    }
}
