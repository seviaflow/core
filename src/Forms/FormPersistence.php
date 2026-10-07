<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Forms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SeviaFlow\Core\Operations\ResourceOperationContext;
use SeviaFlow\Core\Operations\ResourceOperationService;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\ResourceEventContext;
use SeviaFlow\Core\Resources\ResourceEventDispatcher;

final class FormPersistence
{
    private readonly ResourceOperationService $operationService;

    public function __construct(
        FormFields $formFields = new FormFields,
        ResourceEventDispatcher $resourceEventDispatcher = new ResourceEventDispatcher,
        ?ResourceOperationService $operationService = null,
    ) {
        $this->operationService = $operationService ?? new ResourceOperationService(
            formFields: $formFields,
            resourceEventDispatcher: $resourceEventDispatcher,
        );
    }

    /**
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $validatedFieldValues
     */
    public function persist(
        Model $model,
        array $fields,
        array $validatedFieldValues,
        ResourceContext $context,
        ?string $resourceClass = null,
    ): Model {
        return $this->operationService->persist(
            model: $model,
            fields: $fields,
            values: $validatedFieldValues,
            context: $context,
            resourceClass: $resourceClass,
        );
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
        ?Request $request = null,
        ?FormState $state = null,
        ?string $expectedRevision = null,
    ): Model {
        return $this->operationService->execute(
            resourceClass: $resourceClass,
            resourceSlug: $resourceSlug,
            model: $model,
            fields: $fields,
            values: $validatedFieldValues,
            context: $context,
            operationContext: new ResourceOperationContext(
                actor: $user,
                source: ResourceEventContext::SOURCE_FORM,
                request: $request,
                expectedRevision: $expectedRevision,
            ),
            state: $state,
        );
    }
}
