<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use InvalidArgumentException;
use SeviaFlow\Core\Resources\Fields\Field;

final class Section
{
    private ?string $description = null;

    private ?int $columnSpan = null;

    private bool $collapsible = false;

    private bool $collapsed = false;

    /**
     * @var array<int, Field>
     */
    private array $fields = [];

    private function __construct(
        private readonly string $label
    ) {}

    public static function make(string $label): self
    {
        return new self($label);
    }

    public function label(): string
    {
        return $this->label;
    }

    public function description(?string $description = null): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function columnSpan(int|string $span): static
    {
        $this->columnSpan = self::normalizeColumnSpan($span);

        return $this;
    }

    public function getColumnSpan(): ?int
    {
        return $this->columnSpan;
    }

    public function collapsible(bool $condition = true): static
    {
        $this->collapsible = $condition;

        return $this;
    }

    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    public function collapsed(bool $condition = true): static
    {
        $this->collapsed = $condition;

        return $this;
    }

    public function isCollapsed(): bool
    {
        return $this->collapsed;
    }

    /**
     * @param  array<int, Field>  $fields
     */
    public function fields(array $fields): static
    {
        foreach ($fields as $index => $field) {
            if (! $field instanceof Field) {
                throw new InvalidArgumentException(sprintf(
                    'Section field at index %d must be an instance of [%s], [%s] given.',
                    $index,
                    Field::class,
                    get_debug_type($field)
                ));
            }
        }

        $this->fields = array_values($fields);

        return $this;
    }

    /**
     * @return array<int, Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    private static function normalizeColumnSpan(int|string $span): int
    {
        if ($span === 'full') {
            return 12;
        }

        if (! is_int($span) && (! is_string($span) || ! ctype_digit($span))) {
            throw new InvalidArgumentException('Section column span must be an integer or "full".');
        }

        $normalized = (int) $span;
        if ($normalized < 1 || $normalized > 12) {
            throw new InvalidArgumentException('Section column span must be between 1 and 12.');
        }

        return $normalized;
    }
}
