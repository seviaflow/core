<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\ResourceContext;

final class BelongsToManyField extends MultiSelectField
{
    protected static string $type = 'belongs-to-many';

    private ?BelongsToField $optionField = null;

    private function optionField(): BelongsToField
    {
        return $this->optionField ??= BelongsToField::make($this->name());
    }

    public function relationship(string $relationship, string $titleAttribute = 'id'): static
    {
        $this->optionField()->relationship($relationship, $titleAttribute);

        return $this;
    }

    public function getRelationshipName(): string
    {
        return $this->optionField()->getRelationshipName();
    }

    public function query(callable $callback): static
    {
        $this->optionField()->query($callback);

        return $this;
    }

    /** @param class-string $resourceClass */
    public function resource(string $resourceClass): static
    {
        $this->optionField()->resource($resourceClass);

        return $this;
    }

    public function optionsLimit(int $limit): static
    {
        $this->optionField()->optionsLimit($limit);

        return $this;
    }

    /** @param array<int, string> $columns */
    public function searchableOptions(array $columns): static
    {
        $this->optionField()->searchableOptions($columns);

        return $this;
    }

    public function relation(Model $model): BelongsToMany
    {
        $relation = $model->{$this->getRelationshipName()}();
        if (! $relation instanceof BelongsToMany) {
            throw new InvalidArgumentException('BelongsToManyField requires a belongsToMany relationship.');
        }

        return $relation;
    }

    public function allowedQuery(Model $model): Builder
    {
        $related = $this->relation($model)->getRelated();
        $query = $this->optionField()->applyQuery($related->newQuery());

        return $this->optionField()->applyResourceScope($query, $related);
    }

    /** @return array<string, string> */
    public function resolveOptions(Model $model, string $search = '', array $selected = []): array
    {
        $key = $this->relation($model)->getRelatedKeyName();
        $title = $this->optionField()->getTitleAttribute();
        $query = $this->allowedQuery($model);
        $selected = array_slice($this->normalizeValue($selected), 0, $this->getMaxSelected() ?? 1000);
        $chosen = $selected === [] ? [] : (clone $query)->whereIn($key, $selected)->pluck($title, $key)->all();
        $columns = $this->optionField()->getSearchableOptionColumns() ?: [$title];
        if (trim($search) !== '') {
            $query->where(static function (Builder $query) use ($columns, $search): void {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'like', '%'.trim($search).'%');
                }
            });
        }
        $options = $query->orderBy($key)->limit($this->optionField()->getOptionsLimit())->pluck($title, $key)->all();
        $result = [];
        foreach ($chosen + $options as $value => $label) {
            $result[(string) $value] = (string) $label;
        }

        return $result;
    }

    /** @return array<int, string> */
    public function valueForRecord(Model $model): array
    {
        $relation = $this->relation($model);
        $key = $relation->getRelatedKeyName();
        $current = $relation->pluck($relation->getRelated()->qualifyColumn($key))->all();

        return array_map(static fn (mixed $id): string => (string) $id, $this->allowedQuery($model)->whereIn($key, $current)->pluck($key)->all());
    }

    public function sync(Model $model, mixed $value): void
    {
        $ids = $this->normalizeValue($value);
        $relation = $this->relation($model);
        $key = $relation->getRelatedKeyName();
        $allowed = array_map(static fn (mixed $id): string => (string) $id, $this->allowedQuery($model)->whereIn($key, $ids)->pluck($key)->all());
        if (array_diff($ids, $allowed) !== []) {
            throw ValidationException::withMessages([$this->name() => 'One or more selected records are not available.']);
        }
        // Preserve links outside the editable scope instead of silently detaching them.
        $current = array_map(static fn (mixed $id): string => (string) $id, $relation->pluck($relation->getRelated()->qualifyColumn($key))->all());
        $editable = $this->valueForRecord($model);
        $relation->sync(array_values(array_unique(array_merge(array_diff($current, $editable), $ids))));
        $model->unsetRelation($this->getRelationshipName());
    }

    public function prepareForValidation(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return array_map(static fn (mixed $id): mixed => is_int($id) || is_string($id) ? (string) $id : $id, $value);
    }

    public function normalizeValue(mixed $value, ?Model $model = null): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException('Relationship selections must be a list of record IDs.');
        }
        foreach ($value as $id) {
            if ((! is_int($id) && ! is_string($id)) || trim((string) $id) === '') {
                throw new InvalidArgumentException('Relationship selections must contain non-empty record IDs.');
            }
        }

        return array_values(array_unique(array_map(static fn (int|string $id): string => trim((string) $id), $value)));
    }

    protected function defaultApiSchema(): array
    {
        return ['type' => 'array', 'items' => ['type' => 'string'], 'uniqueItems' => true, 'nullable' => true, 'x-seviaflow-relationship' => $this->getRelationshipName()];
    }

    public function defaultValidationRules(ResourceContext|string|null $context = null, mixed $record = null, ?string $modelClass = null, array $fieldValues = []): array
    {
        return array_merge(parent::defaultValidationRules($context, $record, $modelClass, $fieldValues), ['list', 'max:'.($this->getMaxSelected() ?? 1000)]);
    }

    public function defaultNestedValidationRules(ResourceContext|string|null $context = null, mixed $record = null, ?string $modelClass = null, array $fieldValues = []): array
    {
        $rules = ['required', 'string'];
        if ($modelClass !== null) {
            $model = new $modelClass;
            $key = $this->relation($model)->getRelatedKeyName();
            $ids = $fieldValues[$this->name()] ?? [];
            $ids = is_array($ids) ? array_filter($ids, static fn (mixed $id): bool => is_int($id) || is_string($id)) : [];
            $rules[] = Rule::in($this->allowedQuery($model)->whereIn($key, $ids)->pluck($key)->all());
        }

        return ['*' => $rules];
    }
}
