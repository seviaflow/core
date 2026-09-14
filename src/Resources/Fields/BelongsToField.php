<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use LogicException;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Core\Resources\ResourceScopedQuery;

final class BelongsToField extends Field
{
    private const DEFAULT_OPTIONS_LIMIT = 50;

    private ?string $relationshipName = null;

    private ?string $titleAttribute = null;

    private int $optionsLimit = self::DEFAULT_OPTIONS_LIMIT;

    private ?Closure $queryCallback = null;

    /**
     * @var class-string<resource>|null
     */
    private ?string $resourceClass = null;

    /**
     * @var array<int, string>
     */
    private array $searchableOptionColumns = [];

    /**
     * @var class-string<resource>|null
     */
    private ?string $inlineCreateResourceClass = null;

    private ?string $inlineCreateLabel = null;

    private ?string $dependsOnField = null;

    private ?Closure $dependsOnCallback = null;

    public function relationship(string $relationship, string $titleAttribute = 'id'): static
    {
        $relationship = trim($relationship);
        $titleAttribute = trim($titleAttribute);

        if ($relationship === '') {
            throw new InvalidArgumentException('BelongsToField relationship name cannot be empty.');
        }

        if ($titleAttribute === '') {
            throw new InvalidArgumentException('BelongsToField title attribute cannot be empty.');
        }

        $this->relationshipName = $relationship;
        $this->titleAttribute = $titleAttribute;

        return $this;
    }

    public function getRelationshipName(): string
    {
        if ($this->relationshipName === null) {
            throw new LogicException('BelongsToField relationship metadata is required. Call relationship() first.');
        }

        return $this->relationshipName;
    }

    public function getTitleAttribute(): string
    {
        if ($this->titleAttribute === null) {
            throw new LogicException('BelongsToField relationship metadata is required. Call relationship() first.');
        }

        return $this->titleAttribute;
    }

    public function optionsLimit(int $limit): static
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('BelongsToField options limit must be at least 1.');
        }

        $this->optionsLimit = $limit;

        return $this;
    }

    public function getOptionsLimit(): int
    {
        return $this->optionsLimit;
    }

    public function query(callable $callback): static
    {
        $this->queryCallback = Closure::fromCallable($callback);

        return $this;
    }

    public function getQueryCallback(): ?Closure
    {
        return $this->queryCallback;
    }

    /**
     * @param  class-string  $resourceClass
     */
    public function resource(string $resourceClass): static
    {
        if (! is_a($resourceClass, Resource::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'BelongsToField resource class [%s] must extend [%s].',
                $resourceClass,
                Resource::class
            ));
        }

        /** @var class-string<resource> $resourceClass */
        $this->resourceClass = $resourceClass;

        return $this;
    }

    /**
     * @return class-string<resource>|null
     */
    public function getResourceClass(): ?string
    {
        return $this->resourceClass;
    }

    public function applyQuery(Builder $query): Builder
    {
        $callback = $this->queryCallback;

        if ($callback === null) {
            return $query;
        }

        $result = $callback($query);

        if ($result !== null) {
            if (! $result instanceof Builder) {
                throw new InvalidArgumentException(sprintf(
                    'BelongsToField query callback for field [%s] must return [%s] or null, [%s] given.',
                    $this->name(),
                    Builder::class,
                    get_debug_type($result)
                ));
            }

            return $result;
        }

        return $query;
    }

    public function applyDependencyQuery(Builder $query, mixed $parentValue): Builder
    {
        $callback = $this->dependsOnCallback;

        if ($callback === null) {
            return $query;
        }

        $result = $callback($query, $parentValue);

        if ($result !== null) {
            if (! $result instanceof Builder) {
                throw new InvalidArgumentException(sprintf(
                    'BelongsToField dependsOn callback for field [%s] must return [%s] or null, [%s] given.',
                    $this->name(),
                    Builder::class,
                    get_debug_type($result)
                ));
            }

            return $result;
        }

        return $query;
    }

    public function applyResourceScope(Builder $query, Model $relatedModel): Builder
    {
        $resourceClass = $this->resourceClass;

        if ($resourceClass === null && function_exists('app') && app()->bound(ResourceRegistry::class)) {
            $resourceClass = app(ResourceRegistry::class)->findByModel($relatedModel::class);
        }

        if ($resourceClass === null) {
            return $query;
        }

        return ResourceScopedQuery::make($resourceClass, $query);
    }

    /**
     * @param  array<int, string>  $columns
     */
    public function searchableOptions(array $columns): static
    {
        $normalized = [];

        foreach ($columns as $column) {
            $name = trim($column);

            if ($name === '') {
                throw new InvalidArgumentException('BelongsToField searchable option columns cannot contain empty values.');
            }

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
                throw new InvalidArgumentException(sprintf(
                    'BelongsToField searchable option column [%s] is invalid.',
                    $column
                ));
            }

            $normalized[] = $name;
        }

        $this->searchableOptionColumns = array_values(array_unique($normalized));

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getSearchableOptionColumns(): array
    {
        return $this->searchableOptionColumns;
    }

    /**
     * @param  class-string  $resourceClass
     */
    public function inlineCreate(string $resourceClass): static
    {
        if (! is_a($resourceClass, Resource::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'BelongsToField inline create resource class [%s] must extend [%s].',
                $resourceClass,
                Resource::class
            ));
        }

        /** @var class-string<resource> $resourceClass */
        $this->inlineCreateResourceClass = $resourceClass;

        return $this;
    }

    /**
     * @return class-string<resource>|null
     */
    public function getInlineCreateResourceClass(): ?string
    {
        return $this->inlineCreateResourceClass;
    }

    public function inlineCreateLabel(string $label): static
    {
        $label = trim($label);

        if ($label === '') {
            throw new InvalidArgumentException('BelongsToField inline create label cannot be empty.');
        }

        $this->inlineCreateLabel = $label;

        return $this;
    }

    public function getInlineCreateLabel(): ?string
    {
        return $this->inlineCreateLabel;
    }

    public function dependsOn(string $fieldName, callable $callback): static
    {
        $fieldName = trim($fieldName);

        if ($fieldName === '') {
            throw new InvalidArgumentException('BelongsToField dependsOn field name cannot be empty.');
        }

        $this->dependsOnField = $fieldName;
        $this->dependsOnCallback = Closure::fromCallable($callback);

        return $this;
    }

    public function getDependsOnField(): ?string
    {
        return $this->dependsOnField;
    }

    public function getDependsOnCallback(): ?Closure
    {
        return $this->dependsOnCallback;
    }

    public function isDependent(): bool
    {
        return $this->dependsOnField !== null && $this->dependsOnCallback !== null;
    }

    public function defaultValidationRules(
        ResourceContext|string|null $context = null,
        mixed $record = null,
        ?string $modelClass = null,
        array $fieldValues = []
    ): array {
        if ($modelClass === null) {
            return [];
        }

        $model = new $modelClass;
        $relation = $model->{$this->getRelationshipName()}();

        if (! $relation instanceof BelongsTo) {
            throw new InvalidArgumentException(sprintf(
                'Relationship [%s] on model [%s] must be an instance of [%s].',
                $this->getRelationshipName(),
                $model::class,
                BelongsTo::class
            ));
        }

        $rules = [
            Rule::exists(
                $relation->getRelated()->getTable(),
                $relation->getOwnerKeyName()
            ),
        ];

        $rules[] = function (string $attribute, mixed $value, Closure $fail) use ($relation, $fieldValues): void {
            if ($value === null || $value === '') {
                return;
            }

            $query = $relation->getRelated()->newQuery();
            $query = $this->applyQuery($query);

            if ($this->isDependent()) {
                $parentValue = $fieldValues[$this->getDependsOnField()] ?? null;

                if ($parentValue === null || $parentValue === '') {
                    $fail('The selected value is invalid for the current dependency.');

                    return;
                }

                $query = $this->applyDependencyQuery($query, $parentValue);
            }

            $query = $this->applyResourceScope($query, $relation->getRelated());

            if (! $query->whereKey($value)->exists()) {
                $fail($this->isDependent()
                    ? 'The selected value is invalid for the current dependency.'
                    : 'The selected value is invalid.');
            }
        };

        return $rules;
    }
}
