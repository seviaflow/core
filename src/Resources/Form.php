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
     * @var array<int, Tab>
     */
    private array $tabs = [];

    private ?int $columns = null;

    /**
     * @param  array<int, mixed>  $fields
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
     * @param  array<int, mixed>  $sections
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

    public function columns(int $columns): self
    {
        if ($columns < 1 || $columns > 12) {
            throw new InvalidArgumentException('Form columns must be between 1 and 12.');
        }

        $this->columns = $columns;

        return $this;
    }

    public function getColumns(): ?int
    {
        return $this->columns;
    }

    /**
     * @param  array<int, Tab>  $tabs
     */
    public function tabs(array $tabs): self
    {
        foreach ($tabs as $index => $tab) {
            if (! $tab instanceof Tab) {
                throw new InvalidArgumentException(sprintf(
                    'Form tab at index %d must be an instance of [%s], [%s] given.',
                    $index,
                    Tab::class,
                    get_debug_type($tab)
                ));
            }
        }

        $this->tabs = array_values($tabs);

        return $this;
    }

    /**
     * @return array<int, Tab>
     */
    public function getTabs(): array
    {
        return $this->tabs;
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

        foreach ($this->tabs as $tab) {
            foreach ($tab->getAllFields() as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }
}
