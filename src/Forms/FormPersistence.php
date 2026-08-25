<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Forms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Fields\FileField;
use SeviaFlow\Core\Resources\Fields\MoneyField;
use SeviaFlow\Core\Resources\Fields\MultiSelectField;
use SeviaFlow\Core\Resources\Fields\NumberField;
use SeviaFlow\Core\Resources\Fields\PasswordField;
use SeviaFlow\Core\Resources\Fields\ToggleField;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\ResourceEventContext;
use SeviaFlow\Core\Resources\ResourceEventDispatcher;

final class FormPersistence
{
    public function __construct(
        private readonly FormFields $formFields = new FormFields,
        private readonly ResourceEventDispatcher $resourceEventDispatcher = new ResourceEventDispatcher
    ) {}

    /**
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $validatedFieldValues
     */
    public function persist(
        Model $model,
        array $fields,
        array $validatedFieldValues,
        ResourceContext $context
    ): Model {
        $this->applyPersistableValues($model, $fields, $validatedFieldValues, $context);
        $model->save();

        return $model;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $validatedFieldValues
     */
    public function persistWithResourceEvents(
        string $resourceClass,
        string $resourceSlug,
        Model $model,
        array $fields,
        array $validatedFieldValues,
        ResourceContext $context,
        mixed $user = null,
        ?Request $request = null
    ): Model {
        $isCreate = ! $model->exists;
        $original = $isCreate ? [] : $model->getOriginal();
        $changes = $this->applyPersistableValues($model, $fields, $validatedFieldValues, $context);
        $beforeEvent = $isCreate ? ResourceEventContext::OPERATION_CREATING : ResourceEventContext::OPERATION_UPDATING;
        $afterEvent = $isCreate ? ResourceEventContext::OPERATION_CREATED : ResourceEventContext::OPERATION_UPDATED;

        return DB::transaction(function () use (
            $resourceClass,
            $resourceSlug,
            $model,
            $user,
            $request,
            $beforeEvent,
            $afterEvent,
            $original,
            $changes
        ): Model {
            $this->resourceEventDispatcher->dispatch(
                resourceClass: $resourceClass,
                event: $beforeEvent,
                record: $model,
                context: new ResourceEventContext(
                    user: $user,
                    resourceClass: $resourceClass,
                    resourceSlug: $resourceSlug,
                    operation: $beforeEvent,
                    source: ResourceEventContext::SOURCE_FORM,
                    request: $request,
                    original: $original,
                    changes: $changes,
                    metadata: []
                )
            );

            $model->save();

            $this->resourceEventDispatcher->dispatch(
                resourceClass: $resourceClass,
                event: $afterEvent,
                record: $model,
                context: new ResourceEventContext(
                    user: $user,
                    resourceClass: $resourceClass,
                    resourceSlug: $resourceSlug,
                    operation: $afterEvent,
                    source: ResourceEventContext::SOURCE_FORM,
                    request: $request,
                    original: $original,
                    changes: $changes,
                    metadata: []
                )
            );

            return $model;
        });
    }

    /**
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $validatedFieldValues
     * @return array<string, mixed>
     */
    private function applyPersistableValues(
        Model $model,
        array $fields,
        array $validatedFieldValues,
        ResourceContext $context
    ): array {
        $changes = [];

        foreach ($this->formFields->persistable($fields, $context) as $field) {
            $value = $validatedFieldValues[$field->name()] ?? null;

            if ($field instanceof PasswordField && $field->shouldDehydrateOnlyWhenFilled() && ($value === null || $value === '')) {
                continue;
            }

            if ($field instanceof FileField && ! $value instanceof UploadedFile) {
                continue;
            }

            $normalized = $this->normalizePersistedValue($field, $value);
            $model->setAttribute($field->name(), $normalized);
            $changes[$field->name()] = $normalized;
        }

        return $changes;
    }

    private function normalizePersistedValue(Field $field, mixed $value): mixed
    {
        if ($field instanceof ToggleField) {
            return (bool) $value;
        }

        if ($field instanceof MoneyField) {
            if ($value === null || $value === '') {
                return null;
            }

            return $value;
        }

        if ($field instanceof MultiSelectField) {
            if ($value === null || $value === '') {
                return [];
            }

            return is_array($value) ? array_values($value) : [];
        }

        if ($field instanceof FileField && $value instanceof UploadedFile) {
            return $value->store($field->getDirectory(), $field->getDisk());
        }

        if ($field instanceof NumberField) {
            if ($value === null || $value === '') {
                return null;
            }

            if (is_int($value) || is_float($value)) {
                return $value;
            }

            return str_contains((string) $value, '.') ? (float) $value : (int) $value;
        }

        if ($field instanceof PasswordField) {
            if ($value === null || $value === '') {
                return null;
            }

            return Hash::make((string) $value);
        }

        if ($value === '') {
            return null;
        }

        return $value;
    }
}
