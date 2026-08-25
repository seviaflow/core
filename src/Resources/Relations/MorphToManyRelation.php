<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Relations;

use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use SeviaFlow\Core\Resources\Columns\Column;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Resource;

final class MorphToManyRelation
{
    private const DEFAULT_PER_PAGE = 10;

    private const DEFAULT_OPTIONS_LIMIT = 50;

    private ?string $label = null;

    /**
     * @var class-string<resource>|null
     */
    private ?string $resourceClass = null;

    private string $titleAttribute = 'id';

    private int $perPage = self::DEFAULT_PER_PAGE;

    private int $optionsLimit = self::DEFAULT_OPTIONS_LIMIT;

    private bool $allowAttach = true;

    private bool $allowDetach = true;

    /**
     * @var array<int, Column>
     */
    private array $pivotColumns = [];

    /**
     * @var array<int, Field>
     */
    private array $pivotFields = [];

    private function __construct(
        private readonly string $relationshipName
    ) {
        if (trim($relationshipName) === '') {
            throw new InvalidArgumentException('MorphToManyRelation relationship name cannot be empty.');
        }
    }

    public static function make(string $relationship): self
    {
        return new self($relationship);
    }

    public function getRelationshipName(): string
    {
        return $this->relationshipName;
    }

    public function label(?string $label = null): static
    {
        if (func_num_args() === 0) {
            return $this;
        }

        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        if ($this->label !== null) {
            return $this->label;
        }

        return Str::of($this->relationshipName)
            ->replace('_', ' ')
            ->lower()
            ->ucfirst()
            ->toString();
    }

    /**
     * @param  class-string  $resourceClass
     */
    public function resource(string $resourceClass): static
    {
        if (! is_a($resourceClass, Resource::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'MorphToManyRelation resource class [%s] must extend [%s].',
                $resourceClass,
                Resource::class
            ));
        }

        /** @var class-string<resource> $resourceClass */
        $this->resourceClass = $resourceClass;

        return $this;
    }

    /**
     * @return class-string<resource>
     */
    public function getResourceClass(): string
    {
        if ($this->resourceClass === null) {
            throw new LogicException('MorphToManyRelation resource class is required. Call resource() first.');
        }

        return $this->resourceClass;
    }

    public function titleAttribute(string $attribute): static
    {
        $attribute = trim($attribute);

        if ($attribute === '') {
            throw new InvalidArgumentException('MorphToManyRelation title attribute cannot be empty.');
        }

        $this->titleAttribute = $attribute;

        return $this;
    }

    public function getTitleAttribute(): string
    {
        return $this->titleAttribute;
    }

    public function perPage(int $perPage): static
    {
        if ($perPage < 1) {
            throw new InvalidArgumentException('MorphToManyRelation per-page value must be at least 1.');
        }

        $this->perPage = $perPage;

        return $this;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function optionsLimit(int $limit): static
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('MorphToManyRelation options limit must be at least 1.');
        }

        $this->optionsLimit = $limit;

        return $this;
    }

    public function getOptionsLimit(): int
    {
        return $this->optionsLimit;
    }

    public function allowAttach(bool $allow = true): static
    {
        $this->allowAttach = $allow;

        return $this;
    }

    public function canAttach(): bool
    {
        return $this->allowAttach;
    }

    public function allowDetach(bool $allow = true): static
    {
        $this->allowDetach = $allow;

        return $this;
    }

    public function canDetach(): bool
    {
        return $this->allowDetach;
    }

    /**
     * @param  array<int, Column>  $columns
     */
    public function pivotColumns(array $columns): static
    {
        foreach ($columns as $index => $column) {
            if (! $column instanceof Column) {
                throw new InvalidArgumentException(sprintf(
                    'MorphToManyRelation pivot column at index %d must be an instance of [%s], [%s] given.',
                    $index,
                    Column::class,
                    get_debug_type($column)
                ));
            }
        }

        $this->pivotColumns = array_values($columns);

        return $this;
    }

    /**
     * @return array<int, Column>
     */
    public function getPivotColumns(): array
    {
        return $this->pivotColumns;
    }

    /**
     * @param  array<int, Field>  $fields
     */
    public function pivotFields(array $fields): static
    {
        foreach ($fields as $index => $field) {
            if (! $field instanceof Field) {
                throw new InvalidArgumentException(sprintf(
                    'MorphToManyRelation pivot field at index %d must be an instance of [%s], [%s] given.',
                    $index,
                    Field::class,
                    get_debug_type($field)
                ));
            }
        }

        $this->pivotFields = array_values($fields);

        return $this;
    }

    /**
     * @return array<int, Field>
     */
    public function getPivotFields(): array
    {
        return $this->pivotFields;
    }
}
