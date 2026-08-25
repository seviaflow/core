<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources;

use InvalidArgumentException;
use SeviaFlow\Core\Resources\Fields\Field;

final class Form
{
    /**
     * @var array<int, Field>
     */
    private array $fields = [];

    /**
     * @var array<int, Section>
     */
    private array $sections = [];

    /**
     * @param  array<int, Field>  $fields
     */
    public function fields(array $fields): self
    {
        foreach ($fields as $index => $field) {
            if (! $field instanceof Field) {
                throw new InvalidArgumentException(sprintf(
                    'Form field at index %d must be an instance of [%s], [%s] given.',
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
    public function sections(array $sections): self
    {
        foreach ($sections as $index => $section) {
            if (! $section instanceof Section) {
                throw new InvalidArgumentException(sprintf(
                    'Form section at index %d must be an instance of [%s], [%s] given.',
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
            foreach ($section->getFields() as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }
}
