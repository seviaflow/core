<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use InvalidArgumentException;
use SeviaFlow\Core\Resources\Fields\Field;

final class Section
{
    private ?string $description = null;

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
}
