<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Forms;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use SeviaFlow\Core\Resources\Fields\BelongsToField;
use SeviaFlow\Core\Resources\Fields\BelongsToManyField;
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
            if ($field instanceof BelongsToManyField) {
                $selected = $fieldValues[$field->name()] ?? [];
                $options[$field->name()] = $field->resolveOptions($model, (string) ($searchTerms[$field->name()] ?? ''), is_array($selected) ? $selected : []);

                continue;
            }
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

        $query = $field->applyQuery($query);

        $dependsOnField = $field->getDependsOnField();
        $dependsOnCallback = $field->getDependsOnCallback();
        if ($dependsOnField !== null && $dependsOnCallback !== null) {
            $parentValue = $fieldValues[$dependsOnField] ?? null;

            if ($parentValue === null || $parentValue === '') {
                return [];
            }

            $query = $field->applyDependencyQuery($query, $parentValue);
        }

        $query = $field->applyResourceScope($query, $relation->getRelated());

        $selectedRecord = null;
        $selectedValue = $fieldValues[$field->name()] ?? null;
        if (is_scalar($selectedValue) && (string) $selectedValue !== '') {
            $selectedRecord = (clone $query)
                ->where($ownerKey, $selectedValue)
                ->first();
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

        if ($selectedRecord !== null) {
            $value = (string) $selectedRecord->getAttribute($ownerKey);
            $result[$value] = (string) $selectedRecord->getAttribute($titleAttribute);
        }

        foreach ($records as $record) {
            $value = (string) $record->getAttribute($ownerKey);
            $label = (string) $record->getAttribute($titleAttribute);
            $result[$value] = $label;
        }

        return $result;
    }
}
