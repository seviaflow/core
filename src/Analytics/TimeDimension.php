<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Analytics;

use InvalidArgumentException;

final class TimeDimension
{
    /**
     * @var array<int, string>
     */
    public const array GRAINS = ['day', 'week', 'month', 'quarter', 'year'];

    private ?string $label = null;

    private ?string $field = null;

    /**
     * @var array<int, string>
     */
    private array $grains = self::GRAINS;

    private function __construct(
        private readonly string $key,
    ) {
        self::assertKey($key, 'time dimension key');
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

    public function field(string $field): self
    {
        self::assertColumn($field, 'time dimension field');

        $this->field = $field;

        return $this;
    }

    /**
     * @param  array<int, string>  $grains
     */
    public function grains(array $grains): self
    {
        $normalized = [];

        foreach ($grains as $grain) {
            if (! is_string($grain)) {
                throw new InvalidArgumentException('Analytics time grains must be strings.');
            }

            $value = strtolower(trim($grain));
            if (! in_array($value, self::GRAINS, true)) {
                throw new InvalidArgumentException(sprintf('Analytics time grain [%s] is not supported.', $grain));
            }

            $normalized[] = $value;
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('Analytics time dimension requires at least one supported grain.');
        }

        $this->grains = array_values(array_unique($normalized));

        return $this;
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

    public function fieldName(): string
    {
        if (! is_string($this->field) || $this->field === '') {
            return $this->key;
        }

        return $this->field;
    }

    /**
     * @return array<int, string>
     */
    public function grainsDefinition(): array
    {
        return $this->grains;
    }

    public function supportsGrain(string $grain): bool
    {
        return in_array(strtolower(trim($grain)), $this->grains, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->getLabel(),
            'field' => $this->fieldName(),
            'grains' => $this->grains,
        ];
    }

    private static function assertKey(string $key, string $label): void
    {
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $key)) {
            throw new InvalidArgumentException(sprintf('Analytics %s [%s] is invalid.', $label, $key));
        }
    }

    private static function assertColumn(string $column, string $label): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            throw new InvalidArgumentException(sprintf('Analytics %s [%s] is invalid.', $label, $column));
        }
    }
}
