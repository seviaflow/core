<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use Illuminate\Support\Str;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Fields\Field;

final class Tab
{
    private ?string $description = null;

    /**
     * @var array<int, Field>
     */
    private array $fields = [];

    /**
     * @var array<int, Section>
     */
    private array $sections = [];

    private ?string $key = null;

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

    public function key(string $key): static
    {
        $normalized = trim($key);

        if ($normalized === '') {
            throw new InvalidArgumentException('Tab key cannot be empty.');
        }

        $this->key = $normalized;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key ?? Str::slug($this->label);
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
                    'Tab field at index %d must be an instance of [%s], [%s] given.',
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

    /**
     * @param  array<int, Section>  $sections
     */
    public function sections(array $sections): static
    {
        foreach ($sections as $index => $section) {
            if (! $section instanceof Section) {
                throw new InvalidArgumentException(sprintf(
                    'Tab section at index %d must be an instance of [%s], [%s] given.',
                    $index,
                    Section::class,
                    get_debug_type($section)
                ));
            }
        }

        $this->sections = array_values($sections);

        return $this;
    }

    /**
     * @return array<int, Section>
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * @return array<int, Field>
     */
    public function getAllFields(): array
    {
        $fields = $this->fields;

        foreach ($this->sections as $section) {
            $fields = array_merge($fields, $section->getFields());
        }

        return $fields;
    }
}
