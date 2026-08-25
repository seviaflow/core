<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;

final class BelongsToFilter extends Filter
{
    private const DEFAULT_OPTIONS_LIMIT = 50;

    private ?string $relationshipName = null;

    private ?string $titleAttribute = null;

    private int $optionsLimit = self::DEFAULT_OPTIONS_LIMIT;

    private ?Closure $queryCallback = null;

    public function relationship(string $relationship, string $titleAttribute = 'id'): static
    {
        $relationship = trim($relationship);
        $titleAttribute = trim($titleAttribute);

        if ($relationship === '') {
            throw new InvalidArgumentException('BelongsToFilter relationship name cannot be empty.');
        }

        if ($titleAttribute === '') {
            throw new InvalidArgumentException('BelongsToFilter title attribute cannot be empty.');
        }

        $this->relationshipName = $relationship;
        $this->titleAttribute = $titleAttribute;

        return $this;
    }

    public function getRelationshipName(): string
    {
        if ($this->relationshipName === null) {
            throw new LogicException('BelongsToFilter relationship metadata is required. Call relationship() first.');
        }

        return $this->relationshipName;
    }

    public function getTitleAttribute(): string
    {
        if ($this->titleAttribute === null) {
            throw new LogicException('BelongsToFilter relationship metadata is required. Call relationship() first.');
        }

        return $this->titleAttribute;
    }

    public function optionsLimit(int $limit): static
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('BelongsToFilter options limit must be at least 1.');
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
     * @param  class-string<Model>|Model  $resourceModel
     * @return array<array-key, string>
     */
    public function getOptionsFor(Model|string $resourceModel): array
    {
        $model = is_string($resourceModel) ? new $resourceModel : $resourceModel;
        $relation = $this->belongsToRelation($model);

        $query = $relation->getRelated()->newQuery();
        $query->select([$relation->getOwnerKeyName(), $this->getTitleAttribute()]);

        if ($this->queryCallback !== null) {
            $result = ($this->queryCallback)($query);

            if ($result !== null && ! $result instanceof Builder) {
                throw new InvalidArgumentException(sprintf(
                    'BelongsToFilter query callback for filter [%s] must return [%s] or null, [%s] given.',
                    $this->name(),
                    Builder::class,
                    get_debug_type($result)
                ));
            }

            if ($result instanceof Builder) {
                $query = $result;
            }
        }

        return $query
            ->limit($this->optionsLimit)
            ->pluck($this->getTitleAttribute(), $relation->getOwnerKeyName())
            ->map(static fn (mixed $label): string => (string) $label)
            ->all();
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if (! $this->isActive($value)) {
            return $query;
        }

        if (! is_scalar($value)) {
            return $query;
        }

        $selected = (string) $value;

        if ($selected === '') {
            return $query;
        }

        $relation = $this->belongsToRelation($query->getModel());
        $existsQuery = $relation->getRelated()->newQuery()->whereKey($selected);

        if ($this->queryCallback !== null) {
            $result = ($this->queryCallback)($existsQuery);

            if ($result !== null && ! $result instanceof Builder) {
                throw new InvalidArgumentException(sprintf(
                    'BelongsToFilter query callback for filter [%s] must return [%s] or null, [%s] given.',
                    $this->name(),
                    Builder::class,
                    get_debug_type($result)
                ));
            }

            if ($result instanceof Builder) {
                $existsQuery = $result;
            }
        }

        if (! $existsQuery->exists()) {
            return $query;
        }

        return $query->where($this->name(), '=', $selected);
    }

    private function belongsToRelation(Model $model): BelongsTo
    {
        $relation = $model->{$this->getRelationshipName()}();

        if (! $relation instanceof BelongsTo) {
            throw new InvalidArgumentException(sprintf(
                'Relationship [%s] on model [%s] must be an instance of [%s].',
                $this->getRelationshipName(),
                $model::class,
                BelongsTo::class
            ));
        }

        return $relation;
    }
}
