<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Operations;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use SeviaFlow\Core\Files\FileLifecycleManager;
use SeviaFlow\Core\Forms\FieldValueNormalizer;
use SeviaFlow\Core\Forms\FormFields;
use SeviaFlow\Core\Forms\FormState;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Fields\FileField;
use SeviaFlow\Core\Resources\Fields\PasswordField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\ResourceEventContext;
use SeviaFlow\Core\Resources\ResourceEventDispatcher;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Core\Workflows\WorkflowStateFieldGuard;
use Throwable;

final class ResourceOperationService
{
    public function __construct(
        private readonly FormFields $formFields = new FormFields,
        private readonly FieldValueNormalizer $valueNormalizer = new FieldValueNormalizer,
        private readonly ResourceEventDispatcher $resourceEventDispatcher = new ResourceEventDispatcher,
        private readonly WorkflowStateFieldGuard $workflowStateFieldGuard = new WorkflowStateFieldGuard,
    ) {}

    /**
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $values
     */
    public function persist(
        Model $model,
        array $fields,
        array $values,
        ResourceContext $context,
        ?string $resourceClass = null,
        ?FormState $state = null,
    ): Model {
        $fileLifecycle = new FileLifecycleManager($fields);
        $resolvedResourceClass = $this->resolveResourceClass($model, $resourceClass);
        $fileLifecycle->registerRollbackCallback($model);

        try {
            return $this->transaction($model, function () use ($model, $fields, $values, $context, $fileLifecycle, $resolvedResourceClass, $state): Model {
                if ($resolvedResourceClass !== null) {
                    $this->workflowStateFieldGuard->ensurePayloadDoesNotWriteStateField($resolvedResourceClass, $values);
                    $this->workflowStateFieldGuard->initialize($resolvedResourceClass, $model, $context);
                }

                $this->applyValues($model, $fields, $values, $context, $fileLifecycle, $state);
                $model->save();
                $fileLifecycle->finalize($model);

                return $model;
            });
        } catch (Throwable $exception) {
            $fileLifecycle->rollback();

            throw $exception;
        }
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $values
     */
    public function execute(
        string $resourceClass,
        string $resourceSlug,
        Model $model,
        array $fields,
        array $values,
        ResourceContext $context,
        ResourceOperationContext $operationContext,
        ?callable $save = null,
        ?FileLifecycleManager $fileLifecycle = null,
        ?FormState $state = null,
    ): Model {
        $fileLifecycle ??= new FileLifecycleManager($fields);
        $fileLifecycle->registerRollbackCallback($model);

        try {
            return $this->transaction($model, function () use (
                $resourceClass,
                $resourceSlug,
                $model,
                $fields,
                $values,
                $context,
                $operationContext,
                $save,
                $fileLifecycle,
                $state,
            ): Model {
                $isCreate = ! $model->exists;
                $original = is_array($model->getOriginal()) ? $model->getOriginal() : [];
                $this->workflowStateFieldGuard->ensurePayloadDoesNotWriteStateField($resourceClass, $values);
                $changes = $this->workflowStateFieldGuard->initialize($resourceClass, $model, $context);
                $changes = array_merge(
                    $changes,
                    $this->applyValues($model, $fields, $values, $context, $fileLifecycle, $state)
                );
                $beforeEvent = $isCreate
                    ? ResourceEventContext::OPERATION_CREATING
                    : ResourceEventContext::OPERATION_UPDATING;
                $afterEvent = $isCreate
                    ? ResourceEventContext::OPERATION_CREATED
                    : ResourceEventContext::OPERATION_UPDATED;

                $this->dispatch(
                    resourceClass: $resourceClass,
                    resourceSlug: $resourceSlug,
                    event: $beforeEvent,
                    model: $model,
                    original: $original,
                    changes: $changes,
                    operationContext: $operationContext,
                    result: 'pending',
                );

                if ($save !== null) {
                    $save($model);
                } else {
                    $model->save();
                }

                $fileLifecycle->finalize($model);
                $this->resourceEventDispatcher->dispatchAfterCommit(
                    resourceClass: $resourceClass,
                    event: $afterEvent,
                    record: $model,
                    context: $this->eventContext(
                        resourceClass: $resourceClass,
                        resourceSlug: $resourceSlug,
                        event: $afterEvent,
                        model: $model,
                        original: $original,
                        changes: $changes,
                        operationContext: $operationContext,
                        result: 'committed',
                    ),
                );

                return $model;
            });
        } catch (Throwable $exception) {
            $fileLifecycle->rollback();

            throw $exception;
        }
    }

    /**
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function applyValues(
        Model $model,
        array $fields,
        array $values,
        ResourceContext $context,
        ?FileLifecycleManager $fileLifecycle = null,
        ?FormState $state = null,
    ): array {
        $fieldMap = [];

        foreach ($this->formFields->persistable($fields, $context, $state) as $field) {
            $fieldMap[$field->name()] = $field;
        }

        $changes = [];
        foreach ($values as $name => $value) {
            if (! is_string($name)) {
                continue;
            }

            if (isset($fieldMap[$name])) {
                if (
                    $fieldMap[$name] instanceof PasswordField
                    && $fieldMap[$name]->shouldDehydrateOnlyWhenFilled()
                    && ($value === null || trim((string) $value) === '')
                ) {
                    continue;
                }

                if ($fieldMap[$name] instanceof FileField) {
                    if ($fileLifecycle === null && ! ($value instanceof UploadedFile)) {
                        continue;
                    }

                    if ($fileLifecycle !== null) {
                        $fileChange = $fileLifecycle->apply($model, $fieldMap[$name], $value);
                        if (! $fileChange['changed']) {
                            continue;
                        }

                        $changes[$name] = $fileChange['value'];
                        $model->setAttribute($name, $fileChange['value']);

                        continue;
                    }
                }

                $changes[$name] = $this->valueNormalizer->normalize(
                    $fieldMap[$name],
                    $value,
                    $model
                );
                $model->setAttribute($name, $changes[$name]);

                continue;
            }

            if (! array_key_exists($name, $fieldMap) && ! $this->hasFieldNamed($fields, $name)) {
                $changes[$name] = $this->valueNormalizer->normalizeUnknown($value);
                $model->setAttribute($name, $changes[$name]);
            }
        }

        return $changes;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @param  array<int, string>  $fieldNames
     * @return array<int, Field>
     */
    public function fieldsForResource(string $resourceClass, array $fieldNames = []): array
    {
        $lookup = array_fill_keys($fieldNames, true);
        $fields = $resourceClass::form(new Form)->getAllFields();

        if ($fieldNames === []) {
            return $fields;
        }

        return array_values(array_filter(
            $fields,
            static fn (Field $field): bool => isset($lookup[$field->name()])
        ));
    }

    /**
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function prepareForValidation(array $fields, array $values): array
    {
        $fieldMap = [];

        foreach ($fields as $field) {
            $fieldMap[$field->name()] = $field;
        }

        $prepared = [];
        foreach ($values as $name => $value) {
            if (! is_string($name)) {
                continue;
            }

            $prepared[$name] = isset($fieldMap[$name])
                ? $this->valueNormalizer->prepareForValidation($fieldMap[$name], $value)
                : $this->valueNormalizer->normalizeUnknown($value);
        }

        return $prepared;
    }

    /**
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function prepareForImport(array $fields, array $values): array
    {
        $fieldMap = [];

        foreach ($fields as $field) {
            $fieldMap[$field->name()] = $field;
        }

        $prepared = [];
        foreach ($values as $name => $value) {
            if (! is_string($name)) {
                continue;
            }

            $prepared[$name] = isset($fieldMap[$name])
                ? $this->valueNormalizer->prepareForImport($fieldMap[$name], $value)
                : $this->valueNormalizer->normalizeUnknown($value);
        }

        return $prepared;
    }

    /**
     * @param  Closure():Model  $callback
     */
    private function transaction(Model $model, Closure $callback): Model
    {
        return $model->getConnection()->transaction($callback);
    }

    /**
     * @param  class-string<resource>|null  $resourceClass
     * @return class-string<resource>|null
     */
    private function resolveResourceClass(Model $model, ?string $resourceClass): ?string
    {
        if ($resourceClass !== null) {
            return $resourceClass;
        }

        if (! function_exists('app') || ! app()->bound(ResourceRegistry::class)) {
            return null;
        }

        return app(ResourceRegistry::class)->findByModel($model::class);
    }

    /**
     * @param  array<int, Field>  $fields
     */
    private function hasFieldNamed(array $fields, string $name): bool
    {
        foreach ($fields as $field) {
            if ($field->name() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $changes
     */
    private function dispatch(
        string $resourceClass,
        string $resourceSlug,
        string $event,
        Model $model,
        array $original,
        array $changes,
        ResourceOperationContext $operationContext,
        string $result,
    ): void {
        $this->resourceEventDispatcher->dispatch(
            resourceClass: $resourceClass,
            event: $event,
            record: $model,
            context: $this->eventContext(
                resourceClass: $resourceClass,
                resourceSlug: $resourceSlug,
                event: $event,
                model: $model,
                original: $original,
                changes: $changes,
                operationContext: $operationContext,
                result: $result,
            ),
        );
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $changes
     */
    private function eventContext(
        string $resourceClass,
        string $resourceSlug,
        string $event,
        Model $model,
        array $original,
        array $changes,
        ResourceOperationContext $operationContext,
        string $result,
    ): ResourceEventContext {
        $metadata = array_merge($operationContext->metadata(), [
            'record_key' => $model->getKey(),
            'result' => $result,
        ]);

        return new ResourceEventContext(
            user: $operationContext->actor(),
            resourceClass: $resourceClass,
            resourceSlug: $resourceSlug,
            operation: $event,
            source: $operationContext->source(),
            request: $operationContext->request(),
            original: $original,
            changes: $changes,
            metadata: $metadata,
            connection: $model->getConnectionName() ?? $operationContext->connection() ?? $model->getConnection()->getName(),
            correlationId: $operationContext->correlationId(),
        );
    }
}
