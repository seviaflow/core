<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Forms;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Fields\BelongsToField;
use SeviaFlow\Core\Resources\Fields\Field;

final class BelongsToFieldOptions
{
    /**
     * @param  array<int, Field>  $fields
     * @param  class-string<Model>  $modelClass
     * @param  array<string, string>  $searchTerms
     * @param  array<string, mixed>  $fieldValues
     * @return array<string, array<string, string>>
     */
    public function resolve(array $fields, string $modelClass, array $searchTerms = [], array $fieldValues = []): array
    {
        $model = new $modelClass;
        $options = [];

        foreach ($fields as $field) {
            if (! $field instanceof BelongsToField) {
                continue;
            }

            $options[$field->name()] = $this->resolveForField(
                $model,
                $field,
                trim((string) ($searchTerms[$field->name()] ?? '')),
                $fieldValues
            );
        }

        return $options;
    }

    /**
     * @param  non-empty-string|''  $searchTerm
     * @param  array<string, mixed>  $fieldValues
     * @return array<string, string>
     */
    private function resolveForField(Model $resourceModel, BelongsToField $field, string $searchTerm, array $fieldValues): array
    {
        $relation = $resourceModel->{$field->getRelationshipName()}();

        if (! $relation instanceof BelongsTo) {
            throw new InvalidArgumentException(sprintf(
                'Relationship [%s] on model [%s] must be an instance of [%s].',
                $field->getRelationshipName(),
                $resourceModel::class,
                BelongsTo::class
            ));
        }

        $titleAttribute = $field->getTitleAttribute();
        $ownerKey = $relation->getOwnerKeyName();

        $query = $relation->getRelated()
            ->newQuery()
            ->select([$ownerKey, $titleAttribute]);

        $callback = $field->getQueryCallback();
        if ($callback !== null) {
            $result = $callback($query);

            if ($result !== null) {
                if (! $result instanceof Builder) {
                    throw new InvalidArgumentException(sprintf(
                        'BelongsToField query callback for field [%s] must return [%s] or null, [%s] given.',
                        $field->name(),
                        Builder::class,
                        get_debug_type($result)
                    ));
                }

                $query = $result;
            }
        }

        $dependsOnField = $field->getDependsOnField();
        $dependsOnCallback = $field->getDependsOnCallback();
        if ($dependsOnField !== null && $dependsOnCallback !== null) {
            $parentValue = $fieldValues[$dependsOnField] ?? null;

            if ($parentValue === null || $parentValue === '') {
                return [];
            }

            $dependencyResult = $dependsOnCallback($query, $parentValue);

            if ($dependencyResult !== null) {
                if (! $dependencyResult instanceof Builder) {
                    throw new InvalidArgumentException(sprintf(
                        'BelongsToField dependsOn callback for field [%s] must return [%s] or null, [%s] given.',
                        $field->name(),
                        Builder::class,
                        get_debug_type($dependencyResult)
                    ));
                }

                $query = $dependencyResult;
            }
        }

        $searchColumns = $field->getSearchableOptionColumns();
        if ($searchTerm !== '' && $searchColumns !== []) {
            $query->where(static function (Builder $searchQuery) use ($searchColumns, $searchTerm): void {
                foreach ($searchColumns as $index => $column) {
                    if ($index === 0) {
                        $searchQuery->where($column, 'like', '%'.$searchTerm.'%');

                        continue;
                    }

                    $searchQuery->orWhere($column, 'like', '%'.$searchTerm.'%');
                }
            });
        }

        $records = $query
            ->orderBy($ownerKey)
            ->limit($field->getOptionsLimit())
            ->get();

        $result = [];

        foreach ($records as $record) {
            $value = (string) $record->getAttribute($ownerKey);
            $label = (string) $record->getAttribute($titleAttribute);
            $result[$value] = $label;
        }

        return $result;
    }
}
