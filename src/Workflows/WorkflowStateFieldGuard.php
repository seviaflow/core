<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Workflows;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Exceptions\WorkflowStateMutationException;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceContext;
use SeviaFlow\Core\Resources\Workflows\Workflow;

final class WorkflowStateFieldGuard
{
    /**
     * @param  class-string<resource>  $resourceClass
     * @param  array<string, mixed>  $payload
     */
    public function ensurePayloadDoesNotWriteStateField(string $resourceClass, array $payload): void
    {
        $stateField = $this->stateField($resourceClass);

        if ($stateField === null || ! array_key_exists($stateField, $payload)) {
            return;
        }

        throw WorkflowStateMutationException::forField($stateField);
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @return array<string, mixed>
     */
    public function initialize(string $resourceClass, Model $model, ResourceContext $context): array
    {
        if ($context !== ResourceContext::Create || $model->exists) {
            return [];
        }

        $workflow = $this->workflow($resourceClass);
        $initialState = $workflow?->getInitialState();
        if ($initialState === null) {
            return [];
        }

        $stateField = $workflow->getStateField();
        $model->setAttribute($stateField, $initialState);

        return [$stateField => $initialState];
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public function stateField(string $resourceClass): ?string
    {
        return $this->workflow($resourceClass)?->getStateField();
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function workflow(string $resourceClass): ?Workflow
    {
        $workflow = $resourceClass::workflow(new Workflow);

        if ($workflow->getStates() === [] && $workflow->getTransitions() === [] && $workflow->getInitialState() === null) {
            return null;
        }

        return $workflow;
    }
}
