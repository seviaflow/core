<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Workflows;

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Workflows\Transition;
use SeviaFlow\Core\Resources\Workflows\TransitionContext;
use SeviaFlow\Core\Resources\Workflows\Workflow;

final class WorkflowTransitionResolver
{
    /**
     * @param  class-string<resource>  $resourceClass
     */
    public function currentState(string $resourceClass, Model $record): ?string
    {
        $workflow = $this->resolveWorkflow($resourceClass);
        $stateField = $workflow->getStateField();
        $current = $record->getAttribute($stateField);

        if ($current === null || $current === '') {
            return $workflow->getInitialState();
        }

        return (string) $current;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @return array<int, Transition>
     */
    public function availableTransitions(string $resourceClass, Model $record, mixed $user = null): array
    {
        $workflow = $this->resolveWorkflow($resourceClass);
        $currentState = $this->currentState($resourceClass, $record);

        if ($currentState === null) {
            return [];
        }

        $available = [];

        foreach ($workflow->getTransitions() as $transition) {
            if (! in_array($currentState, $transition->getFromStates(), true)) {
                continue;
            }

            $context = new TransitionContext(
                user: $user,
                resourceClass: $resourceClass,
                transition: $transition->name(),
                fromState: $currentState,
                toState: $transition->getToState(),
                workflow: $workflow
            );

            if (! $transition->isVisibleFor($record, $user, $context)) {
                continue;
            }

            if (! $transition->isAuthorizedFor($user, $record, $context)) {
                continue;
            }

            $available[] = $transition;
        }

        return $available;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public function canRun(string $resourceClass, Model $record, string $transitionName, mixed $user = null): bool
    {
        foreach ($this->availableTransitions($resourceClass, $record, $user) as $transition) {
            if ($transition->name() === $transitionName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public function resolveWorkflow(string $resourceClass): Workflow
    {
        return $resourceClass::workflow(new Workflow);
    }
}
